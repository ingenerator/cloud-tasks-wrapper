<?php


namespace Ingenerator\CloudTasksWrapper\Client;


use DateTimeImmutable;
use Google\ApiCore\ApiException;
use Google\Cloud\Tasks\V2\CloudTasksClient;
use Google\Cloud\Tasks\V2\HttpMethod;
use Google\Cloud\Tasks\V2\HttpRequest;
use Google\Cloud\Tasks\V2\OidcToken;
use Google\Cloud\Tasks\V2\Task;
use Google\Protobuf\Internal\Message;
use Google\Protobuf\Timestamp;
use Ingenerator\CloudTasksWrapper\TaskTypeConfigProvider;
use Ingenerator\PHPUtils\StringEncoding\JSON;
use Psr\Log\LoggerInterface;

class CloudTaskCreator implements TaskCreator
{
    protected CloudTasksClient $client;

    protected TaskTypeConfigProvider $task_config;

    protected LoggerInterface $logger;

    public function __construct(
        CloudTasksClient $client,
        TaskTypeConfigProvider $task_config,
        LoggerInterface $logger
    ) {
        $this->client      = $client;
        $this->task_config = $task_config;
        $this->logger      = $logger;
    }

    public function create(string $task_type_name, array $options = []): string
    {
        $task_type = $this->task_config->getConfig($task_type_name);

        $options = array_merge(
            [
                // Data to be sent in the body, can be JSON or form-encoded:
                // - to send JSON, 'body' => ['json' => [//data]]
                // - to send form, 'body' => ['form' => [//data]]
                'body'                => NULL,
                // Extra headers to send with the HTTP request
                'headers'             => [],
                // Optionally add GET parameters to the handler URL. The handler URL itself comes from
                // config.
                'query'               => NULL,
                // Optionally specify when the task should first be executed
                'schedule_send_after' => NULL,
                // Optional, specify a task ID for server-side dedupe by Cloud Tasks. Note per the
                // docs this significantly reduces throughput especially if it is not a
                // well-distributed hash value. For fastest dispatch allow Cloud Tasks to duplicate
                // and deal with de-duping on receipt (necessary anyway as Tasks is always
                // at-least-once delivery).
                'task_id'             => NULL,
                // Optional, *instead* of task_id, specify task_id_from to have the library automatically
                // calculate the task_id as an SHA256 hash of the application-provided string. Supports the
                // common case where you want to use a known string for deduping, but want the throughput of
                // well-distributed random-like task names.
                'task_id_from'        => NULL,
            ],
            $options
        );

        $handler_url = $task_type->getHandlerUrl();
        if ($options['query']) {
            $handler_url .= '?'.\http_build_query($options['query']);
        }

        $options = $this->prepareBody($options);

        $task = $this->createObj(
            Task::class,
            [
                'name'          => $this->getTaskName($task_type->getQueuePath(), $options),
                'http_request'  => $this->createObj(
                    HttpRequest::class,
                    [
                        'url'         => $handler_url,
                        'http_method' => HttpMethod::POST,
                        'oidc_token'  => $this->oidcTokenUnlessAnonymous($task_type->getSignerEmail()),
                        'headers'     => $options['headers'],
                        'body'        => $options['body'],
                    ]
                ),
                'schedule_time' => $this->toTimestampOrNull($options['schedule_send_after']),
            ]
        );

        try {
            $result = $this->client->createTask(
                $task_type->getQueuePath(),
                $task,
                [
                    // Note that we set retrySettings based on the task type config (falling back to our custom global
                    // defaults). This is different to the default CloudTasksClient behaviour, which does not retry
                    // CreateTask calls as they are not guaranteed idempotent without a client-side name attribute.
                    // In our case they are because task handlers should already be written to be idempotent to cope
                    // with at-least-once delivery, so we can safely retry creating the task.
                    'retrySettings' => $task_type->getCreateRetrySettings(),
                ]
            );

            return $result->getName();

        } catch (ApiException $e) {
            $this->logger->error(
                'Failed to create cloud task: '.$e->getBasicMessage(),
                [
                    'exception'          => $e,
                    'exception_metadata' => $e->getMetadata(),
                    'task_info'          => [
                        'post_url'  => $handler_url,
                        'task_type' => $task_type,
                    ],
                ]
            );
            throw new TaskCreationFailedException(
                'Failed to create task: '.$e->getBasicMessage(),
                0,
                $e
            );
        }
    }

    /**
     * @param string $internal_queue_name
     *
     * @return OidcToken
     */
    protected function oidcTokenUnlessAnonymous(string $email): ?OidcToken
    {
        if ($email === 'anonymous') {
            return NULL;
        }

        return new OidcToken(
            [
                'service_account_email' => $email,
            ]
        );
    }

    protected function toTimestampOrNull(?DateTimeImmutable $datetime): ?Timestamp
    {
        if ($datetime === NULL) {
            return NULL;
        }

        return new Timestamp(
            [
                'seconds' => $datetime->getTimestamp(),
                'nanos'   => 1000 * $datetime->format('u'),
            ]
        );
    }

    protected function createObj(string $class, array $vars): Message
    {
        // Note passing the array through array_filter first - protobuf internally differentiates
        // between a field that is not present / undefined vs one that is explicitly set to null
        // and some fields don't have valid `null` values

        return new $class(array_filter($vars));
    }

    protected function prepareBody(array $options): array
    {
        if ($options['body'] === NULL) {
            return $options;
        }

        if (\is_array($options['body'])) {
            $type = \array_keys($options['body']);
            if ($type === ['json']) {
                $options['body']                    = JSON::encode($options['body']['json'], FALSE);
                $options['headers']['Content-Type'] = 'application/json';
            } elseif ($type === ['form']) {
                $options['body']                    = \http_build_query($options['body']['form']);
                $options['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
            } else {
                throw new \InvalidArgumentException('Invalid body type: '.JSON::encode($type, FALSE));
            }
        }

        if ( ! \is_string($options['body'])) {
            throw new \InvalidArgumentException('Invalid body type: '.\get_debug_type($options['body']));
        }


        return $options;
    }

    /**
     * Return the fully-qualified task name (which must include the queue path) if any
     *
     * @param string $queue_path
     * @param array  $options
     *
     * @return string|null
     */
    protected function getTaskName(string $queue_path, array $options): ?string
    {
        if (isset($options['task_id_from'])) {
            if (isset($options['task_id'])) {
                throw new \InvalidArgumentException('Cannot set both task_id_from and task_id');
            }

            $task_id = \hash('sha256', $options['task_id_from']);
        } else {
            $task_id = $options['task_id'] ?? NULL;
        }

        if (empty($task_id)) {
            return NULL;
        }

        return $queue_path.'/tasks/'.$task_id;
    }
}

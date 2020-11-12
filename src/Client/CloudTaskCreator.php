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

    public function create(string $task_type, array $options = []): string
    {
        $config = $this->task_config->getConfig($task_type);

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
                // Optional, specify a task name for server-side dedupe by Cloud Tasks. Note per the
                // docs this significantly reduces throughput especially if it is not a
                // well-distributed hash value. For fastest dispatch allow Cloud Tasks to duplicate
                // and deal with de-duping on receipt (necessary anyway as Tasks is always
                // at-least-once delivery).
                'task_name'           => NULL,
                // Optional, *instead* of task_name, specify task_name_from to have the library automatically
                // calculate the task_name as an SHA256 hash of the application-provided string. Supports the
                // common case where you want to use a known string for deduping, but want the throughput of
                // well-distributed random-like task names.
                'task_name_from'      => NULL,
            ],
            $options
        );

        $handler_url = $config['handler_url'];
        if ($options['query']) {
            $handler_url .= '?'.\http_build_query($options['query']);
        }

        $options = $this->prepareBody($options);

        $task = $this->createObj(
            Task::class,
            [
                'name'          => $this->getTaskName($options),
                'http_request'  => $this->createObj(
                    HttpRequest::class,
                    [
                        'url'         => $handler_url,
                        'http_method' => HttpMethod::POST,
                        'oidc_token'  => $this->oidcTokenUnlessAnonymous($config['signer_email']),
                        'headers'     => $options['headers'],
                        'body'        => $options['body'],
                    ]
                ),
                'schedule_time' => $this->toTimestampOrNull($options['schedule_send_after']),
            ]
        );

        try {
            //@todo: set retry and timeout settings either on the operation or the client
            // NB that the defaults don't appear to be the defaults in the docs, and customising doesn't work the way
            // you think either (doesn't seem to actually accept a RetrySettings despite saying it does)
            $result = $this->client->createTask(
                $config['queue-path'],
                $task
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

    protected function getTaskName(array $options): ?string
    {
        if (isset($options['task_name_from'])) {
            if (isset($options['task_name'])) {
                throw new \InvalidArgumentException('Cannot set both task_name_from and task_name');
            }

            return \hash('sha256', $options['task_name_from']);
        }

        return $options['task_name'] ?? NULL;
    }
}

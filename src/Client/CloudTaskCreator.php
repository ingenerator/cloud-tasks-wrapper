<?php

namespace Ingenerator\CloudTasksWrapper\Client;

use DateTimeImmutable;
use Google\ApiCore\ApiException;
use Google\Cloud\Tasks\V2\Client\CloudTasksClient;
use Google\Cloud\Tasks\V2\HttpMethod;
use Google\Cloud\Tasks\V2\HttpRequest;
use Google\Cloud\Tasks\V2\OidcToken;
use Google\Cloud\Tasks\V2\Task;
use Google\Protobuf\Timestamp;
use Google\Rpc\Code;
use Ingenerator\CloudTasksWrapper\TaskTypeConfigProvider;
use Psr\Log\LoggerInterface;

use function http_build_query;

class CloudTaskCreator implements TaskCreator
{
    protected CloudTasksClientInterface $client;

    public function __construct(
        CloudTasksClient|CloudTasksClientInterface $client,
        protected TaskTypeConfigProvider $task_config,
        protected LoggerInterface $logger,
    ) {
        $this->client = $client instanceof CloudTasksClientInterface
            ? $client
            : new CloudTasksClientAdapter($client);
    }

    public function create(string $task_type_name, ?CreateTaskOptions $options = null): string
    {
        $task_type = $this->task_config->getConfig($task_type_name);
        $options ??= new CreateTaskOptions([]);

        $handler_url = $options->getCustomHandlerUrl() ?? $task_type->getHandlerUrl();
        if ($options->hasQuery()) {
            $handler_url .= '?'.http_build_query($options->getQuery());
        }

        $task_name = $options->buildTaskName($task_type->getQueuePath());

        // Note passing the array values through array_filter first - protobuf internally differentiates
        // between a field that is not present / undefined vs one that is explicitly set to null
        // and some fields don't have valid `null` values
        $task = new Task(array_filter(
            [
                'name' => $task_name,
                'http_request' => new HttpRequest(array_filter(
                    [
                        'url' => $handler_url,
                        'http_method' => HttpMethod::POST,
                        'oidc_token' => $this->oidcTokenUnlessAnonymous(
                            $task_type->getSignerEmail(),
                            $task_type->getCustomTokenAudience()
                        ),
                        'headers' => $options->getHeaders(),
                        'body' => $options->getBodyContent(),
                    ]
                )),
                'schedule_time' => $this->toTimestampOrNull($options->getScheduleSendAfter()),
            ]
        ));

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
            if ($e->getCode() === Code::ALREADY_EXISTS && !$options->shouldThrowOnDuplicate()) {
                // This is an expected failure, we are using server-side-task-dedupe and the caller has asked
                // for us to silently ignore failures to create duplicates of tasks that have already been queued.
                assert(is_string($task_name), 'Can only fire ALREADY_EXISTS if a task name was provided');

                return $task_name;
            }

            $this->logger->error(
                'Failed to create cloud task: '.$e->getBasicMessage(),
                [
                    'exception' => $e,
                    'exception_metadata' => $e->getMetadata(),
                    'task_info' => [
                        'post_url' => $handler_url,
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

    protected function oidcTokenUnlessAnonymous(string $email, ?string $custom_audience): ?OidcToken
    {
        if ($email === 'anonymous') {
            return null;
        }

        $options = [
            'service_account_email' => $email,
        ];
        if ($custom_audience !== null) {
            $options['audience'] = $custom_audience;
        }

        return new OidcToken($options);
    }

    protected function toTimestampOrNull(?DateTimeImmutable $datetime): ?Timestamp
    {
        if (!$datetime instanceof DateTimeImmutable) {
            return null;
        }

        return new Timestamp(
            [
                'seconds' => $datetime->getTimestamp(),
                'nanos' => 1000 * $datetime->format('u'),
            ]
        );
    }
}

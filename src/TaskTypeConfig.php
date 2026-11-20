<?php

namespace Ingenerator\CloudTasksWrapper;

use Exception;
use Google\Cloud\Tasks\V2\Client\CloudTasksClient;
use Ingenerator\PHPUtils\Object\ObjectPropertyPopulator;

use function str_replace;

/**
 * Represents the overall config for a particular task type.
 *
 * @phpstan-type TRetrySettingsArray array{
 *     retriesEnabled?: true,
 *     noRetriesRpcTimeoutMillis?: int,
 *     retryableCodes?: list<string>,
 *     initialRetryDelayMillis?: int,
 *     retryDelayMultiplier?: float,
 *     maxRetryDelayMillis?: int,
 *     initialRpcTimeoutMillis?: int,
 *     rpcTimeoutMultiplier?: float,
 *     maxRpcTimeoutMillis?: int,
 *     totalTimeoutMillis?: int,
 *     maxRetries?: int,
 *     retryFunction?: callable(Exception,array<mixed>):bool
 * }
 */
class TaskTypeConfig
{
    /**
     * Custom settings for retrying creating tasks of this type.
     *
     * @var TRetrySettingsArray
     */
    protected array $create_retry_settings;

    /**
     * The URL handler for this task type (without any querystring).
     */
    protected string $handler_url;

    /**
     * The project, name and location of the queue this task type will be sent through.
     */
    protected array $queue;

    /**
     * Service account email used to sign execution requests for this task type.
     */
    protected string $signer_email;

    /**
     * Optional value to use as the `audience` of the JWT issued to authorise requests as this task type.
     *
     * If left empty, cloud tasks will use the complete handler_url.
     */
    protected ?string $custom_token_audience = null;

    /**
     * @param array<string, mixed> $cfg
     */
    public function __construct(
        /**
         * Internal application-specific identifier for this task type.
         */
        protected string $task_type,
        array $cfg,
    ) {
        if (isset($cfg['handler_url'])) {
            $cfg['handler_url'] = str_replace('{TASK_TYPE}', $this->task_type, $cfg['handler_url']);
        }
        ObjectPropertyPopulator::assignHash($this, $cfg);
    }

    /**
     * @return TRetrySettingsArray
     */
    public function getCreateRetrySettings(): array
    {
        return $this->create_retry_settings;
    }

    public function getHandlerUrl(): string
    {
        return $this->handler_url;
    }

    public function getQueuePath(): string
    {
        return CloudTasksClient::queueName(
            $this->queue['project'],
            $this->queue['location'],
            $this->queue['name'],
        );
    }

    public function getSignerEmail(): string
    {
        return $this->signer_email;
    }

    public function getCustomTokenAudience(): ?string
    {
        return $this->custom_token_audience;
    }

    public function getTaskType(): string
    {
        return $this->task_type;
    }
}

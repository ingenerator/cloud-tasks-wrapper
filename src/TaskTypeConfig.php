<?php


namespace Ingenerator\CloudTasksWrapper;


use Google\Cloud\Tasks\V2\CloudTasksClient;
use Ingenerator\PHPUtils\Object\ObjectPropertyPopulator;

/**
 * Represents the overall config for a particular task type
 */
class TaskTypeConfig
{
    /**
     * Custom settings for retrying creating tasks of this type
     *
     * @var array
     */
    protected array $create_retry_settings;

    /**
     * The URL handler for this task type (without any querystring)
     *
     * @var string
     */
    protected string $handler_url;

    /**
     * The project, name and location of the queue this task type will be sent through
     *
     * @var array
     */
    protected array $queue;

    /**
     * Service account email used to sign execution requests for this task type
     *
     * @var string
     */
    protected string $signer_email;

    /**
     * Optional value to use as the `audience` of the JWT issued to authorise requests as this task type.
     *
     * If left empty, cloud tasks will use the complete handler_url.
     */
    protected ?string $custom_token_audience = null;

    /**
     * Internal application-specific identifier for this task type
     *
     * @var string
     */
    protected string $task_type;

    public function __construct(string $task_type, array $cfg)
    {
        $this->task_type = $task_type;
        if (isset($cfg['handler_url'])) {
            $cfg['handler_url'] = \str_replace('{TASK_TYPE}', $task_type, $cfg['handler_url']);
        }
        ObjectPropertyPopulator::assignHash($this, $cfg);
    }

    /**
     * @return array
     */
    public function getCreateRetrySettings(): array
    {
        return $this->create_retry_settings;
    }

    /**
     * @return string
     */
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

    /**
     * @return string
     */
    public function getSignerEmail(): string
    {
        return $this->signer_email;
    }

    public function getCustomTokenAudience(): ?string
    {
        return $this->custom_token_audience;
    }

    /**
     * @return string
     */
    public function getTaskType(): string
    {
        return $this->task_type;
    }

}

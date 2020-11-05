<?php


namespace Ingenerator\CloudTasksWrapper\Server;


/**
 * Represents the internal result of handling a task
 *
 * This will be used for logging and other internal processing, then is mapped to a suitable
 * TaskResponse which carries the appropriate HTTP status back to your application controller /
 * dispatch layer.
 *
 * Usually created from a named constructor on CoreTaskResult or a custom class.
 */
abstract class TaskHandlerResult
{

    /**
     * @var string internal status code
     */
    protected string $code;

    /**
     * @var string short status message for logging etc
     */
    protected string $msg;

    /**
     * @var array any extra values to pass along to the logger
     */
    protected array $log_context;

    protected function __construct(string $code, string $msg, array $log_context = [])
    {
        $this->code        = $code;
        $this->msg         = $msg;
        $this->log_context = $log_context;
    }

    /**
     * @return string
     */
    public function getMsg(): string
    {
        return $this->msg;
    }

    /**
     * @return array
     */
    public function getLogContext(): array
    {
        return $this->log_context;
    }

    /**
     * @return string
     */
    public function getCode(): string
    {
        return $this->code;
    }

    public function toArray(): array
    {
        return [
            'code'        => $this->code,
            'msg'         => $this->msg,
            'log_context' => $this->log_context,
        ];
    }

}

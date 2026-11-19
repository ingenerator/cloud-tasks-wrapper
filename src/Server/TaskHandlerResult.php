<?php

namespace Ingenerator\CloudTasksWrapper\Server;

/**
 * Represents the internal result of handling a task.
 *
 * This will be used for logging and other internal processing, then is mapped to a suitable
 * TaskResponse which carries the appropriate HTTP status back to your application controller /
 * dispatch layer.
 *
 * Usually created from a named constructor on CoreTaskResult or a custom class.
 */
abstract class TaskHandlerResult
{
    protected function __construct(
        /**
         * @var string internal status code
         */
        protected string $code,
        /**
         * @var string short status message for logging etc
         */
        protected string $msg,
        /**
         * @var array any extra values to pass along to the logger
         */
        protected array $log_context = [],
    ) {
    }

    public function getMsg(): string
    {
        return $this->msg;
    }

    public function getLogContext(): array
    {
        return $this->log_context;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'msg' => $this->msg,
            'log_context' => $this->log_context,
        ];
    }
}

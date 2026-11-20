<?php

declare(strict_types=1);

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
    /**
     * @param non-empty-string $code internal status code
     * @param string $msg short status message for logging etc
     * @param array<string, mixed> $log_context any extra values to pass along to the logger
     */
    protected function __construct(
        protected string $code,
        protected string $msg,
        protected array $log_context = [],
    ) {
    }

    public function getMsg(): string
    {
        return $this->msg;
    }

    /**
     * @return array<string, mixed>
     */
    public function getLogContext(): array
    {
        return $this->log_context;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @return array{code: non-empty-string, msg: string, log_context: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'msg' => $this->msg,
            'log_context' => $this->log_context,
        ];
    }
}

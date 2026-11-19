<?php

namespace Ingenerator\CloudTasksWrapper\Server;

class TaskResponse
{
    public function __construct(
        private readonly int $status_code,
        private readonly string $status_code_name,
        private readonly TaskHandlerResult $result,
    ) {
    }

    public function getStatusCode(): int
    {
        return $this->status_code;
    }

    public function getStatusCodeName(): string
    {
        return $this->status_code_name;
    }

    public function getResult(): TaskHandlerResult
    {
        return $this->result;
    }
}

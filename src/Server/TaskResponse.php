<?php

namespace Ingenerator\CloudTasksWrapper\Server;

class TaskResponse
{
    public function __construct(
        private int $status_code,
        private string $status_code_name,
        private TaskHandlerResult $result,
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

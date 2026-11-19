<?php

namespace Ingenerator\CloudTasksWrapper\Server;

class TaskResponse
{
    private int $status_code;
    private string $status_code_name;
    private TaskHandlerResult $result;

    public function __construct(int $http_code, string $http_code_string, TaskHandlerResult $result)
    {
        $this->status_code = $http_code;
        $this->status_code_name = $http_code_string;
        $this->result = $result;
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

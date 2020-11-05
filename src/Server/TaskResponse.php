<?php


namespace Ingenerator\CloudTasksWrapper\Server;


class TaskResponse
{
    private int $status_code;
    private string $status_code_name;
    private TaskHandlerResult $result;

    public function __construct(int $http_code, string $http_code_string, TaskHandlerResult $result)
    {
        $this->status_code      = $http_code;
        $this->status_code_name = $http_code_string;
        $this->result           = $result;
    }

    /**
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->status_code;
    }

    /**
     * @return string
     */
    public function getStatusCodeName(): string
    {
        return $this->status_code_name;
    }

    /**
     * @return \Ingenerator\CloudTasksWrapper\Server\TaskHandlerResult
     */
    public function getResult(): TaskHandlerResult
    {
        return $this->result;
    }


}

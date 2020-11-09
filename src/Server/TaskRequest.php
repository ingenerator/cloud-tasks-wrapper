<?php


namespace Ingenerator\CloudTasksWrapper\Server;


use Psr\Http\Message\ServerRequestInterface;

class TaskRequest
{
    protected ServerRequestInterface $request;
    protected string $task_type;

    public function __construct(ServerRequestInterface $request, string $task_type)
    {
        $this->request   = $request;
        $this->task_type = $task_type;
    }

    public function getFullUrl(): string
    {
        return $this->request->getUri();
    }

    public function getTaskType(): string
    {
        return $this->task_type;
    }

}

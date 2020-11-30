<?php


namespace Ingenerator\CloudTasksWrapper\Server;

use Ingenerator\CloudTasksWrapper\Server\TaskResult\CoreTaskResult;
use Psr\Http\Message\ServerRequestInterface;

class TaskController
{

    protected TaskHandlerChain $chain;

    protected TaskHandlerFactory $handler_factory;

    protected TaskResultCodeMapper $result_mapper;

    protected string $url_pattern;

    public function __construct(
        TaskHandlerChain $chain,
        TaskHandlerFactory $handler_factory,
        TaskResultCodeMapper $result_mapper,
        string $url_pattern
    ) {
        $this->chain           = $chain;
        $this->handler_factory = $handler_factory;
        $this->url_pattern     = $url_pattern;
        $this->result_mapper   = $result_mapper;
    }

    public function handle(ServerRequestInterface $request): TaskResponse
    {
        $task_req = $this->createTaskRequest($request);
        try {
            $handler  = $this->handler_factory->getHandler($task_req->getTaskType());
            $result   = $this->chain->process($task_req, $handler);
        } catch (UnknownTaskTypeException $e) {
            $result = CoreTaskResult::handlerNotFound($task_req->getTaskType());
        }

        return $this->result_mapper->getHttpResponse($result);
    }

    protected function createTaskRequest(ServerRequestInterface $request): TaskRequest
    {
        $path = $request->getUri()->getPath();
        if ( ! \preg_match($this->url_pattern, $path, $matches)) {
            throw new \InvalidArgumentException(
                'URL path `'.$path.'` does not match task URL pattern `'.$this->url_pattern.'`'
            );
        };

        if ( ! isset($matches['task_type'])) {
            throw new \InvalidArgumentException(
                __CLASS__.'->url_pattern must include a <task_type> capture group'
            );
        }

        return new TaskRequest($request, $matches['task_type']);
    }

}

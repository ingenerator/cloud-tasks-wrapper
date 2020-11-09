<?php


namespace Ingenerator\CloudTasksWrapper\Server;


class TaskHandlerChain
{
    /**
     * @var \Ingenerator\CloudTasksWrapper\Server\TaskHandler
     */
    protected ?TaskHandler $handler = NULL;

    /**
     * @var \Ingenerator\CloudTasksWrapper\Server\TaskHandlerMiddleware[]
     */
    protected $middlewares = [];

    public function __construct(TaskHandlerMiddleware...$middlewares)
    {
        $this->middlewares = $middlewares;
    }

    public function process(TaskRequest $request, TaskHandler $handler): TaskHandlerResult
    {
        $chain          = clone $this;
        $chain->handler = $handler;

        return $chain->nextHandler($request);
    }

    public function nextHandler(TaskRequest $request): TaskHandlerResult
    {
        if ( ! $this->handler) {
            throw new \UnderflowException('No TaskHandler was provided to '.__CLASS__);
        }
        if ($middleware = \array_shift($this->middlewares)) {
            return $middleware->process($request, $this);
        }

        return $this->handler->handle($request);
    }

}

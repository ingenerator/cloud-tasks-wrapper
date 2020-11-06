<?php


namespace Ingenerator\CloudTasksWrapper\Server;


use Psr\Http\Message\ServerRequestInterface;

class TaskHandlerChain
{
    /**
     * @var \Ingenerator\CloudTasksWrapper\Server\TaskHandlerMiddleware[]
     */
    protected $middlewares = [];

    protected TaskHandler $handler;

    public function __construct(TaskHandlerMiddleware...$middlewares)
    {
        $this->middlewares = $middlewares;
    }

    public function nextHandler(ServerRequestInterface $request): TaskHandlerResult
    {
        if ($middleware = \array_shift($this->middlewares)) {
            return $middleware->handle($request, $this);
        }

        return $this->handler->handle($request);
    }

}

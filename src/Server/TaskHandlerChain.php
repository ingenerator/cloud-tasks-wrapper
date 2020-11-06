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

    public function nextHandler(ServerRequestInterface $request): TaskHandlerResult
    {
        throw new \BadMethodCallException('Not tested yet');
        if ($middleware = \array_shift($this->middlewares)) {
            return $middleware->handle($request, $this);
        }

        return $this->handler->handle($request);
    }

}

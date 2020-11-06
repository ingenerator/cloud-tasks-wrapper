<?php

namespace Ingenerator\CloudTasksWrapper\Server\Middleware;

use Ingenerator\CloudTasksWrapper\Server\TaskHandlerChain;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerMiddleware;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerResult;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Predominantly used for testing other middlewares, to allow test suites to stub a chain
 */
class TaskCallableExecutingMiddleware implements TaskHandlerMiddleware
{
    protected $callable;

    public function __construct(callable $callable)
    {
        $this->callable = $callable;
    }

    public function handle(
        ServerRequestInterface $request,
        TaskHandlerChain $chain
    ): TaskHandlerResult {
        $func = $this->callable;

        return $func($request);
    }

}

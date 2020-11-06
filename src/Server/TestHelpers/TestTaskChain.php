<?php


namespace Ingenerator\CloudTasksWrapper\Server\TestHelpers;


use Ingenerator\CloudTasksWrapper\Server\Middleware\TaskCallableExecutingMiddleware;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerChain;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerResult;
use Ingenerator\CloudTasksWrapper\Server\TaskResult\ArbitraryTaskResult;
use Psr\Http\Message\ServerRequestInterface;

class TestTaskChain
{

    public static function withArbitraryResult(
        string $code,
        ?string $msg = NULL,
        array $log_context = []
    ): TaskHandlerChain {
        return static::withFixedResult(new ArbitraryTaskResult($code, $msg, $log_context));
    }

    public static function withFixedResult(TaskHandlerResult $result): TaskHandlerChain
    {
        return static::will(
            function (ServerRequestInterface $request) use ($result) { return $result; }
        );
    }

    public static function will(callable $handler): TaskHandlerChain
    {
        return new TaskHandlerChain(
            new TaskCallableExecutingMiddleware($handler)
        );
    }

    public static function neverCallsNext(): TaskHandlerChain
    {
        return static::will(
            function () {
                throw new \LogicException('Did not expect $chain->nextHandler() to be called');
            }
        );
    }

}

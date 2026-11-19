<?php

namespace Ingenerator\CloudTasksWrapper\TestHelpers\Server;

use BadMethodCallException;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerChain;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerMiddleware;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerResult;
use Ingenerator\CloudTasksWrapper\Server\TaskRequest;

/**
 * Predominantly used for testing other middlewares, to allow test suites to stub a chain.
 */
final class TestMiddleware implements TaskHandlerMiddleware
{
    private $callable;

    public static function callsNext(): TestMiddleware
    {
        return new self(
            fn (TaskRequest $req, TaskHandlerChain $chain): TaskHandlerResult => $chain->nextHandler($req)
        );
    }

    public static function neverCalled(): TestMiddleware
    {
        return new self(
            function (): never { throw new BadMethodCallException(self::class.' should never be called'); }
        );
    }

    public static function returnsResult(TaskHandlerResult $result): TestMiddleware
    {
        return new self(
            fn (): TaskHandlerResult => $result
        );
    }

    public function __construct(callable $callable)
    {
        $this->callable = $callable;
    }

    public function process(TaskRequest $request, TaskHandlerChain $chain): TaskHandlerResult
    {
        return ($this->callable)($request, $chain);
    }
}

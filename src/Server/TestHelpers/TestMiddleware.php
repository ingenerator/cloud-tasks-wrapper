<?php

namespace Ingenerator\CloudTasksWrapper\Server\TestHelpers;

use Ingenerator\CloudTasksWrapper\Server\TaskHandlerChain;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerMiddleware;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerResult;
use Ingenerator\CloudTasksWrapper\Server\TaskRequest;

/**
 * Predominantly used for testing other middlewares, to allow test suites to stub a chain
 */
class TestMiddleware implements TaskHandlerMiddleware
{
    protected $callable;

    public static function callsNext(): TestMiddleware
    {
        return new static(
            function (TaskRequest $req, TaskHandlerChain $chain) {
                return $chain->nextHandler($req);
            }
        );
    }

    public static function neverCalled(): TestMiddleware
    {
        return new static(
            function () { throw new \BadMethodCallException(__CLASS__.' should never be called'); }
        );
    }

    public static function returnsResult(TaskHandlerResult $result): TestMiddleware
    {
        return new static(
            function () use ($result) { return $result; }
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

<?php

namespace Ingenerator\CloudTasksWrapper\TestHelpers\Server;

use BadMethodCallException;
use Ingenerator\CloudTasksWrapper\Server\TaskHandler;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerResult;
use Ingenerator\CloudTasksWrapper\Server\TaskRequest;
use PHPUnit\Framework\Assert;

class TestTaskHandler implements TaskHandler
{
    protected $callable;

    public static function expectsReqAndReturns(
        TaskRequest $expect_req,
        TaskHandlerResult $result,
    ): TestTaskHandler {
        return new static(
            function ($got_req) use ($expect_req, $result): TaskHandlerResult {
                Assert::assertSame($expect_req, $got_req);

                return $result;
            }
        );
    }

    public static function neverCalled(): TestTaskHandler
    {
        return new static(
            function (): void { throw new BadMethodCallException('Did not expect handler call'); }
        );
    }

    public static function returnsResult(TaskHandlerResult $result): TestTaskHandler
    {
        return new static(fn (): TaskHandlerResult => $result);
    }

    public function __construct(callable $callable)
    {
        $this->callable = $callable;
    }

    public function handle(TaskRequest $request): TaskHandlerResult
    {
        return ($this->callable)($request);
    }
}

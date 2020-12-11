<?php


namespace Ingenerator\CloudTasksWrapper\TestHelpers\Server;


use Ingenerator\CloudTasksWrapper\Server\TaskHandler;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerChain;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerResult;
use Ingenerator\CloudTasksWrapper\Server\TaskResult\ArbitraryTaskResult;

class TestTaskChain extends TaskHandlerChain
{
    protected bool $allow_next = TRUE;

    public static function withArbitraryResult(
        string $code,
        ?string $msg = NULL,
        array $log_context = []
    ): TestTaskChain {
        return static::withFixedResult(new ArbitraryTaskResult($code, $msg, $log_context));
    }

    public static function withFixedResult(TaskHandlerResult $result): TestTaskChain
    {
        return static::withHandler(TestTaskHandler::returnsResult($result), TRUE);
    }

    public static function will(callable $handler): TestTaskChain
    {
        return static::withHandler(new TestTaskHandler($handler), TRUE);
    }

    public static function willThrow(\Throwable $e): TestTaskChain
    {
        return static::will(function () use ($e) { throw $e; });
    }

    public static function withHandler(TaskHandler $handler, bool $allow_next = TRUE): TestTaskChain
    {
        $chain             = new static;
        $chain->handler    = $handler;
        $chain->allow_next = $allow_next;

        return $chain;
    }

    public static function nextNeverCalled(): TestTaskChain
    {
        $i             = new static;
        $i->handler    = TestTaskHandler::neverCalled();
        $i->allow_next = FALSE;

        return $i;
    }

    public function nextHandler(\Ingenerator\CloudTasksWrapper\Server\TaskRequest $request): TaskHandlerResult
    {
        if ( ! $this->allow_next) {
            throw new \BadMethodCallException('Unexpected call to '.__METHOD__);
        }

        return parent::nextHandler($request);
    }


}

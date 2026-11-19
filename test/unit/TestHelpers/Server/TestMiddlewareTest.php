<?php

namespace test\unit\Ingenerator\CloudTasksWrapper\TestHelpers\Server;

use Closure;
use Ingenerator\CloudTasksWrapper\Server\TaskRequest;
use Ingenerator\CloudTasksWrapper\Server\TaskResult\ArbitraryTaskResult;
use Ingenerator\CloudTasksWrapper\TestHelpers\Server\TaskRequestStub;
use Ingenerator\CloudTasksWrapper\TestHelpers\Server\TestMiddleware;
use Ingenerator\CloudTasksWrapper\TestHelpers\Server\TestTaskChain;
use PHPUnit\Framework\TestCase;

class TestMiddlewareTest extends TestCase
{
    /**
     * @var Closure
     */
    protected $callable;

    public function testItIsInitialisable(): void
    {
        $this->assertInstanceOf(
            TestMiddleware::class,
            $this->newSubject()
        );
    }

    public function testItPassesToRequestAndReturnsResultWithoutCallingNext(): void
    {
        $result = new ArbitraryTaskResult('anything');
        $request = TaskRequestStub::any();
        $this->callable = function (TaskRequest $r) use ($request, $result): ArbitraryTaskResult {
            $this->assertSame($request, $r);

            return $result;
        };
        $this->assertSame(
            $result,
            $this->newSubject()->process($request, TestTaskChain::nextNeverCalled())
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->callable = (fn (): ArbitraryTaskResult => new ArbitraryTaskResult('anything'));
    }

    protected function newSubject(): TestMiddleware
    {
        return new TestMiddleware($this->callable);
    }
}

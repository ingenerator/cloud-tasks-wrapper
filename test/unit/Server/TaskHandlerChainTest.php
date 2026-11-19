<?php

namespace test\unit\Ingenerator\CloudTasksWrapper\Server;

use Ingenerator\CloudTasksWrapper\Server\TaskHandlerChain;
use Ingenerator\CloudTasksWrapper\Server\TaskResult\ArbitraryTaskResult;
use Ingenerator\CloudTasksWrapper\TestHelpers\Server\TaskRequestStub;
use Ingenerator\CloudTasksWrapper\TestHelpers\Server\TestMiddleware;
use Ingenerator\CloudTasksWrapper\TestHelpers\Server\TestTaskHandler;
use Ingenerator\PHPUtils\Object\ObjectPropertyRipper;
use PHPUnit\Framework\TestCase;
use UnderflowException;

class TaskHandlerChainTest extends TestCase
{
    protected $middlewares = [];

    public function testItIsInitialisable(): void
    {
        $this->assertInstanceOf(TaskHandlerChain::class, $this->newSubject());
    }

    public function testItThrowsIfCallingNextHandlerWithoutInitialisingHandler(): void
    {
        $this->middlewares = [TestMiddleware::neverCalled()];
        $subject = $this->newSubject();
        $this->expectException(UnderflowException::class);
        $subject->nextHandler(TaskRequestStub::any());
    }

    public function testItsProcessMethodDoesNotModifyStateOfOriginalObject(): void
    {
        $this->middlewares = [
            TestMiddleware::callsNext(),
            TestMiddleware::callsNext(),
        ];

        $subject = $this->newSubject();

        $subject->process(
            TaskRequestStub::any(),
            new TestTaskHandler(
                fn (): ArbitraryTaskResult => new ArbitraryTaskResult('anything')
            )
        );
        $this->assertSame(
            ['middlewares' => $this->middlewares, 'handler' => null],
            ObjectPropertyRipper::rip($subject, ['middlewares', 'handler']),
            'Does not modify state of original object'
        );
    }

    public function testItsProcessCallsHandlerAndReturnsResultWithoutMiddlewares(): void
    {
        $this->middlewares = [];
        $rsp = new ArbitraryTaskResult('anything');
        $request = TaskRequestStub::any();
        $handler = TestTaskHandler::expectsReqAndReturns($request, $rsp);

        $this->assertSame(
            $rsp,
            $this->newSubject()->process($request, $handler),
            'Returns handler response'
        );
    }

    public function testItsProcessCallsMiddlewaresBeforeReturningHandlerResult(): void
    {
        $rsp = new ArbitraryTaskResult('anything');
        $request = TaskRequestStub::any();
        $middleware_calls = [];
        $this->middlewares = [
            new TestMiddleware(
                function ($got_req, TaskHandlerChain $chain) use (&$middleware_calls) {
                    $middleware_calls[] = ['one', $got_req];

                    return $chain->nextHandler($got_req);
                }
            ),
            new TestMiddleware(
                function ($got_req, TaskHandlerChain $chain) use (&$middleware_calls) {
                    $middleware_calls[] = ['two', $got_req];

                    return $chain->nextHandler($got_req);
                }
            ),
        ];

        $handler = TestTaskHandler::expectsReqAndReturns($request, $rsp);

        $this->assertSame(
            $rsp,
            $this->newSubject()->process($request, $handler),
            'Returns handler response'
        );
        $this->assertSame(
            [
                ['one', $request],
                ['two', $request],
            ],
            $middleware_calls
        );
    }

    public function testItsProcessShortCircuitsIfMiddlewareReturnsResultWithoutNext(): void
    {
        $rsp = new ArbitraryTaskResult('anything');
        $request = TaskRequestStub::any();
        $this->middlewares = [
            TestMiddleware::callsNext(),
            TestMiddleware::returnsResult($rsp),
            TestMiddleware::neverCalled(),
        ];
        $this->assertSame(
            $rsp,
            $this->newSubject()->process($request, TestTaskHandler::neverCalled()),
            'Returns middleware short-circuit response'
        );
    }

    protected function newSubject(): TaskHandlerChain
    {
        return new TaskHandlerChain(...$this->middlewares);
    }
}

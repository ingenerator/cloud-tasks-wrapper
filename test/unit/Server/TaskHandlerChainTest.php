<?php


namespace test\unit\Ingenerator\CloudTasksWrapper\Server;


use GuzzleHttp\Psr7\ServerRequest;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerChain;
use Ingenerator\CloudTasksWrapper\Server\TaskResult\ArbitraryTaskResult;
use Ingenerator\CloudTasksWrapper\Server\TestHelpers\TestMiddleware;
use Ingenerator\CloudTasksWrapper\Server\TestHelpers\TestTaskHandler;
use Ingenerator\PHPUtils\Object\ObjectPropertyRipper;
use PHPUnit\Framework\TestCase;

class TaskHandlerChainTest extends TestCase
{
    protected $middlewares = [];

    public function test_it_is_initialisable()
    {
        $this->assertInstanceOf(TaskHandlerChain::class, $this->newSubject());
    }

    public function test_it_throws_if_calling_next_handler_without_initialising_handler()
    {
        $this->middlewares = [TestMiddleware::neverCalled()];
        $subject           = $this->newSubject();
        $this->expectException(\UnderflowException::class);
        $subject->nextHandler(new ServerRequest('POST', 'anything'));
    }

    public function test_its_process_method_does_not_modify_state_of_original_object()
    {
        $this->middlewares = [
            TestMiddleware::callsNext(),
            TestMiddleware::callsNext(),
        ];

        $subject = $this->newSubject();

        $subject->process(
            new ServerRequest('POST', 'anything'),
            new TestTaskHandler(
                function () { return new ArbitraryTaskResult('anything'); }
            )
        );
        $this->assertSame(
            ['middlewares' => $this->middlewares, 'handler' => NULL],
            ObjectPropertyRipper::rip($subject, ['middlewares', 'handler']),
            'Does not modify state of original object'
        );
    }

    public function test_its_process_calls_handler_and_returns_result_without_middlewares()
    {
        $this->middlewares = [];
        $rsp               = new ArbitraryTaskResult('anything');
        $request           = new ServerRequest('POST', 'anything');
        $handler           = TestTaskHandler::expectsReqAndReturns($request, $rsp);

        $this->assertSame(
            $rsp,
            $this->newSubject()->process($request, $handler),
            'Returns handler response'
        );
    }

    public function test_its_process_calls_middlewares_before_returning_handler_result()
    {
        $rsp               = new ArbitraryTaskResult('anything');
        $request           = new ServerRequest('POST', 'anything');
        $middleware_calls  = [];
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
                ['two', $request]
            ],
            $middleware_calls
        );
    }


    public function test_its_process_short_circuits_if_middleware_returns_result_without_next()
    {
        $rsp               = new ArbitraryTaskResult('anything');
        $request           = new ServerRequest('POST', 'anything');
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

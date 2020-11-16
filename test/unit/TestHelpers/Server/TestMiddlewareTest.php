<?php


namespace test\unit\Ingenerator\CloudTasksWrapper\TestHelpers\Server;


use GuzzleHttp\Psr7\ServerRequest;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerChain;
use Ingenerator\CloudTasksWrapper\Server\TaskRequest;
use Ingenerator\CloudTasksWrapper\Server\TaskResult\ArbitraryTaskResult;
use Ingenerator\CloudTasksWrapper\TestHelpers\Server\TaskRequestStub;
use Ingenerator\CloudTasksWrapper\TestHelpers\Server\TestMiddleware;
use Ingenerator\CloudTasksWrapper\TestHelpers\Server\TestTaskChain;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

class TestMiddlewareTest extends TestCase
{
    /**
     * @var \Closure
     */
    protected $callable;

    public function test_it_is_initialisable()
    {
        $this->assertInstanceOf(
            TestMiddleware::class,
            $this->newSubject()
        );
    }

    public function test_it_passes_to_request_and_returns_result_without_calling_next()
    {
        $result         = new ArbitraryTaskResult('anything');
        $request        = TaskRequestStub::any();
        $this->callable = function (TaskRequest $r) use ($request, $result) {
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
        $this->callable = function () { return new ArbitraryTaskResult('anything'); };
    }


    protected function newSubject(): TestMiddleware
    {
        return new TestMiddleware($this->callable);
    }

}

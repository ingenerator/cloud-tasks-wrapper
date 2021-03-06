<?php


namespace test\unit\Ingenerator\CloudTasksWrapper\Server\Middleware;


use Ingenerator\CloudTasksWrapper\Server\Middleware\TaskMutexLockingMiddleware;
use Ingenerator\CloudTasksWrapper\Server\TaskResult\ArbitraryTaskResult;
use Ingenerator\CloudTasksWrapper\Server\TaskResult\CoreTaskResult;
use Ingenerator\CloudTasksWrapper\TestHelpers\Server\TaskRequestStub;
use Ingenerator\CloudTasksWrapper\TestHelpers\Server\TestTaskChain;
use Ingenerator\CloudTasksWrapper\TestHelpers\Server\TestTaskHandler;
use Ingenerator\PHPUtils\Mutex\MockMutexWrapper;
use PHPUnit\Framework\TestCase;

class TaskMutexLockingMiddlewareTest extends TestCase
{
    protected MockMutexWrapper $mutex;

    public function test_it_is_initialisable()
    {
        $this->assertInstanceOf(TaskMutexLockingMiddleware::class, $this->newSubject());
    }

    /**
     * @testWith ["https://my.handler/_task/anything", "task-5c737dfa9f1aee5d5cee02ded109cb25"]
     *           ["https://my.handler/_task/anything?foo=bar", "task-58edbfa5da73f53ad2324fd00aacdcdd"]
     */
    public function test_it_takes_mutex_based_on_request_url($url, $expect_lock_name)
    {
        $this->mutex = new MockMutexWrapper;
        $result      = $this->newSubject()->process(
            TaskRequestStub::with(['url' => $url]),
            TestTaskChain::will(
                function () {
                    return new ArbitraryTaskResult('mutex-name', $this->mutex->getCurrentLockName());
                }
            )
        );
        $this->assertSame($expect_lock_name, $result->getMsg());
    }

    public function test_it_returns_result_of_next_handler_if_mutex_available()
    {
        $res    = new ArbitraryTaskResult('got-mutex');
        $req    = TaskRequestStub::any();
        $result = $this->newSubject()->process(
            $req,
            TestTaskChain::withHandler(TestTaskHandler::expectsReqAndReturns($req, $res))
        );
        $this->assertSame($res, $result);
    }

    public function test_it_returns_mutex_timeout_without_running_handler_if_locked()
    {
        $this->mutex->willTimeoutEverything();

        $result = $this->newSubject()->process(
            TaskRequestStub::any(),
            TestTaskChain::nextNeverCalled()
        );
        $this->assertSame(CoreTaskResult::MUTEX_TIMEOUT, $result->getCode());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->mutex = new MockMutexWrapper;
    }

    protected function newSubject()
    {
        return new TaskMutexLockingMiddleware($this->mutex);
    }
}

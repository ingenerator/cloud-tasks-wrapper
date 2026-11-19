<?php

declare(strict_types=1);

namespace test\unit\Ingenerator\CloudTasksWrapper\Server\Middleware;

use Ingenerator\CloudTasksWrapper\Server\Middleware\TaskMutexLockingMiddleware;
use Ingenerator\CloudTasksWrapper\Server\TaskResult\ArbitraryTaskResult;
use Ingenerator\CloudTasksWrapper\Server\TaskResult\CoreTaskResult;
use Ingenerator\CloudTasksWrapper\TestHelpers\Server\TaskRequestStub;
use Ingenerator\CloudTasksWrapper\TestHelpers\Server\TestTaskChain;
use Ingenerator\CloudTasksWrapper\TestHelpers\Server\TestTaskHandler;
use Ingenerator\PHPUtils\Mutex\MockMutexWrapper;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class TaskMutexLockingMiddlewareTest extends TestCase
{
    protected MockMutexWrapper $mutex;

    public function testItIsInitialisable(): void
    {
        $this->assertInstanceOf(TaskMutexLockingMiddleware::class, $this->newSubject());
    }

    #[TestWith(['https://my.handler/_task/anything', 'task-5c737dfa9f1aee5d5cee02ded109cb25'])]
    #[TestWith(['https://my.handler/_task/anything?foo=bar', 'task-58edbfa5da73f53ad2324fd00aacdcdd'])]
    public function testItTakesMutexBasedOnRequestUrl($url, $expect_lock_name): void
    {
        $this->mutex = new MockMutexWrapper();
        $result = $this->newSubject()->process(
            TaskRequestStub::with(['url' => $url]),
            TestTaskChain::will(
                fn (): ArbitraryTaskResult => new ArbitraryTaskResult('mutex-name', $this->mutex->getCurrentLockName())
            )
        );
        $this->assertSame($expect_lock_name, $result->getMsg());
    }

    public function testItReturnsResultOfNextHandlerIfMutexAvailable(): void
    {
        $res = new ArbitraryTaskResult('got-mutex');
        $req = TaskRequestStub::any();
        $result = $this->newSubject()->process(
            $req,
            TestTaskChain::withHandler(TestTaskHandler::expectsReqAndReturns($req, $res))
        );
        $this->assertSame($res, $result);
    }

    public function testItReturnsMutexTimeoutWithoutRunningHandlerIfLocked(): void
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
        $this->mutex = new MockMutexWrapper();
    }

    protected function newSubject(): TaskMutexLockingMiddleware
    {
        return new TaskMutexLockingMiddleware($this->mutex);
    }
}

<?php


namespace test\unit\Ingenerator\CloudTasksWrapper\Server\Middleware;


use GuzzleHttp\Psr7\ServerRequest;
use Ingenerator\CloudTasksWrapper\Server\Middleware\TaskMutexLockingMiddleware;
use Ingenerator\CloudTasksWrapper\Server\TaskResult\ArbitraryTaskResult;
use Ingenerator\CloudTasksWrapper\Server\TaskResult\CoreTaskResult;
use Ingenerator\CloudTasksWrapper\Server\TestHelpers\TestTaskChain;
use Ingenerator\PHPUtils\Mutex\MockMutexWrapper;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

class TaskMutexLockingMiddlewareTest extends TestCase
{
    protected MockMutexWrapper $mutex;

    public function test_it_is_initialisable()
    {
        $this->assertInstanceOf(TaskMutexLockingMiddleware::class, $this->newSubject());
    }

    public function test_it_takes_mutex_based_on_request_url()
    {
        $this->markTestIncomplete();
    }

    public function test_it_returns_result_of_next_handler_if_mutex_available()
    {
        $result = $this->newSubject()->handle(
            new ServerRequest('POST', '/task/some-task'),
            TestTaskChain::will(
                function (ServerRequestInterface $req) {
                    return new ArbitraryTaskResult('got-mutex', $req->getUri());
                }
            )
        );
        $this->assertSame(
            [
                'code' => 'got-mutex',
                'url'  => '/task/some-task'
            ],
            [
                'code' => $result->getCode(),
                'url'  => $result->getMsg()
            ]
        );
    }

    public function test_it_returns_mutex_timeout_without_running_handler_if_locked()
    {
        $this->mutex->willTimeoutEverything();

        $result = $this->newSubject()->handle(
            new ServerRequest('POST', '/task/some-task'),
            TestTaskChain::neverCallsNext()
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

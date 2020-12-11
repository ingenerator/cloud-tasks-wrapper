<?php


namespace test\unit\Ingenerator\CloudTasksWrapper\Server\Middleware;


use Ingenerator\CloudTasksWrapper\Server\CloudTaskCannotBeValidException;
use Ingenerator\CloudTasksWrapper\Server\Middleware\ExceptionCatchingMiddleware;
use Ingenerator\CloudTasksWrapper\Server\TaskResult\ArbitraryTaskResult;
use Ingenerator\CloudTasksWrapper\Server\TaskResult\CoreTaskResult;
use Ingenerator\CloudTasksWrapper\TestHelpers\Server\TaskRequestStub;
use Ingenerator\CloudTasksWrapper\TestHelpers\Server\TestTaskChain;
use PHPUnit\Framework\TestCase;

class ExceptionCatchingMiddlewareTest extends TestCase
{
    public function test_it_is_initialisable()
    {
        $this->assertInstanceOf(ExceptionCatchingMiddleware::class, $this->newSubject());
    }

    public function test_it_converts_task_cannot_be_valid_exception_to_cannot_be_valid()
    {
        $exception = new CloudTaskCannotBeValidException('Missing some crucial thing');
        $result    = $this->newSubject()->process(
            TaskRequestStub::any(),
            TestTaskChain::willThrow($exception)
        );
        $this->assertEquals(
            [
                'code'        => CoreTaskResult::CANNOT_BE_VALID,
                'msg'         => 'Cannot be valid: Missing some crucial thing',
                'log_context' => ['exception' => $exception],
            ],
            $result->toArray()
        );
    }

    /**
     * @testWith ["Error"]
     *           ["RuntimeException"]
     */
    public function test_it_converts_uncaught_throwable_to_uncaught_exception_result($err_class)
    {
        $exception = new $err_class('I have a problem');
        $result    = $this->newSubject()->process(
            TaskRequestStub::any(),
            TestTaskChain::willThrow($exception)
        );
        $this->assertEquals(
            [
                'code'        => CoreTaskResult::UNCAUGHT_EXCEPTION,
                'msg'         => '['.$err_class.'] I have a problem',
                'log_context' => ['exception' => $exception],
            ],
            $result->toArray()
        );
    }

    public function test_it_returns_unmodified_result_from_next_handler_on_success()
    {
        $expect = new ArbitraryTaskResult('someCustomCode');
        $result = $this->newSubject()->process(
            TaskRequestStub::any(),
            TestTaskChain::withFixedResult($expect)
        );
        $this->assertSame($expect, $result);
    }

    protected function newSubject()
    {
        return new ExceptionCatchingMiddleware();
    }


}

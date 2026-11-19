<?php

namespace test\unit\Ingenerator\CloudTasksWrapper\Server\Middleware;

use Ingenerator\CloudTasksWrapper\Server\CloudTaskCannotBeValidException;
use Ingenerator\CloudTasksWrapper\Server\Middleware\ExceptionCatchingMiddleware;
use Ingenerator\CloudTasksWrapper\Server\TaskResult\ArbitraryTaskResult;
use Ingenerator\CloudTasksWrapper\Server\TaskResult\CoreTaskResult;
use Ingenerator\CloudTasksWrapper\TestHelpers\Server\TaskRequestStub;
use Ingenerator\CloudTasksWrapper\TestHelpers\Server\TestTaskChain;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class ExceptionCatchingMiddlewareTest extends TestCase
{
    public function testItIsInitialisable(): void
    {
        $this->assertInstanceOf(ExceptionCatchingMiddleware::class, $this->newSubject());
    }

    public function testItConvertsTaskCannotBeValidExceptionToCannotBeValid(): void
    {
        $exception = new CloudTaskCannotBeValidException('Missing some crucial thing');
        $result = $this->newSubject()->process(
            TaskRequestStub::any(),
            TestTaskChain::willThrow($exception)
        );
        $this->assertEquals(
            [
                'code' => CoreTaskResult::CANNOT_BE_VALID,
                'msg' => 'Cannot be valid: Missing some crucial thing',
                'log_context' => ['exception' => $exception],
            ],
            $result->toArray()
        );
    }

    #[TestWith(['Error'])]
    #[TestWith(['RuntimeException'])]
    public function testItConvertsUncaughtThrowableToUncaughtExceptionResult($err_class): void
    {
        $exception = new $err_class('I have a problem');
        $result = $this->newSubject()->process(
            TaskRequestStub::any(),
            TestTaskChain::willThrow($exception)
        );
        $this->assertEquals(
            [
                'code' => CoreTaskResult::UNCAUGHT_EXCEPTION,
                'msg' => '['.$err_class.'] I have a problem',
                'log_context' => ['exception' => $exception],
            ],
            $result->toArray()
        );
    }

    public function testItReturnsUnmodifiedResultFromNextHandlerOnSuccess(): void
    {
        $expect = new ArbitraryTaskResult('someCustomCode');
        $result = $this->newSubject()->process(
            TaskRequestStub::any(),
            TestTaskChain::withFixedResult($expect)
        );
        $this->assertSame($expect, $result);
    }

    protected function newSubject(): ExceptionCatchingMiddleware
    {
        return new ExceptionCatchingMiddleware();
    }
}

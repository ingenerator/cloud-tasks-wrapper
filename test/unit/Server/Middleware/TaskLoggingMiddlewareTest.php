<?php


namespace test\unit\Ingenerator\CloudTasksWrapper\Server\Middleware;


use GuzzleHttp\Psr7\ServerRequest;
use Ingenerator\CloudTasksWrapper\Server\Middleware\TaskLoggingMiddleware;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerChain;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerResult;
use Ingenerator\CloudTasksWrapper\Server\TaskResult\ArbitraryTaskResult;
use Ingenerator\CloudTasksWrapper\Server\TaskResultCodeMapper;
use Ingenerator\PHPUtils\DateTime\Clock\StoppedMockClock;
use Ingenerator\PHPUtils\StringEncoding\JSON;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\Test\TestLogger;

class TaskLoggingMiddlewareTest extends TestCase
{
    protected LoggerInterface $logger;

    protected TaskResultCodeMapper $code_mapper;

    protected StoppedMockClock $clock;

    public function test_it_is_initialisable()
    {
        $this->assertInstanceOf(TaskLoggingMiddleware::class, $this->newSubject());
    }

    public function test_it_returns_unmodified_result_from_next_handler()
    {
        $expect = new ArbitraryTaskResult('someCustomCode');
        $result = $this->newSubject()->handle(
            new ServerRequest('POST', '/some-task-handler'),
            TaskHandlerChainStub::withFixedResult($expect)
        );
        $this->assertSame($expect, $result);
    }

    public function test_it_logs_task_result_with_configured_loglevel()
    {
        $this->code_mapper = new TaskResultCodeMapper(
            ['someCustomCode' => ['loglevel' => LogLevel::WARNING]]
        );

        $this->newSubject()->handle(
            new ServerRequest('POST', '/some-task-handler'),
            TaskHandlerChainStub::withArbitraryResult('someCustomCode', 'I did not work')
        );

        $this->assertTrue(
            $this->logger->hasWarning('Task: [someCustomCode] I did not work'),
            'Should have logged correct message - got: '.JSON::encode($this->logger->records)
        );
    }

    public function test_it_includes_log_context_in_log()
    {
        $this->code_mapper = new TaskResultCodeMapper(
            ['someCustomCode' => ['loglevel' => LogLevel::INFO]]
        );

        $this->newSubject()->handle(
            new ServerRequest('POST', '/some-task-handler'),
            TaskHandlerChainStub::withArbitraryResult(
                'someCustomCode',
                'I worked',
                ['data' => 'I really did']
            ),
        );

        $log = $this->assertLoggedOnce(LogLevel::INFO);
        $this->assertSame(
            'I really did',
            $log['context']['data'],
            'Should have logged context'
        );
    }

    public function test_it_includes_timing_in_log()
    {
        $this->code_mapper = new TaskResultCodeMapper(
            ['someCustomCode' => ['loglevel' => LogLevel::INFO]]
        );

        $this->newSubject()->handle(
            new ServerRequest('POST', '/some-task-handler'),
            TaskHandlerChainStub::will(
                function () {
                    $this->clock->tickMicroseconds(213204);

                    return new ArbitraryTaskResult('someCustomCode');
                }
            )
        );

        $log = $this->assertLoggedOnce(LogLevel::INFO);
        $this->assertSame(213, $log['context']['time_ms'], 'Should have logged time');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock       = StoppedMockClock::atNow();
        $this->logger      = new TestLogger;
        $this->code_mapper = new TaskResultCodeMapper(
            ['someCustomCode' => ['loglevel' => LogLevel::WARNING]]
        );
    }

    protected function newSubject()
    {
        return new TaskLoggingMiddleware(
            $this->clock,
            $this->logger,
            $this->code_mapper
        );
    }

    protected function assertLoggedOnce(string $level): array
    {
        $infos = $this->logger->recordsByLevel[$level];
        $this->assertCount(1, $infos, 'Should have logged exactly once');

        return \array_shift($infos);
    }


}

class TaskHandlerChainStub extends TaskHandlerChain
{
    /**
     * @var callable
     */
    private $next_handler;

    public static function withArbitraryResult(
        string $code,
        ?string $msg = NULL,
        array $log_context = []
    ): TaskHandlerChainStub {
        return static::withFixedResult(new ArbitraryTaskResult($code, $msg, $log_context));
    }

    public static function withFixedResult(TaskHandlerResult $result): TaskHandlerChainStub
    {
        return static::will(
            function (ServerRequestInterface $request) use ($result) {
                return $result;
            }
        );
    }

    public static function will(callable $handler): TaskHandlerChainStub
    {
        $i               = new static;
        $i->next_handler = $handler;

        return $i;
    }

    public function nextHandler(ServerRequestInterface $request): TaskHandlerResult
    {
        $handler = $this->next_handler;

        return $handler($request);
    }
}

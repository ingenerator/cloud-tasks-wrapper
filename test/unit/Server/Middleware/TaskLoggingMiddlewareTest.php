<?php


namespace test\unit\Ingenerator\CloudTasksWrapper\Server\Middleware;


use Ingenerator\CloudTasksWrapper\Server\Middleware\TaskLoggingMiddleware;
use Ingenerator\CloudTasksWrapper\Server\TaskResult\ArbitraryTaskResult;
use Ingenerator\CloudTasksWrapper\Server\TaskResultCodeMapper;
use Ingenerator\CloudTasksWrapper\TestHelpers\Server\TaskRequestStub;
use Ingenerator\CloudTasksWrapper\TestHelpers\Server\TestTaskChain;
use Ingenerator\PHPUtils\DateTime\Clock\StoppedMockClock;
use Ingenerator\PHPUtils\StringEncoding\JSON;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\Test\TestLogger;

class TaskLoggingMiddlewareTest extends TestCase
{
    protected LoggerInterface      $logger;

    protected TaskResultCodeMapper $code_mapper;

    protected StoppedMockClock     $clock;

    protected                      $default_context = [];

    public function test_it_is_initialisable()
    {
        $this->assertInstanceOf(TaskLoggingMiddleware::class, $this->newSubject());
    }

    public function test_it_returns_unmodified_result_from_next_handler()
    {
        $expect = new ArbitraryTaskResult('someCustomCode');
        $result = $this->newSubject()->process(
            TaskRequestStub::any(),
            TestTaskChain::withFixedResult($expect)
        );
        $this->assertSame($expect, $result);
    }

    public function test_it_logs_task_result_with_configured_loglevel()
    {
        $this->code_mapper = new TaskResultCodeMapper(
            ['someCustomCode' => ['loglevel' => LogLevel::WARNING]]
        );

        $this->newSubject()->process(
            TaskRequestStub::any(),
            TestTaskChain::withArbitraryResult('someCustomCode', 'I did not work')
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

        $this->newSubject()->process(
            TaskRequestStub::any(),
            TestTaskChain::withArbitraryResult(
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

    public function test_it_includes_optional_context_in_all_logs()
    {
        $this->default_context = [
            'foo'  => 'bar',
            'data' => 'will be overwritten',
        ];
        $this->code_mapper     = new TaskResultCodeMapper(['code' => ['loglevel' => LogLevel::INFO]]);

        $this->newSubject()->process(
            TaskRequestStub::any(),
            TestTaskChain::withArbitraryResult('code', 'I worked', ['data' => 'Overwrote default']),
        );

        $log = $this->assertLoggedOnce(LogLevel::INFO);
        $this->assertSame(
            [
                'foo'  => 'bar',
                'data' => 'Overwrote default',
            ],
            [
                'foo'  => $log['context']['foo'],
                'data' => $log['context']['data'],
            ]
        );
    }

    public function test_it_includes_timing_in_log()
    {
        $this->code_mapper = new TaskResultCodeMapper(
            ['someCustomCode' => ['loglevel' => LogLevel::INFO]]
        );

        $this->newSubject()->process(
            TaskRequestStub::any(),
            TestTaskChain::will(
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
            $this->code_mapper,
            $this->default_context
        );
    }

    protected function assertLoggedOnce(string $level): array
    {
        $infos = $this->logger->recordsByLevel[$level];
        $this->assertCount(1, $infos, 'Should have logged exactly once');

        return \array_shift($infos);
    }

}

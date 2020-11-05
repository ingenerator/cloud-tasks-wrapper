<?php


namespace test\unit\Ingenerator\CloudTasksWrapper\Server;


use GuzzleHttp\Psr7\ServerRequest;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlingLogger;
use Ingenerator\CloudTasksWrapper\Server\TaskResult\ArbitraryTaskResult;
use Ingenerator\CloudTasksWrapper\Server\TaskResultCodeMapper;
use Ingenerator\PHPUtils\StringEncoding\JSON;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\Test\TestLogger;

class TaskHandlingLoggerTest extends TestCase
{
    protected LoggerInterface $logger;

    protected TaskResultCodeMapper $code_mapper;

    public function test_it_is_initialisable()
    {
        $this->assertInstanceOf(TaskHandlingLogger::class, $this->newSubject());
    }

    public function test_it_logs_task_result_with_configured_loglevel()
    {
        $this->code_mapper = new TaskResultCodeMapper(
            ['someCustomCode' => ['loglevel' => LogLevel::WARNING]]
        );

        $this->newSubject()->logResult(
            new ServerRequest('POST', '/some-task-handler'),
            new ArbitraryTaskResult('someCustomCode', 'I did not work'),
            105.20
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

        $this->newSubject()->logResult(
            new ServerRequest('POST', '/some-task-handler'),
            new ArbitraryTaskResult('someCustomCode', 'I worked', ['data' => 'I really did']),
            105.20
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

        $this->newSubject()->logResult(
            new ServerRequest('POST', '/some-task-handler'),
            new ArbitraryTaskResult('someCustomCode', 'I worked', ['data' => 'I really did']),
            105.20
        );

        $log = $this->assertLoggedOnce(LogLevel::INFO);
        $this->assertSame(105.20, $log['context']['time_ms'], 'Should have logged time');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger      = new TestLogger;
        $this->code_mapper = new TaskResultCodeMapper(
            ['someCustomCode' => ['loglevel' => LogLevel::WARNING]]
        );
    }

    protected function newSubject()
    {
        return new TaskHandlingLogger(
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

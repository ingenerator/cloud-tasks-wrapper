<?php

declare(strict_types=1);

namespace test\unit\Ingenerator\CloudTasksWrapper\Server;

use Ingenerator\CloudTasksWrapper\Server\TaskResult\ArbitraryTaskResult;
use Ingenerator\CloudTasksWrapper\Server\TaskResult\CoreTaskResult;
use Ingenerator\CloudTasksWrapper\Server\TaskResultCodeMapper;
use Ingenerator\PHPUtils\Object\ConstantDirectory;
use InvalidArgumentException;
use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

final class TaskResultCodeMapperTest extends TestCase
{
    /**
     * @var array<string, array{http_status?: int, loglevel?: string}>
     */
    protected array $custom_codes = [];

    public function testItIsInitialisable(): void
    {
        $this->assertInstanceOf(TaskResultCodeMapper::class, $this->newSubject());
    }

    public function testItBuildsHttpResponseFromTaskResultBasedOnConfig(): void
    {
        $this->custom_codes['myCustomStatus'] = ['http_status' => 200];
        $response = $this->newSubject()->getHttpResponse(
            new ArbitraryTaskResult('myCustomStatus')
        );
        $this->assertSame(
            [
                'code' => 200,
                'name' => 'MyCustomStatus',
            ],
            [
                'code' => $response->getStatusCode(),
                'name' => $response->getStatusCodeName(),
            ]
        );
    }

    public function testItProvidesLogLevelForTaskResultBasedOnConfig(): void
    {
        $this->custom_codes['myCustomStatus'] = ['loglevel' => LogLevel::INFO];
        $this->assertSame(
            LogLevel::INFO,
            $this->newSubject()->getLogLevel(CoreTaskResult::success('Anything'))
        );
    }

    public function testItThrowsForHttpResponseIfNotMappedOrInvalid(): void
    {
        $result = new ArbitraryTaskResult('somethingIsNotDefined');
        $subject = $this->newSubject();
        $this->expectException(InvalidArgumentException::class);
        $subject->getHttpResponse($result);
    }

    public function testItThrowsForLogLevelIfNotMapped(): void
    {
        $result = new ArbitraryTaskResult('somethingIsNotDefined');
        $subject = $this->newSubject();
        $this->expectException(InvalidArgumentException::class);
        $subject->getLogLevel($result);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function provider_core_task_results(): array
    {
        $consts = ConstantDirectory::forClass(CoreTaskResult::class)->listConstants();

        return array_map(fn (string $code): array => [$code], $consts);
    }

    #[DataProvider('provider_core_task_results')]
    public function testItsDefaultMappingDefinesValuesForAllCoreTaskResults(string $code): void
    {
        $result = new ArbitraryTaskResult($code);
        $subject = $this->newSubject();
        $response = $subject->getHttpResponse($result);
        $this->assertMatchesRegularExpression(
            '/^[2345]\d\d$/',
            (string) $response->getStatusCode(),
            'Defines a valid HTTP status code'
        );

        $this->assertContains(
            $subject->getLogLevel($result),
            ConstantDirectory::forClass(LogLevel::class)->listConstants(),
            'Defines a valid loglevel'
        );
    }

    /**
     * @return Iterator<int, array<array<string, array<string, int|string>|int|string>|string>>
     */
    public static function provider_code_overrides(): Iterator
    {
        yield [
            [CoreTaskResult::AUTH_NOT_PROVIDED => ['http_status' => 281]],
            CoreTaskResult::AUTH_NOT_PROVIDED,
            ['http' => 281, 'loglevel' => LogLevel::WARNING], // Uses default loglevel
        ];
        yield [
            [CoreTaskResult::MUTEX_TIMEOUT => ['loglevel' => LogLevel::EMERGENCY]],
            CoreTaskResult::MUTEX_TIMEOUT,
            ['http' => 409, 'loglevel' => LogLevel::EMERGENCY], // Uses default http code
        ];
        yield [
            [
                CoreTaskResult::BAD_HTTP_METHOD => [
                    'http_status' => 297,
                    'loglevel' => LogLevel::EMERGENCY,
                ],
            ],
            CoreTaskResult::BAD_HTTP_METHOD,
            ['http' => 297, 'loglevel' => LogLevel::EMERGENCY], // Uses both overrides
        ];
    }

    /**
     * @param array<string, array{http_status?: int, loglevel?: string}> $custom_codes
     * @param array{http: int, loglevel: string} $expect
     */
    #[DataProvider('provider_code_overrides')]
    public function testUsersCanOverrideBehaviourOfCoreCodes(array $custom_codes, string $code, array $expect): void
    {
        $this->custom_codes = $custom_codes;
        $result = new ArbitraryTaskResult($code);
        $subject = $this->newSubject();
        $this->assertSame(
            $expect,
            [
                'http' => $subject->getHttpResponse($result)->getStatusCode(),
                'loglevel' => $subject->getLogLevel($result),
            ]
        );
    }

    protected function newSubject(): TaskResultCodeMapper
    {
        return new TaskResultCodeMapper($this->custom_codes);
    }
}

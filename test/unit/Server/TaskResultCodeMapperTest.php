<?php


namespace test\unit\Ingenerator\CloudTasksWrapper\Server;


use Ingenerator\CloudTasksWrapper\Server\TaskResult\ArbitraryTaskResult;
use Ingenerator\CloudTasksWrapper\Server\TaskResult\CoreTaskResult;
use Ingenerator\CloudTasksWrapper\Server\TaskResultCodeMapper;
use Ingenerator\PHPUtils\Object\ConstantDirectory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

class TaskResultCodeMapperTest extends TestCase
{
    protected $custom_codes = [];

    public function test_it_is_initialisable()
    {
        $this->assertInstanceOf(TaskResultCodeMapper::class, $this->newSubject());
    }

    public function test_it_builds_http_response_from_task_result_based_on_config()
    {
        $this->custom_codes['myCustomStatus'] = ['http_status' => 200];
        $response                             = $this->newSubject()->getHttpResponse(
            new ArbitraryTaskResult('myCustomStatus')
        );
        $this->assertSame(
            [
                'code' => 200,
                'name' => 'MyCustomStatus'
            ],
            [
                'code' => $response->getStatusCode(),
                'name' => $response->getStatusCodeName()
            ]
        );
    }

    public function test_it_provides_log_level_for_task_result_based_on_config()
    {
        $this->custom_codes['myCustomStatus'] = ['loglevel' => LogLevel::INFO];
        $this->assertSame(
            LogLevel::INFO,
            $this->newSubject()->getLogLevel(CoreTaskResult::success('Anything'))
        );
    }

    public function test_it_throws_for_http_response_if_not_mapped_or_invalid()
    {
        $result  = new ArbitraryTaskResult('somethingIsNotDefined');
        $subject = $this->newSubject();
        $this->expectException(\InvalidArgumentException::class);
        $subject->getHttpResponse($result);
    }

    public function test_it_throws_for_log_level_if_not_mapped()
    {
        $result  = new ArbitraryTaskResult('somethingIsNotDefined');
        $subject = $this->newSubject();
        $this->expectException(\InvalidArgumentException::class);
        $subject->getLogLevel($result);
    }

    public function provider_core_task_results(): array
    {
        $consts = ConstantDirectory::forClass(CoreTaskResult::class)->listConstants();

        return array_map(function (string $code) { return [$code]; }, $consts);
    }

    /**
     * @dataProvider provider_core_task_results
     */
    public function test_its_default_mapping_defines_values_for_all_core_task_results($code)
    {
        $result   = new ArbitraryTaskResult($code);
        $subject  = $this->newSubject();
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

    public function provider_code_overrides(): array
    {
        return [
            [
                [CoreTaskResult::AUTH_NOT_PROVIDED => ['http_status' => 281]],
                CoreTaskResult::AUTH_NOT_PROVIDED,
                ['http' => 281, 'loglevel' => LogLevel::WARNING] // Uses default loglevel
            ],
            [
                [\Ingenerator\CloudTasksWrapper\Server\TaskResult\CoreTaskResult::MUTEX_TIMEOUT => ['loglevel' => LogLevel::EMERGENCY]],
                CoreTaskResult::MUTEX_TIMEOUT,
                ['http' => 409, 'loglevel' => LogLevel::EMERGENCY] // Uses default http code
            ],
            [
                [
                    CoreTaskResult::BAD_HTTP_METHOD => [
                        'http_status' => 297,
                        'loglevel'    => LogLevel::EMERGENCY
                    ]
                ],
                CoreTaskResult::BAD_HTTP_METHOD,
                ['http' => 297, 'loglevel' => LogLevel::EMERGENCY] // Uses both overrides
            ],
        ];
    }

    /**
     * @dataProvider provider_code_overrides
     */
    public function test_users_can_override_behaviour_of_core_codes($custom_codes, $code, $expect)
    {
        $this->custom_codes = $custom_codes;
        $result             = new ArbitraryTaskResult($code);
        $subject            = $this->newSubject();
        $this->assertSame(
            $expect,
            [
                'http'     => $subject->getHttpResponse($result)->getStatusCode(),
                'loglevel' => $subject->getLogLevel($result),
            ]
        );
    }

    protected function newSubject(): TaskResultCodeMapper
    {
        return new TaskResultCodeMapper($this->custom_codes);
    }

}

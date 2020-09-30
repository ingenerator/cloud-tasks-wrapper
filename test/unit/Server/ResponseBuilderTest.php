<?php


namespace test\unit\Ingenerator\CloudTasksWrapper\Server;


use Ingenerator\CloudTasksWrapper\Server\ResponseBuilder;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerResponse;
use Ingenerator\PHPUtils\Mutex\MutexTimedOutException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use ReflectionClass;
use ReflectionMethod;
use TypeError;
use function call_user_func_array;

class ResponseBuilderTest extends TestCase
{
    public function provider_build_default()
    {
        $custom_args = [
            'mutexTimeout'      => [new MutexTimedOutException('any', 5, 0)],
            'uncaughtException' => [new TypeError('Ooops')],

        ];
        $cases       = [];
        $class       = new ReflectionClass(ResponseBuilder::class);
        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $cases[] = [
                ResponseBuilder::class.'::'.$method->getName(),
                $custom_args[$method->getName()] ?? ['any old messae'],
            ];
        }

        return $cases;
    }

    /**
     * @dataProvider provider_build_default
     */
    public function test_it_can_build_default_for_each_exposed_method($method, $args)
    {
        $result = call_user_func_array($method, $args);
        $this->assertInstanceOf(TaskHandlerResponse::class, $result);
    }

    /**
     * We don't need to test every single predefined mapper, just a couple to see the build method works
     *
     * @return array[]
     */
    public function provider_expected_responses_sample()
    {
        $e = new InvalidArgumentException('bad stuff');

        return [
            [
                function () { return ResponseBuilder::authExpired('Whatever'); },
                [
                    'code'             => 'authExpired',
                    'msg'              => 'Whatever',
                    'http_status'      => 298,
                    'http_status_name' => 'AuthExpired',
                    'loglevel'         => LogLevel::ERROR,
                    'log_context'      => [],
                ],
            ],
            [
                // Note the standard HTTP 403 status code
                function () { return ResponseBuilder::authNotProvided('where is it?'); },
                [
                    'code'             => 'authNotProvided',
                    'msg'              => 'where is it?',
                    'http_status'      => 403,
                    'http_status_name' => 'AuthNotProvided',
                    'loglevel'         => LogLevel::WARNING,
                    'log_context'      => [],
                ],
            ],
            [
                // Note the standard HTTP 403 status code
                function () use ($e) { return ResponseBuilder::uncaughtException($e); },
                [
                    'code'             => 'uncaughtException',
                    'msg'              => '[InvalidArgumentException] bad stuff',
                    'http_status'      => 500,
                    'http_status_name' => 'UncaughtException',
                    'loglevel'         => LogLevel::EMERGENCY,
                    'log_context'      => ['exception' => $e],
                ],
            ],
        ];
    }

    /**
     * @dataProvider provider_expected_responses_sample
     */
    public function test_it_provides_all_expected_values_for_sample_of_objects($callable, $expect)
    {
        $result = $callable();
        /** @var TaskHandlerResponse $result */
        $this->assertSame($expect, $result->toArray());
    }


}

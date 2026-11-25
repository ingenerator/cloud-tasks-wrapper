<?php

declare(strict_types=1);

namespace test\unit\Ingenerator\CloudTasksWrapper\Server\TaskResult;

use Error;
use Ingenerator\CloudTasksWrapper\Server\CloudTaskCannotBeValidException;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerResult;
use Ingenerator\CloudTasksWrapper\Server\TaskResult\CoreTaskResult;
use Ingenerator\PHPUtils\Mutex\MutexTimedOutException;
use Ingenerator\PHPUtils\Object\ConstantDirectory;
use Ingenerator\PHPUtils\StringEncoding\JSON;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UnderflowException;

use function call_user_func_array;

final class CoreTaskResultTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<mixed>, array{msg: string, log_context: array<string,mixed>}}>
     */
    public static function provider_status_constructors(): iterable
    {
        /**
         * This data provider and test is a sanity test that:
         *  - There is a named constructor for every CoreTaskResult status and the code matches
         *  - The constructors work as expected
         *
         * It will fail if new codes are added without a corresponding method
         */
        $mutex_exception = new MutexTimedOutException('ab', 15, 1);
        $invalid_exception = new CloudTaskCannotBeValidException('It has no info');
        $misc_e = new Error('That was an error');

        foreach (ConstantDirectory::forClass(CoreTaskResult::class)->listConstants() as $code) {
            assert(is_string($code), 'Constant value must be a string');
            yield $code => match ($code) {
                CoreTaskResult::AUTH_INVALID => [
                    $code,
                    ['Invalid!'],
                    ['msg' => 'Invalid!', 'log_context' => []],
                ],
                CoreTaskResult::AUTH_NOT_PROVIDED => [
                    $code,
                    ['No auth!'],
                    ['msg' => 'No auth!', 'log_context' => []],
                ],
                CoreTaskResult::BAD_HTTP_METHOD => [
                    $code,
                    ['PUT'],
                    ['msg' => '`PUT` not accepted', 'log_context' => []],
                ],
                CoreTaskResult::CANNOT_BE_VALID => [
                    $code,
                    [$invalid_exception],
                    [
                        'msg' => 'Cannot be valid: It has no info',
                        'log_context' => ['exception' => $invalid_exception],
                    ],
                ],
                CoreTaskResult::DUPLICATE_DELIVERY => [
                    $code,
                    ['You sent this!'],
                    ['msg' => 'You sent this!', 'log_context' => []],
                ],
                CoreTaskResult::HANDLER_NOT_FOUND => [
                    $code,
                    ['any-task'],
                    ['msg' => 'No task handler for `any-task`', 'log_context' => []],
                ],
                CoreTaskResult::MUTEX_TIMEOUT => [
                    $code,
                    [$mutex_exception],
                    [
                        'msg' => 'Mutex failed: '.$mutex_exception->getMessage(),
                        'log_context' => ['exception' => $mutex_exception],
                    ],
                ],
                CoreTaskResult::SUCCESS => [
                    $code,
                    [],
                    ['msg' => 'ok', 'log_context' => []],
                ],
                CoreTaskResult::UNCAUGHT_EXCEPTION => [
                    $code,
                    [$misc_e],
                    ['msg' => '[Error] That was an error',
                        'log_context' => ['exception' => $misc_e],
                    ],
                ],
                default => throw new UnderflowException('No expectation defined for '.JSON::encode($code)),
            };
        }
    }

    /**
     * @param list<mixed>|null $args
     * @param array{msg: string, log_context: array<string,mixed>} $expect
     */
    #[DataProvider('provider_status_constructors')]
    public function testItHasNamedConstructorForEachStatusAndCanBeCreated(
        string $method,
        ?array $args,
        array $expect,
    ): void {
        if ($args === null) {
            $this->fail('No testcase defined for CoreTaskResult::'.$method);
        }
        /** @phpstan-ignore argument.type (can't dynamically resolve the callable method here, but we want the test to fail on nonexistent anyway) */
        $result = call_user_func_array(CoreTaskResult::class.'::'.$method, $args);
        /** @var TaskHandlerResult $result */
        $this->assertSame(
            array_merge(['code' => $method], $expect),
            $result->toArray()
        );
    }
}

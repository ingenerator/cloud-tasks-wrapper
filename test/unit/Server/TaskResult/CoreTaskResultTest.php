<?php

declare(strict_types=1);

namespace test\unit\Ingenerator\CloudTasksWrapper\Server\TaskResult;

use Error;
use Ingenerator\CloudTasksWrapper\Server\CloudTaskCannotBeValidException;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerResult;
use Ingenerator\CloudTasksWrapper\Server\TaskResult\CoreTaskResult;
use Ingenerator\PHPUtils\Mutex\MutexTimedOutException;
use Ingenerator\PHPUtils\Object\ConstantDirectory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function array_fill_keys;
use function call_user_func_array;

final class CoreTaskResultTest extends TestCase
{
    /**
     * @return mixed[]
     */
    public static function provider_status_constructors(): array
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

        $codes = array_fill_keys(
            ConstantDirectory::forClass(CoreTaskResult::class)->listConstants(),
            []
        );

        $map = array_merge(
            $codes,
            [
                CoreTaskResult::AUTH_INVALID => [
                    ['Invalid!'],
                    ['msg' => 'Invalid!', 'log_context' => []],
                ],
                CoreTaskResult::AUTH_NOT_PROVIDED => [
                    ['No auth!'],
                    ['msg' => 'No auth!', 'log_context' => []],
                ],
                CoreTaskResult::BAD_HTTP_METHOD => [
                    ['PUT'],
                    ['msg' => '`PUT` not accepted', 'log_context' => []],
                ],
                CoreTaskResult::CANNOT_BE_VALID => [
                    [$invalid_exception],
                    [
                        'msg' => 'Cannot be valid: It has no info',
                        'log_context' => ['exception' => $invalid_exception],
                    ],
                ],
                CoreTaskResult::DUPLICATE_DELIVERY => [
                    ['You sent this!'],
                    ['msg' => 'You sent this!', 'log_context' => []],
                ],
                CoreTaskResult::HANDLER_NOT_FOUND => [
                    ['any-task'],
                    ['msg' => 'No task handler for `any-task`', 'log_context' => []],
                ],
                CoreTaskResult::MUTEX_TIMEOUT => [
                    [$mutex_exception],
                    [
                        'msg' => 'Mutex failed: '.$mutex_exception->getMessage(),
                        'log_context' => ['exception' => $mutex_exception],
                    ],
                ],
                CoreTaskResult::SUCCESS => [
                    [],
                    ['msg' => 'ok', 'log_context' => []],
                ],
                CoreTaskResult::UNCAUGHT_EXCEPTION => [
                    [$misc_e],
                    ['msg' => '[Error] That was an error',
                        'log_context' => ['exception' => $misc_e],
                    ],
                ],
            ]
        );

        $cases = [];
        foreach ($map as $code => $vars) {
            $cases[] = [$code, $vars[0] ?? null, $vars[1] ?? null];
        }

        return $cases;
    }

    #[DataProvider('provider_status_constructors')]
    public function testItHasNamedConstructorForEachStatusAndCanBeCreated(
        string $method,
        ?array $args,
        ?array $expect,
    ): void {
        if ($args === null) {
            $this->fail('No testcase defined for CoreTaskResult::'.$method);
        }
        $result = call_user_func_array(CoreTaskResult::class.'::'.$method, $args);
        /** @var TaskHandlerResult $result */
        $this->assertSame(
            array_merge(['code' => $method], $expect),
            $result->toArray()
        );
    }
}

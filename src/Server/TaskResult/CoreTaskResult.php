<?php

declare(strict_types=1);

namespace Ingenerator\CloudTasksWrapper\Server\TaskResult;

use Ingenerator\CloudTasksWrapper\Server\CloudTaskCannotBeValidException;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerResult;
use Ingenerator\PHPUtils\Mutex\MutexTimedOutException;
use Throwable;

final class CoreTaskResult extends TaskHandlerResult
{
    public const string AUTH_INVALID = 'authInvalid';
    public const string AUTH_NOT_PROVIDED = 'authNotProvided';
    public const string BAD_HTTP_METHOD = 'badHttpMethod';
    public const string CANNOT_BE_VALID = 'cannotBeValid';
    public const string HANDLER_NOT_FOUND = 'handlerNotFound';
    public const string DUPLICATE_DELIVERY = 'duplicateDelivery';
    public const string MUTEX_TIMEOUT = 'mutexTimeout';
    public const string SUCCESS = 'success';
    public const string UNCAUGHT_EXCEPTION = 'uncaughtException';

    public static function authInvalid(string $msg, array $log_context = []): CoreTaskResult
    {
        return new self(self::AUTH_INVALID, $msg, $log_context);
    }

    public static function authNotProvided(string $msg = 'No auth provided'): CoreTaskResult
    {
        return new self(self::AUTH_NOT_PROVIDED, $msg);
    }

    public static function badHTTPMethod(string $method): CoreTaskResult
    {
        return new self(self::BAD_HTTP_METHOD, "`$method` not accepted");
    }

    public static function cannotBeValid(CloudTaskCannotBeValidException $e): CoreTaskResult
    {
        return new self(self::CANNOT_BE_VALID, 'Cannot be valid: '.$e->getMessage(), ['exception' => $e]);
    }

    public static function duplicateDelivery(string $msg): CoreTaskResult
    {
        return new self(self::DUPLICATE_DELIVERY, $msg);
    }

    public static function handlerNotFound(string $task_type): CoreTaskResult
    {
        return new self(self::HANDLER_NOT_FOUND, 'No task handler for `'.$task_type.'`');
    }

    public static function mutexTimeout(MutexTimedOutException $e): CoreTaskResult
    {
        return new self(
            self::MUTEX_TIMEOUT,
            'Mutex failed: '.$e->getMessage(),
            ['exception' => $e]
        );
    }

    public static function success(string $msg = 'ok'): CoreTaskResult
    {
        return new self(self::SUCCESS, $msg);
    }

    public static function uncaughtException(Throwable $e): CoreTaskResult
    {
        return new self(
            self::UNCAUGHT_EXCEPTION,
            sprintf('[%s] %s', $e::class, $e->getMessage()),
            ['exception' => $e]
        );
    }
}

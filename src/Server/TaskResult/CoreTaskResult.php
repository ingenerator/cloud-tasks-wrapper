<?php

namespace Ingenerator\CloudTasksWrapper\Server\TaskResult;

use Ingenerator\CloudTasksWrapper\Server\CloudTaskCannotBeValidException;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerResult;
use Ingenerator\PHPUtils\Mutex\MutexTimedOutException;
use Throwable;

class CoreTaskResult extends TaskHandlerResult
{
    public const AUTH_INVALID = 'authInvalid';
    public const AUTH_NOT_PROVIDED = 'authNotProvided';
    public const BAD_HTTP_METHOD = 'badHttpMethod';
    public const CANNOT_BE_VALID = 'cannotBeValid';
    public const HANDLER_NOT_FOUND = 'handlerNotFound';
    public const DUPLICATE_DELIVERY = 'duplicateDelivery';
    public const MUTEX_TIMEOUT = 'mutexTimeout';
    public const SUCCESS = 'success';
    public const UNCAUGHT_EXCEPTION = 'uncaughtException';

    public static function authInvalid(string $msg, array $log_context = []): TaskHandlerResult
    {
        return new static(static::AUTH_INVALID, $msg, $log_context);
    }

    public static function authNotProvided(string $msg = 'No auth provided'): TaskHandlerResult
    {
        return new static(static::AUTH_NOT_PROVIDED, $msg);
    }

    public static function badHTTPMethod(string $method): TaskHandlerResult
    {
        return new static(static::BAD_HTTP_METHOD, "`$method` not accepted");
    }

    public static function cannotBeValid(CloudTaskCannotBeValidException $e): TaskHandlerResult
    {
        return new static(static::CANNOT_BE_VALID, 'Cannot be valid: '.$e->getMessage(), ['exception' => $e]);
    }

    public static function duplicateDelivery(string $msg): TaskHandlerResult
    {
        return new static(static::DUPLICATE_DELIVERY, $msg);
    }

    public static function handlerNotFound(string $task_type): TaskHandlerResult
    {
        return new static(static::HANDLER_NOT_FOUND, 'No task handler for `'.$task_type.'`');
    }

    public static function mutexTimeout(MutexTimedOutException $e): TaskHandlerResult
    {
        return new static(
            static::MUTEX_TIMEOUT,
            'Mutex failed: '.$e->getMessage(),
            ['exception' => $e]
        );
    }

    public static function success(string $msg = 'ok'): TaskHandlerResult
    {
        return new static(static::SUCCESS, $msg);
    }

    public static function uncaughtException(Throwable $e): TaskHandlerResult
    {
        return new static(
            static::UNCAUGHT_EXCEPTION,
            sprintf('[%s] %s', $e::class, $e->getMessage()),
            ['exception' => $e]
        );
    }
}

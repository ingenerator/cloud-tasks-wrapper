<?php


namespace Ingenerator\CloudTasksWrapper\Server\TaskResult;


use Ingenerator\CloudTasksWrapper\Server\CloudTaskCannotBeValidException;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerResult;
use Ingenerator\PHPUtils\Mutex\MutexTimedOutException;

class CoreTaskResult extends TaskHandlerResult
{
    const AUTH_EXPIRED = 'authExpired';
    const AUTH_INVALID = 'authInvalid';
    const AUTH_NOT_PROVIDED = 'authNotProvided';
    const BAD_HTTP_METHOD = 'badHttpMethod';
    const CANNOT_BE_VALID = 'cannotBeValid';
    const DUPLICATE_DELIVERY = 'duplicateDelivery';
    const MUTEX_TIMEOUT = 'mutexTimeout';
    const SUCCESS = 'success';
    const UNCAUGHT_EXCEPTION = 'uncaughtException';

    public static function authExpired(string $msg = 'Auth token has expired'): TaskHandlerResult
    {
        return new static(static::AUTH_EXPIRED, $msg);
    }

    public static function authInvalid(string $msg): TaskHandlerResult
    {
        return new static(static::AUTH_INVALID, $msg);
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
        return new static(static::CANNOT_BE_VALID, $e->getMessage(), ['exception' => $e]);
    }

    public static function duplicateDelivery(string $msg): TaskHandlerResult
    {
        return new static(static::DUPLICATE_DELIVERY, $msg);
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

    public static function uncaughtException(\Throwable $e): TaskHandlerResult
    {
        return new static(
            static::UNCAUGHT_EXCEPTION,
            sprintf('[%s] %s', \get_class($e), $e->getMessage()),
            ['exception' => $e]
        );
    }
}
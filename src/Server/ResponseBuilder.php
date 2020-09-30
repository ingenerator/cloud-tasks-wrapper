<?php


namespace Ingenerator\CloudTasksWrapper\Server;


use Ingenerator\PHPUtils\Mutex\MutexTimedOutException;
use Psr\Log\LogLevel;

class ResponseBuilder
{
    protected static $http_code_map = [
        200 => [
            'success' => LogLevel::INFO,
        ],
        220 => [
            'noLongerRequired' => LogLevel::NOTICE,
        ],
        293 => [
            // This probably just means they signed up late
            'eventTooSoon' => LogLevel::NOTICE,
        ],
        294 => [
            // This is more likely a code problem / very delayed delivery
            'eventInPast' => LogLevel::WARNING,
        ],
        295 => [
            'eventCancelled' => LogLevel::WARNING,
        ],
        296 => [
            'duplicateDelivery' => LogLevel::WARNING,
        ],
        297 => [
            'authInvalid' => LogLevel::ERROR,
        ],
        298 => [
            'authExpired' => LogLevel::ERROR,
        ],
        299 => [
            'cannotBeValid' => LogLevel::CRITICAL,
        ],
        400 => [
            'badHTTPMethod' => LogLevel::WARNING,
        ],
        403 => [
            'authNotProvided' => LogLevel::WARNING,
        ],
        409 => [
            'mutexTimeout' => LogLevel::WARNING,
        ],
        500 => [
            'uncaughtException' => LogLevel::EMERGENCY,
        ],
    ];

    public static function authExpired(string $msg): TaskHandlerResponse
    {
        return static::buildDefault(__FUNCTION__, $msg);
    }

    protected static function buildDefault(
        string $code,
        string $msg,
        array $log_context = []
    ): TaskHandlerResponse {
        $config = static::findCodeConfig($code);

        return new TaskHandlerResponse(
            array_merge(
                [
                    'code'        => $code,
                    'msg'         => $msg,
                    'log_context' => $log_context,
                ],
                $config
            )
        );
    }

    protected static function findCodeConfig(string $code)
    {
        foreach (static::$http_code_map as $http_status => $code_loglevel_map) {
            if (isset($code_loglevel_map[$code])) {
                return [
                    'http_status'      => $http_status,
                    'http_status_name' => \ucfirst($code),
                    'loglevel'         => $code_loglevel_map[$code],
                ];
            }
        }

        throw new \InvalidArgumentException(
            'No response mapping defined for task response code '.$code
        );
    }

    public static function authInvalid(string $msg): TaskHandlerResponse
    {
        return static::buildDefault(__FUNCTION__, $msg);
    }

    public static function authNotProvided(string $msg = 'No auth provided'): TaskHandlerResponse
    {
        return static::buildDefault(__FUNCTION__, $msg);
    }

    public static function badHTTPMethod(string $method): TaskHandlerResponse
    {
        return static::buildDefault(__FUNCTION__, "`$method` not accepted");
    }

    public static function cannotBeValid(string $msg): TaskHandlerResponse
    {
        return static::buildDefault(__FUNCTION__, $msg);
    }

    public static function duplicateDelivery(string $msg): TaskHandlerResponse
    {
        return static::buildDefault(__FUNCTION__, $msg);
    }

    public static function eventCancelled(string $msg): TaskHandlerResponse
    {
        return static::buildDefault(__FUNCTION__, $msg);
    }

    public static function eventInPast(string $msg): TaskHandlerResponse
    {
        return static::buildDefault(__FUNCTION__, $msg);
    }

    public static function eventTooSoon(string $msg): TaskHandlerResponse
    {
        return static::buildDefault(__FUNCTION__, $msg);
    }

    public static function mutexTimeout(MutexTimedOutException $e): TaskHandlerResponse
    {
        return static::buildDefault(
            __FUNCTION__,
            'Mutex failed: '.$e->getMessage(),
            ['exception' => $e]
        );
    }

    public static function noLongerRequired(string $msg): TaskHandlerResponse
    {
        return static::buildDefault(__FUNCTION__, $msg);
    }

    public static function success(string $msg = 'ok'): TaskHandlerResponse
    {
        return static::buildDefault(__FUNCTION__, $msg);
    }

    public static function uncaughtException(\Throwable $e): TaskHandlerResponse
    {
        return static::buildDefault(
            __FUNCTION__,
            sprintf('[%s] %s', \get_class($e), $e->getMessage()),
            ['exception' => $e]
        );
    }

}

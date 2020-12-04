<?php


namespace Ingenerator\CloudTasksWrapper\Server;


use Ingenerator\CloudTasksWrapper\Server\TaskResult\CoreTaskResult;
use Ingenerator\PHPUtils\ArrayHelpers\AssociativeArrayUtils;
use Psr\Log\LogLevel;

class TaskResultCodeMapper
{
    protected array $code_map = [
        CoreTaskResult::SUCCESS            => [
            'http_status' => 200,
            'loglevel'    => LogLevel::INFO,
        ],
        CoreTaskResult::DUPLICATE_DELIVERY => [
            'http_status' => 296,
            'loglevel'    => LogLevel::WARNING,
        ],
        CoreTaskResult::CANNOT_BE_VALID    => [
            'http_status' => 299,
            'loglevel'    => LogLevel::CRITICAL,
        ],
        CoreTaskResult::BAD_HTTP_METHOD    => [
            'http_status' => 400,
            'loglevel'    => LogLevel::WARNING,
        ],
        CoreTaskResult::AUTH_NOT_PROVIDED  => [
            'http_status' => 401,
            'loglevel'    => LogLevel::WARNING,
        ],
        CoreTaskResult::AUTH_INVALID       => [
            'http_status' => 403,
            'loglevel'    => LogLevel::ERROR,
        ],
        CoreTaskResult::HANDLER_NOT_FOUND => [
            'http_status' => 404,
            // Not actually logged normally, these are fired before the middleware
            'loglevel' => LogLevel::WARNING,
        ],
        CoreTaskResult::MUTEX_TIMEOUT      => [
            'http_status' => 409,
            'loglevel'    => LogLevel::WARNING,
        ],
        CoreTaskResult::UNCAUGHT_EXCEPTION => [
            'http_status' => 500,
            'loglevel'    => LogLevel::EMERGENCY,
        ],
    ];

    public function __construct(array $custom_map = [])
    {
        $this->code_map = AssociativeArrayUtils::deepMerge($this->code_map, $custom_map);
    }

    protected function findCodeConfig(TaskHandlerResult $result)
    {
        $result_code = $result->getCode();
        if (isset($this->code_map[$result_code])) {
            return $this->code_map[$result_code];
        }

        throw new \InvalidArgumentException(
            'No response mapping defined for task response code '.$result_code
        );
    }

    public function getHttpResponse(TaskHandlerResult $result): TaskResponse
    {
        $config = $this->findCodeConfig($result);

        return new TaskResponse(
            $config['http_status'],
            \ucfirst($result->getCode()),
            $result
        );
    }

    public function getLogLevel(TaskHandlerResult $result): string
    {
        return $this->findCodeConfig($result)['loglevel'];
    }
}

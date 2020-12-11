<?php

namespace Ingenerator\CloudTasksWrapper\Server\Middleware;

use Ingenerator\CloudTasksWrapper\Server\TaskHandlerChain;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerMiddleware;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerResult;
use Ingenerator\CloudTasksWrapper\Server\TaskRequest;
use Ingenerator\CloudTasksWrapper\Server\TaskResultCodeMapper;
use Ingenerator\PHPUtils\DateTime\Clock\RealtimeClock;
use Psr\Log\LoggerInterface;

/**
 * Logs the result of each task handling operation
 *
 * This is provided as a dependency to allow for custom logging strategies (e.g. only logging
 * certain types of results, logging stats as well as metrics, etc) in applications.
 */
class TaskLoggingMiddleware implements TaskHandlerMiddleware
{

    protected array $default_context;

    /**
     * @var \Ingenerator\PHPUtils\DateTime\Clock\RealtimeClock
     */
    protected RealtimeClock $clock;

    protected LoggerInterface $logger;

    protected TaskResultCodeMapper $code_mapper;

    public function __construct(
        RealtimeClock $clock,
        LoggerInterface $logger,
        TaskResultCodeMapper $code_mapper,
        array $default_log_context = []
    ) {
        $this->logger          = $logger;
        $this->code_mapper     = $code_mapper;
        $this->clock           = $clock;
        $this->default_context = $default_log_context;
    }

    public function process(TaskRequest $request, TaskHandlerChain $chain): TaskHandlerResult
    {
        $start  = $this->clock->getMicrotime();
        $result = $chain->nextHandler($request);
        $end    = $this->clock->getMicrotime();
        $this->logResult($request, $result, 1000 * ($end - $start));

        return $result;
    }


    protected function logResult(TaskRequest $request, TaskHandlerResult $result, int $time_ms)
    {
        // By default, request is not logged on the assumption your logger already includes info on
        // the request URL etc within log metadata
        $this->logger->log(
            $this->code_mapper->getLogLevel($result),
            \sprintf('Task: [%s] %s', $result->getCode(), $result->getMsg()),
            array_merge(
                $this->default_context,
                [
                    'time_ms' => $time_ms,
                    'task'    => [
                        'type'    => $request->getTaskType(),
                        'id'      => $request->getTaskName(),
                        'retries' => (string) $request->getRetryCount(),
                        'caller'  => $request->getCallerEmail(),
                    ],
                ],
                $result->getLogContext()
            )
        );
    }

}

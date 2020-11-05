<?php

namespace Ingenerator\CloudTasksWrapper\Server;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

/**
 * Logs the result of each task handling operation
 *
 * This is provided as a dependency to allow for custom logging strategies (e.g. only logging
 * certain types of results, logging stats as well as metrics, etc) in applications.
 */
class TaskHandlingLogger
{
    protected LoggerInterface $logger;

    protected TaskResultCodeMapper $code_mapper;

    public function __construct(LoggerInterface $logger, TaskResultCodeMapper $code_mapper)
    {
        $this->logger      = $logger;
        $this->code_mapper = $code_mapper;
    }

    public function logResult(
        ServerRequestInterface $request,
        TaskHandlerResult $result,
        float $time_ms
    ) {
        // By default, request is not logged on the assumption your logger already includes info on
        // the request URL etc within log metadata
        $this->logger->log(
            $this->code_mapper->getLogLevel($result),
            \sprintf('Task: [%s] %s', $result->getCode(), $result->getMsg()),
            array_merge(['time_ms' => $time_ms], $result->getLogContext())
        );
    }

}

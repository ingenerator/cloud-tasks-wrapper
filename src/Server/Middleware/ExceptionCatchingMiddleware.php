<?php


namespace Ingenerator\CloudTasksWrapper\Server\Middleware;


use Ingenerator\CloudTasksWrapper\Server\CloudTaskCannotBeValidException;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerChain;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerMiddleware;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerResult;
use Ingenerator\CloudTasksWrapper\Server\TaskRequest;
use Ingenerator\CloudTasksWrapper\Server\TaskResult\CoreTaskResult;

/**
 * Catches exceptions during task handlers and returns suitable codes
 *
 * Only catches exceptions below this level of the middleware chain, not from middlewares that come first (e.g. the
 * logger).
 */
class ExceptionCatchingMiddleware implements TaskHandlerMiddleware
{
    public function process(TaskRequest $request, TaskHandlerChain $chain): TaskHandlerResult
    {
        try {
            return $chain->nextHandler($request);
        } catch (CloudTaskCannotBeValidException $e) {
            return CoreTaskResult::cannotBeValid($e);
        } catch (\Throwable $e) {
            return CoreTaskResult::uncaughtException($e);
        }
    }

}

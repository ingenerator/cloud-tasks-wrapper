<?php


namespace Ingenerator\CloudTasksWrapper\Server;


use Ingenerator\CloudTasksWrapper\Server\Middleware\ExceptionCatchingMiddleware;
use Ingenerator\CloudTasksWrapper\Server\Middleware\TaskLoggingMiddleware;
use Ingenerator\CloudTasksWrapper\Server\Middleware\TaskMutexLockingMiddleware;
use Ingenerator\CloudTasksWrapper\Server\Middleware\TaskRequestAuthenticatingMiddleware;

class TaskHandlerChain
{
    protected ?TaskHandler $handler = NULL;

    /**
     * @var \Ingenerator\CloudTasksWrapper\Server\TaskHandlerMiddleware[]
     */
    protected $middlewares = [];

    /**
     * Creates a middleware stack with the default middlewares
     *
     * Syntax sugar for dependency construction to make it obvious how we recommend wiring up
     * middlewares.
     *
     * The sequence is:
     *
     *  - Logging first so that everything bar dependency issues gets logged
     *  - Exception catching next to get as many exceptions as possible
     *  - Auth next so that spam calls can't DDOS through the mutex implementation
     *  - Then mutex so that a given task is only processed once at a time
     *
     * @param \Ingenerator\CloudTasksWrapper\Server\Middleware\TaskLoggingMiddleware               $logging
     * @param \Ingenerator\CloudTasksWrapper\Server\Middleware\ExceptionCatchingMiddleware         $catcher
     * @param \Ingenerator\CloudTasksWrapper\Server\Middleware\TaskRequestAuthenticatingMiddleware $auth
     * @param \Ingenerator\CloudTasksWrapper\Server\Middleware\TaskMutexLockingMiddleware          $mutex
     *
     * @return \Ingenerator\CloudTasksWrapper\Server\TaskHandlerChain
     */
    public static function makeDefault(
        TaskLoggingMiddleware $logging,
        ExceptionCatchingMiddleware $catcher,
        TaskRequestAuthenticatingMiddleware $auth,
        TaskMutexLockingMiddleware $mutex
    ): TaskHandlerChain {
        return new static($logging, $catcher, $auth, $mutex);
    }

    public function __construct(TaskHandlerMiddleware...$middlewares)
    {
        $this->middlewares = $middlewares;
    }

    public function process(TaskRequest $request, TaskHandler $handler): TaskHandlerResult
    {
        $chain          = clone $this;
        $chain->handler = $handler;

        return $chain->nextHandler($request);
    }

    public function nextHandler(TaskRequest $request): TaskHandlerResult
    {
        if ( ! $this->handler) {
            throw new \UnderflowException('No TaskHandler was provided to '.__CLASS__);
        }
        if ($middleware = \array_shift($this->middlewares)) {
            return $middleware->process($request, $this);
        }

        return $this->handler->handle($request);
    }

}

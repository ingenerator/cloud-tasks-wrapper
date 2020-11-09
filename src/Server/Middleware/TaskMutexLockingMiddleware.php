<?php


namespace Ingenerator\CloudTasksWrapper\Server\Middleware;


use Ingenerator\CloudTasksWrapper\Server\TaskHandlerChain;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerMiddleware;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerResult;
use Ingenerator\CloudTasksWrapper\Server\TaskResult\CoreTaskResult;
use Ingenerator\PHPUtils\Mutex\MutexTimedOutException;
use Ingenerator\PHPUtils\Mutex\MutexWrapper;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Uses a mutex (usually database-backed) to prevent concurrent duplicate task processing
 *
 * As tasks may be delivered more than once, (and in some cases apps may also trigger duplicates),
 * the processing is enclosed in a mutex. This prevents race conditions from concurrent execution of
 * the same task. Assuming task handlers are idempotent (or stateful e.g. they become no-op if
 * already run) this ensures that it's safe to use at-least-once dispatch and delivery.
 */
class TaskMutexLockingMiddleware implements TaskHandlerMiddleware
{
    /**
     * @var \Ingenerator\PHPUtils\Mutex\MutexWrapper
     */
    protected MutexWrapper $mutex;

    public function __construct(MutexWrapper $mutex)
    {
        $this->mutex = $mutex;
    }

    public function process(
        ServerRequestInterface $request,
        TaskHandlerChain $chain
    ): TaskHandlerResult {
        try {
            // Use a mutex to protect against concurrent deliveries of the same task
            return $this->mutex->withLock(
                $this->getMutexName($request),
                // The timeout should be short, it's better that we return fast and let cloud tasks reschedule
                // than that we keep lots of blocking locks on our own mysql - NB this entirely independent of
                // the expected execution time of any particular handler
                1,
                function () use ($request, $chain) {
                    return $chain->nextHandler($request);
                }
            );
        } catch (MutexTimedOutException $e) {
            // No problem, we'll let cloud tasks try again shortly
            return CoreTaskResult::mutexTimeout($e);
        }
    }

    protected function getMutexName(ServerRequestInterface $request): string
    {
        // MD5 is fine for the lock name here, collisions don't matter (they just mean very rarely two different tasks
        // could get queued one behind the other).
        //
        // NB that this assumes that everything required to uniquely ID a given task execution is in the GET
        return 'task-'.\hash('md5', (string) $request->getUri());
    }


}

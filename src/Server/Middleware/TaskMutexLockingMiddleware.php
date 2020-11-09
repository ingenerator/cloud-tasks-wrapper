<?php


namespace Ingenerator\CloudTasksWrapper\Server\Middleware;


use Ingenerator\CloudTasksWrapper\Server\TaskHandlerChain;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerMiddleware;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerResult;
use Ingenerator\CloudTasksWrapper\Server\TaskRequest;
use Ingenerator\CloudTasksWrapper\Server\TaskResult\CoreTaskResult;
use Ingenerator\PHPUtils\Mutex\MutexTimedOutException;
use Ingenerator\PHPUtils\Mutex\MutexWrapper;

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

    public function process(TaskRequest $request, TaskHandlerChain $chain): TaskHandlerResult
    {
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

    protected function getMutexName(TaskRequest $request): string
    {
        // MD5 is fine for the lock name here, collisions don't matter (they just mean very rarely
        // two different tasks could get queued one behind the other).
        //
        // We intentionally assume that everything required to uniquely ID a given task execution is
        // in the GET string *NOT* the task name property from Cloud Tasks.
        //
        // This is because task names are only unique if you assign them when creating the task, but
        // this reduces throughput. For better throughput, Cloud Tasks recommend using their
        // server-side generated names / IDs. However then you may have multiple submissions for the
        // same target payload (e.g. due to user retry / concurrent operations) with different
        // unique names.
        //
        // Therefore by insisting that the target URL is unique (e.g. it contains the ID of the
        // record to operate on / some other identity in the GET params) this mutex will ensure that
        // even concurrently queuing operations on the same entity will still mean they are
        // processed in sequence while also protecting against platform-level more-than-once
        // delivery.
        return 'task-'.\hash('md5', (string) $request->getFullUrl());
    }

}

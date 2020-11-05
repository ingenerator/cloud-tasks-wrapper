<?php


namespace Ingenerator\CloudTasksWrapper\Server\TaskResult;


use Ingenerator\CloudTasksWrapper\Server\TaskHandlerResult;

/**
 * Represents a task result with any arbitrary status - generally for use in dev
 *
 * Generally speaking, applications should use either the CoreTaskResult or define a(n)
 * application-specific result class that defines any custom result codes. This class is primarily
 * for cases where you need to rapidly add a single code, or more usually to allow stubbing and
 * mocking the core (or other) results for unit testing etc.
 */
class ArbitraryTaskResult extends TaskHandlerResult
{
    public function __construct(
        string $code,
        ?string $msg = NULL,
        array $log_context = []
    ) {
        parent::__construct($code, $msg ?? $code, $log_context);
    }

}

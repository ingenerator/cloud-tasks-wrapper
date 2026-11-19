<?php

declare(strict_types=1);

namespace Ingenerator\CloudTasksWrapper\Server;

interface TaskHandlerFactory
{
    /**
     * Implement this method to provide a handler for each given task type.
     *
     * Usually this would be a binding to your dependency container or similar.
     *
     * @throws UnknownTaskTypeException if there is no handler for this task type
     */
    public function getHandler(string $task_type): TaskHandler;
}

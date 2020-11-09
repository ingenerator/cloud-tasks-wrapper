<?php


namespace Ingenerator\CloudTasksWrapper\Server;

interface TaskHandlerFactory
{

    /**
     * Implement this method to provide a handler for each given task type
     *
     * Usually this would be a binding to your dependency container or similar.
     *
     * @param string $task_type
     *
     * @return \Ingenerator\CloudTasksWrapper\Server\TaskHandler
     */
    public function getHandler(string $task_type): TaskHandler;

}

<?php


namespace Ingenerator\CloudTasksWrapper\Server;


interface TaskHandler
{
    public function handle(TaskRequest $request): TaskHandlerResult;
}

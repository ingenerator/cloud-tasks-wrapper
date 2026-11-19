<?php

declare(strict_types=1);

namespace Ingenerator\CloudTasksWrapper\Server;

interface TaskHandlerMiddleware
{
    public function process(TaskRequest $request, TaskHandlerChain $chain): TaskHandlerResult;
}

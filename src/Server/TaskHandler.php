<?php

declare(strict_types=1);

namespace Ingenerator\CloudTasksWrapper\Server;

interface TaskHandler
{
    public function handle(TaskRequest $request): TaskHandlerResult;
}

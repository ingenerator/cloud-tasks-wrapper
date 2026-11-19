<?php

declare(strict_types=1);

namespace Ingenerator\CloudTasksWrapper\Client;

interface TaskCreator
{
    public function create(string $task_type, ?CreateTaskOptions $options = null): string;
}

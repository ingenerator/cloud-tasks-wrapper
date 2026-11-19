<?php

declare(strict_types=1);

namespace Ingenerator\CloudTasksWrapper\Client;

use Google\Cloud\Tasks\V2\Task;

/**
 * @internal
 */
interface CloudTasksClientInterface
{
    public function createTask(string $parent, Task $task, array $call_options = []): Task;
}

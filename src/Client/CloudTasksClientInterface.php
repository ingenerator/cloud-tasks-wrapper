<?php

declare(strict_types=1);

namespace Ingenerator\CloudTasksWrapper\Client;

use Google\Cloud\Tasks\V2\Task;
use Ingenerator\CloudTasksWrapper\TaskTypeConfig;

/**
 * @internal
 *
 * @phpstan-import-type TRetrySettingsArray from TaskTypeConfig
 *
 * @phpstan-type TCallOptionsArray array{
 *     retrySettings?: TRetrySettingsArray
 * }
 */
interface CloudTasksClientInterface
{
    /**
     * @param TCallOptionsArray $call_options
     */
    public function createTask(string $parent, Task $task, array $call_options = []): Task;
}

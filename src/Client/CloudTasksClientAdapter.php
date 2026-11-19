<?php


namespace Ingenerator\CloudTasksWrapper\Client;


use Google\Cloud\Tasks\V2\Client\CloudTasksClient;
use Google\Cloud\Tasks\V2\CreateTaskRequest;
use Google\Cloud\Tasks\V2\Task;

/**
 * @internal
 */
final class CloudTasksClientAdapter implements CloudTasksClientInterface
{

    public function __construct(
        private readonly CloudTasksClient $client
    )
    {
    }

    public function createTask(string $parent, Task $task, array $call_options = []): Task
    {
        $request = (new CreateTaskRequest())
            ->setParent($parent)
            ->setTask($task);

        return $this->client->createTask($request, $call_options);
    }
}

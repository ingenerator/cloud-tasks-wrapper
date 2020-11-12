<?php


namespace Ingenerator\CloudTasksWrapper;


use Google\Cloud\Tasks\V2\CloudTasksClient;
use Ingenerator\PHPUtils\ArrayHelpers\AssociativeArrayUtils;

class TaskTypeConfigProvider
{

    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function getConfig(string $task_type): array
    {
        if ($task_type === '_default') {
            throw new \InvalidArgumentException('Cannot directly access _default task config');
        }

        if ( ! isset($this->config[$task_type])) {
            throw new \UnderflowException('No task type '.$task_type.' is defined');
        }

        $cfg = AssociativeArrayUtils::deepMerge(
            $this->config['_default'] ?? [],
            $this->config[$task_type]
        );

        $cfg['queue-path'] = CloudTasksClient::queueName(
            $cfg['queue']['project'],
            $cfg['queue']['location'],
            $cfg['queue']['name'],
        );

        $cfg['handler_url'] = \str_replace('{TASK_TYPE}', $task_type, $cfg['handler_url']);

        return $cfg;
    }
}

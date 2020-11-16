<?php


namespace Ingenerator\CloudTasksWrapper;


use Google\ApiCore\ApiStatus;
use Google\Cloud\Tasks\V2\CloudTasksClient;
use Ingenerator\PHPUtils\ArrayHelpers\AssociativeArrayUtils;

class TaskTypeConfigProvider
{

    /**
     * @var array default options for retrying creation of cloud tasks
     */
    protected static $defaultCreateRetry = [
        'initialRetryDelayMillis' => 100,
        'retryDelayMultiplier'    => 1.3,
        'maxRetryDelayMillis'     => 10000,
        // NB that retryableCodes is not customisable due to the internals of array merging
        'retryableCodes'          => [ApiStatus::DEADLINE_EXCEEDED, ApiStatus::UNAVAILABLE],
        'retriesEnabled'          => TRUE,
    ];

    protected array $config;

    public function __construct(array $config)
    {
        $config['_default']['create_retry_settings'] = \array_merge(
            static::$defaultCreateRetry,
            $config['_default']['create_retry_settings'] ?? []
        );
        $this->config                                = $config;
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

        if ($cfg['create_retry_settings']['retryableCodes'] !== static::$defaultCreateRetry['retryableCodes']) {
            // This is because the ::deepMerge will always combine rather than replace an indexed array of codes
            // And it doesn't seem likely enough that we would ever need to customise it at a per-application level
            // to justify the complexity of merging that value differently.
            throw new \InvalidArgumentException('Cannot customise `retryableCodes` for creating cloud tasks');
        }

        $cfg['queue-path'] = CloudTasksClient::queueName(
            $cfg['queue']['project'],
            $cfg['queue']['location'],
            $cfg['queue']['name'],
        );

        $cfg['handler_url'] = \str_replace('{TASK_TYPE}', $task_type, $cfg['handler_url']);

        return $cfg;
    }
}

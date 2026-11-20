<?php

namespace Ingenerator\CloudTasksWrapper;

use Google\ApiCore\ApiStatus;
use Ingenerator\PHPUtils\ArrayHelpers\AssociativeArrayUtils;
use InvalidArgumentException;
use UnderflowException;

use function array_merge;

/**
 * @phpstan-import-type TRetrySettingsArray from TaskTypeConfig
 * @phpstan-import-type TPartialTaskTypeConfigArray from TaskTypeConfig
 */
class TaskTypeConfigProvider
{
    /**
     * @var TRetrySettingsArray default options for retrying creation of cloud tasks
     */
    protected static array $defaultCreateRetry = [
        'initialRetryDelayMillis' => 100,
        'retryDelayMultiplier' => 1.3,
        'maxRetryDelayMillis' => 10000,
        // NB that retryableCodes is not customisable per task due to the internals of array merging
        'retriesEnabled' => true,
    ];

    /**
     * @var list<string>
     */
    protected static array $retryableCodes = [
        ApiStatus::DEADLINE_EXCEEDED,
        ApiStatus::UNAVAILABLE,
    ];

    /**
     * @var array<string, TPartialTaskTypeConfigArray>
     */
    protected array $config;

    /**
     * @param array<string, TPartialTaskTypeConfigArray> $config
     */
    public function __construct(array $config)
    {
        $config['_default']['create_retry_settings'] = array_merge(
            static::$defaultCreateRetry,
            $config['_default']['create_retry_settings'] ?? []
        );
        $this->config = $config;
    }

    public function getConfig(string $task_type): TaskTypeConfig
    {
        if ($task_type === '_default') {
            throw new InvalidArgumentException('Cannot directly access _default task config');
        }

        if (!isset($this->config[$task_type])) {
            throw new UnderflowException('No task type '.$task_type.' is defined');
        }

        $cfg = AssociativeArrayUtils::deepMerge(
            $this->config['_default'] ?? [],
            $this->config[$task_type]
        );

        if (isset($cfg['create_retry_settings']['retryableCodes'])) {
            // This is because the ::deepMerge will always combine rather than replace an indexed array of codes
            // And it doesn't seem likely enough that we would ever need to customise it at a per-task-type level
            // to justify the complexity of merging that value differently.
            throw new InvalidArgumentException('Cannot customise `retryableCodes` for creating cloud tasks');
        }

        $cfg['create_retry_settings']['retryableCodes'] = static::$retryableCodes;

        return new TaskTypeConfig($task_type, $cfg);
    }
}

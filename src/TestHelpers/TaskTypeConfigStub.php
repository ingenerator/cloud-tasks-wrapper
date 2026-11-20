<?php

declare(strict_types=1);

namespace Ingenerator\CloudTasksWrapper\TestHelpers;

use Ingenerator\CloudTasksWrapper\TaskTypeConfig;
use Ingenerator\CloudTasksWrapper\TaskTypeConfigProvider;
use Ingenerator\PHPUtils\ArrayHelpers\AssociativeArrayUtils;

/**
 * @phpstan-import-type TPartialTaskTypeConfigArray from TaskTypeConfig
 */
final class TaskTypeConfigStub extends TaskTypeConfigProvider
{
    protected bool $allow_any_task_type = false;

    public static function withAnyTaskType(): TaskTypeConfigStub
    {
        $i = new self([]);
        $i->allow_any_task_type = true;

        return $i;
    }

    /**
     * @param TPartialTaskTypeConfigArray $opts
     */
    public static function withTaskType(string $type, array $opts = []): TaskTypeConfigStub
    {
        return new self([$type => $opts]);
    }

    public function __construct(array $config)
    {
        parent::__construct(
            AssociativeArrayUtils::deepMerge(
                [
                    '_default' => [
                        'queue' => [
                            'project' => 'dev',
                            'location' => 'europe',
                            'name' => 'my-queue',
                        ],
                        'signer_email' => 'tasks-signer@serviceaccount.test',
                        'handler_url' => 'http://app-http/_do_task/{TASK_TYPE}',
                    ],
                ],
                $config
            )
        );
    }

    public function getConfig(string $task_type): TaskTypeConfig
    {
        if ($this->allow_any_task_type && !isset($this->config[$task_type])) {
            $this->config[$task_type] = [];
        }

        return parent::getConfig($task_type);
    }
}

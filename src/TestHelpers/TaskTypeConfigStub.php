<?php


namespace Ingenerator\CloudTasksWrapper\TestHelpers;


use Ingenerator\CloudTasksWrapper\TaskTypeConfig;
use Ingenerator\CloudTasksWrapper\TaskTypeConfigProvider;
use Ingenerator\PHPUtils\ArrayHelpers\AssociativeArrayUtils;

class TaskTypeConfigStub extends TaskTypeConfigProvider
{
    protected bool $allow_any_task_type = FALSE;

    public static function withAnyTaskType(): TaskTypeConfigProvider
    {
        $i                      = new static([]);
        $i->allow_any_task_type = TRUE;

        return $i;
    }

    public static function withTaskType(string $type, array $opts = []): TaskTypeConfigProvider
    {
        return new static([$type => $opts]);
    }

    public function __construct(array $config)
    {
        parent::__construct(
            AssociativeArrayUtils::deepMerge(
                [
                    '_default' => [
                        'queue'        => [
                            'project'  => 'dev',
                            'location' => 'europe',
                            'name'     => 'my-queue',
                        ],
                        'signer_email' => 'tasks-signer@serviceaccount.test',
                        'handler_url'  => 'http://app-http/_do_task/{TASK_TYPE}',
                    ],
                ],
                $config
            )
        );
    }

    public function getConfig(string $task_type): TaskTypeConfig
    {
        if ($this->allow_any_task_type and ! isset($this->config[$task_type])) {
            $this->config[$task_type] = [];
        }

        return parent::getConfig($task_type);
    }

}

<?php


namespace Ingenerator\CloudTasksWrapper;


use Google\ApiCore\ValidationException;
use PHPUnit\Framework\TestCase;

class TaskTypeConfigTest extends TestCase
{
    protected array $vars = [];
    protected string $type = 'foo-task';

    public function test_it_is_initialisable()
    {
        $this->assertInstanceOf(TaskTypeConfig::class, $this->newSubject());
    }

    public function test_it_throws_with_invalid_queue_path_components()
    {
        $this->vars['queue'] = [
            'project'  => NULL,
            'location' => 'outer-space',
            'name'     => 'slow-things',
        ];
        $subject             = $this->newSubject();
        $this->expectException(ValidationException::class);
        $subject->getQueuePath();
    }

    public function test_it_provides_queue_path_from_config_array()
    {
        $this->vars['queue'] = ['project' => 'big-work', 'location' => 'outer-space', 'name' => 'slow-things'];
        $this->assertSame(
            'projects/big-work/locations/outer-space/queues/slow-things',
            $this->newSubject()->getQueuePath()
        );
    }

    public function test_it_replaces_task_type_parameter_in_handler_url()
    {
        $this->vars['handler_url'] = 'https://some.handler/_do_task/{TASK_TYPE}';
        $this->type                = 'slow-job';

        $subject = $this->newSubject();
        $this->assertSame(
            'https://some.handler/_do_task/slow-job',
            $subject->getHandlerUrl()
        );
    }

    protected function newSubject(): TaskTypeConfig
    {
        return new TaskTypeConfig($this->type, $this->vars);
    }
}

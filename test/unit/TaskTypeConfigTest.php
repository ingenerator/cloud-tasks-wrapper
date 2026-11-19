<?php

declare(strict_types=1);

namespace Ingenerator\CloudTasksWrapper;

use Google\ApiCore\ValidationException;
use PHPUnit\Framework\TestCase;

class TaskTypeConfigTest extends TestCase
{
    protected array $vars = [];
    protected string $type = 'foo-task';

    public function testItIsInitialisable(): void
    {
        $this->assertInstanceOf(TaskTypeConfig::class, $this->newSubject());
    }

    public function testItThrowsWithInvalidQueuePathComponents(): void
    {
        $this->vars['queue'] = [
            'project' => './foo',
            'location' => 'outer-space',
            'name' => 'slow-things',
        ];
        $subject = $this->newSubject();
        $this->expectException(ValidationException::class);
        $subject->getQueuePath();
    }

    public function testItProvidesQueuePathFromConfigArray(): void
    {
        $this->vars['queue'] = ['project' => 'big-work', 'location' => 'outer-space', 'name' => 'slow-things'];
        $this->assertSame(
            'projects/big-work/locations/outer-space/queues/slow-things',
            $this->newSubject()->getQueuePath()
        );
    }

    public function testItReplacesTaskTypeParameterInHandlerUrl(): void
    {
        $this->vars['handler_url'] = 'https://some.handler/_do_task/{TASK_TYPE}';
        $this->type = 'slow-job';

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

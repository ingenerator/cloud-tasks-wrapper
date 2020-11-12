<?php


namespace Ingenerator\CloudTasksWrapper\Client\TestHelpers;


use Ingenerator\CloudTasksWrapper\Client\TaskCreationFailedException;
use Ingenerator\CloudTasksWrapper\Client\TaskCreator;
use PHPUnit\Framework\Assert;

class MockCloudTaskCreator implements TaskCreator
{
    protected array $calls = [];

    protected ?\Throwable $will_throw = NULL;

    public static function willThrow(?\Throwable $e = NULL)
    {
        $i             = new static;
        $i->will_throw = $e ?? new TaskCreationFailedException('Failed to create task');

        return $i;
    }

    public function create(string $task_type, array $options = []): string
    {
        $this->calls[] = [
            'task_type' => $task_type,
            'options'   => $options,
        ];

        if ($this->will_throw) {
            throw $this->will_throw;
        }

        return \uniqid();
    }

    public function assertNoTasksQueued(): void
    {
        Assert::assertSame([], $this->calls);
    }

    public function assertQueuedExactly(array $expect): void
    {
        Assert::assertSame($expect, $this->calls);
    }

}

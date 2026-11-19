<?php

namespace Ingenerator\CloudTasksWrapper\TestHelpers\Client;

use Ingenerator\CloudTasksWrapper\Client\CreateTaskOptions;
use Ingenerator\CloudTasksWrapper\Client\TaskCreationFailedException;
use Ingenerator\CloudTasksWrapper\Client\TaskCreator;
use Ingenerator\PHPUtils\DateTime\DateString;
use PHPUnit\Framework\Assert;
use Throwable;

use function json_encode;
use function uniqid;

final class MockCloudTaskCreator implements TaskCreator
{
    /**
     * @var array<string, mixed[]|string>[]
     */
    private array $calls = [];

    private ?Throwable $will_throw = null;

    public static function willThrow(?Throwable $e = null): static
    {
        $i = new self();
        $i->will_throw = $e ?? new TaskCreationFailedException('Failed to create task');

        return $i;
    }

    public function create(string $task_type, ?CreateTaskOptions $options = null): string
    {
        $options ??= new CreateTaskOptions([]);
        $this->calls[] = [
            'task_type' => $task_type,
            'options' => $this->convertOptionsToScalars($options->getRawOptions()),
        ];

        if ($this->will_throw instanceof Throwable) {
            throw $this->will_throw;
        }

        return uniqid();
    }

    public function assertNoTasksQueued(): void
    {
        Assert::assertSame([], $this->calls);
    }

    /**
     * @param mixed[][] $expect
     */
    public function assertQueuedExactly(array $expect): void
    {
        Assert::assertSame(
            array_map(
                function (array $call): array {
                    $call['options'] = $this->convertOptionsToScalars($call['options']);

                    return $call;
                },
                $expect
            ),
            $this->calls
        );
    }

    /**
     * Assert that exactly one task was created, and that it had the expected type.
     */
    public function assertQueuedExactlyOne(string $expect_task_type): void
    {
        Assert::assertSame(
            [$expect_task_type],
            \array_map(fn (array $t): string|array => $t['task_type'], $this->calls)
        );
    }

    /**
     * Convert all options values to scalars that can be compared with strict equality.
     *
     * The testcase will almost never have the actual object instances that were passed for schedule_send_after
     * or throttle_interval. So they need to be converted to some sort of scalar that we can compare.
     *
     * @param array<string, mixed> $opts
     */
    private function convertOptionsToScalars(array $opts): array
    {
        if ($opts['schedule_send_after'] ?? null) {
            $opts['schedule_send_after'] = DateString::format($opts['schedule_send_after'], 'Y-m-d H:i:s.u');
        }

        if ($opts['throttle_interval'] ?? null) {
            // DateInterval is hard to format back to the interval string. JSON the values that have any value should
            // be enough to make it comparable and vaguely understandable in the diff.
            $opts['throttle_interval'] = json_encode(array_filter((array) $opts['throttle_interval']));
        }

        return $opts;
    }
}

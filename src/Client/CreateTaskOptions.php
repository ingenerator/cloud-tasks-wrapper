<?php

namespace Ingenerator\CloudTasksWrapper\Client;

use DateInterval;
use DateTimeImmutable;
use Ingenerator\PHPUtils\DateTime\DateTimeImmutableFactory;
use Ingenerator\PHPUtils\Object\ObjectPropertyPopulator;
use Ingenerator\PHPUtils\StringEncoding\JSON;
use InvalidArgumentException;

use function array_keys;
use function ceil;
use function hash;
use function http_build_query;
use function str_contains;

class CreateTaskOptions
{
    /**
     * Time the object was created (mostly to provide consistent / testable time-based behaviour).
     */
    private DateTimeImmutable $_create_time;

    /**
     * The originally externally-supplied options.
     */
    private array $_raw_options;

    /**
     * Data to be sent in the body, can be JSON or form-encoded:
     * - to send JSON, 'body' => ['json' => [//data]]
     * - to send form, 'body' => ['form' => [//data]]
     */
    private ?string $body = null;

    /**
     * Extra headers to send with the HTTP request to the task handler.
     */
    private array $headers = [];

    /**
     * Optionally specify a complete URL the task should be sent to, otherwise this will come from the TaskTypeConfig.
     */
    private ?string $custom_handler_url = null;

    /**
     * Optionally add GET parameters to the handler URL.
     *
     * The handler URL itself comes from the task type config.
     */
    private ?array $query = null;

    /**
     * Optionally specify when the task should first be executed.
     */
    private ?DateTimeImmutable $schedule_send_after = null;

    /**
     * Optional, specify a task ID for server-side dedupe by Cloud Tasks. Note per the
     * docs this significantly reduces throughput especially if it is not a
     * well-distributed hash value. For fastest dispatch allow Cloud Tasks to duplicate
     * and deal with de-duping on receipt (necessary anyway as Tasks is always
     * at-least-once delivery).
     */
    private ?string $task_id = null;

    /**
     * Optional, *instead* of task_id, specify task_id_from to have the library automatically
     * calculate the task_id as an SHA256 hash of the application-provided string. Supports the
     * common case where you want to use a known string for deduping, but want the throughput of
     * well-distributed random-like task names.
     */
    private ?string $task_id_from = null;

    /**
     * Optional, combine with `task_id_from` to include header, query values and schedule_send_after in the hashed ID.
     *
     * It's very common you just want to dedupe a particular execution at a particular time. This option hashes the
     * values that usually make the submission unique. Can be used with, or without, the task_id_from option.
     */
    private bool $task_id_hash_options = false;

    /**
     * Number of seconds to delay execution after the end of a throttling window.
     *
     * See main throttling docs for further details.
     */
    private ?int $throttle_delay_secs = 60;

    /**
     * Optional, defer and throttle task execution to run on the provided interval.
     *
     * Use this where e.g. you want to queue a task on high-frequency changes but dedupe / group the
     * executions together once the data for a given record has settled in some way. See main docs for
     * details on usage.
     */
    private ?DateInterval $throttle_interval = null;

    /**
     * Whether to throw on a duplicate task (ALREADY_EXISTS) error, or to treat this as a safe condition.
     *
     * In many cases if you are setting a task_id (or task_id_from) to support server-side deduplication,
     * then it's expected that you may get an `ALREADY_EXISTS` error when a task is deduplicated. Often
     * this can be silently ignored by your app (if you just care it ran once) - set this flag to FALSE
     * to have the wrapper swallow that error.
     */
    private bool $throw_on_duplicate = true;

    /**
     * Create an instance.
     *
     * See the class property list for the allowed keys and documentation
     */
    public function __construct(array $options)
    {
        // Copied to allow the MockTaskCreator to capture it
        $options['_raw_options'] = $options;
        $options['_create_time'] ??= new DateTimeImmutable();
        $encoded = $this->encodeBodyOption($options['body'] ?? null);
        if ($encoded) {
            $options['body'] = $encoded['body'];
            // ??= means only set this key if not already present / null
            // equivalent to if (!isset($op['hdr']['CT']) { $op['hdr']['CT'] = $foo } or to using array_merge
            // to set a default
            $options['headers']['Content-Type'] ??= $encoded['Content-Type'];
        }

        ObjectPropertyPopulator::assignHash($this, $options);

        if ($this->task_id and $this->task_id_from) {
            throw new InvalidArgumentException('Cannot set both task_id and task_id_from');
        }
        if ($this->task_id and $this->task_id_hash_options) {
            throw new InvalidArgumentException('Cannot set both task_id and task_id_hash_options');
        }

        if (str_contains($this->custom_handler_url ?? '', '?') and !empty($this->query)) {
            throw new InvalidArgumentException(
                'Cannot set a query if your custom_handler_url already includes a querystring'
            );
        }

        if (isset($options['throttle_delay_secs']) and !$this->throttle_interval) {
            // Only matters if they set it explicitly so we check $options not the property which has a default
            throw new InvalidArgumentException('Cannot use throttle_delay_secs without throttle_interval');
        }

        if ($this->throttle_interval) {
            $this->parseThrottleOptions();
        }
        if ($this->task_id_hash_options) {
            $this->appendOptionsToTaskIdFrom();
        }
    }

    private function encodeBodyOption($body): ?array
    {
        if (!is_array($body)) {
            return null;
        }

        $type = implode(',', array_keys($body));

        return match ($type) {
            'json' => [
                'body' => JSON::encode($body['json'], false),
                'Content-Type' => 'application/json',
            ],
            'form' => [
                'body' => http_build_query($body['form']),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            default => throw new InvalidArgumentException('Invalid body type: '.JSON::encode($type, false)),
        };
    }

    private function parseThrottleOptions(): void
    {
        if ($this->schedule_send_after) {
            throw new InvalidArgumentException('Cannot set both schedule_send_after and throttle_interval');
        }
        if (!$this->task_id_from) {
            throw new InvalidArgumentException('Cannot set throttle_interval without task_id_from');
        }

        // Round up the curent time to the end of the next bucketing window.
        // E.g. a task executed at 11:03:30 with a `PT5M` interval will be rounded up to 11:05:00.

        $bucket_seconds = $this->calculateIntervalSeconds($this->throttle_interval);
        $bucket_ends_ts = ($bucket_seconds * ceil($this->_create_time->format('U.u') / $bucket_seconds));

        // Then add the throttle_delay_seconds to get the time the task should actually run.
        // So e.g. with the default 60 second delay, our 11:03:30 task will run at 11:06:00.
        // The delay just needs to be long enough to guarantee that anything that created a task at 11:04:59.999999 will
        // have completed / committed any work-in-progress by the time the deferred task is executed.
        $exec_time = DateTimeImmutableFactory::atUnixSeconds($bucket_ends_ts + $this->throttle_delay_secs);

        $this->schedule_send_after = $exec_time;
        $this->task_id_from .= '@'.$this->schedule_send_after->format('U.u');
    }

    private function calculateIntervalSeconds(?DateInterval $interval): int
    {
        $start = new DateTimeImmutable();
        $end = $start->add($interval);
        $delta = $end->getTimestamp() - $start->getTimestamp();

        return $delta;
    }

    private function appendOptionsToTaskIdFrom(): void
    {
        $parts = [];
        if ($this->schedule_send_after) {
            $parts[] = '@'.$this->schedule_send_after->format('U.u');
        }
        if ($this->query) {
            $parts[] = '?'.http_build_query($this->query);
        }
        if ($this->headers) {
            $parts[] = JSON::encode($this->headers);
        }
        $this->task_id_from .= \implode('', $parts);
    }

    /**
     * Return the fully-qualified task name (which must include the queue path) if any.
     */
    public function buildTaskName(string $queue_path): ?string
    {
        if ($this->task_id_from) {
            $id = hash('sha256', $this->task_id_from);
        } elseif ($this->task_id) {
            $id = $this->task_id;
        } else {
            return null;
        }

        return $queue_path.'/tasks/'.$id;
    }

    public function getBodyContent(): ?string
    {
        return $this->body;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getCustomHandlerUrl(): ?string
    {
        return $this->custom_handler_url;
    }

    public function getQuery(): ?array
    {
        return $this->query;
    }

    public function getRawOptions(): array
    {
        return $this->_raw_options;
    }

    public function getScheduleSendAfter(): ?DateTimeImmutable
    {
        return $this->schedule_send_after;
    }

    public function hasQuery(): bool
    {
        return !empty($this->query);
    }

    public function shouldThrowOnDuplicate(): bool
    {
        return $this->throw_on_duplicate;
    }
}

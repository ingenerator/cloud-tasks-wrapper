<?php


namespace Ingenerator\CloudTasksWrapper\Client;


use Ingenerator\PHPUtils\Object\ObjectPropertyPopulator;
use Ingenerator\PHPUtils\StringEncoding\JSON;

class CreateTaskOptions
{

    /**
     * The originally externally-supplied options
     *
     * @var array
     */
    private array $_raw_options;

    /**
     * Data to be sent in the body, can be JSON or form-encoded:
     * - to send JSON, 'body' => ['json' => [//data]]
     * - to send form, 'body' => ['form' => [//data]]
     *
     * @var string|null
     */
    private ?string $body = NULL;

    /**
     * Extra headers to send with the HTTP request to the task handler
     *
     * @var array
     */
    private array $headers = [];

    /**
     * Optionally add GET parameters to the handler URL.
     *
     * The handler URL itself comes from the task type config.
     *
     * @var array|null
     */
    private ?array $query = NULL;

    /**
     * Optionally specify when the task should first be executed
     *
     * @var \DateTimeImmutable|null
     */
    private ?\DateTimeImmutable $schedule_send_after = NULL;

    /**
     * Optional, specify a task ID for server-side dedupe by Cloud Tasks. Note per the
     * docs this significantly reduces throughput especially if it is not a
     * well-distributed hash value. For fastest dispatch allow Cloud Tasks to duplicate
     * and deal with de-duping on receipt (necessary anyway as Tasks is always
     * at-least-once delivery).
     *
     * @var string|null
     */
    private ?string $task_id = NULL;

    /**
     * Optional, *instead* of task_id, specify task_id_from to have the library automatically
     * calculate the task_id as an SHA256 hash of the application-provided string. Supports the
     * common case where you want to use a known string for deduping, but want the throughput of
     * well-distributed random-like task names.
     *
     * @var string|null
     */
    private ?string $task_id_from = NULL;

    /**
     * Whether to throw on a duplicate task (ALREADY_EXISTS) error, or to treat this as a safe condition.
     *
     * In many cases if you are setting a task_id (or task_id_from) to support server-side deduplication,
     * then it's expected that you may get an `ALREADY_EXISTS` error when a task is deduplicated. Often
     * this can be silently ignored by your app (if you just care it ran once) - set this flag to FALSE
     * to have the wrapper swallow that error.
     *
     * @var bool
     */
    private bool $throw_on_duplicate = TRUE;

    /**
     * Create an instance.
     *
     * See the class property list for the allowed keys and documentation
     *
     * @param array $options
     */
    public function __construct(array $options)
    {
        // Copied to allow the MockTaskCreator to capture it
        $options['_raw_options'] = $options;
        if (isset($options['task_id']) and isset($options['task_id_from'])) {
            throw new \InvalidArgumentException('Cannot set both task_id and task_id_from');
        }

        if (is_array($options['body'] ?? NULL)) {
            $options = $this->parseBodyOptions($options);
        }

        ObjectPropertyPopulator::assignHash($this, $options);
    }

    /**
     * @param array $options
     *
     * @return array
     */
    private function parseBodyOptions(array $options): array
    {
        $type = \array_keys($options['body']);
        if ($type === ['json']) {
            $options['body']      = JSON::encode($options['body']['json'], FALSE);
            $default_content_type = 'application/json';
        } elseif ($type === ['form']) {
            $options['body']      = \http_build_query($options['body']['form']);
            $default_content_type = 'application/x-www-form-urlencoded';
        } else {
            throw new \InvalidArgumentException('Invalid body type: '.JSON::encode($type, FALSE));
        }

        if ( ! isset($options['headers']['Content-Type'])) {
            $options['headers']['Content-Type'] = $default_content_type;
        }

        return $options;
    }

    /**
     * @return string|null
     */
    public function getBodyContent(): ?string
    {
        return $this->body;
    }

    /**
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @return array|null
     */
    public function getQuery(): ?array
    {
        return $this->query;
    }

    public function hasQuery(): bool
    {
        return ! empty($this->query);

    }

    /**
     * @return \DateTimeImmutable|null
     */
    public function getScheduleSendAfter(): ?\DateTimeImmutable
    {
        return $this->schedule_send_after;
    }

    /**
     * Return the fully-qualified task name (which must include the queue path) if any
     *
     * @param string $queue_path
     * @param array  $options
     *
     * @return string|null
     */
    public function buildTaskName(string $queue_path): ?string
    {
        if ($this->task_id_from) {
            $id = \hash('sha256', $this->task_id_from);
        } elseif ($this->task_id) {
            $id = $this->task_id;
        } else {
            return NULL;
        }

        return $queue_path.'/tasks/'.$id;
    }

    /**
     * @return array
     */
    public function getRawOptions(): array
    {
        return $this->_raw_options;
    }

    /**
     * @return bool
     */
    public function shouldThrowOnDuplicate(): bool
    {
        return $this->throw_on_duplicate;
    }

}

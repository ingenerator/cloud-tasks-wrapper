<?php


namespace Ingenerator\CloudTasksWrapper\Server;


use Psr\Http\Message\ServerRequestInterface;

/**
 * Encapsulates common details of the incoming task request from the underlying HTTP request
 *
 * Exposes the header properties per https://cloud.google.com/tasks/docs/creating-http-target-tasks
 * as well as application-specific metadata.
 */
class TaskRequest
{
    protected ?string $caller_email = NULL;
    protected ServerRequestInterface $request;
    protected string $task_type;

    public function __construct(ServerRequestInterface $request, string $task_type)
    {
        $this->request   = $request;
        $this->task_type = $task_type;
    }

    /**
     * The complete external URL that was called to trigger this task handler
     *
     * @return string
     */
    public function getFullUrl(): string
    {
        return $this->request->getUri();
    }

    public function getTaskType(): string
    {
        return $this->task_type;
    }

    /**
     * The name of the queue
     *
     * @return string
     * @see https://cloud.google.com/tasks/docs/creating-http-target-tasks#handler
     */
    public function getQueueName(): string
    {
        return $this->request->getHeaderLine('X-CloudTasks-QueueName');
    }

    /**
     * The "short" name of the task, or, if no name was specified at creation, a unique system-generated id.
     *
     * This is the my-task-id value in the complete task name, ie, task_name =
     * projects/my-project-id/locations/my-location/queues/my-queue-id/tasks/my-task-id.
     *
     * @return string
     * @see https://cloud.google.com/tasks/docs/creating-http-target-tasks#handler
     */
    public function getTaskName(): string
    {
        return $this->request->getHeaderLine('X-CloudTasks-TaskName');
    }

    /**
     * The number of times this task has been retried. For the first attempt, this value is 0.
     *
     * This number includes attempts where the task failed due to 5XX error codes and never reached
     * the execution phase.
     *
     * @return int
     * @see https://cloud.google.com/tasks/docs/creating-http-target-tasks#handler
     */
    public function getRetryCount(): int
    {
        return (int) $this->request->getHeaderLine('X-CloudTasks-TaskRetryCount');
    }

    /**
     * The total number of times that the task has received a response from the handler.
     *
     * Since Cloud Tasks deletes the task once a successful response has been received, all previous
     * handler responses were failures. This number does not include failures due to 5XX error
     * codes.
     *
     * @return int
     * @see https://cloud.google.com/tasks/docs/creating-http-target-tasks#handler
     */
    public function getExecutionCount(): int
    {
        return (int) $this->request->getHeaderLine('X-CloudTasks-TaskExecutionCount');
    }

    /**
     * The HTTP response code from the previous retry.
     *
     * @return string|null
     * @see https://cloud.google.com/tasks/docs/creating-http-target-tasks#handler
     */
    public function getPreviousResponse(): ?string
    {
        return $this->request->getHeaderLine('X-CloudTasks-TaskPreviousResponse') ?: NULL;
    }

    /**
     * The reason for retrying the task.
     *
     * @return string|null
     * @see https://cloud.google.com/tasks/docs/creating-http-target-tasks#handler
     */
    public function getRetryReason(): ?string
    {
        return $this->request->getHeaderLine('X-CloudTasks-TaskRetryReason') ?: NULL;
    }

    /**
     * The schedule time of the task
     *
     * @return \DateTimeImmutable|null
     * @see https://cloud.google.com/tasks/docs/creating-http-target-tasks#handler
     */
    public function getScheduledTime(): ?\DateTimeImmutable
    {
        $time = $this->request->getHeaderLine('X-CloudTasks-TaskETA');
        if (empty($time)) {
            return NULL;
        }

        // @todo: throw if unexpected / invalid format?
        // @todo: this needs to go to php-utils
        // Work round some PHP edge cases.
        // First, a float with a .00 casts to string with no decimal point, which causes the createFromFormat to fail
        // on exact seconds. Use sprintf to ensure there's always a decimal present
        $dt = \DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', (float) $time));

        // Second, creating from unix timestamp of any kind always sets the TZ to UTC (*even* if you specify a timezone
        // in the constructor).
        // So cast it back to the default timezone for consistency with other date time constructor formats
        return $dt->setTimezone(new \DateTimeZone(\date_default_timezone_get()));
    }

    public function getHttpRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    public function getCallerEmail(): ?string
    {
        return $this->caller_email;
    }

    public function setCallerEmail(?string $email): void
    {
        $this->caller_email = $email;
    }

}

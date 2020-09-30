<?php


namespace Ingenerator\CloudTasksWrapper\Client;


use DateTimeImmutable;
use Google\ApiCore\ApiException;
use Google\Cloud\Tasks\V2\CloudTasksClient;
use Google\Cloud\Tasks\V2\HttpMethod;
use Google\Cloud\Tasks\V2\HttpRequest;
use Google\Cloud\Tasks\V2\OidcToken;
use Google\Cloud\Tasks\V2\Task;
use Google\Protobuf\Internal\Message;
use Google\Protobuf\Timestamp;
use Psr\Log\LoggerInterface;

class CloudTaskCreator
{
    /**
     * @var CloudTasksClient
     */
    protected $client;

    /**
     * @var CloudTasksQueueMapper
     */
    protected $queue_mappper;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(
        CloudTasksClient $client,
        CloudTasksQueueMapper $queue_mapper,
        LoggerInterface $logger
    ) {
        $this->client        = $client;
        $this->queue_mappper = $queue_mapper;
        $this->logger        = $logger;
    }

    /**
     * Create a task to be sent by HTTP POST
     *
     * By default will be sent signed with an OIDC token for verification at the receiving end
     *
     * @param string $internal_queue_name
     * @param string $post_url
     * @param array  $options
     *
     * @return string the task name
     *
     * @throws TaskCreationFailedException
     */
    public function createTask(
        string $internal_queue_name,
        string $post_url,
        array $options = []
    ): string {
        $options = array_merge(
            [
                'schedule_send_after' => NULL,
                // Optional, specify a task name for server-side dedupe by Cloud Tasks. Note per the
                // docs this significantly reduces throughput especially if it is not a
                // well-distributed hash value. For fastest dispatch allow Cloud Tasks to duplicate
                // and deal with de-duping on receipt (necessary anyway as Tasks is always
                // at-least-once delivery).
                'task_name'           => NULL,
            ],
            $options
        );

        $task = $this->createObj(
            Task::class,
            [
                'name'          => $options['task_name'],
                'http_request'  => $this->createObj(
                    HttpRequest::class,
                    [
                        'url'         => $post_url,
                        'http_method' => HttpMethod::POST,
                        'oidc_token'  => $this->oidcTokenUnlessAnonymous($internal_queue_name),
                    ]
                ),
                'schedule_time' => $this->toTimestampOrNull($options['schedule_send_after'])
            ]
        );

        try {
            //@todo: set retry and timeout settings either on the operation or the client
            // NB that the defaults don't appear to be the defaults in the docs, and customising doesn't work the way
            // you think either (doesn't seem to actually accept a RetrySettings despite saying it does)
            $result = $this->client->createTask(
                $this->queue_mappper->pathFor($internal_queue_name),
                $task
            );

            return $result->getName();

        } catch (ApiException $e) {
            $this->logger->error(
                'Failed to create cloud task: '.$e->getBasicMessage(),
                [
                    'exception'          => $e,
                    'exception_metadata' => $e->getMetadata(),
                    'task_info'          => [
                        'post_url'       => $post_url,
                        'internal_queue' => $internal_queue_name
                    ],
                ]
            );
            throw new TaskCreationFailedException(
                'Failed to create task: '.$e->getBasicMessage(),
                0,
                $e
            );
        }
    }

    /**
     * @param string $internal_queue_name
     *
     * @return OidcToken
     */
    protected function oidcTokenUnlessAnonymous(string $internal_queue_name): ?OidcToken
    {
        $email = $this->queue_mappper->getOidcSignerEmail($internal_queue_name);
        if ($email === 'anonymous') {
            return NULL;
        }

        return new OidcToken(
            [
                'service_account_email' => $email,
            ]
        );
    }

    protected function toTimestampOrNull(?DateTimeImmutable $datetime): ?Timestamp
    {
        if ($datetime === NULL) {
            return NULL;
        }

        return new Timestamp(
            [
                'seconds' => $datetime->getTimestamp(),
                'nanos'   => 1000 * $datetime->format('u')
            ]
        );
    }

    protected function createObj(string $class, array $vars): Message
    {
        // Note passing the array through array_filter first - protobuf internally differentiates
        // between a field that is not present / undefined vs one that is explicitly set to null
        // and some fields don't have valid `null` values

        return new $class(array_filter($vars));
    }
}

<?php


namespace Ingenerator\CloudTasksWrapper\Client;


use DateTime;
use DateTimeImmutable;
use Google\ApiCore\ApiException;
use Google\Cloud\Tasks\V2\CloudTasksClient;
use Google\Cloud\Tasks\V2\HttpMethod;
use Google\Cloud\Tasks\V2\HttpRequest;
use Google\Cloud\Tasks\V2\OidcToken;
use Google\Cloud\Tasks\V2\Task;
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
    public function createTask(string $internal_queue_name, string $post_url, array $options = []): string
    {
        $options = array_merge(
            [
                'schedule_send_after'  => NULL,
                // Verifying OIDC tokens is trickier than it seems due to having to fetch the certs
                // So we'll use tokenista for now.
                'send_oidc_token'      => FALSE,
            ],
            $options
        );

        // @todo: Record metrics, log failures

        $http_request = [
            'url'         => $post_url,
            'http_method' => HttpMethod::POST,
        ];

        if ($options['send_oidc_token']) {
            $http_request['oidc_token'] = $this->createOidcToken($internal_queue_name);
        }

        $task_opts = [
            // Optional, specify name for dedupe (reduces throughput especially if contiguous)
            //'name' => 'well-distributed-hash-or-uuid'
            'http_request' => new HttpRequest($http_request),
            // Can't init schedule_time to null, Protobuf gives an exception about merging values of different types
            // It clearly internally differentiates between `null` and `not assigned`
            //'schedule_time' => NULL
        ];

        if ($options['schedule_send_after']) {
            $task_opts['schedule_time'] = $this->toTimestamp($options['schedule_send_after']);
        }

        try {
            //@todo: set retry and timeout settings either on the operation or the client
            // NB that the defaults don't appear to be the defaults in the docs, and customising doesn't work the way
            // you think either (doesn't seem to actually accept a RetrySettings despite saying it does)
            $result = $this->client->createTask(
                $this->queue_mappper->pathFor($internal_queue_name),
                new Task($task_opts)
            );

            return $result->getName();

        } catch (ApiException $e) {
            $this->logger->error(
                'Failed to create cloud task: '.$e->getBasicMessage(),
                [
                    'exception'          => $e,
                    'exception_metadata' => $e->getMetadata(),
                    'task_http_request'  => $http_request,
                ]
            );
            throw new TaskCreationFailedException('Failed to create task: '.$e->getBasicMessage(), 0, $e);
        }
    }

    /**
     * @param string $internal_queue_name
     *
     * @return OidcToken
     */
    protected function createOidcToken(string $internal_queue_name): OidcToken
    {
        return new OidcToken(
            [
                'service_account_email' => $this->queue_mappper->getOidcSignerEmail($internal_queue_name),
            ]
        );
    }

    protected function toTimestamp(DateTimeImmutable $datetime): Timestamp
    {
        $ts = new Timestamp;
        $ts->fromDateTime(new DateTime($datetime->format(DateTimeImmutable::ATOM)));

        return $ts;
    }
}

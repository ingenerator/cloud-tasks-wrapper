<?php


namespace test\unit\Ingenerator\CloudTasksWrapper\Client;


use Google\ApiCore\ApiException;
use Google\Cloud\Tasks\V2\CloudTasksClient;
use Google\Cloud\Tasks\V2\Task;
use Google\Protobuf\Timestamp;
use Google\Rpc\Code;
use Ingenerator\CloudTasksWrapper\Client\CloudTaskCreator;
use Ingenerator\CloudTasksWrapper\Client\CloudTasksQueueMapper;
use Ingenerator\CloudTasksWrapper\Client\TaskCreationFailedException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Psr\Log\Test\TestLogger;

class CloudTaskCreatorTest extends TestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    protected $tasks_client;
    /**
     * @var \Psr\Log\NullLogger
     */
    protected $logger;

    protected $queue_config = [
        'default_project'  => 'dev',
        'default_location' => 'here',
        'default_signer'   => 'himthere@service.com',
        'queues'           => [
            'any-old-queue' => [],
        ],
    ];

    public function test_it_is_initialisable()
    {
        $this->assertInstanceOf(CloudTaskCreator::class, $this->newSubject());
    }

    /**
     * @testWith ["someone@service.com",true, "someone@service.com"]
     *           ["anonymous", false, null]
     */
    public function test_it_adds_oidc_token_for_queue_name_unless_queue_is_configured_anonymous(
        $signer,
        $expect_has_oidc,
        $expect_oidc_email
    ) {
        $this->queue_config['queues']['fast-queue']['signer'] = $signer;
        $this->newSubject()->createTask('fast-queue', 'https://any.url');

        $task = $this->tasks_client->assertCreatedOneTask();
        // @todo: update this test to use the ->hasOidcToken when the method is fixed https://github.com/protocolbuffers/protobuf/issues/7926
//        $this->assertSame($expect_has_oidc, $task->getHttpRequest()->hasOidcToken());
        if ($expect_has_oidc) {
            $this->assertSame(
                $expect_oidc_email,
                $task->getHttpRequest()->getOidcToken()->getServiceAccountEmail()
            );
        } else {
            $this->assertNull($task->getHttpRequest()->getOidcToken());
        }
    }

    public function test_it_sets_task_to_post_to_provided_url()
    {
        $this->newSubject()->createTask('any-old-queue', 'https://my.handler/foo?id=bar');
        $task = $this->tasks_client->assertCreatedOneTask();
        $this->assertSame('https://my.handler/foo?id=bar', $task->getHttpRequest()->getUrl());
    }

    /**
     * @testWith [{}, ""]
     *           [{"task_name": "absad237"}, "absad237"]
     */
    public function test_it_adds_task_name_if_provided($opts, $expect)
    {
        $this->newSubject()->createTask('any-old-queue', 'https://any.thing', $opts);
        $task = $this->tasks_client->assertCreatedOneTask();
        $this->assertSame($expect, $task->getName());
    }

    public function provider_schedule_time()
    {
        $ts = time() + 500;

        return [
            [[], NULL],
            [
                [
                    'schedule_send_after' => \DateTimeImmutable::createFromFormat(
                        'U.u',
                        "$ts.123456"
                    )
                ],
                ['seconds' => $ts, 'nanos' => 123456000]
            ]
        ];
    }

    /**
     * @dataProvider provider_schedule_time
     */
    public function test_it_adds_timestamp_for_scheduled_time_if_provided($opts, $expect)
    {
        $this->newSubject()->createTask('any-old-queue', 'https://any.thing', $opts);
        $task = $this->tasks_client->assertCreatedOneTask();
        if ($expect === NULL) {
            $this->assertSame($expect, $task->getScheduleTime());
        } else {
            $time = $task->getScheduleTime();
            $this->assertInstanceOf(Timestamp::class, $time);
            $this->assertSame(
                $expect,
                ['seconds' => $time->getSeconds(), 'nanos' => $time->getNanos()]
            );
        }
    }

    public function test_it_submits_to_the_configured_queue_url_for_the_internal_name()
    {
        $this->queue_config['queues']['fast-queue'] = [
            'project'  => 'our-project',
            'location' => 'europe',
            'name'     => 'queue-name',
        ];
        $this->newSubject()->createTask('fast-queue', 'https://any.thing');
        $this->tasks_client->assertCreatedOneTaskInQueue(
            CloudTasksClient::queueName('our-project', 'europe', 'queue-name')
        );
    }

    public function test_it_logs_nothing_on_success()
    {
        $this->logger = new TestLogger();
        $this->newSubject()->createTask('any-old-queue', 'https://any.thing');
        $this->assertSame([], $this->logger->records);
    }

    public function test_it_logs_and_throws_on_failure()
    {
        $this->logger       = new TestLogger();
        $api_exception      = ApiException::createFromApiResponse(
            'Create task broke!',
            Code::UNKNOWN
        );
        $this->tasks_client = TasksClientSpy::willThrowOnCreate($api_exception);
        $subject            = $this->newSubject();
        try {
            $subject->createTask('any-old-queue', 'https://any.thing');
            $this->fail('Expected exception, none got');
        } catch (TaskCreationFailedException $e) {
            $this->assertSame('Failed to create task: Create task broke!', $e->getMessage());
            $this->assertSame($api_exception, $e->getPrevious());
        }

        $this->assertTrue(
            $this->logger->hasErrorThatMatches(
                '/^Failed to create cloud task: Create task broke!$/'
            )
        );
    }

    public function test_it_provides_retry_options_when_sending_task()
    {
        $this->markTestIncomplete();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->tasks_client = new TasksClientSpy;
        $this->logger       = new NullLogger;
    }

    protected function newSubject()
    {
        return new CloudTaskCreator(
            $this->tasks_client,
            new CloudTasksQueueMapper($this->queue_config),
            $this->logger
        );
    }
}

class TasksClientSpy extends CloudTasksClient
{
    protected $created = [];
    protected $create_will_throw;

    public function __construct() { }

    public static function willThrowOnCreate(\Exception $e)
    {
        $i                    = new static;
        $i->create_will_throw = $e;

        return $i;
    }

    public function createTask($parent, $task, array $optionalArgs = [])
    {
        if ($this->create_will_throw) {
            throw $this->create_will_throw;
        }
        $this->created[] = ['parent' => $parent, 'task' => $task, 'args' => $optionalArgs];
        $result          = clone $task;
        $result->setName('created-task-name');

        return $result;
    }

    public function assertCreatedOneTask(): Task
    {
        Assert::assertCount(1, $this->created);

        return $this->created[0]['task'];
    }

    public function assertCreatedOneTaskInQueue(string $queue)
    {
        Assert::assertCount(1, $this->created);
        Assert::assertSame($queue, $this->created[0]['parent']);
    }

}

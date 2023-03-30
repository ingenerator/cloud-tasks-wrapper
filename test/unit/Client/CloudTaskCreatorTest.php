<?php


namespace test\unit\Ingenerator\CloudTasksWrapper\Client;


use Google\ApiCore\ApiException;
use Google\ApiCore\ApiStatus;
use Google\Cloud\Tasks\V2\CloudTasksClient;
use Google\Cloud\Tasks\V2\HttpMethod;
use Google\Cloud\Tasks\V2\Task;
use Google\Protobuf\Timestamp;
use Google\Rpc\Code;
use Ingenerator\CloudTasksWrapper\Client\CloudTaskCreator;
use Ingenerator\CloudTasksWrapper\Client\CloudTasksQueueMapper;
use Ingenerator\CloudTasksWrapper\Client\CreateTaskOptions;
use Ingenerator\CloudTasksWrapper\Client\TaskCreationFailedException;
use Ingenerator\CloudTasksWrapper\TestHelpers\TaskTypeConfigStub;
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

    protected TaskTypeConfigStub $task_config;

    public function test_it_is_initialisable()
    {
        $this->assertInstanceOf(CloudTaskCreator::class, $this->newSubject());
    }

    /**
     * @testWith [{}, ""]
     *           [{"body":"any body"}, "any body"]
     *           // tests for JSON / form etc are covered by the tests on CreateTaskOptions
     */
    public function test_it_adds_body_and_headers_if_provided($opts, $expect_body)
    {
        $this->newSubject()->create('some-task', new CreateTaskOptions($opts));
        $task = $this->tasks_client->assertCreatedOneTask();
        $this->assertSame($expect_body, $task->getHttpRequest()->getBody(), 'Sets body');
    }

    /**
     * @testWith ["someone@service.com",true, "someone@service.com"]
     *           ["anonymous", false, null]
     */
    public function test_it_adds_oidc_token_for_task_unless_task_type_is_configured_anonymous(
        $signer,
        $expect_has_oidc,
        $expect_oidc_email
    ) {
        $this->task_config = TaskTypeConfigStub::withTaskType('do-something', ['signer_email' => $signer]);
        $this->newSubject()->create('do-something');

        $task = $this->tasks_client->assertCreatedOneTask();
        $this->assertSame($expect_has_oidc, $task->getHttpRequest()->hasOidcToken());
        if ($expect_has_oidc) {
            $this->assertSame(
                $expect_oidc_email,
                $task->getHttpRequest()->getOidcToken()->getServiceAccountEmail()
            );
        } else {
            $this->assertNull($task->getHttpRequest()->getOidcToken());
        }
    }

    /**
     * @testWith [{"custom_token_audience": null}, ""]
     *           [{}, ""]
     *           [{"custom_token_audience": "pick-me!"}, "pick-me!"]
     */
    public function test_it_configures_custom_audience_for_authenticated_task_if_configured($config, $expect)
    {
        $this->task_config = TaskTypeConfigStub::withTaskType(
            'do-something',
            ['signer_email' => 'someone@service.com', ...$config]
        );
        $this->newSubject()->create('do-something');

        $task = $this->tasks_client->assertCreatedOneTask();
        $this->assertTrue($task->getHttpRequest()->hasOidcToken(), 'should have oidc token');
        $this->assertSame($expect, $task->getHttpRequest()->getOidcToken()->getAudience());
    }

    public function provider_task_url()
    {
        return [
            'just from config, no query'                                                 => [
                [],
                "https://my.handler.foo/something",
            ],
            'from config, with CreateTaskOptions.query'                                  => [
                ['query' => ['id' => 15, 'scope' => 'any']],
                "https://my.handler.foo/something?id=15&scope=any",
            ],
            'with CreateTaskOptions.custom_handler_url and no query'                     => [
                ['custom_handler_url' => 'https://my-handler.com/whatever'],
                "https://my-handler.com/whatever",
            ],
            'with CreateTaskOptions.custom_handler_url including own query and no query' => [
                ['custom_handler_url' => 'https://my-handler.com/whatever?foo=bar'],
                "https://my-handler.com/whatever?foo=bar",
            ],
            'with CreateTaskOptions.custom_handler_url and CreateTaskOptions.query'      => [
                [
                    'custom_handler_url' => 'https://my-handler.com/whatever',
                    'query'              => ['id' => 15, 'scope' => 'any'],
                ],
                "https://my-handler.com/whatever?id=15&scope=any",
            ],
        ];
    }

    /**
     * @dataProvider provider_task_url
     */
    public function test_it_sets_task_to_post_to_custom_or_config_url_optionally_adding_query_params($opts, $expect)
    {
        $this->task_config = TaskTypeConfigStub::withTaskType(
            'do-something',
            ['handler_url' => 'https://my.handler.foo/something']
        );
        $this->newSubject()->create('do-something', new CreateTaskOptions($opts));
        $task = $this->tasks_client->assertCreatedOneTask();
        $this->assertSame($expect, $task->getHttpRequest()->getUrl());
        $this->assertSame(HttpMethod::POST, $task->getHttpRequest()->getHttpMethod());
    }

    /**
     * @testWith [{}, ""]
     *           [{"task_id": "absad237"}, "absad237"]
     */
    public function test_it_adds_task_name_from_id_if_provided($opts, $expect_id)
    {
        $this->task_config = TaskTypeConfigStub::withTaskType(
            'some-task',
            [
                'queue' => ['project' => 'mine', 'location' => 'mars', 'name' => 'archival'],
            ]
        );
        $this->newSubject()->create('some-task', new CreateTaskOptions($opts));
        $task = $this->tasks_client->assertCreatedOneTask();
        if ($expect_id) {
            $expect_name = CloudTasksClient::taskName('mine', 'mars', 'archival', $expect_id);
        } else {
            $expect_name = "";
        }
        $this->assertSame($expect_name, $task->getName());
    }

    /**
     * @testWith [{}, []]
     *           [{"headers": {"X-SomeThing": "This"}}, {"X-SomeThing": "This"}]
     */
    public function test_it_adds_http_headers_if_provided($opts, $expect)
    {
        $this->newSubject()->create('some-task', new CreateTaskOptions($opts));
        $task = $this->tasks_client->assertCreatedOneTask();
        $this->assertSame($expect, \iterator_to_array($task->getHttpRequest()->getHeaders()));
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
                    ),
                ],
                ['seconds' => $ts, 'nanos' => 123456000],
            ],
        ];
    }

    /**
     * @dataProvider provider_schedule_time
     */
    public function test_it_adds_timestamp_for_scheduled_time_if_provided($opts, $expect)
    {
        $this->newSubject()->create('any-old-job', new CreateTaskOptions($opts));
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

    public function test_it_submits_to_the_configured_queue_url_for_the_task_options()
    {
        $this->task_config = TaskTypeConfigStub::withTaskType(
            'something',
            [
                'queue' => [
                    'project'  => 'mine',
                    'location' => 'mars',
                    'name'     => 'archival',
                ],
            ]
        );
        $result            = $this->newSubject()->create('something');
        $this->assertSame(
            CloudTasksClient::taskName('mine', 'mars', 'archival', 'created-task-name'),
            $result
        );
        $this->tasks_client->assertCreatedOneTaskInQueue(
            CloudTasksClient::queueName('mine', 'mars', 'archival')
        );
    }

    public function test_it_logs_nothing_on_success()
    {
        $this->logger = new TestLogger();
        $this->newSubject()->create('anything');
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
            $subject->create('anything');
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

    public function test_it_bubbles_already_exists_if_set_to_throw_on_duplicate()
    {
        $this->logger       = new TestLogger();
        $api_exception      = ApiException::createFromApiResponse(
            'Already got it!',
            Code::ALREADY_EXISTS
        );
        $this->tasks_client = TasksClientSpy::willThrowOnCreate($api_exception);
        $subject            = $this->newSubject();
        try {
            $subject->create(
                'anything',
                new CreateTaskOptions(['task_id' => 'foobar', 'throw_on_duplicate' => TRUE])
            );
            $this->fail('Expected exception, none got');
        } catch (TaskCreationFailedException $e) {
            $this->assertSame('Failed to create task: Already got it!', $e->getMessage());
            $this->assertSame($api_exception, $e->getPrevious());
        }

        $this->assertTrue(
            $this->logger->hasErrorThatMatches(
                '/^Failed to create cloud task: Already got it!$/'
            )
        );
    }

    public function test_it_swallows_already_exists_if_set_not_to_throw_on_duplicate()
    {
        $this->task_config  = TaskTypeConfigStub::withTaskType(
            'anything',
            [
                'queue' => ['project' => 'mine', 'location' => 'mars', 'name' => 'archival',],
            ]
        );
        $this->logger       = new TestLogger();
        $this->tasks_client = TasksClientSpy::willThrowOnCreate(
            ApiException::createFromApiResponse(
                'Already got it!',
                Code::ALREADY_EXISTS
            )
        );
        $subject            = $this->newSubject();
        $result             = $subject->create(
            'anything',
            new CreateTaskOptions(['task_id' => 'foobar', 'throw_on_duplicate' => FALSE])
        );
        $this->assertSame(
            CloudTasksClient::taskName('mine', 'mars', 'archival', 'foobar'),
            $result,
            'returns full task name'
        );
        $this->assertSame([], $this->logger->records);
    }


    public function test_it_provides_retry_options_when_sending_task()
    {
        $this->task_config = TaskTypeConfigStub::withTaskType(
            'something',
            [
                'create_retry_settings' => [
                    'initialRetryDelayMillis' => 100,
                    'retryDelayMultiplier'    => 1.3,
                    'maxRetryDelayMillis'     => 10000,
                    'retryableCodes'          => [ApiStatus::DEADLINE_EXCEEDED, ApiStatus::UNAVAILABLE],
                    'retriesEnabled'          => TRUE,
                ],
            ]
        );
        $this->newSubject()->create('something');
        $this->tasks_client->assertCreatedOneTaskWithOptions(
            [
                'retrySettings' => [
                    'initialRetryDelayMillis' => 100,
                    'retryDelayMultiplier'    => 1.3,
                    'maxRetryDelayMillis'     => 10000,
                    'retryableCodes'          => [ApiStatus::DEADLINE_EXCEEDED, ApiStatus::UNAVAILABLE],
                    'retriesEnabled'          => TRUE,
                ],
            ]
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->tasks_client = new TasksClientSpy;
        $this->logger       = new NullLogger;
        $this->task_config  = TaskTypeConfigStub::withAnyTaskType();
    }

    protected function newSubject()
    {
        return new CloudTaskCreator(
            $this->tasks_client,
            $this->task_config,
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
        $result->setName($parent.'/tasks/created-task-name');

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

    public function assertCreatedOneTaskWithOptions(array $options)
    {
        Assert::assertCount(1, $this->created);
        Assert::assertSame($options, $this->created[0]['args']);
    }

}

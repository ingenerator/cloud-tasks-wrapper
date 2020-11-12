<?php


namespace test\unit\Ingenerator\CloudTasksWrapper\Client;


use Google\ApiCore\ApiException;
use Google\Cloud\Tasks\V2\CloudTasksClient;
use Google\Cloud\Tasks\V2\HttpMethod;
use Google\Cloud\Tasks\V2\Task;
use Google\Protobuf\Timestamp;
use Google\Rpc\Code;
use Ingenerator\CloudTasksWrapper\Client\CloudTaskCreator;
use Ingenerator\CloudTasksWrapper\Client\CloudTasksQueueMapper;
use Ingenerator\CloudTasksWrapper\Client\TaskCreationFailedException;
use Ingenerator\CloudTasksWrapper\TaskTypeConfigProvider;
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

    protected $task_config = [
        '_default'  => [
            'queue'        => [
                'project'  => 'good-proj',
                'location' => 'the-moon',
                'name'     => 'priority',
            ],
            'signer_email' => 'neil@armstrong.serviceaccount.test',
            'handler_url'  => 'https://moon.test/my-task',
        ],
        'some-task' => [],
    ];

    public function test_it_is_initialisable()
    {
        $this->assertInstanceOf(CloudTaskCreator::class, $this->newSubject());
    }

    public function test_it_throws_if_specifying_multiple_body_types()
    {
        $subject = $this->newSubject();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid body type');
        $subject->create(
            'some-task',
            [
                'body' => ['form' => ['foo' => 'bar'], 'json' => ['any' => 'json']],
            ]
        );
    }

    public function test_it_throws_if_specifying_invalid_body()
    {
        $subject = $this->newSubject();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid body type');
        $subject->create(
            'some-task',
            [
                'body' => new \stdClass,
            ]
        );
    }

    public function provider_body_encoding()
    {
        return [
            [
                ['form' => ['foo' => 'bar', 'child' => ['any' => 'thing']]],
                'foo=bar&child%5Bany%5D=thing',
                'application/x-www-form-urlencoded',
            ],
            [
                ['json' => ['foo' => 'bar', 'child' => ['any' => 'thing']]],
                '{"foo":"bar","child":{"any":"thing"}}',
                'application/json',
            ],
            [
                'My custom payload',
                'My custom payload',
                NULL,
            ],
            [
                NULL,
                '',
                NULL,
            ],
        ];
    }

    /**
     * @dataProvider provider_body_encoding
     */
    public function test_it_adds_json_or_form_body_with_headers($opt_body, $expect_body, $expect_content_type)
    {
        $this->newSubject()->create('some-task', ['body' => $opt_body, 'headers' => ['X-something' => 'Else']]);
        $task = $this->tasks_client->assertCreatedOneTask();
        $this->assertSame($expect_body, $task->getHttpRequest()->getBody(), 'Sets body');
        $this->assertSame(
            $expect_content_type,
            $task->getHttpRequest()->getHeaders()['Content-Type'] ?? NULL,
            'Sets content type'
        );
        $this->assertSame(
            'Else',
            $task->getHttpRequest()->getHeaders()['X-something'],
            'Doesn\'t overwrite other headers'
        );
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
        $this->task_config['do-something']['signer_email'] = $signer;
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
     * @testWith [[], "https://my.handler.foo/something"]
     *           [{"query": {"id": 15, "scope": "any"}}, "https://my.handler.foo/something?id=15&scope=any"]
     */
    public function test_it_sets_task_to_post_to_provided_url_optionally_adding_query_params($opts, $expect)
    {
        $this->task_config['do-something']['handler_url'] = 'https://my.handler.foo/something';
        $this->newSubject()->create('do-something', $opts);
        $task = $this->tasks_client->assertCreatedOneTask();
        $this->assertSame($expect, $task->getHttpRequest()->getUrl());
        $this->assertSame(HttpMethod::POST, $task->getHttpRequest()->getHttpMethod());
    }

    /**
     * @testWith [{}, ""]
     *           [{"task_name": "absad237"}, "absad237"]
     */
    public function test_it_adds_task_name_if_provided($opts, $expect)
    {
        $this->task_config['some-task'] = [];
        $this->newSubject()->create('some-task', $opts);
        $task = $this->tasks_client->assertCreatedOneTask();
        $this->assertSame($expect, $task->getName());
    }

    /**
     * @testWith [{}, []]
     *           [{"headers": {"X-SomeThing": "This"}}, {"X-SomeThing": "This"}]
     */
    public function test_it_adds_http_headers_if_provided($opts, $expect)
    {
        $this->task_config['some-task'] = [];
        $this->newSubject()->create('some-task', $opts);
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
        $this->task_config['any-old-job'] = [];
        $this->newSubject()->create('any-old-job', $opts);
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
        $this->task_config['something']['queue'] = [
            'project'  => 'mine',
            'location' => 'mars',
            'name'     => 'archival',
        ];
        $this->newSubject()->create('something');
        $this->tasks_client->assertCreatedOneTaskInQueue(
            CloudTasksClient::queueName('mine', 'mars', 'archival')
        );
    }

    public function test_it_logs_nothing_on_success()
    {
        $this->task_config['anything'] = [];
        $this->logger                  = new TestLogger();
        $this->newSubject()->create('anything');
        $this->assertSame([], $this->logger->records);
    }

    public function test_it_logs_and_throws_on_failure()
    {
        $this->task_config['anything'] = [];
        $this->logger                  = new TestLogger();
        $this->logger                  = new TestLogger();
        $api_exception                 = ApiException::createFromApiResponse(
            'Create task broke!',
            Code::UNKNOWN
        );
        $this->tasks_client            = TasksClientSpy::willThrowOnCreate($api_exception);
        $subject                       = $this->newSubject();
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
            new TaskTypeConfigProvider($this->task_config),
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

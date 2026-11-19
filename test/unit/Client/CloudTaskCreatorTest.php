<?php

declare(strict_types=1);

namespace test\unit\Ingenerator\CloudTasksWrapper\Client;

use DateTimeImmutable;
use Exception;
use Google\ApiCore\ApiException;
use Google\ApiCore\ApiStatus;
use Google\Cloud\Tasks\V2\Client\CloudTasksClient;
use Google\Cloud\Tasks\V2\HttpMethod;
use Google\Cloud\Tasks\V2\Task;
use Google\Protobuf\Timestamp;
use Google\Rpc\Code;
use Ingenerator\CloudTasksWrapper\Client\CloudTaskCreator;
use Ingenerator\CloudTasksWrapper\Client\CloudTasksClientInterface;
use Ingenerator\CloudTasksWrapper\Client\CreateTaskOptions;
use Ingenerator\CloudTasksWrapper\Client\TaskCreationFailedException;
use Ingenerator\CloudTasksWrapper\TestHelpers\TaskTypeConfigStub;
use Iterator;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Psr\Log\Test\TestLogger;

use function iterator_to_array;

final class CloudTaskCreatorTest extends TestCase
{
    protected TasksClientSpy $tasks_client;
    protected TestLogger|NullLogger $logger;

    protected TaskTypeConfigStub $task_config;

    public function testItIsInitialisable(): void
    {
        $this->assertInstanceOf(CloudTaskCreator::class, $this->newSubject());
    }

    #[TestWith([[], ''])]
    #[TestWith([['body' => 'any body'], 'any body'])]
    public function testItAddsBodyAndHeadersIfProvided($opts, $expect_body): void
    {
        $this->newSubject()->create('some-task', new CreateTaskOptions($opts));
        $task = $this->tasks_client->assertCreatedOneTask();
        $this->assertSame($expect_body, $task->getHttpRequest()->getBody(), 'Sets body');
    }

    #[TestWith(['someone@service.com', true, 'someone@service.com'])]
    #[TestWith(['anonymous', false, null])]
    public function testItAddsOidcTokenForTaskUnlessTaskTypeIsConfiguredAnonymous(
        $signer,
        $expect_has_oidc,
        $expect_oidc_email,
    ): void {
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

    #[TestWith([['custom_token_audience' => null], ''])]
    #[TestWith([[], ''])]
    #[TestWith([['custom_token_audience' => 'pick-me!'], 'pick-me!'])]
    public function testItConfiguresCustomAudienceForAuthenticatedTaskIfConfigured($config, $expect): void
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

    /**
     * @return Iterator<string, mixed[]>
     */
    public static function provider_task_url(): Iterator
    {
        yield 'just from config, no query' => [
            [],
            'https://my.handler.foo/something',
        ];
        yield 'from config, with CreateTaskOptions.query' => [
            ['query' => ['id' => 15, 'scope' => 'any']],
            'https://my.handler.foo/something?id=15&scope=any',
        ];
        yield 'with CreateTaskOptions.custom_handler_url and no query' => [
            ['custom_handler_url' => 'https://my-handler.com/whatever'],
            'https://my-handler.com/whatever',
        ];
        yield 'with CreateTaskOptions.custom_handler_url including own query and no query' => [
            ['custom_handler_url' => 'https://my-handler.com/whatever?foo=bar'],
            'https://my-handler.com/whatever?foo=bar',
        ];
        yield 'with CreateTaskOptions.custom_handler_url and CreateTaskOptions.query' => [
            [
                'custom_handler_url' => 'https://my-handler.com/whatever',
                'query' => ['id' => 15, 'scope' => 'any'],
            ],
            'https://my-handler.com/whatever?id=15&scope=any',
        ];
    }

    #[DataProvider('provider_task_url')]
    public function testItSetsTaskToPostToCustomOrConfigUrlOptionallyAddingQueryParams(array $opts, string $expect): void
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

    #[TestWith([[], ''])]
    #[TestWith([['task_id' => 'absad237'], 'absad237'])]
    public function testItAddsTaskNameFromIdIfProvided($opts, $expect_id): void
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
            $expect_name = '';
        }
        $this->assertSame($expect_name, $task->getName());
    }

    #[TestWith([[], []])]
    #[TestWith([['headers' => ['X-SomeThing' => 'This']], ['X-SomeThing' => 'This']])]
    public function testItAddsHttpHeadersIfProvided($opts, $expect): void
    {
        $this->newSubject()->create('some-task', new CreateTaskOptions($opts));
        $task = $this->tasks_client->assertCreatedOneTask();
        $this->assertSame($expect, iterator_to_array($task->getHttpRequest()->getHeaders()));
    }

    /**
     * @return Iterator<int, bool[][]|DateTimeImmutable[][]|int[][]|null[]>
     */
    public static function provider_schedule_time(): Iterator
    {
        $ts = time() + 500;
        yield [[], null];
        yield [
            [
                'schedule_send_after' => DateTimeImmutable::createFromFormat(
                    'U.u',
                    "$ts.123456"
                ),
            ],
            ['seconds' => $ts, 'nanos' => 123456000],
        ];
    }

    #[DataProvider('provider_schedule_time')]
    public function testItAddsTimestampForScheduledTimeIfProvided(array $opts, ?array $expect): void
    {
        $this->newSubject()->create('any-old-job', new CreateTaskOptions($opts));
        $task = $this->tasks_client->assertCreatedOneTask();
        if ($expect === null) {
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

    public function testItSubmitsToTheConfiguredQueueUrlForTheTaskOptions(): void
    {
        $this->task_config = TaskTypeConfigStub::withTaskType(
            'something',
            [
                'queue' => [
                    'project' => 'mine',
                    'location' => 'mars',
                    'name' => 'archival',
                ],
            ]
        );
        $result = $this->newSubject()->create('something');
        $this->assertSame(
            CloudTasksClient::taskName('mine', 'mars', 'archival', 'created-task-name'),
            $result
        );
        $this->tasks_client->assertCreatedOneTaskInQueue(
            CloudTasksClient::queueName('mine', 'mars', 'archival')
        );
    }

    public function testItLogsNothingOnSuccess(): void
    {
        $this->logger = new TestLogger();
        $this->newSubject()->create('anything');
        $this->assertSame([], $this->logger->records);
    }

    public function testItLogsAndThrowsOnFailure(): void
    {
        $this->logger = new TestLogger();
        $api_exception = ApiException::createFromApiResponse(
            'Create task broke!',
            Code::UNKNOWN
        );
        $this->tasks_client = TasksClientSpy::willThrowOnCreate($api_exception);
        $subject = $this->newSubject();
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

    public function testItBubblesAlreadyExistsIfSetToThrowOnDuplicate(): void
    {
        $this->logger = new TestLogger();
        $api_exception = ApiException::createFromApiResponse(
            'Already got it!',
            Code::ALREADY_EXISTS
        );
        $this->tasks_client = TasksClientSpy::willThrowOnCreate($api_exception);
        $subject = $this->newSubject();
        try {
            $subject->create(
                'anything',
                new CreateTaskOptions(['task_id' => 'foobar', 'throw_on_duplicate' => true])
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

    public function testItSwallowsAlreadyExistsIfSetNotToThrowOnDuplicate(): void
    {
        $this->task_config = TaskTypeConfigStub::withTaskType(
            'anything',
            [
                'queue' => ['project' => 'mine', 'location' => 'mars', 'name' => 'archival'],
            ]
        );
        $this->logger = new TestLogger();
        $this->tasks_client = TasksClientSpy::willThrowOnCreate(
            ApiException::createFromApiResponse(
                'Already got it!',
                Code::ALREADY_EXISTS
            )
        );
        $subject = $this->newSubject();
        $result = $subject->create(
            'anything',
            new CreateTaskOptions(['task_id' => 'foobar', 'throw_on_duplicate' => false])
        );
        $this->assertSame(
            CloudTasksClient::taskName('mine', 'mars', 'archival', 'foobar'),
            $result,
            'returns full task name'
        );
        $this->assertSame([], $this->logger->records);
    }

    public function testItProvidesRetryOptionsWhenSendingTask(): void
    {
        $this->task_config = TaskTypeConfigStub::withTaskType(
            'something',
            [
                'create_retry_settings' => [
                    'initialRetryDelayMillis' => 100,
                    'retryDelayMultiplier' => 1.3,
                    'maxRetryDelayMillis' => 10000,
                    'retryableCodes' => [ApiStatus::DEADLINE_EXCEEDED, ApiStatus::UNAVAILABLE],
                    'retriesEnabled' => true,
                ],
            ]
        );
        $this->newSubject()->create('something');
        $this->tasks_client->assertCreatedOneTaskWithOptions(
            [
                'retrySettings' => [
                    'initialRetryDelayMillis' => 100,
                    'retryDelayMultiplier' => 1.3,
                    'maxRetryDelayMillis' => 10000,
                    'retryableCodes' => [ApiStatus::DEADLINE_EXCEEDED, ApiStatus::UNAVAILABLE],
                    'retriesEnabled' => true,
                ],
            ]
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->tasks_client = new TasksClientSpy();
        $this->logger = new NullLogger();
        $this->task_config = TaskTypeConfigStub::withAnyTaskType();
    }

    protected function newSubject(): CloudTaskCreator
    {
        return new CloudTaskCreator(
            $this->tasks_client,
            $this->task_config,
            $this->logger
        );
    }
}

final class TasksClientSpy implements CloudTasksClientInterface
{
    /**
     * @var array<string, mixed[]|Task|string>[]
     */
    private array $created = [];
    private ?Exception $create_will_throw = null;

    public function __construct()
    {
    }

    public static function willThrowOnCreate(Exception $e): static
    {
        $i = new self();
        $i->create_will_throw = $e;

        return $i;
    }

    public function createTask(string $parent, Task $task, array $call_options = []): Task
    {
        if ($this->create_will_throw instanceof Exception) {
            throw $this->create_will_throw;
        }
        $this->created[] = ['parent' => $parent, 'task' => $task, 'args' => $call_options];
        $result = clone $task;
        $result->setName($parent.'/tasks/created-task-name');

        return $result;
    }

    public function assertCreatedOneTask(): Task
    {
        Assert::assertCount(1, $this->created);

        return $this->created[0]['task'];
    }

    public function assertCreatedOneTaskInQueue(string $queue): void
    {
        Assert::assertCount(1, $this->created);
        Assert::assertSame($queue, $this->created[0]['parent']);
    }

    public function assertCreatedOneTaskWithOptions(array $options): void
    {
        Assert::assertCount(1, $this->created);
        Assert::assertSame($options, $this->created[0]['args']);
    }
}

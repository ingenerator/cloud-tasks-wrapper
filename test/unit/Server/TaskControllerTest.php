<?php

declare(strict_types=1);

namespace test\unit\Ingenerator\CloudTasksWrapper\Server;

use GuzzleHttp\Psr7\ServerRequest;
use Ingenerator\CloudTasksWrapper\Server\TaskController;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerChain;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerFactory;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerResult;
use Ingenerator\CloudTasksWrapper\Server\TaskRequest;
use Ingenerator\CloudTasksWrapper\Server\TaskResult\CoreTaskResult;
use Ingenerator\CloudTasksWrapper\Server\TaskResultCodeMapper;
use Ingenerator\CloudTasksWrapper\TestHelpers\Server\ArrayTaskHandlerFactory;
use Ingenerator\CloudTasksWrapper\TestHelpers\Server\TestTaskHandler;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TaskControllerTest extends TestCase
{
    private TaskHandlerChain $chain;

    private TaskHandlerFactory $handler_factory;

    private TaskResultCodeMapper $result_mapper;

    private string $url_pattern = '#^/all-my-tasks/(?P<task_type>.+)$/#';

    public function testItIsInitialisable(): void
    {
        $this->assertInstanceOf(TaskController::class, $this->newSubject());
    }

    public function testItThrowsIfUrlPatternHasNoTaskTypeCaptureGroup(): void
    {
        $this->url_pattern = '#/all-my-tasks(.+)#';
        $subject = $this->newSubject();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('task_type');
        $subject->handle(new ServerRequest('POST', '/all-my-tasks/anything'));
    }

    public function testItThrowsIfUrlDoesNotMatchUrlPattern(): void
    {
        $this->url_pattern = '#/all-my-tasks/(?P<task_type>.+)$#';
        $subject = $this->newSubject();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('does not match');
        $subject->handle(new ServerRequest('POST', '/not-a-task'));
    }

    public function testItReturns404IfTaskHandlerFactoryThrowsUnknownTask(): void
    {
        $this->url_pattern = '#^/_tasks/(?P<task_type>.+)$#';
        $this->handler_factory = new ArrayTaskHandlerFactory(['any' => TestTaskHandler::neverCalled()]);
        $response = $this->newSubject()->handle(
            new ServerRequest('POST', 'https://any.thing/_tasks/some-task')
        );
        $this->assertSame(
            [
                'code' => CoreTaskResult::HANDLER_NOT_FOUND,
                'msg' => 'No task handler for `some-task`',
                'log_context' => [],
            ],
            $response->getResult()->toArray()
        );
    }

    public function testItCreatesAndRunsTaskHandlerForTypeParsedFromUrl(): void
    {
        $this->url_pattern = '#^/_tasks/(?P<task_type>.+)$#';
        $this->handler_factory = new ArrayTaskHandlerFactory(
            [
                'this-task' => new TestTaskHandler(
                    fn (TaskRequest $req): TaskHandlerResult => CoreTaskResult::success($req->getTaskType())
                ),
                'other-task' => TestTaskHandler::neverCalled(),
            ]
        );

        $response = $this->newSubject()->handle(
            new ServerRequest('POST', 'https://any.host/_tasks/this-task?foo=bar&baz=bil')
        );

        $this->assertSame(
            [
                'code' => CoreTaskResult::SUCCESS,
                'msg' => 'this-task',
                'log_context' => [],
            ],
            $response->getResult()->toArray()
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->chain = new TaskHandlerChain();
        $this->handler_factory = new ArrayTaskHandlerFactory([]);
        $this->result_mapper = new TaskResultCodeMapper();
    }

    protected function newSubject(): TaskController
    {
        return new TaskController(
            $this->chain,
            $this->handler_factory,
            $this->result_mapper,
            $this->url_pattern
        );
    }
}

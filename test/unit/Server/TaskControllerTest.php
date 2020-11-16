<?php


namespace test\unit\Ingenerator\CloudTasksWrapper\Server;


use GuzzleHttp\Psr7\ServerRequest;
use Ingenerator\CloudTasksWrapper\Server\TaskController;
use Ingenerator\CloudTasksWrapper\Server\TaskHandler;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerChain;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerFactory;
use Ingenerator\CloudTasksWrapper\Server\TaskRequest;
use Ingenerator\CloudTasksWrapper\Server\TaskResult\CoreTaskResult;
use Ingenerator\CloudTasksWrapper\Server\TaskResultCodeMapper;
use Ingenerator\CloudTasksWrapper\TestHelpers\Server\TestTaskHandler;
use PHPUnit\Framework\TestCase;

class TaskControllerTest extends TestCase
{

    private TaskHandlerChain $chain;

    private TaskHandlerFactory $handler_factory;

    private TaskResultCodeMapper $result_mapper;

    private string $url_pattern = '#^/all-my-tasks/(?P<task_type>.+)$/#';

    public function test_it_is_initialisable()
    {
        $this->assertInstanceOf(TaskController::class, $this->newSubject());
    }

    public function test_it_throws_if_url_pattern_has_no_task_type_capture_group()
    {
        $this->url_pattern = '#/all-my-tasks(.+)#';
        $subject           = $this->newSubject();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('task_type');
        $subject->handle(new ServerRequest('POST', '/all-my-tasks/anything'));
    }

    public function test_it_throws_if_url_does_not_match_url_pattern()
    {
        $this->url_pattern = '#/all-my-tasks/(?P<task_type>.+)$#';
        $subject           = $this->newSubject();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not match');
        $subject->handle(new ServerRequest('POST', '/not-a-task'));
    }

    public function test_it_creates_and_runs_task_handler_for_type_parsed_from_url()
    {
        $this->url_pattern     = '#^/_tasks/(?P<task_type>.+)$#';
        $this->handler_factory = new TaskHandlerFactoryStub(
            [
                'this-task'  => new TestTaskHandler(
                    function (TaskRequest $req) {
                        return CoreTaskResult::success($req->getTaskType());
                    }
                ),
                'other-task' => TestTaskHandler::neverCalled()
            ]
        );

        $response = $this->newSubject()->handle(
            new ServerRequest('POST', 'https://any.host/_tasks/this-task?foo=bar&baz=bil')
        );

        $this->assertSame(
            [
                'code'        => CoreTaskResult::SUCCESS,
                'msg'         => 'this-task',
                'log_context' => []
            ],
            $response->getResult()->toArray()
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->chain           = new TaskHandlerChain;
        $this->handler_factory = new TaskHandlerFactoryStub;
        $this->result_mapper   = new TaskResultCodeMapper;
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

class TaskHandlerFactoryStub implements TaskHandlerFactory
{
    /**
     * @var \Ingenerator\CloudTasksWrapper\Server\TaskHandler[]
     */
    private array $handlers;

    /**
     * @param TaskHandler[] $handlers
     */
    public function __construct(array $handlers = [])
    {
        $this->handlers = $handlers;
    }

    public function getHandler(string $task_type): TaskHandler
    {
        if (isset($this->handlers[$task_type])) {
            return $this->handlers[$task_type];
        }

        throw new \InvalidArgumentException('No handler defined for task type '.$task_type);
    }

}


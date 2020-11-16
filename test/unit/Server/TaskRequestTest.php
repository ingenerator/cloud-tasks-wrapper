<?php


namespace test\unit\Ingenerator\CloudTasksWrapper\Server;


use GuzzleHttp\Psr7\ServerRequest;
use Ingenerator\CloudTasksWrapper\Server\CloudTaskCannotBeValidException;
use Ingenerator\CloudTasksWrapper\Server\TaskRequest;
use Ingenerator\PHPUtils\DateTime\DateString;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

class TaskRequestTest extends TestCase
{
    protected ServerRequestInterface $http_req;
    protected string $type = 'foo-task';

    public function test_it_is_initialisable()
    {
        $this->assertInstanceOf(TaskRequest::class, $this->newSubject());
    }

    public function test_it_provides_complete_task_url_as_string()
    {
        $this->http_req = new ServerRequest('POST', 'https://my.app/task?foo=bar');
        $this->assertSame(
            'https://my.app/task?foo=bar',
            $this->newSubject()->getFullUrl()
        );
    }

    /**
     * @testWith [{"X-CloudTasks-TaskETA":"1501912403.555197"}, "2017-08-05T06:53:23.555197+01:00"]
     *           [{}, null]
     */
    public function test_it_provides_cloud_tasks_eta_as_datetime_or_null($hdrs, $expect)
    {
        $dtz = \date_default_timezone_get();
        try {
            \date_default_timezone_set('Europe/London');

            $this->http_req = new ServerRequest('POST', 'https://my.app/task?foo=bar', $hdrs);
            $this->assertSame(
                $expect,
                DateString::isoMS($this->newSubject()->getScheduledTime(), NULL)
            );
        } finally {
            \date_default_timezone_set($dtz);
        }
    }

    public function test_it_provides_queue_name()
    {
        $this->http_req = new ServerRequest(
            'POST',
            'https://my.app/task?foo=bar',
            [
                'X-CloudTasks-QueueName' => 'my-queue'
            ]
        );
        $this->assertSame('my-queue', $this->newSubject()->getQueueName());
    }

    public function test_it_provides_task_name()
    {
        $this->http_req = new ServerRequest(
            'POST',
            'https://my.app/task?foo=bar',
            [
                'X-CloudTasks-TaskName' => 'task12472315223'
            ]
        );
        $this->assertSame('task12472315223', $this->newSubject()->getTaskName());
    }

    public function test_it_provides_retry_count()
    {
        $this->http_req = new ServerRequest(
            'POST',
            'https://my.app/task?foo=bar',
            [
                'X-CloudTasks-TaskRetryCount' => '5'
            ]
        );
        $this->assertSame(5, $this->newSubject()->getRetryCount());
    }

    public function test_it_provides_execution_count()
    {
        $this->http_req = new ServerRequest(
            'POST',
            'https://my.app/task?foo=bar',
            [
                'X-CloudTasks-TaskExecutionCount' => '3'
            ]
        );
        $this->assertSame(3, $this->newSubject()->getExecutionCount());
    }

    /**
     * @testWith [{"X-CloudTasks-TaskPreviousResponse": "499"}, "499"]
     *           [{}, null]
     */
    public function test_it_provides_previous_response_if_present($hdrs, $expect)
    {
        $this->http_req = new ServerRequest(
            'POST',
            'https://my.app/task?foo=bar',
            $hdrs
        );
        $this->assertSame($expect, $this->newSubject()->getPreviousResponse());
    }

    /**
     * @testWith [{"X-CloudTasks-TaskRetryReason": "didn't work before"}, "didn't work before"]
     *           [{}, null]
     */
    public function test_it_provides_retry_reason_if_present($hdrs, $expect)
    {
        $this->http_req = new ServerRequest(
            'POST',
            'https://my.app/task?foo=bar',
            $hdrs
        );
        $this->assertSame($expect, $this->newSubject()->getRetryReason());
    }

    public function test_its_require_query_provides_value_if_present()
    {
        $this->http_req = $this->http_req->withQueryParams(['foo' => 'bar']);
        $this->assertSame('bar', $this->newSubject()->requireQueryParam('foo'));
    }

    /**
     * @testWith [[]]
     *           [{"something":"else"}]
     *           [{"foo":""}]
     */
    public function test_its_require_query_throws_if_value_missing_or_empty($vars)
    {
        $this->http_req = $this->http_req->withQueryParams($vars);
        $subject        = $this->newSubject();
        $this->expectException(CloudTaskCannotBeValidException::class);
        $subject->requireQueryParam('foo');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->http_req = new ServerRequest('POST', 'http://foo.bar.com/anything');
    }


    protected function newSubject(): TaskRequest
    {
        return new TaskRequest($this->http_req, $this->type);
    }

}

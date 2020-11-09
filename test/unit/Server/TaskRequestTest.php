<?php


namespace test\unit\Ingenerator\CloudTasksWrapper\Server;


use GuzzleHttp\Psr7\ServerRequest;
use Ingenerator\CloudTasksWrapper\Server\TaskRequest;
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

<?php

declare(strict_types=1);

namespace test\unit\Ingenerator\CloudTasksWrapper\Server;

use GuzzleHttp\Psr7\ServerRequest;
use Ingenerator\CloudTasksWrapper\Server\CloudTaskCannotBeValidException;
use Ingenerator\CloudTasksWrapper\Server\TaskRequest;
use Ingenerator\PHPUtils\DateTime\DateString;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

use function date_default_timezone_get;
use function date_default_timezone_set;

final class TaskRequestTest extends TestCase
{
    protected ServerRequestInterface $http_req;
    protected string $type = 'foo-task';

    public function testItIsInitialisable(): void
    {
        $this->assertInstanceOf(TaskRequest::class, $this->newSubject());
    }

    public function testItProvidesCompleteTaskUrlAsString(): void
    {
        $this->http_req = new ServerRequest('POST', 'https://my.app/task?foo=bar');
        $this->assertSame(
            'https://my.app/task?foo=bar',
            $this->newSubject()->getFullUrl()
        );
    }

    #[TestWith([['X-CloudTasks-TaskETA' => '1501912403.555197'], '2017-08-05T06:53:23.555197+01:00'])]
    #[TestWith([[], null])]
    public function testItProvidesCloudTasksEtaAsDatetimeOrNull($hdrs, $expect): void
    {
        $dtz = date_default_timezone_get();
        try {
            date_default_timezone_set('Europe/London');

            $this->http_req = new ServerRequest('POST', 'https://my.app/task?foo=bar', $hdrs);
            $this->assertSame(
                $expect,
                DateString::isoMS($this->newSubject()->getScheduledTime(), null)
            );
        } finally {
            date_default_timezone_set($dtz);
        }
    }

    public function testItProvidesQueueName(): void
    {
        $this->http_req = new ServerRequest(
            'POST',
            'https://my.app/task?foo=bar',
            [
                'X-CloudTasks-QueueName' => 'my-queue',
            ]
        );
        $this->assertSame('my-queue', $this->newSubject()->getQueueName());
    }

    public function testItProvidesTaskName(): void
    {
        $this->http_req = new ServerRequest(
            'POST',
            'https://my.app/task?foo=bar',
            [
                'X-CloudTasks-TaskName' => 'task12472315223',
            ]
        );
        $this->assertSame('task12472315223', $this->newSubject()->getTaskName());
    }

    public function testItProvidesRetryCount(): void
    {
        $this->http_req = new ServerRequest(
            'POST',
            'https://my.app/task?foo=bar',
            [
                'X-CloudTasks-TaskRetryCount' => '5',
            ]
        );
        $this->assertSame(5, $this->newSubject()->getRetryCount());
    }

    public function testItProvidesExecutionCount(): void
    {
        $this->http_req = new ServerRequest(
            'POST',
            'https://my.app/task?foo=bar',
            [
                'X-CloudTasks-TaskExecutionCount' => '3',
            ]
        );
        $this->assertSame(3, $this->newSubject()->getExecutionCount());
    }

    #[TestWith([['X-CloudTasks-TaskPreviousResponse' => '499'], '499'])]
    #[TestWith([[], null])]
    public function testItProvidesPreviousResponseIfPresent($hdrs, $expect): void
    {
        $this->http_req = new ServerRequest(
            'POST',
            'https://my.app/task?foo=bar',
            $hdrs
        );
        $this->assertSame($expect, $this->newSubject()->getPreviousResponse());
    }

    #[TestWith([['X-CloudTasks-TaskRetryReason' => "didn't work before"], "didn't work before"])]
    #[TestWith([[], null])]
    public function testItProvidesRetryReasonIfPresent($hdrs, $expect): void
    {
        $this->http_req = new ServerRequest(
            'POST',
            'https://my.app/task?foo=bar',
            $hdrs
        );
        $this->assertSame($expect, $this->newSubject()->getRetryReason());
    }

    #[TestWith([[], null])]
    #[TestWith([['foo' => 'bar'], null])]
    #[TestWith([['param' => 'this'], 'this'])]
    public function testItsOptionalQueryProvidesValueOrNull($vars, $expect): void
    {
        $this->http_req = $this->http_req->withQueryParams($vars);
        $this->assertSame($expect, $this->newSubject()->optionalQueryParam('param'));
    }

    public function testItsRequireQueryProvidesValueIfPresent(): void
    {
        $this->http_req = $this->http_req->withQueryParams(['foo' => 'bar']);
        $this->assertSame('bar', $this->newSubject()->requireQueryParam('foo'));
    }

    #[TestWith([[]])]
    #[TestWith([['something' => 'else']])]
    #[TestWith([['foo' => '']])]
    public function testItsRequireQueryThrowsIfValueMissingOrEmpty($vars): void
    {
        $this->http_req = $this->http_req->withQueryParams($vars);
        $subject = $this->newSubject();
        $this->expectException(CloudTaskCannotBeValidException::class);
        $subject->requireQueryParam('foo');
    }

    public function testItsRequireBodyFieldReturnsValue(): void
    {
        $this->http_req = $this->http_req->withParsedBody(['some_payload_field' => 'My value']);
        $subject = $this->newSubject();
        $this->assertSame('My value', $subject->requireBodyField('some_payload_field'));
    }

    #[TestWith([null])]
    #[TestWith([[]])]
    #[TestWith([['other' => 'things']])]
    #[TestWith([['anything' => null]])]
    #[TestWith([['anything' => '']])]
    public function testItsRequireBodyFieldThrowsIfValueMissingOrEmpty($body): void
    {
        $this->http_req = $this->http_req->withParsedBody($body);
        $subject = $this->newSubject();
        $this->expectException(CloudTaskCannotBeValidException::class);
        $subject->requireBodyField('anything');
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

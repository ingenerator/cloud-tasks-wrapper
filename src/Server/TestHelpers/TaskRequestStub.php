<?php


namespace Ingenerator\CloudTasksWrapper\Server\TestHelpers;


use GuzzleHttp\Psr7\ServerRequest;
use Ingenerator\CloudTasksWrapper\Server\TaskRequest;

class TaskRequestStub extends TaskRequest
{
    public static function any(): TaskRequest
    {
        return new TaskRequest(
            new ServerRequest('POST', 'http://foo.bar/anything'),
            'some-task'
        );
    }

}

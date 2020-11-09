<?php


namespace Ingenerator\CloudTasksWrapper\Server\TestHelpers;


use GuzzleHttp\Psr7\ServerRequest;
use Ingenerator\CloudTasksWrapper\Server\TaskRequest;
use Ingenerator\PHPUtils\ArrayHelpers\AssociativeArrayUtils;

class TaskRequestStub extends TaskRequest
{
    public static function any(): TaskRequest
    {
        return new TaskRequest(
            new ServerRequest('POST', 'http://foo.bar/anything'),
            'some-task'
        );
    }

    public static function with(array $options)
    {
        $defaults = [
            'method'    => 'POST',
            'url'       => 'http://foo.bar/anything',
            'task_type' => 'some-task',
            'headers'   => []
        ];
        $options  = AssociativeArrayUtils::deepMerge($defaults, $options);

        return new TaskRequest(
            new ServerRequest(
                $options['method'],
                $options['url'],
                $options['headers']
            ),
            $options['task_type']
        );
    }

    public static function withAuthToken(string $token = 'abc1234')
    {
        return static::with(['headers' => ['Authorization' => 'Bearer '.$token]]);
    }

}

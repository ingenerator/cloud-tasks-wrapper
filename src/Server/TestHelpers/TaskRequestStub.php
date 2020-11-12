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
            'query'     => [],
            'task_type' => 'some-task',
            'headers'   => [],
        ];
        $options  = AssociativeArrayUtils::deepMerge($defaults, $options);

        $request = new ServerRequest(
            $options['method'],
            $options['url'],
            $options['headers']
        );

        return new TaskRequest(
            $request->withQueryParams($options['query']),
            $options['task_type']
        );
    }

    public static function withAuthToken(string $token = 'abc1234')
    {
        return static::with(['headers' => ['Authorization' => 'Bearer '.$token]]);
    }

    public static function withQuery(array $query)
    {
        return static::with(['query' => $query]);
    }

}

<?php


namespace Ingenerator\CloudTasksWrapper\TestHelpers\Server;


use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Utils;
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
            'method'       => 'POST',
            'url'          => 'http://foo.bar/anything',
            'query'        => [],
            'task_type'    => 'some-task',
            'headers'      => [],
            'caller_email' => NULL,
            'body'         => NULL,
            'parsed_body'  => NULL,
        ];
        $options  = AssociativeArrayUtils::deepMerge($defaults, $options);

        $request = new ServerRequest(
            $options['method'],
            $options['url'],
            $options['headers']
        );
        $request = $request->withQueryParams($options['query']);
        if (isset($options['parsed_body'])) {
            $request = $request->withParsedBody($options['parsed_body']);
        }
        if (isset($options['body'])) {
            $request = $request->withBody(Utils::streamFor($options['body']));
        }

        $req               = new TaskRequest($request, $options['task_type']);
        $req->caller_email = $options['caller_email'];

        return $req;
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

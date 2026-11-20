<?php

namespace Ingenerator\CloudTasksWrapper\TestHelpers\Server;

use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Utils;
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

    /**
     * @param array{
     *     method?: string,
     *     url?: string,
     *     query?: array<string, mixed>,
     *     task_type?: string,
     *     headers?: array<string, string|array<string,string>>,
     *     caller_email?: string|null,
     *     body?: string|null,
     *     parsed_body?: array<mixed>|null,
     * } $options
     */
    public static function with(array $options): TaskRequest
    {
        $options = [
            'method' => 'POST',
            'url' => 'http://foo.bar/anything',
            'query' => [],
            'task_type' => 'some-task',
            'headers' => [],
            'caller_email' => null,
            'body' => null,
            'parsed_body' => null,
            ...$options,
        ];

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

        $req = new TaskRequest($request, $options['task_type']);
        $req->caller_email = $options['caller_email'];

        return $req;
    }

    public static function withAuthToken(string $token = 'abc1234'): TaskRequest
    {
        return static::with(['headers' => ['Authorization' => 'Bearer '.$token]]);
    }

    /**
     * @param array<string, mixed> $query
     */
    public static function withQuery(array $query): TaskRequest
    {
        return static::with(['query' => $query]);
    }
}

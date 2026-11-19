<?php

namespace test\unit\Ingenerator\CloudTasksWrapper\Server\Middleware;

use Ingenerator\CloudTasksWrapper\Server\Middleware\JsonBodyParsingMiddleware;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerMiddleware;
use Ingenerator\CloudTasksWrapper\Server\TaskRequest;
use Ingenerator\CloudTasksWrapper\Server\TaskResult\ArbitraryTaskResult;
use Ingenerator\CloudTasksWrapper\Server\TaskResult\CoreTaskResult;
use Ingenerator\CloudTasksWrapper\TestHelpers\Server\TaskRequestStub;
use Ingenerator\CloudTasksWrapper\TestHelpers\Server\TestTaskChain;
use PHPUnit\Framework\TestCase;

class JsonBodyParsingMiddlewareTest extends TestCase
{
    public function testItIsInitialisable()
    {
        $this->assertInstanceOf(JsonBodyParsingMiddleware::class, $this->newSubject());
        $this->assertInstanceOf(TaskHandlerMiddleware::class, $this->newSubject());
    }

    public function testItReturnsUnmodifiedResultFromNextHandler()
    {
        $expect = new ArbitraryTaskResult('someCustomCode');
        $result = $this->newSubject()->process(
            TaskRequestStub::any(),
            TestTaskChain::withFixedResult($expect)
        );
        $this->assertSame($expect, $result);
    }

    public function testItsParsedBodyIsUnmodifiedWhenNotJson()
    {
        $parsed_body = ['something not to be messed with'];
        $request = TaskRequestStub::with([
            'headers' => ['Content-Type' => 'text/plain'],
            'parsed_body' => $parsed_body,
        ]);

        // store http request before we call process()
        $http_req = $request->getHttpRequest();

        $result = $this->newSubject()->process(
            $request,
            TestTaskChain::will(
                function (TaskRequest $request) use ($parsed_body) {
                    // assert here to test parsed body is correct on the way in
                    $this->assertSame($parsed_body, $request->getHttpRequest()->getParsedBody());

                    return new ArbitraryTaskResult('OK');
                }
            )
        );

        // assert http req is the same as before
        $this->assertSame($http_req, $request->getHttpRequest());
        $this->assertSame(
            [
                'code' => 'OK',
                'msg' => 'OK',
                'log_context' => [],
            ],
            $result->toArray()
        );
    }

    public function testItParsesJsonBody()
    {
        $request = TaskRequestStub::with([
            'headers' => ['Content-Type' => 'application/json'],
            'body' => '{"A":[1,2,3]}',
        ]);

        $result = new ArbitraryTaskResult('OK');
        $actual_result = $this->newSubject()->process(
            $request,
            TestTaskChain::will(
                function (TaskRequest $request) use ($result) {
                    $this->assertSame(
                        ['A' => [1, 2, 3]],
                        $request->getHttpRequest()->getParsedBody(),
                        'Parsed body should be decoded within chain'
                    );

                    return $result;
                }
            )
        );

        $this->assertSame(['A' => [1, 2, 3]], $request->getHttpRequest()->getParsedBody());
        $this->assertSame('{"A":[1,2,3]}', $request->getHttpRequest()->getBody()->getContents());
        $this->assertSame($result, $actual_result);
    }

    public function testItReturnsCouldNotBeValidResultWithInvalidJson()
    {
        $request = TaskRequestStub::with([
            'headers' => ['Content-Type' => 'application/json'],
            'body' => '{Invalid JSON}',
        ]);
        $result = $this->newSubject()->process($request, TestTaskChain::nextNeverCalled())->toArray();
        $this->assertNull($request->getHttpRequest()->getParsedBody());
        $this->assertSame(CoreTaskResult::CANNOT_BE_VALID, $result['code']);
        $this->assertSame('Cannot be valid: Could not decode JSON body', $result['msg']);
    }

    protected function newSubject(): JsonBodyParsingMiddleware
    {
        return new JsonBodyParsingMiddleware();
    }
}

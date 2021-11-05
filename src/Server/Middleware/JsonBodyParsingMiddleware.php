<?php
/**
 * @author    Craig Gosman <craig@ingenerator.com>
 * @licence   BSD-3-Clause
 */

namespace Ingenerator\CloudTasksWrapper\Server\Middleware;

use Ingenerator\CloudTasksWrapper\Server\CloudTaskCannotBeValidException;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerChain;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerMiddleware;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerResult;
use Ingenerator\CloudTasksWrapper\Server\TaskRequest;
use Ingenerator\CloudTasksWrapper\Server\TaskResult\CoreTaskResult;
use Ingenerator\PHPUtils\StringEncoding\InvalidJSONException;
use Ingenerator\PHPUtils\StringEncoding\JSON;

class JsonBodyParsingMiddleware implements TaskHandlerMiddleware
{

    public function process(TaskRequest $request, TaskHandlerChain $chain): TaskHandlerResult
    {
        $http_req = $request->getHttpRequest();
        if ($http_req->getHeaderLine('Content-Type') === 'application/json') {
            try {
                $body = $http_req->getBody()->getContents();
                // rewind the body seeing getContents() leaves the marker at the end
                $request->getHttpRequest()->getBody()->rewind();
                $request->setRequestParsedBody(JSON::decode($body));
            } catch (InvalidJSONException $e) {
                return CoreTaskResult::cannotBeValid(
                    new CloudTaskCannotBeValidException('Could not decode JSON body', 0, $e)
                );
            }
        }

        return $chain->nextHandler($request);
    }

}

<?php

namespace Ingenerator\CloudTasksWrapper\Server\Middleware;

use Ingenerator\CloudTasksWrapper\Server\TaskHandlerChain;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerMiddleware;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerResult;
use Ingenerator\CloudTasksWrapper\Server\TaskRequest;
use Ingenerator\CloudTasksWrapper\Server\TaskResult\CoreTaskResult;
use Ingenerator\CloudTasksWrapper\TaskTypeConfigProvider;
use Ingenerator\OIDCTokenVerifier\TokenConstraints;
use Ingenerator\OIDCTokenVerifier\TokenVerificationResult;
use Ingenerator\OIDCTokenVerifier\TokenVerifier;

use function preg_match;
use function sprintf;

class TaskRequestAuthenticatingMiddleware implements TaskHandlerMiddleware
{
    public function __construct(protected TaskTypeConfigProvider $task_type_config, protected TokenVerifier $token_verifier)
    {
    }

    public function process(TaskRequest $request, TaskHandlerChain $chain): TaskHandlerResult
    {
        $http_req = $request->getHttpRequest();
        $request_method = $http_req->getMethod();
        if ($request_method !== 'POST') {
            // If it's not the right request method it is almost certainly not from us. Send a
            // generic 400 so that whoever is poking it finds that out and so it stands out in logs.
            // If this is Cloud Tasks it will be retried :(
            return CoreTaskResult::badHTTPMethod($request_method);
        }

        $auth = $http_req->getHeaderLine('Authorization');
        if (empty($auth)) {
            return CoreTaskResult::authNotProvided('No `Authorization` header');
        }

        if (!preg_match('/^Bearer (.+)$/', $auth, $matches)) {
            return CoreTaskResult::authInvalid('`Authorization` must be a bearer token');
        }

        $result = $this->verifyTokenForTaskRequest($matches[1], $request);
        if (!$result->isVerified()) {
            return $this->createAuthFailureResult($result);
        }

        // Auth was successful, store the info onto the request object for use elsewhere
        $request->setCallerEmail($result->getPayload()->email ?? null);

        return $chain->nextHandler($request);
    }

    protected function verifyTokenForTaskRequest(string $token, TaskRequest $request): TokenVerificationResult
    {
        $expect_signer = $this
            ->task_type_config
            ->getConfig($request->getTaskType())
            ->getSignerEmail();

        $result = $this->token_verifier->verify(
            $token,
            new TokenConstraints(
                [
                    'audience_path_and_query' => $request->getFullUrl(),
                    'email_exact' => $expect_signer,
                ]
            )
        );

        return $result;
    }

    protected function createAuthFailureResult(TokenVerificationResult $result): TaskHandlerResult
    {
        $failure = $result->getFailure();

        return CoreTaskResult::authInvalid(
            sprintf(
                'Token failed (%s: %s)',
                $failure::class,
                $failure->getMessage()
            ),
            ['exception' => $failure]
        );
    }
}

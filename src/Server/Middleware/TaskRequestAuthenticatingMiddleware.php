<?php


namespace Ingenerator\CloudTasksWrapper\Server\Middleware;


use Ingenerator\CloudTasksWrapper\Server\TaskHandlerChain;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerMiddleware;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerResult;
use Ingenerator\CloudTasksWrapper\Server\TaskRequest;
use Ingenerator\OIDCTokenVerifier\TokenVerifier;

class TaskRequestAuthenticatingMiddleware implements TaskHandlerMiddleware
{
    /**
     * @var \Ingenerator\OIDCTokenVerifier\TokenVerifier
     */
    protected TokenVerifier $token_verifier;

    public function __construct(
        TokenVerifier $oidc_token_verifier
    ) {
        $this->token_verifier = $oidc_token_verifier;
    }

    public function process(TaskRequest $request, TaskHandlerChain $chain): TaskHandlerResult
    {
//        if ( ! $request->isMethod(ServerRequestInterface::POST)) {
//            // If it's not the right request method it is almost certainly not from us. Send a generic 400 so that
//            // whoever is poking it finds that out and so it stands out in logs. If this is Cloud Tasks it will be
//            // retried :(
//            return CoreTaskResult::badHTTPMethod($request->getMethod());
//        }
//
//        $token = $request->getHttpHeader('X-Tokenista');
//
//        if ( ! $token) {
//            // If there is no auth at all then it is almost certainly not from us. Send a generic 403 and show them the
//            // door. If this is Cloud Tasks it will be retried :(
//            return CoreTaskResult::authNotProvided();
//        }
//
//        $validation = $this->tokenista->validate($token, ['url' => $request->getUri()]);
//        if ($validation->isExpired()) {
//            return CoreTaskResult::authExpired(
//                'Token expired at '.$validation->getTokenExpiry()->format(\DateTime::ATOM)
//            );
//        }
//        if ( ! $validation->isValid()) {
//            return CoreTaskResult::authInvalid(
//                'Token invalid: '.implode(', ', $validation->getStatusCodes())
//            );
//        }
//
//        // OK, it's valid, carry on
//        return NULL;
    }


}

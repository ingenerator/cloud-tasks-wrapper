<?php


namespace Ingenerator\CloudTasksWrapper\Server;

use Ingenerator\CloudTasksWrapper\Server\Middleware\TaskLoggingMiddleware;
use Ingenerator\CloudTasksWrapper\Server\TaskResult\CoreTaskResult;
use Ingenerator\OIDCTokenVerifier\TokenVerifier;
use Ingenerator\PHPUtils\Mutex\MutexTimedOutException;
use Ingenerator\PHPUtils\Mutex\MutexWrapper;
use Psr\Http\Message\ServerRequestInterface;

class TaskHandlerWrapper
{

    protected TaskLoggingMiddleware $logger;

    protected MutexWrapper $mutex;

    protected TokenVerifier $token_verifier;

    public function __construct(
        TokenVerifier $oidc_token_verifier,
        MutexWrapper $mutex,
        TaskLoggingMiddleware $logger
    ) {
        $this->token_verifier = $oidc_token_verifier;
        $this->logger         = $logger;
        $this->mutex          = $mutex;
    }

    public function handle(
        ServerRequestInterface $request,
        TaskHandler $handler
    ): TaskResponse {
        try {
            $result = $this->doHandle($request, $handler);
        } catch (CloudTaskCannotBeValidException $e) {
            $result = CoreTaskResult::cannotBeValid($e);
        } catch (\Throwable $e) {
            $result = CoreTaskResult::uncaughtException($e);
        }

        $this->logger->logResult($request, $result, 0);

        return $result;
    }

    protected function doHandle(
        ServerRequestInterface $request,
        TaskHandler $handler
    ): TaskHandlerResult {

    }


}

<?php


namespace Ingenerator\CloudTasksWrapper\Server;

use Ingenerator\OIDCTokenVerifier\TokenVerifier;
use Ingenerator\PHPUtils\Mutex\MutexTimedOutException;
use Ingenerator\PHPUtils\Mutex\MutexWrapper;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

class TaskHandlerWrapper
{

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var MutexWrapper
     */
    protected $mutex;

    /**
     * @var \Ingenerator\OIDCTokenVerifier\TokenVerifier
     */
    protected $token_verifier;

    public function __construct(
        TokenVerifier $oidc_token_verifier,
        MutexWrapper $mutex,
        LoggerInterface $logger
    ) {
        $this->token_verifier = $oidc_token_verifier;
        $this->logger         = $logger;
        $this->mutex          = $mutex;
    }

    public function handle(
        ServerRequestInterface $request,
        TaskHandler $handler
    ): TaskHandlerResponse {
        try {
            $result = $this->doHandle($request, $handler);
        } catch (CloudTaskCannotBeValidException $e) {
            $result = ResponseBuilder::cannotBeValid($e);
        } catch (MutexTimedOutException $e) {
            // No problem, we'll let cloud tasks try again shortly
            // This is logged in the outer wrapper
            return ResponseBuilder::mutexTimeout($e);
        } catch (\Throwable $e) {
            $result = ResponseBuilder::uncaughtException($e);
        }

        // @todo: record a metric

        $this->logger->log(
            $result->getLoglevel(),
            \sprintf('Task: [%s] %s', $result->getCode(), $result->getMsg()),
            array_merge(['suppress_trace' => TRUE], $result->getLogContext())
        );

        return $result;
    }

    protected function doHandle(
        ServerRequestInterface $request,
        TaskHandler $handler
    ): TaskHandlerResponse {
        if ( ! $request->isMethod(ServerRequestInterface::POST)) {
            // If it's not the right request method it is almost certainly not from us. Send a generic 400 so that
            // whoever is poking it finds that out and so it stands out in logs. If this is Cloud Tasks it will be
            // retried :(
            return ResponseBuilder::badHTTPMethod($request->getMethod());
        }

        if ($auth_result = $this->authenticateOrBuildFailureResult($request)) {
            return $auth_result;
        }

        // Use a mutex to protect against concurrent deliveries of the same task
        // MD5 is fine for the lock name here, collisions don't matter (they just mean very rarely two different tasks
        // could get queued one behind the other).
        //
        // NB that this assumes that everything required to uniquely ID a given task execution is in the GET
        return $this->mutex->withLock(
            'task-'.\hash('md5', $request->getUri()),
            // The timeout should be short, it's better that we return fast and let cloud tasks reschedule
            // than that we keep lots of blocking locks on our own mysql - NB this entirely independent of
            // the expected execution time of any particular handler
            1,
            function () use ($request, $handler) {
                return $handler->handle($request);
            }
        );
    }

    protected function authenticateOrBuildFailureResult(ServerRequestInterface $request
    ): ?TaskHandlerResponse {
        $token = $request->getHttpHeader('X-Tokenista');

        if ( ! $token) {
            // If there is no auth at all then it is almost certainly not from us. Send a generic 403 and show them the
            // door. If this is Cloud Tasks it will be retried :(
            return ResponseBuilder::authNotProvided();
        }

        $validation = $this->tokenista->validate($token, ['url' => $request->getUri()]);
        if ($validation->isExpired()) {
            return ResponseBuilder::authExpired(
                'Token expired at '.$validation->getTokenExpiry()->format(\DateTime::ATOM)
            );
        }
        if ( ! $validation->isValid()) {
            return ResponseBuilder::authInvalid(
                'Token invalid: '.implode(', ', $validation->getStatusCodes())
            );
        }

        // OK, it's valid, carry on
        return NULL;
    }

}

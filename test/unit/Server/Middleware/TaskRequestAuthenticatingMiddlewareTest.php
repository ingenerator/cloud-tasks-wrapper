<?php

namespace test\unit\Ingenerator\CloudTasksWrapper\Server\Middleware;

use Exception;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Ingenerator\CloudTasksWrapper\Server\Middleware\TaskRequestAuthenticatingMiddleware;
use Ingenerator\CloudTasksWrapper\Server\TaskRequest;
use Ingenerator\CloudTasksWrapper\Server\TaskResult\ArbitraryTaskResult;
use Ingenerator\CloudTasksWrapper\Server\TaskResult\CoreTaskResult;
use Ingenerator\CloudTasksWrapper\TestHelpers\Server\TaskRequestStub;
use Ingenerator\CloudTasksWrapper\TestHelpers\Server\TestTaskChain;
use Ingenerator\CloudTasksWrapper\TestHelpers\TaskTypeConfigStub;
use Ingenerator\OIDCTokenVerifier\TestHelpers\MockTokenVerifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

use function base64_encode;

class TaskRequestAuthenticatingMiddlewareTest extends TestCase
{
    protected MockTokenVerifier $token_verifier;
    protected TaskTypeConfigStub $task_type_config;

    public function testItIsInitialisable()
    {
        $this->assertInstanceOf(
            TaskRequestAuthenticatingMiddleware::class,
            $this->newSubject()
        );
    }

    public function testItReturnsBadMethodIfRequestNotPost()
    {
        $result = $this->newSubject()
            ->process(
                TaskRequestStub::with(['method' => 'GET']),
                TestTaskChain::nextNeverCalled()
            );

        $this->assertSame(
            [
                'code' => CoreTaskResult::BAD_HTTP_METHOD,
                'msg' => '`GET` not accepted',
                'log_context' => [],
            ],
            $result->toArray()
        );
    }

    public function testItReturnsAuthNotPresentIfNoAuthToken()
    {
        $result = $this->newSubject()
            ->process(
                TaskRequestStub::with(['headers' => []]),
                TestTaskChain::nextNeverCalled()
            );

        $this->assertSame(
            [
                'code' => CoreTaskResult::AUTH_NOT_PROVIDED,
                'msg' => 'No `Authorization` header',
                'log_context' => [],
            ],
            $result->toArray()
        );
    }

    public function testItReturnsAuthInvalidIfAuthIsNotBearer()
    {
        $result = $this->newSubject()
            ->process(
                TaskRequestStub::with(
                    [
                        'headers' => ['Authorization' => 'Basic '.base64_encode('open:sesame')],
                    ]
                ),
                TestTaskChain::nextNeverCalled()
            );

        $this->assertSame(
            [
                'code' => CoreTaskResult::AUTH_INVALID,
                'msg' => '`Authorization` must be a bearer token',
                'log_context' => [],
            ],
            $result->toArray()
        );
    }

    public function testItValidatesTokenWithTaskTypeSignerEmailAndTaskUrlAsAudiencePathAndQuery()
    {
        $this->task_type_config = TaskTypeConfigStub::withTaskType(
            'my-custom-task',
            ['signer_email' => 'foo@bar.serviceaccount.com']
        );
        $this->token_verifier = MockTokenVerifier::willFailWith(
            new Exception('Anything')
        );
        $this->newSubject()
            ->process(
                TaskRequestStub::with(
                    [
                        'url' => 'http://foo.bar.com/_task?foo=bar&data=some%20thing',
                        'headers' => ['Authorization' => 'Bearer abc215.1242121asd2.ad7215724'],
                        'task_type' => 'my-custom-task',
                    ]
                ),
                TestTaskChain::nextNeverCalled()
            );

        $this->token_verifier->assertVerifiedOnce(
            'abc215.1242121asd2.ad7215724',
            [
                'audience_path_and_query' => 'http://foo.bar.com/_task?foo=bar&data=some%20thing',
                'email_exact' => 'foo@bar.serviceaccount.com',
            ]
        );
    }

    public static function provider_auth_failures()
    {
        return [
            [
                new SignatureInvalidException('Sig verification failed'),
                'Token failed ('.SignatureInvalidException::class.': Sig verification failed)',
            ],
            [
                new ExpiredException('Expired token'),
                'Token failed ('.ExpiredException::class.': Expired token)',
            ],
        ];
    }

    #[DataProvider('provider_auth_failures')]
    public function testItReturnsAuthInvalidIfTokenVerificationFails(Throwable $e, $expect_msg)
    {
        $this->token_verifier = MockTokenVerifier::willFailWith($e);
        $result = $this->newSubject()
            ->process(
                TaskRequestStub::withAuthToken(),
                TestTaskChain::nextNeverCalled()
            );

        $this->assertSame(
            [
                'code' => CoreTaskResult::AUTH_INVALID,
                'msg' => $expect_msg,
                'log_context' => ['exception' => $e],
            ],
            $result->toArray()
        );
    }

    public function testItAddsTokenInfoToRequestAndReturnsNextHandlerResultOnSuccess()
    {
        $this->token_verifier = MockTokenVerifier::willSucceedWith(
            ['email' => 'foo@some.iam.gserviceaccount.com']
        );
        $request = TaskRequestStub::withAuthToken();
        $this->assertSame(null, $request->getCallerEmail(), 'Has no caller email before');

        $result = $this->newSubject()->process(
            $request,
            TestTaskChain::will(
                fn (TaskRequest $request) => new ArbitraryTaskResult('OK', $request->getCallerEmail())
            )
        );

        $this->assertSame(
            [
                'code' => 'OK',
                'msg' => 'foo@some.iam.gserviceaccount.com',
                'log_context' => [],
            ],
            $result->toArray()
        );

        // It would be neater to make the request fully idempotent *but* we would ideally like to
        // be able to get the user email at higher levels of the middleware stack e.g. to have it
        // in the logging middleware even if that is called before the auth middleware. That means
        // we can't clone it at the auth layer, as the earlier middlewares would then have a
        // different reference.
        $this->assertSame(
            'foo@some.iam.gserviceaccount.com',
            $request->getCallerEmail(),
            'Request has caller email after'
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->task_type_config = TaskTypeConfigStub::withAnyTaskType();
        $this->token_verifier = MockTokenVerifier::notCalled();
    }

    protected function newSubject(): TaskRequestAuthenticatingMiddleware
    {
        return new TaskRequestAuthenticatingMiddleware(
            $this->task_type_config,
            $this->token_verifier
        );
    }
}

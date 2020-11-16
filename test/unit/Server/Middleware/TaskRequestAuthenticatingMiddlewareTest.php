<?php


namespace test\unit\Ingenerator\CloudTasksWrapper\Server\Middleware;


use Firebase\JWT\SignatureInvalidException;
use Ingenerator\CloudTasksWrapper\Server\Middleware\TaskRequestAuthenticatingMiddleware;
use Ingenerator\CloudTasksWrapper\Server\TaskRequest;
use Ingenerator\CloudTasksWrapper\Server\TaskResult\ArbitraryTaskResult;
use Ingenerator\CloudTasksWrapper\Server\TaskResult\CoreTaskResult;
use Ingenerator\CloudTasksWrapper\TestHelpers\TaskTypeConfigStub;
use Ingenerator\CloudTasksWrapper\TestHelpers\Server\TaskRequestStub;
use Ingenerator\CloudTasksWrapper\TestHelpers\Server\TestTaskChain;
use Ingenerator\OIDCTokenVerifier\TokenVerificationResult;
use Ingenerator\OIDCTokenVerifier\TokenVerifier;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

class TaskRequestAuthenticatingMiddlewareTest extends TestCase
{
    protected TokenVerifier $token_verifier;
    protected TaskTypeConfigStub $task_type_config;

    public function test_it_is_initialisable()
    {
        $this->assertInstanceOf(
            TaskRequestAuthenticatingMiddleware::class,
            $this->newSubject()
        );
    }

    public function test_it_returns_bad_method_if_request_not_post()
    {
        $result = $this->newSubject()
            ->process(
                TaskRequestStub::with(['method' => 'GET']),
                TestTaskChain::nextNeverCalled()
            );

        $this->assertSame(
            [
                'code'        => CoreTaskResult::BAD_HTTP_METHOD,
                'msg'         => '`GET` not accepted',
                'log_context' => [],
            ],
            $result->toArray()
        );
    }

    public function test_it_returns_auth_not_present_if_no_auth_token()
    {
        $result = $this->newSubject()
            ->process(
                TaskRequestStub::with(['headers' => []]),
                TestTaskChain::nextNeverCalled()
            );

        $this->assertSame(
            [
                'code'        => CoreTaskResult::AUTH_NOT_PROVIDED,
                'msg'         => 'No `Authorization` header',
                'log_context' => [],
            ],
            $result->toArray()
        );
    }

    public function test_it_returns_auth_invalid_if_auth_is_not_bearer()
    {
        $result = $this->newSubject()
            ->process(
                TaskRequestStub::with(
                    [
                        'headers' => ['Authorization' => 'Basic '.\base64_encode('open:sesame')],
                    ]
                ),
                TestTaskChain::nextNeverCalled()
            );

        $this->assertSame(
            [
                'code'        => CoreTaskResult::AUTH_INVALID,
                'msg'         => '`Authorization` must be a bearer token',
                'log_context' => [],
            ],
            $result->toArray()
        );
    }

    public function test_it_validates_token_using_token_verifier_with_signer_email_for_task_type()
    {
        $this->task_type_config = TaskTypeConfigStub::withTaskType(
            'my-custom-task',
            ['signer_email' => 'foo@bar.serviceaccount.com']
        );
        $this->token_verifier   = TokenVerifierStub::willFailWith(
            new \Exception('Anything')
        );
        $this->newSubject()
            ->process(
                TaskRequestStub::with(
                    [
                        'headers'   => ['Authorization' => 'Bearer abc215.1242121asd2.ad7215724'],
                        'task_type' => 'my-custom-task',
                    ]
                ),
                TestTaskChain::nextNeverCalled()
            );

        $this->token_verifier->assertVerifiedOnce(
            'abc215.1242121asd2.ad7215724',
            [
                'email_exact' => 'foo@bar.serviceaccount.com',
            ]
        );
    }

    public function test_it_returns_auth_invalid_if_token_is_not_properly_signed()
    {
        $exception            = new SignatureInvalidException('Sig verification failed');
        $this->token_verifier = TokenVerifierStub::willFailWith($exception);
        $result               = $this->newSubject()
            ->process(
                TaskRequestStub::withAuthToken(),
                TestTaskChain::nextNeverCalled()
            );

        $this->assertSame(
            [
                'code'        => CoreTaskResult::AUTH_INVALID,
                'msg'         => 'Token failed (Firebase\JWT\SignatureInvalidException: Sig verification failed)',
                'log_context' => ['exception' => $exception],
            ],
            $result->toArray()
        );
    }

    public function test_it_returns_auth_expired_if_auth_token_expired()
    {
        $this->markTestIncomplete();
        // So, we have a bit of a conflict here:
        // - if the token has expired, there is no point retrying
        // - if the token is not yet valid, it's potentially a timing issue and we *should* retry
        // - if there's some arbitrary error (sig validation etc) then it may be that we've got old
        //   or wrong keys and a retry would eventually work
        // - it could also be a deployment issue - e.g. a service has accidentally dropped
        //   permission for a particular service account to call it - and so we'd want to retain
        //   and rery failing tasks until we get a deployment out to authorise them again?
        // - so we may occasionally want to actually retry a task where auth failed, even though in
        //   most cases we'd want to fail it fast.
        // - hmmm
    }

    public function test_it_returns_auth_failed_if_auth_token_not_for_authorised_user()
    {
        $this->markTestIncomplete();
        // So, here we need to provide a mapping of TaskType to the allowed users??
    }

    public function test_it_adds_token_info_to_request_and_returns_next_handler_result_on_success()
    {
        $this->token_verifier = TokenVerifierStub::willSucceedWith(
            ['email' => 'foo@some.iam.gserviceaccount.com']
        );
        $request              = TaskRequestStub::withAuthToken();
        $this->assertSame(NULL, $request->getCallerEmail(), 'Has no caller email before');

        $result = $this->newSubject()->process(
            $request,
            TestTaskChain::will(
                function (TaskRequest $request) {
                    return new ArbitraryTaskResult('OK', $request->getCallerEmail());
                }
            )
        );

        $this->assertSame(
            [
                'code'        => 'OK',
                'msg'         => 'foo@some.iam.gserviceaccount.com',
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
        $this->token_verifier   = TokenVerifierStub::notCalled();
    }

    protected function newSubject(): TaskRequestAuthenticatingMiddleware
    {
        return new TaskRequestAuthenticatingMiddleware(
            $this->task_type_config,
            $this->token_verifier
        );
    }

}

class TokenVerifierStub implements TokenVerifier
{
    protected $calls = [];
    protected ?TokenVerificationResult $result;

    public static function willFailWith(\Exception $e)
    {
        return new static(TokenVerificationResult::createFailure($e));
    }

    public static function willSucceedWith(array $token_props)
    {
        $obj = (object) $token_props;

        return new static(TokenVerificationResult::createSuccess($obj));
    }

    public static function notCalled()
    {
        return new static(NULL);
    }

    protected function __construct(?TokenVerificationResult $result)
    {
        $this->result = $result;
    }

    public function verify(string $token, array $extra_constraints = []): TokenVerificationResult
    {
        $this->calls[] = [$token, $extra_constraints];
        if ($this->result) {
            return $this->result;
        }

        throw new \BadMethodCallException('Unexpected call to '.__METHOD__);
    }

    public function assertVerifiedOnce(string $token, array $extra_constraints = [])
    {
        Assert::assertSame([[$token, $extra_constraints]], $this->calls);
    }

}

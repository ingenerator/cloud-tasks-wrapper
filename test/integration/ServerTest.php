<?php


namespace test\integration;


use Firebase\JWT\JWT;
use Google\Auth\Cache\MemoryCacheItemPool;
use GuzzleHttp\Psr7\ServerRequest;
use Ingenerator\CloudTasksWrapper\Factory\TaskServerFactory;
use Ingenerator\CloudTasksWrapper\Server\TaskHandler;
use Ingenerator\CloudTasksWrapper\Server\TaskResult\ArbitraryTaskResult;
use Ingenerator\CloudTasksWrapper\TestHelpers\Server\ArrayTaskHandlerFactory;
use Ingenerator\PHPUtils\Mutex\MockMutexWrapper;
use Ingenerator\PHPUtils\StringEncoding\JSON;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;

class ServerTest extends TestCase
{

    public function test_it_can_process_successful_tasks()
    {
        $signer_email = 'someone@service.account.test';
        $handler_url  = 'http://my.app/_do_task/do-my-task?person=15&option=something';
        $controller   = TaskServerFactory::makeController(
            new NullLogger,
            new MockMutexWrapper,
            new MemoryCacheItemPool,
            new ArrayTaskHandlerFactory(['do-my-task' => $this->makeTestHandler()]),
            [
                // Task type config - NB most options not relevant
                'do-my-task' => ['signer_email' => $signer_email],
            ],
            [
                // Server config - NB allowing our custom OIDC endpoint instead of the default google one
                'token_issuer' => 'https://ingenerator.github.io/oidc-auth-sandbox',
                'result_map'   => [
                    'customTaskResult' => [
                        'http_status' => 295,
                        'loglevel'    => LogLevel::INFO,
                    ],
                ],

            ]
        );
        $response     = $controller->handle($this->makeServerRequest($handler_url, $signer_email));
        $this->assertSame(
            [
                'code'        => 'customTaskResult',
                'msg'         => 'Got task from '.$signer_email,
                'log_context' => [
                    'task_type' => 'do-my-task',
                    'query'     => [
                        'person'  => '15',
                        'option'  => 'something',
                        'bespoke' => NULL,
                    ],
                ],

            ],
            $response->getResult()->toArray(),
            'Task result matches'
        );
        $this->assertSame(295, $response->getStatusCode(), 'Correct HTTP status');
        $this->assertSame('CustomTaskResult', $response->getStatusCodeName(), 'Correct HTTP status name');
    }

    private function makeTestHandler()
    {
        return new class implements TaskHandler {

            public function handle(
                \Ingenerator\CloudTasksWrapper\Server\TaskRequest $request
            ): \Ingenerator\CloudTasksWrapper\Server\TaskHandlerResult {
                return new ArbitraryTaskResult(
                    'customTaskResult',
                    'Got task from '.$request->getCallerEmail(),
                    [
                        'task_type' => $request->getTaskType(),
                        'query'     => [
                            'person'  => $request->requireQueryParam('person'),
                            'option'  => $request->optionalQueryParam('option'),
                            'bespoke' => $request->optionalQueryParam('bespoke'),
                        ],
                    ]
                );
            }

        };
    }

    private function makeAuthToken(string $audience, string $email)
    {
        $keys = JSON::decodeArray(
            \file_get_contents('https://ingenerator.github.io/oidc-auth-sandbox/jwks-private-keys.json')
        );
        $key  = $keys['keys'][0];

        return JWT::encode(
            [
                'aud'            => $audience,
                'email'          => $email,
                'email_verified' => TRUE,
                'exp'            => time() + 30,
                'iat'            => time(),
                'iss'            => 'https://ingenerator.github.io/oidc-auth-sandbox',
            ],
            $key['private_key'],
            'RS256',
            $key['kid']
        );
    }

    /**
     * @param string $handler_url
     * @param string $signer_email
     *
     * @return ServerRequest|\Psr\Http\Message\ServerRequestInterface
     */
    private function makeServerRequest(string $handler_url, string $signer_email)
    {
        $req   = new ServerRequest(
            'POST',
            $handler_url,
            [
                'Authorization' => 'Bearer '.$this->makeAuthToken($handler_url, $signer_email),
            ]
        );
        $query = \parse_url($handler_url, PHP_URL_QUERY);
        \parse_str($query, $query_vars);

        return $req->withQueryParams($query_vars);
    }

}

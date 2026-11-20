<?php

declare(strict_types=1);

namespace test\integration;

use Firebase\JWT\JWT;
use Google\Auth\Cache\MemoryCacheItemPool;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\ServerRequest;
use Ingenerator\CloudTasksWrapper\Factory\TaskServerFactory;
use Ingenerator\CloudTasksWrapper\Server\TaskHandler;
use Ingenerator\CloudTasksWrapper\Server\TaskRequest;
use Ingenerator\CloudTasksWrapper\Server\TaskResult\ArbitraryTaskResult;
use Ingenerator\CloudTasksWrapper\TestHelpers\Server\ArrayTaskHandlerFactory;
use Ingenerator\PHPUtils\Mutex\MockMutexWrapper;
use Ingenerator\PHPUtils\StringEncoding\JSON;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use UnexpectedValueException;

use function parse_str;
use function parse_url;

final class ServerTest extends TestCase
{
    /**
     * @var array{kid: string, private_key: string}
     */
    private static array $jwkPrivateKey;

    /**
     * @return array{kid: string, private_key: string}
     */
    private function fetchTestJwkPrivateKey(): array
    {
        $client = new Client();
        // @todo: We should run a local emulator for integration tests, not rely on public HTTP requests
        $data = $client->get('https://ingenerator.github.io/oidc-auth-sandbox/jwks-private-keys.json');
        $keys = JSON::decodeArray($data->getBody()->getContents());
        if (
            isset($keys['keys'])
            && is_array($keys['keys'])
            && isset($keys['keys'][0])
            && is_array($keys['keys'][0])
            && is_string($keys['keys'][0]['kid'] ?? null)
            && is_string($keys['keys'][0]['private_key'] ?? null)
        ) {
            return $keys['keys'][0];
        }

        throw new UnexpectedValueException('Invalid content from test keyserver URL');
    }

    public function testItCanProcessSuccessfulTasks(): void
    {
        $signer_email = 'someone@service.account.test';
        $handler_url = 'http://my.app/_do_task/do-my-task?person=15&option=something';
        $controller = TaskServerFactory::makeController(
            new NullLogger(),
            new MockMutexWrapper(),
            new MemoryCacheItemPool(),
            new ArrayTaskHandlerFactory(['do-my-task' => $this->makeTestHandler()]),
            [
                // Task type config - NB most options not relevant
                'do-my-task' => ['signer_email' => $signer_email],
            ],
            [
                // Server config - NB allowing our custom OIDC endpoint instead of the default google one
                'token_issuer' => 'https://ingenerator.github.io/oidc-auth-sandbox',
                'result_map' => [
                    'customTaskResult' => [
                        'http_status' => 295,
                        'loglevel' => LogLevel::INFO,
                    ],
                ],
            ]
        );
        $response = $controller->handle($this->makeServerRequest($handler_url, $signer_email));
        $this->assertSame(
            [
                'code' => 'customTaskResult',
                'msg' => 'Got task from '.$signer_email,
                'log_context' => [
                    'task_type' => 'do-my-task',
                    'query' => [
                        'person' => '15',
                        'option' => 'something',
                        'bespoke' => null,
                    ],
                ],
            ],
            $response->getResult()->toArray(),
            'Task result matches'
        );
        $this->assertSame(295, $response->getStatusCode(), 'Correct HTTP status');
        $this->assertSame('CustomTaskResult', $response->getStatusCodeName(), 'Correct HTTP status name');
    }

    private function makeTestHandler(): TaskHandler
    {
        return new class implements TaskHandler {
            public function handle(
                TaskRequest $request,
            ): ArbitraryTaskResult {
                return new ArbitraryTaskResult(
                    'customTaskResult',
                    'Got task from '.$request->getCallerEmail(),
                    [
                        'task_type' => $request->getTaskType(),
                        'query' => [
                            'person' => $request->requireQueryParam('person'),
                            'option' => $request->optionalQueryParam('option'),
                            'bespoke' => $request->optionalQueryParam('bespoke'),
                        ],
                    ]
                );
            }
        };
    }

    private function makeAuthToken(string $audience, string $email): string
    {
        self::$jwkPrivateKey ??= self::fetchTestJwkPrivateKey();
        $key = self::$jwkPrivateKey;

        return JWT::encode(
            [
                'aud' => $audience,
                'email' => $email,
                'email_verified' => true,
                'exp' => time() + 30,
                'iat' => time(),
                'iss' => 'https://ingenerator.github.io/oidc-auth-sandbox',
            ],
            $key['private_key'],
            'RS256',
            $key['kid']
        );
    }

    private function makeServerRequest(string $handler_url, string $signer_email): ServerRequestInterface
    {
        $req = new ServerRequest(
            'POST',
            $handler_url,
            [
                'Authorization' => 'Bearer '.$this->makeAuthToken($handler_url, $signer_email),
            ]
        );
        $query = parse_url($handler_url, PHP_URL_QUERY);
        $this->assertIsString($query, 'Handler URL must contain a query string');
        parse_str($query, $query_vars);

        return $req->withQueryParams($query_vars);
    }
}

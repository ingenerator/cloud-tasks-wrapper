<?php


namespace Ingenerator\CloudTasksWrapper\Factory;


use Ingenerator\CloudTasksWrapper\Server\Middleware\ExceptionCatchingMiddleware;
use Ingenerator\CloudTasksWrapper\Server\Middleware\TaskLoggingMiddleware;
use Ingenerator\CloudTasksWrapper\Server\Middleware\TaskMutexLockingMiddleware;
use Ingenerator\CloudTasksWrapper\Server\Middleware\TaskRequestAuthenticatingMiddleware;
use Ingenerator\CloudTasksWrapper\Server\TaskController;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerChain;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerFactory;
use Ingenerator\CloudTasksWrapper\Server\TaskResultCodeMapper;
use Ingenerator\CloudTasksWrapper\TaskTypeConfigProvider;
use Ingenerator\OIDCTokenVerifier\OIDCTokenVerifier;
use Ingenerator\OIDCTokenVerifier\OpenIDDiscoveryCertificateProvider;
use Ingenerator\PHPUtils\DateTime\Clock\RealtimeClock;
use Ingenerator\PHPUtils\Mutex\MutexWrapper;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

class TaskServerFactory
{
    /**
     * This builds a complete, default, task server stack from just the external dependencies and config.
     *
     * For more advanced customisation you can of course replace this with an equivalent implementation / dependency
     * container config.
     *
     * @param LoggerInterface        $logger
     * @param MutexWrapper           $mutex_wrapper
     * @param CacheItemPoolInterface $certificate_cache
     * @param TaskHandlerFactory     $handler_factory
     * @param array                  $task_types
     * @param array                  $server_config
     *
     * @return TaskController
     */
    public static function makeController(
        LoggerInterface $logger,
        MutexWrapper $mutex_wrapper,
        CacheItemPoolInterface $certificate_cache,
        TaskHandlerFactory $handler_factory,
        array $task_types,
        array $server_config
    ): TaskController {
        $server_config = array_merge(
            [
                'result_map'          => [
                    // This should be a hash of custom task result codes / overrides to the treatment of
                    // CoreTaskResult:: codes.
                    //
                    // e.g.:
                    //
                    // CustomTaskResult::IMAGE_INVALID => [
                    //   // Allocate a specific code to highlight it in your request logs, but 2xx it to mark it does
                    //   // not need to be retried.
                    //   'http_status' => 295,
                    //   // But log as a warning as this is an error case
                    //   'loglevel'    => LogLevel::WARNING
                    // ],
                ],
                // If you're using an emulator in dev / CI you may want to provide a custom token issuer here. Tasks
                // sent by Cloud Tasks will be signed by google.
                'token_issuer'        => 'https://accounts.google.com',
                // See the OpenIDDiscoveryCertificateProvider for full options here
                // Note the code below to allow insecure issuers if you specified an http:// token issuer
                'oidc_cert_options'   => [],
                // The URL pattern should be a regex that can extract the task_type value from the URL. It must capture
                // a `task_type` named parameter as below. Note that the TaskController will throw an
                // \InvalidArgumentException if the URL does not match this pattern.
                // Note there will usually be an overlap between this and your task_types._default.handler_url
                // configuration, but they are separate because it is valid to have a server that is not a client, or a
                // client that is not a server.
                'handler_url_pattern' => '#^/_do_task/(?P<task_type>.+)$#',
            ],
            $server_config
        );

        if (\parse_url($server_config['token_issuer'], PHP_URL_SCHEME) === 'http') {
            // The configured token issuer is http:// - presumably because you are using an emulator in dev / CI.
            // We need to explicitly allow the OpenIDDiscoveryCertificateProvider to fetch these, as by default it
            // requires the discovery and JWKS urls to be https.
            // If for some reason you want to disable this (which will prevent you validating certs) then you can set
            // an explicit FALSE on the array you pass in.
            $server_config['oidc_cert_options']['allow_insecure'] ??= TRUE;
        }

        $result_mapper = new TaskResultCodeMapper($server_config['result_map']);
        $task_types    = new TaskTypeConfigProvider($task_types);

        return new TaskController(
            TaskHandlerChain::makeDefault(
                new TaskLoggingMiddleware(new RealtimeClock, $logger, $result_mapper),
                new ExceptionCatchingMiddleware(),
                new TaskRequestAuthenticatingMiddleware(
                    $task_types,
                    new OIDCTokenVerifier(
                        new OpenIDDiscoveryCertificateProvider(
                            new \GuzzleHttp\Client,
                            $certificate_cache,
                            $logger,
                            $server_config['oidc_cert_options']
                        ),
                        $server_config['token_issuer'],
                    )
                ),
                new TaskMutexLockingMiddleware($mutex_wrapper)
            ),
            $handler_factory,
            $result_mapper,
            $server_config['handler_url_pattern']
        );
    }
}

<?php


namespace Ingenerator\CloudTasksWrapper\Client;


use Google\Cloud\Core\InsecureCredentialsWrapper;
use Google\Cloud\Tasks\V2\CloudTasksClient;
use Grpc\Channel;
use Grpc\ChannelCredentials;
use Psr\Cache\CacheItemPoolInterface;
use RuntimeException;

class CloudTasksClientFactory
{

    public static function makeClient(CacheItemPoolInterface $auth_cache, array $config): CloudTasksClient
    {
        $base_options = [
            'credentialsConfig' => ['authCache' => $auth_cache],
        ];

        if ($config['use_emulator']) {
            if (!class_exists(InsecureCredentialsWrapper::class)) {
                throw new RuntimeException(
                    'You need google/cloud-core >1.44.4 to use a cloud tasks emulator with insecure credentials');
            }
            $options = array_merge(
                $base_options,
                [
                    'apiEndpoint'     => $config['emulator_endpoint'],
                    'credentials'     => $config['credentials'] ?? new InsecureCredentialsWrapper(),
                    'transportConfig' => [
                        'grpc' => [
                            'channel' => new Channel(
                                $config['emulator_endpoint'],
                                ['credentials' => ChannelCredentials::createInsecure()]
                            ),
                        ],
                    ],
                ]
            );
        } else {
            $options = \array_merge(
                $base_options,
                ['credentials' => $config['credentials']]
            );
        }

        return new CloudTasksClient($options);
    }

}

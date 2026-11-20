<?php

declare(strict_types=1);

namespace Ingenerator\CloudTasksWrapper\Client;

use Google\ApiCore\CredentialsWrapper;
use Google\ApiCore\InsecureCredentialsWrapper;
use Google\Auth\FetchAuthTokenInterface;
use Google\Cloud\Tasks\V2\Client\CloudTasksClient;
use Grpc\Channel;
use Grpc\ChannelCredentials;
use InvalidArgumentException;
use Psr\Cache\CacheItemPoolInterface;

class CloudTasksClientFactory
{
    /**
     * @param array{
     *     use_emulator?: bool,
     *     emulator_endpoint?: string,
     *     credentials?: FetchAuthTokenInterface|CredentialsWrapper|null
     * } $config
     */
    public static function makeClient(CacheItemPoolInterface $auth_cache, array $config): CloudTasksClient
    {
        $base_options = [
            'credentialsConfig' => ['authCache' => $auth_cache],
        ];

        if ($config['use_emulator'] ?? false) {
            if (empty($config['emulator_endpoint'])) {
                throw new InvalidArgumentException('emulator_endpoint must be provided when use_emulator is true');
            }

            $options = [
                ...$base_options,
                'apiEndpoint' => $config['emulator_endpoint'],
                'credentials' => $config['credentials'] ?? new InsecureCredentialsWrapper(),
                'transportConfig' => [
                    'grpc' => [
                        'channel' => new Channel(
                            $config['emulator_endpoint'],
                            ['credentials' => ChannelCredentials::createInsecure()]
                        ),
                    ],
                ],
            ];
        } else {
            $options = $base_options;
            if (isset($config['credentials'])) {
                $options['credentials'] = $config['credentials'];
            }
        }

        return new CloudTasksClient($options);
    }
}

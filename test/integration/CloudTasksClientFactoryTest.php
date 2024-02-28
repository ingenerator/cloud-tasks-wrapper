<?php

namespace test\integration;

use Google\ApiCore\CredentialsWrapper;
use Google\Cloud\Tasks\V2\CloudTasksClient;
use Ingenerator\CloudTasksWrapper\Client\CloudTasksClientFactory;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;

class CloudTasksClientFactoryTest extends TestCase
{

    public function test_it_can_create_client_when_called_with_emulator_configuration()
    {
        $cache = $this->getMockBuilder(CacheItemPoolInterface::class)->getMock();
        $client = CloudTasksClientFactory::makeClient($cache, [
            'use_emulator' => true,
            'emulator_endpoint' => 'cloud_tasks_emulator:8123',
            'credentials' => null
        ]);
        $this->assertInstanceOf(CloudTasksClient::class, $client);
    }

    public function test_it_can_create_client_when_called_with_production_configuration()
    {
        $cache = $this->getMockBuilder(CacheItemPoolInterface::class)->getMock();
        $client = CloudTasksClientFactory::makeClient($cache, [
            'use_emulator' => false,
            // NB that the emulator_endpoint is not relevant here, but it doesn't matter
            // if a project still provides it.
            'emulator_endpoint' => 'cloud_tasks_emulator:8123',
            'credentials' => $this->getMockBuilder(CredentialsWrapper::class)->disableOriginalConstructor()->getMock(),
        ]);
        $this->assertInstanceOf(CloudTasksClient::class, $client);
    }
}

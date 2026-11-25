<?php

declare(strict_types=1);

namespace test\integration;

use Google\ApiCore\CredentialsWrapper;
use Google\Cloud\Tasks\V2\Client\CloudTasksClient;
use Ingenerator\CloudTasksWrapper\Client\CloudTasksClientFactory;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;

final class CloudTasksClientFactoryTest extends TestCase
{
    public function testItCanCreateClientWhenCalledWithEmulatorConfiguration(): void
    {
        $cache = $this->createStub(CacheItemPoolInterface::class);
        $client = CloudTasksClientFactory::makeClient($cache, [
            'use_emulator' => true,
            'emulator_endpoint' => 'cloud_tasks_emulator:8123',
            'credentials' => null,
        ]);
        $this->assertInstanceOf(CloudTasksClient::class, $client);
    }

    public function testItThrowsIfConfiguringEmulatorWithoutUrl(): void
    {
        $cache = $this->createStub(CacheItemPoolInterface::class);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('emulator_endpoint must be provided when use_emulator is true');
        CloudTasksClientFactory::makeClient($cache, [
            'use_emulator' => true,
        ]);
    }

    public function testItCanCreateClientWhenCalledWithProductionConfiguration(): void
    {
        $cache = $this->createStub(CacheItemPoolInterface::class);
        $client = CloudTasksClientFactory::makeClient($cache, [
            'use_emulator' => false,
            // NB that the emulator_endpoint is not relevant here, but it doesn't matter
            // if a project still provides it.
            'emulator_endpoint' => 'cloud_tasks_emulator:8123',
            'credentials' => $this->createStub(CredentialsWrapper::class),
        ]);
        $this->assertInstanceOf(CloudTasksClient::class, $client);
    }
}

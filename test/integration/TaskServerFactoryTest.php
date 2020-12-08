<?php


namespace test\integration;


use Ingenerator\CloudTasksWrapper\Factory\TaskServerFactory;
use Ingenerator\CloudTasksWrapper\Server\TaskController;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerFactory;
use Ingenerator\PHPUtils\Mutex\MutexWrapper;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

class TaskServerFactoryTest extends TestCase
{

    public function test_can_create_server_stack()
    {
        $controller = TaskServerFactory::makeController(
            $this->createStub(LoggerInterface::class),
            $this->createStub(MutexWrapper::class),
            $this->createStub(CacheItemPoolInterface::class),
            $this->createStub(TaskHandlerFactory::class),
            [],
            []
        );
        $this->assertInstanceOf(TaskController::class, $controller);
    }

}

<?php


namespace test\integration;


use Ingenerator\CloudTasksWrapper\Factory\TaskServerFactory;
use Ingenerator\CloudTasksWrapper\Server\TaskController;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerFactory;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerMiddleware;
use Ingenerator\PHPUtils\Mutex\MutexWrapper;
use Ingenerator\PHPUtils\Object\ObjectPropertyRipper;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use function array_pop;

class TaskServerFactoryTest extends TestCase
{

    public function test_can_create_server_stack()
    {
        $mock_middleware = $this->createStub(TaskHandlerMiddleware::class);

        $controller = TaskServerFactory::makeController(
            $this->createStub(LoggerInterface::class),
            $this->createStub(MutexWrapper::class),
            $this->createStub(CacheItemPoolInterface::class),
            $this->createStub(TaskHandlerFactory::class),
            [],
            [],
            $mock_middleware
        );
        $this->assertInstanceOf(TaskController::class, $controller);

        $chain       = ObjectPropertyRipper::ripOne($controller, 'chain');
        $middlewares = ObjectPropertyRipper::ripOne($chain, 'middlewares');
        $this->assertSame($mock_middleware, array_pop($middlewares));
    }

    public function test_can_create_server_stack_with_multiple_additional_middlewares()
    {
        $middleware1 = $this->createStub(TaskHandlerMiddleware::class);
        $middleware2 = $this->createStub(TaskHandlerMiddleware::class);
        $middleware3 = $this->createStub(TaskHandlerMiddleware::class);

        $controller = TaskServerFactory::makeController(
            $this->createStub(LoggerInterface::class),
            $this->createStub(MutexWrapper::class),
            $this->createStub(CacheItemPoolInterface::class),
            $this->createStub(TaskHandlerFactory::class),
            [],
            [],
            $middleware1,
            $middleware2,
            $middleware3
        );
        $this->assertInstanceOf(TaskController::class, $controller);

        $chain       = ObjectPropertyRipper::ripOne($controller, 'chain');
        $middlewares = ObjectPropertyRipper::ripOne($chain, 'middlewares');

        array_splice($middlewares, 0, -3);
        $this->assertSame([$middleware1, $middleware2, $middleware3], $middlewares);
    }

}

<?php


namespace Ingenerator\CloudTasksWrapper\TestHelpers\Server;


use Ingenerator\CloudTasksWrapper\Server\TaskHandler;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerFactory;
use Ingenerator\CloudTasksWrapper\Server\UnknownTaskTypeException;

class ArrayTaskHandlerFactory implements TaskHandlerFactory
{
    /**
     * @var \Ingenerator\CloudTasksWrapper\Server\TaskHandler[]
     */
    private array $handlers;

    /**
     * @param TaskHandler[] $handlers keyed by task type
     */
    public function __construct(array $handlers)
    {
        $this->handlers = $handlers;
    }

    public function getHandler(string $task_type): TaskHandler
    {
        if (isset($this->handlers[$task_type])) {
            return $this->handlers[$task_type];
        }

        throw new UnknownTaskTypeException('No handler defined for task type '.$task_type);
    }
}

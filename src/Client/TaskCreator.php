<?php


namespace Ingenerator\CloudTasksWrapper\Client;


interface TaskCreator
{

    public function create(string $task_type, ?CreateTaskOptions $options = NULL): string;

}

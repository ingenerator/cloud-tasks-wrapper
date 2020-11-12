<?php


namespace Ingenerator\CloudTasksWrapper\Client;


interface TaskCreator
{

    public function create(string $task_type, array $options = []): string;

}

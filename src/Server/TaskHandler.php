<?php


namespace Ingenerator\CloudTasksWrapper\Server;


use Psr\Http\Message\ServerRequestInterface;

interface TaskHandler
{
    public function handle(ServerRequestInterface $request): TaskHandlerResponse;
}

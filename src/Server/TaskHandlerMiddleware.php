<?php


namespace Ingenerator\CloudTasksWrapper\Server;


use Psr\Http\Message\ServerRequestInterface;

interface TaskHandlerMiddleware
{

    public function process(
        ServerRequestInterface $request,
        TaskHandlerChain $chain
    ): TaskHandlerResult;

}

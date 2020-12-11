<?php


namespace Ingenerator\CloudTasksWrapper\Server;


/**
 * Throw this if a task execution cannot possibly be valid
 *
 * For example, if it's missing querystring values / referring to an entity that does not exist.
 * Cloud Tasks will be sent a 2XX failure to prevent further retries.
 */
class CloudTaskCannotBeValidException extends \InvalidArgumentException
{

}

<?php

declare(strict_types=1);

namespace Ingenerator\CloudTasksWrapper\Server;

use InvalidArgumentException;

/**
 * Throw this if a task execution cannot possibly be valid.
 *
 * For example, if it's missing querystring values / referring to an entity that does not exist.
 * Cloud Tasks will be sent a 2XX failure to prevent further retries.
 */
class CloudTaskCannotBeValidException extends InvalidArgumentException
{
}

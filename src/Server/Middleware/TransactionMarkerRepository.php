<?php
/**
 * @author    Craig Gosman <craig@ingenerator.com>
 * @licence   BSD-3-Clause
 */

namespace Ingenerator\CloudTasksWrapper\Server\Middleware;

interface TransactionMarkerRepository
{
    public function exists(string $uuid): bool;
}

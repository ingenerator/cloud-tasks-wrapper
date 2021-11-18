<?php
/**
 * @author    Craig Gosman <craig@ingenerator.com>
 * @licence   BSD-3-Clause
 */

namespace Ingenerator\CloudTasksWrapper\Client;

use DateTimeImmutable;
use Ingenerator\CloudTasksWrapper\Server\Middleware\TransactionMarkerMiddleware;
use Ingenerator\PHPUtils\DateTime\DateString;

class TransactionMarkerMiddlewareHelper
{
    public static function addTransactionMarkerHeaders(array $task, string $uuid, DateTimeImmutable $expiry): array
    {
        $task['options']['headers'][TransactionMarkerMiddleware::TRANSACTION_MARKER_HEADER] = $uuid;
        $task['options']['headers'][TransactionMarkerMiddleware::TRANSACTION_MARKER_EXPIRE_HEADER] = DateString::ymdhis(
            $expiry
        );

        return $task;
    }
}

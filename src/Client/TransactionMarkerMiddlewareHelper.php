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
    public static function addTransactionMarkerHeaders(array $options, string $uuid, DateTimeImmutable $expiry): array
    {
        $options['headers'][TransactionMarkerMiddleware::TRANSACTION_MARKER_HEADER] = $uuid;
        $options['headers'][TransactionMarkerMiddleware::TRANSACTION_MARKER_EXPIRE_HEADER] = DateString::ymdhis(
            $expiry
        );

        return $options;
    }
}

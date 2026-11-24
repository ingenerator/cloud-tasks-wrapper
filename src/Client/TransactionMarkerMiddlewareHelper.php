<?php

declare(strict_types=1);

/**
 * @author    Craig Gosman <craig@ingenerator.com>
 *
 * @licence   BSD-3-Clause
 */

namespace Ingenerator\CloudTasksWrapper\Client;

use DateTimeImmutable;
use Ingenerator\CloudTasksWrapper\Server\Middleware\TransactionMarkerMiddleware;
use Ingenerator\PHPUtils\DateTime\DateString;

class TransactionMarkerMiddlewareHelper
{
    /**
     * @param array{headers?:array<string, mixed>, ...<string, mixed>} $options
     *
     * @return array<string, mixed>
     */
    public static function addTransactionMarkerHeaders(array $options, string $uuid, DateTimeImmutable $expiry): array
    {
        // @todo: Should accept and return `CreateTaskOptions` (e.g. cloned with extra headers) for better clarity & type-safety.
        $options['headers'][TransactionMarkerMiddleware::TRANSACTION_MARKER_HEADER] = $uuid;
        $options['headers'][TransactionMarkerMiddleware::TRANSACTION_MARKER_EXPIRE_HEADER] = DateString::ymdhis(
            $expiry
        );

        return $options;
    }
}

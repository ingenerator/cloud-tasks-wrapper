<?php
/**
 * @author    Craig Gosman <craig@ingenerator.com>
 * @licence   BSD-3-Clause
 */

namespace Ingenerator\CloudTasksWrapper\Server\Middleware;

use DateTimeImmutable;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerResult;
use Ingenerator\PHPUtils\DateTime\DateString;

class TransactionMarkerResult extends TaskHandlerResult
{
    const NOT_YET_VISIBLE     = 'notYetVisible';
    const TRANSACTION_EXPIRED = 'transactionExpired';

    public static function notYetVisible(string $uuid, DateTimeImmutable $expiry): TransactionMarkerResult
    {
        return new static(
            static::NOT_YET_VISIBLE,
            'transaction not ready',
            [
                'uuid'   => $uuid,
                'expiry' => DateString::ymdhis($expiry),
            ]
        );
    }

    public static function transactionExpired(string $uuid, DateTimeImmutable $expiry): TransactionMarkerResult
    {
        return new static(
            static::TRANSACTION_EXPIRED,
            'transaction rolled back',
            [
                'uuid'   => $uuid,
                'expiry' => DateString::ymdhis($expiry),
            ]
        );
    }

}

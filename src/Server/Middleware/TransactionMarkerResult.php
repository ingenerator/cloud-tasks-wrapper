<?php

declare(strict_types=1);

/**
 * @author    Craig Gosman <craig@ingenerator.com>
 *
 * @licence   BSD-3-Clause
 */

namespace Ingenerator\CloudTasksWrapper\Server\Middleware;

use DateTimeImmutable;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerResult;
use Ingenerator\PHPUtils\DateTime\DateString;

final class TransactionMarkerResult extends TaskHandlerResult
{
    public const string NOT_YET_VISIBLE = 'notYetVisible';
    public const string TRANSACTION_EXPIRED = 'transactionExpired';

    public static function notYetVisible(string $uuid, DateTimeImmutable $expiry): TransactionMarkerResult
    {
        return new self(
            self::NOT_YET_VISIBLE,
            'transaction not ready',
            [
                'uuid' => $uuid,
                'expiry' => DateString::ymdhis($expiry),
            ]
        );
    }

    public static function transactionExpired(string $uuid, DateTimeImmutable $expiry): TransactionMarkerResult
    {
        return new self(
            self::TRANSACTION_EXPIRED,
            'transaction rolled back',
            [
                'uuid' => $uuid,
                'expiry' => DateString::ymdhis($expiry),
            ]
        );
    }
}

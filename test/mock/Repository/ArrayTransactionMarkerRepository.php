<?php
/**
 * @author    Craig Gosman <craig@ingenerator.com>
 * @licence   BSD-3-Clause
 */

namespace test\mock\Ingenerator\CloudTasksWrapper\Repository;

use Ingenerator\CloudTasksWrapper\Server\Middleware\TransactionMarkerRepository;


class ArrayTransactionMarkerRepository implements TransactionMarkerRepository
{
    private array $uuids = [];

    public static function withNothing(): ArrayTransactionMarkerRepository
    {
        return new static;
    }

    public static function withUuids(array $uuids): ArrayTransactionMarkerRepository
    {
        $static        = new static;
        $static->uuids = $uuids;

        return $static;
    }

    public function exists(string $uuid): bool
    {
        return in_array($uuid, $this->uuids);
    }
}

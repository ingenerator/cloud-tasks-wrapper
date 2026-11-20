<?php

/**
 * @author    Craig Gosman <craig@ingenerator.com>
 *
 * @licence   BSD-3-Clause
 */

namespace test\mock\Ingenerator\CloudTasksWrapper\Repository;

use Ingenerator\CloudTasksWrapper\Server\Middleware\TransactionMarkerRepository;

final class ArrayTransactionMarkerRepository implements TransactionMarkerRepository
{
    /**
     * @var list<string>
     */
    private array $uuids = [];

    public static function withNothing(): ArrayTransactionMarkerRepository
    {
        return new self();
    }

    /**
     * @param list<string> $uuids
     */
    public static function withUuids(array $uuids): ArrayTransactionMarkerRepository
    {
        $static = new self();
        $static->uuids = $uuids;

        return $static;
    }

    public function exists(string $uuid): bool
    {
        return in_array($uuid, $this->uuids);
    }
}

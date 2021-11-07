<?php
/**
 * @author    Craig Gosman <craig@ingenerator.com>
 * @licence   BSD-3-Clause
 */

namespace Ingenerator\CloudTasksWrapper\Server\Middleware;

use DateTimeImmutable;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerChain;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerMiddleware;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerResult;
use Ingenerator\CloudTasksWrapper\Server\TaskRequest;
use Ingenerator\PHPUtils\DateTime\DateTimeImmutableFactory;

class TransactionMarkerMiddleware implements TaskHandlerMiddleware
{
    private TransactionMarkerRepository $transaction_marker_repo;

    public function __construct(TransactionMarkerRepository $transaction_marker_repo)
    {
        $this->transaction_marker_repo = $transaction_marker_repo;
    }

    public function process(TaskRequest $request, TaskHandlerChain $chain): TaskHandlerResult
    {
        $uuid = $request->getHttpRequest()->getHeaderLine('X-Transaction');

        // go go go - if it's nothing to do with us / or it's ready to begin
        if (empty($uuid) or $this->transaction_marker_repo->exists($uuid)) {
            return $chain->nextHandler($request);
        }

        $expiry = DateTimeImmutableFactory::fromYmdHis(
            $request->getHttpRequest()->getHeaderLine('X-Transaction-Expire')
        );

        // try again later, we're not ready yet
        if (new DateTimeImmutable() < $expiry) {
            return TransactionMarkerResult::notYetVisible($uuid, $expiry);
        }

        // transaction never completed e.g. rolled back
        return TransactionMarkerResult::transactionExpired($uuid, $expiry);
    }

}

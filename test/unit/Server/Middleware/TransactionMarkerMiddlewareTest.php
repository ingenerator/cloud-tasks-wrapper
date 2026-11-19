<?php

declare(strict_types=1);

/**
 * @author    Craig Gosman <craig@ingenerator.com>
 *
 * @licence   BSD-3-Clause
 */

namespace test\unit\Ingenerator\CloudTasksWrapper\Server\Middleware;

use DateTimeImmutable;
use Ingenerator\CloudTasksWrapper\Server\Middleware\TransactionMarkerMiddleware;
use Ingenerator\CloudTasksWrapper\Server\Middleware\TransactionMarkerResult;
use Ingenerator\CloudTasksWrapper\Server\TaskHandlerMiddleware;
use Ingenerator\CloudTasksWrapper\Server\TaskResult\ArbitraryTaskResult;
use Ingenerator\CloudTasksWrapper\TestHelpers\Server\TaskRequestStub;
use Ingenerator\CloudTasksWrapper\TestHelpers\Server\TestTaskChain;
use Ingenerator\CloudTasksWrapper\TestHelpers\Server\TestTaskHandler;
use Ingenerator\PHPUtils\DateTime\DateString;
use PHPUnit\Framework\TestCase;
use test\mock\Ingenerator\CloudTasksWrapper\Repository\ArrayTransactionMarkerRepository;

class TransactionMarkerMiddlewareTest extends TestCase
{
    private ArrayTransactionMarkerRepository $transaction_marker_repo;

    public function testItIsInitialisable(): void
    {
        $this->assertInstanceOf(TransactionMarkerMiddleware::class, $this->newSubject());
        $this->assertInstanceOf(TaskHandlerMiddleware::class, $this->newSubject());
    }

    public function testItReturnsResponseOfNextInChainIfNoXTransactionHeader(): void
    {
        $res = new ArbitraryTaskResult('no-transaction-header');
        $req = TaskRequestStub::any();
        $this->assertSame(
            $res,
            $this->newSubject()->process(
                $req,
                TestTaskChain::withHandler(TestTaskHandler::expectsReqAndReturns($req, $res))
            )
        );
    }

    public function testItReturnsResponseOfNextInChainIfTransactionMarkerExists(): void
    {
        $this->transaction_marker_repo = ArrayTransactionMarkerRepository::withUuids(['abcd-efgh']);

        $req = TaskRequestStub::with(['headers' => ['X-Transaction' => 'abcd-efgh']]);
        $res = new ArbitraryTaskResult('transaction-marker-exists');
        $this->assertSame(
            $res,
            $this->newSubject()->process(
                $req,
                TestTaskChain::withHandler(TestTaskHandler::expectsReqAndReturns($req, $res))
            )
        );
    }

    public function testItDoesNotRunIfRequestIsBeforeExpiry(): void
    {
        $future = DateString::ymdhis(new DateTimeImmutable('tomorrow'));

        $request = TaskRequestStub::with(
            ['headers' => ['X-Transaction' => 'abcd-1234', 'X-Transaction-Expire' => $future]]
        );

        $this->assertSame(
            [
                'code' => TransactionMarkerResult::NOT_YET_VISIBLE,
                'msg' => 'transaction not ready',
                'log_context' => [
                    'uuid' => 'abcd-1234',
                    'expiry' => $future,
                ],
            ],
            $this->newSubject()->process($request, TestTaskChain::nextNeverCalled())->toArray()
        );
    }

    public function testItDoesNotRunIfAfterExpiry(): void
    {
        $request = TaskRequestStub::with(
            [
                'headers' => [
                    'X-Transaction' => 'abcd-1234',
                    'X-Transaction-Expire' => DateString::ymdhis(new DateTimeImmutable('-1s')),
                ],
            ]
        );

        $this->assertSame(
            [
                'code' => TransactionMarkerResult::TRANSACTION_EXPIRED,
                'msg' => 'transaction rolled back',
                'log_context' => [
                    'uuid' => 'abcd-1234',
                    'expiry' => DateString::ymdhis(new DateTimeImmutable('-1s')),
                ],
            ],
            $this->newSubject()->process($request, TestTaskChain::nextNeverCalled())->toArray()
        );
    }

    private function newSubject(): TransactionMarkerMiddleware
    {
        return new TransactionMarkerMiddleware($this->transaction_marker_repo);
    }

    protected function setUp(): void
    {
        $this->transaction_marker_repo = ArrayTransactionMarkerRepository::withNothing();
        parent::setUp();
    }
}

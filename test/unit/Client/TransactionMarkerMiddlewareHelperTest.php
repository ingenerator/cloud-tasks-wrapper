<?php
/**
 * @author    Craig Gosman <craig@ingenerator.com>
 * @licence   proprietary
 */

namespace unit\Client;

use Ingenerator\CloudTasksWrapper\Client\CreateTaskOptions;
use Ingenerator\CloudTasksWrapper\Client\TransactionMarkerMiddlewareHelper;
use Ingenerator\CloudTasksWrapper\Server\Middleware\TransactionMarkerMiddleware;
use Ingenerator\PHPUtils\DateTime\DateTimeImmutableFactory;
use PHPUnit\Framework\TestCase;
use function uniqid;

class TransactionMarkerMiddlewareHelperTest extends TestCase
{
    public function test_it_adds_header_options_to_task_array()
    {
        $options = ['headers' => ['X-CustomHeader' => 'foo']];
        $uuid    = uniqid();
        $expiry  = DateTimeImmutableFactory::atUnixSeconds(1570792394);
        $this->assertSame(
            [
                'headers' => [
                    'X-CustomHeader'       => 'foo',
                    TransactionMarkerMiddleware::TRANSACTION_MARKER_HEADER        => $uuid,
                    TransactionMarkerMiddleware::TRANSACTION_MARKER_EXPIRE_HEADER => '2019-10-11 11:13:14',
                ],
            ],
            TransactionMarkerMiddlewareHelper::addTransactionMarkerHeaders($options, $uuid, $expiry)
        );
    }

    public function test_options_are_compatible_with_CreateTaskOptions()
    {
        $uuid   = uniqid();
        $expiry = DateTimeImmutableFactory::atUnixSeconds(1570792394);
        $result = new CreateTaskOptions(
            TransactionMarkerMiddlewareHelper::addTransactionMarkerHeaders([], $uuid, $expiry)
        );

        $this->assertInstanceOf(CreateTaskOptions::class, $result);
        $this->assertSame([
            TransactionMarkerMiddleware::TRANSACTION_MARKER_HEADER        => $uuid,
            TransactionMarkerMiddleware::TRANSACTION_MARKER_EXPIRE_HEADER => '2019-10-11 11:13:14',
        ], $result->getHeaders());
    }
}

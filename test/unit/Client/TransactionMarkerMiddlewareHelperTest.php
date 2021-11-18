<?php
/**
 * @author    Craig Gosman <craig@ingenerator.com>
 * @licence   proprietary
 */

namespace unit\Client;

use Ingenerator\CloudTasksWrapper\Client\TransactionMarkerMiddlewareHelper;
use Ingenerator\PHPUtils\DateTime\DateTimeImmutableFactory;
use PHPUnit\Framework\TestCase;
use function uniqid;

class TransactionMarkerMiddlewareHelperTest extends TestCase
{
    public function test_it_adds_header_options_to_task_array()
    {
        $task   = [
            'anything' => 'something',
            'options'  => ['headers' => ['X-CustomHeader' => 'foo']],
        ];
        $uuid   = uniqid();
        $expiry = DateTimeImmutableFactory::atUnixSeconds(1570792394);
        $this->assertSame(
            [
                'anything' => 'something',
                'options'  => [
                    'headers' => [
                        'X-CustomHeader'       => 'foo',
                        'X-Transaction'        => $uuid,
                        'X-Transaction-Expire' => '2019-10-11 11:13:14',
                    ],
                ],
            ],
            TransactionMarkerMiddlewareHelper::addTransactionMarkerHeaders($task, $uuid, $expiry)
        );
    }
}

<?php


namespace test\unit\Ingenerator\CloudTasksWrapper\Client;


use Ingenerator\CloudTasksWrapper\Client\CreateTaskOptions;
use Ingenerator\PHPUtils\Object\ObjectPropertyRipper;
use PHPUnit\Framework\TestCase;

class CreateTaskOptionsTest extends TestCase
{

    public function test_it_is_initialisable()
    {
        $this->assertInstanceOf(CreateTaskOptions::class, new CreateTaskOptions([]));
    }

    public function provider_invalid_args()
    {
        return [
            [
                ['unknown_prop' => 'anything'],
                'unknown_prop',
            ],
            [
                ['body' => ['unexpected']],
                'body',
            ],
            [
                // Can't specify both `form` and `json` body types
                ['body' => ['form' => ['foo' => 'bar'], 'json' => ['any' => 'json']]],
                'body',
            ],
            [
                ['task_id' => 'something', 'task_id_from' => 'something-else'],
                'task_id',
            ],
            [
                // Can't specify explicit task_id with throttle_interval
                ['task_id' => 'anything', 'throttle_interval' => new \DateInterval('PT5M')],
                'throttle_interval',
            ],
            [
                // Can't specify schedule_send_after with throttle_interval
                ['schedule_send_after' => new \DateTimeImmutable, 'throttle_interval' => new \DateInterval('PT5M')],
                'throttle_interval',
            ],
            [
                // Can't specify throttle_interval without task_id_from
                ['throttle_interval' => new \DateInterval('PT5M')],
                'task_id_from',
            ],
            [
                // Can't specify throttle_delay_secs without throttle_interval
                ['throttle_delay_secs' => 30],
                'throttle_delay_secs',
            ],
        ];
    }

    /**
     * @dataProvider provider_invalid_args
     */
    public function test_it_throws_on_construction_with_invalid_args($args, $expect_msg)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectErrorMessage($expect_msg);
        new CreateTaskOptions($args);
    }

    /**
     * @testWith [{"query": {"foo": "bar"}}, true]
     *           [{"query": null}, false]
     *           [{}, false]
     */
    public function test_it_can_have_query($opts, $expect)
    {
        $subject = new CreateTaskOptions($opts);
        $this->assertSame($expect, $subject->hasQuery());
        if ($expect) {
            $this->assertSame($opts['query'], $subject->getQuery());
        }
    }

    public function provider_body_encoding()
    {
        return [
            [
                ['body' => ['form' => ['foo' => 'bar', 'child' => ['any' => 'thing']]]],
                ['Content-Type' => 'application/x-www-form-urlencoded'],
                'foo=bar&child%5Bany%5D=thing',
            ],
            [
                ['body' => ['json' => ['foo' => 'bar', 'child' => ['any' => 'thing']]]],
                ['Content-Type' => 'application/json'],
                '{"foo":"bar","child":{"any":"thing"}}',
            ],
            [
                // Merges content-type header with options
                [
                    'body'    => ['form' => ['foo' => 'bar']],
                    'headers' => ['X-foo' => 'bar'],
                ],
                ['X-foo' => 'bar', 'Content-Type' => 'application/x-www-form-urlencoded'],
                'foo=bar',
            ],
            [
                // Custom content-type overrides default
                [
                    'body'    => ['form' => ['foo' => 'bar']],
                    'headers' => ['Content-Type' => 'text/plain'],
                ],
                ['Content-Type' => 'text/plain'],
                'foo=bar',
            ],
            [
                ['body' => 'My custom payload'],
                [],
                'My custom payload',
                NULL,
            ],
            [
                [],
                [],
                NULL,
            ],
        ];
    }

    /**
     * @dataProvider provider_body_encoding
     */
    public function test_it_can_encode_body_types_and_sets_default_content_type_header(
        array $opts,
        array $expect_headers,
        ?string $expect_raw_body
    ) {
        $subject = new CreateTaskOptions($opts);
        $this->assertSame($expect_headers, $subject->getHeaders());
        $this->assertSame($expect_raw_body, $subject->getBodyContent());
    }

    /**
     * @testWith [{}, null]
     *           [{"task_id": "absad237"}, "/some/queue/path/tasks/absad237"]
     *           [{"task_id_from": "some application string"}, "/some/queue/path/tasks/4b8c4feeb019bce54dc03b087553ae8d5066115ccf5221a1dca26a8fdec9c50e"]
     */
    public function test_it_can_build_task_name_from_explicit_id_or_hashable_value($opts, $expect)
    {
        $subject = new CreateTaskOptions($opts);
        $this->assertSame($expect, $subject->buildTaskName('/some/queue/path'));
    }

    /**
     * @testWith [{}, true]
     *           [{"task_id": "foo"}, true]
     *           [{"task_id_from": "bar"}, true]
     *           [{"task_id": "foo", "throw_on_duplicate": false}, false]
     */
    public function test_it_defaults_to_throw_on_duplicate_task_id_but_can_override($opts, $expect)
    {
        $subject = new CreateTaskOptions($opts);
        $this->assertSame($expect, $subject->shouldThrowOnDuplicate());
    }

    /**
     * @testWith ["2020-12-07 11:03:02.3082", 60, "2020-12-07T11:06:00.0000", "app-task-id@1607339160.000000"]
     *           ["2020-12-07 11:04:59.9999", 60, "2020-12-07T11:06:00.0000", "app-task-id@1607339160.000000"]
     *           ["2020-12-07 11:05:00.0001", 60, "2020-12-07T11:11:00.0000", "app-task-id@1607339460.000000"]
     *           ["2020-12-07 11:05:00.0001", 30, "2020-12-07T11:10:30.0000", "app-task-id@1607339430.000000"]
     */
    public function test_it_can_optionally_create_trailing_edge_throttle(
        string $create_at,
        int $delay_secs,
        string $expect_at,
        string $expect_id_from
    ) {
        $subject = new CreateTaskOptions(
            [
                '_create_time'        => new \DateTimeImmutable($create_at),
                'task_id_from'        => 'app-task-id',
                'throttle_interval'   => new \DateInterval('PT5M'),
                'throttle_delay_secs' => $delay_secs,
            ]
        );

        $this->assertEquals(new \DateTimeImmutable($expect_at), $subject->getScheduleSendAfter());
        // Asserting internal state here - only to get a more obvious assertion failure message if the
        // task name doesn't meet expectation (because from the public interface we can only see the
        // SHA of the resultant value). We still assert the hash as the final source of truth.
        $this->assertEquals($expect_id_from, ObjectPropertyRipper::ripOne($subject, 'task_id_from'));
        $this->assertEquals(
            'q/tasks/'.hash('sha256', $expect_id_from),
            $subject->buildTaskName('q')
        );
    }

    public function test_it_uses_default_create_time_for_throttle_if_not_set()
    {
        $subject   = new CreateTaskOptions(
            [
                'task_id_from'        => 'anything',
                'throttle_interval'   => new \DateInterval('PT1H'),
                'throttle_delay_secs' => 60,
            ]
        );
        $scheduled = $subject->getScheduleSendAfter();
        // Actual value is asserted above, this just tests that the flow didn't require an explicit date
        $this->assertNotNull($scheduled);
        $this->assertSame($scheduled, $subject->getScheduleSendAfter(), 'Scheduled send after is immutable');
        // If real time is right at the end of bucketing window, the event will fire as little as 60 seconds in the future
        $this->assertGreaterThan(new \DateTimeImmutable('+59 seconds'), $scheduled);
    }

}

<?php


namespace test\unit\Ingenerator\CloudTasksWrapper\Client;


use Ingenerator\CloudTasksWrapper\Client\CreateTaskOptions;
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

}

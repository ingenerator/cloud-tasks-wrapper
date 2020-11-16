<?php


namespace Ingenerator\CloudTasksWrapper;


use Google\ApiCore\ApiStatus;
use PHPUnit\Framework\TestCase;

class TaskTypeConfigProviderTest extends TestCase
{
    protected array $config = [
        '_default' => [
            'queue'        => [
                'project'  => 'good-proj',
                'location' => 'the-moon',
                'name'     => 'priority',
            ],
            'signer_email' => 'neil@armstrong.serviceaccount.test',
            'handler_url'  => 'https://moon.test/my-task',
        ],
    ];

    public function test_it_is_initialisable()
    {
        $this->assertInstanceOf(TaskTypeConfigProvider::class, $this->newSubject());
    }

    public function test_it_throws_on_unknown_task_type()
    {
        $this->config = ['_default' => []];
        $subject      = $this->newSubject();
        $this->expectException(\UnderflowException::class);
        $subject->getConfig('unknown');
    }

    public function test_it_throws_on_direct_access_to_default()
    {
        $this->config = ['_default' => []];
        $subject      = $this->newSubject();
        $this->expectException(\InvalidArgumentException::class);
        $subject->getConfig('_default');
    }

    public function test_it_throws_on_attempt_to_override_retryable_codes()
    {
        $this->config['_default']['create_retry_settings']['retryableCodes'] = ['foo'];
        $this->config['any-task']                                            = [];

        $subject = $this->newSubject();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('retryableCodes');
        $subject->getConfig('any-task');
    }

    public function test_it_provides_explicit_values_for_known_task_type()
    {
        $this->config = [
            'do-a-thing' => [
                'queue'        => [
                    'project'  => 'good-proj',
                    'location' => 'the-moon',
                    'name'     => 'slow-stuff',
                ],
                'signer_email' => 'neil@armstrong.serviceaccount.test',
                'handler_url'  => 'https://moon.test/my-task',
            ],
        ];
        $cfg          = $this->newSubject()->getConfig('do-a-thing');
        unset($cfg['create_retry_settings']); // Tested separately
        $this->assertSame(
            [
                'queue'        => [
                    'project'  => 'good-proj',
                    'location' => 'the-moon',
                    'name'     => 'slow-stuff',
                ],
                'signer_email' => 'neil@armstrong.serviceaccount.test',
                'handler_url'  => 'https://moon.test/my-task',
                'queue-path'   => 'projects/good-proj/locations/the-moon/queues/slow-stuff',
            ],
            $cfg
        );
    }

    public function test_it_merges_default_config_for_known_task_type()
    {
        $this->config = [
            '_default'         => [
                'queue'        => [
                    'project'  => 'good-proj',
                    'location' => 'the-moon',
                    'name'     => 'priority',
                ],
                'signer_email' => 'neil@armstrong.serviceaccount.test',
                'handler_url'  => 'https://moon.test/my-task',
            ],
            'background-thing' => [
                'queue'       => [
                    'name' => 'unimportant',
                ],
                'handler_url' => 'https://glacier.test/background',
            ],
        ];
        $cfg          = $this->newSubject()->getConfig('background-thing');
        unset($cfg['create_retry_settings']); // Tested separately
        $this->assertSame(
            [
                'queue'        => [
                    'project'  => 'good-proj',
                    'location' => 'the-moon',
                    'name'     => 'unimportant',
                ],
                'signer_email' => 'neil@armstrong.serviceaccount.test',
                'handler_url'  => 'https://glacier.test/background',
                'queue-path'   => 'projects/good-proj/locations/the-moon/queues/unimportant',
            ],
            $cfg
        );
    }

    public function test_it_replaces_task_type_parameter_in_handler_url()
    {
        $this->config['_default']['handler_url'] = 'https://some.handler/_do_task/{TASK_TYPE}';
        $this->config['slow-job']                = [];
        $this->config['other-job']               = [];

        $subject = $this->newSubject();
        $this->assertSame(
            [
                'slow-job'  => 'https://some.handler/_do_task/slow-job',
                'other-job' => 'https://some.handler/_do_task/other-job',
            ],
            [
                'slow-job'  => $subject->getConfig('slow-job')['handler_url'],
                'other-job' => $subject->getConfig('other-job')['handler_url'],
            ],
        );
    }

    public function test_it_provides_default_creation_retry_options_if_none_provided()
    {
        unset($this->config['_default']['create_retry_settings']);
        $this->config['my-task'] = [];

        $this->assertSame(
            [
                'initialRetryDelayMillis' => 100,
                'retryDelayMultiplier'    => 1.3,
                'maxRetryDelayMillis'     => 10000,
                'retryableCodes'          => [ApiStatus::DEADLINE_EXCEEDED, ApiStatus::UNAVAILABLE],
                'retriesEnabled'          => TRUE,
            ],
            $this->newSubject()->getConfig('my-task')['create_retry_settings']
        );
    }

    public function provider_custom_retry()
    {
        return [
            [
                // Override one prop for all methods
                [
                    '_default'  => ['create_retry_settings' => ['retryDelayMultiplier' => 5]],
                    'some-task' => [],
                ],
                [
                    'initialRetryDelayMillis' => 100,
                    'retryDelayMultiplier'    => 5,
                    'maxRetryDelayMillis'     => 10000,
                    'retryableCodes'          => [ApiStatus::DEADLINE_EXCEEDED, ApiStatus::UNAVAILABLE],
                    'retriesEnabled'          => TRUE,
                ],
            ],
            [
                // Override for a single task type
                [
                    '_default'  => ['create_retry_settings' => ['maxRetryDelayMillis' => 5000]],
                    'some-task' => ['create_retry_settings' => ['maxRetryDelayMillis' => 20000]],
                ],
                [
                    'initialRetryDelayMillis' => 100,
                    'retryDelayMultiplier'    => 1.3,
                    'maxRetryDelayMillis'     => 20000,
                    'retryableCodes'          => [ApiStatus::DEADLINE_EXCEEDED, ApiStatus::UNAVAILABLE],
                    'retriesEnabled'          => TRUE,
                ],
            ],
        ];
    }

    /**
     * @dataProvider provider_custom_retry
     */
    public function test_it_can_customise_creation_retry_options_for_all_tasks_or_one_task($config, $expect)
    {
        $this->config                            = $config;
        $this->config['_default']['queue']       = ['project' => 'a', 'location' => 'b', 'name' => 'c'];
        $this->config['_default']['handler_url'] = 'http://foo.com';

        $this->assertSame(
            $expect,
            $this->newSubject()->getConfig('some-task')['create_retry_settings']
        );
    }

    protected function newSubject()
    {
        return new TaskTypeConfigProvider($this->config);
    }

}

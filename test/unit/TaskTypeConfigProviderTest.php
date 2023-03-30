<?php


namespace Ingenerator\CloudTasksWrapper;


use Google\ApiCore\ApiStatus;
use Google\Cloud\Tasks\V2\CloudTasksClient;
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
        $this->assertSame(
            [
                'type'     => 'do-a-thing',
                'signer'   => 'neil@armstrong.serviceaccount.test',
                'audience' => NULL,
            ],
            [
                'type'     => $cfg->getTaskType(),
                'signer'   => $cfg->getSignerEmail(),
                'audience' => $cfg->getCustomTokenAudience(),
            ],
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

        $cfg = $this->newSubject()->getConfig('background-thing');

        $this->assertSame(
            [
                'queue_path' => CloudTasksClient::queueName('good-proj', 'the-moon', 'unimportant'),
                'handler'    => 'https://glacier.test/background',
                'signer'     => 'neil@armstrong.serviceaccount.test',
                'audience' => null,
            ],
            [
                'queue_path' => $cfg->getQueuePath(),
                'handler'    => $cfg->getHandlerUrl(),
                'signer'     => $cfg->getSignerEmail(),
                'audience' => $cfg->getCustomTokenAudience(),
            ]
        );
    }

    public function test_it_can_provide_custom_token_audience_if_configured()
    {
        $this->config = [
            'do-a-thing' => [
                'queue'                 => [
                    'project'  => 'good-proj',
                    'location' => 'the-moon',
                    'name'     => 'slow-stuff',
                ],
                'signer_email'          => 'neil@armstrong.serviceaccount.test',
                'handler_url'           => 'https://moon.test/my-task',
                'custom_token_audience' => 'pick me!',
            ],
        ];
        $cfg          = $this->newSubject()->getConfig('do-a-thing');
        $this->assertSame('pick me!', $cfg->getCustomTokenAudience());
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
            $this->newSubject()->getConfig('my-task')->getCreateRetrySettings()
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
            $this->newSubject()->getConfig('some-task')->getCreateRetrySettings()
        );
    }

    protected function newSubject()
    {
        return new TaskTypeConfigProvider($this->config);
    }

}

<?php

declare(strict_types=1);

namespace Ingenerator\CloudTasksWrapper;

use Google\ApiCore\ApiStatus;
use Google\Cloud\Tasks\V2\Client\CloudTasksClient;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UnderflowException;

class TaskTypeConfigProviderTest extends TestCase
{
    protected array $config = [
        '_default' => [
            'queue' => [
                'project' => 'good-proj',
                'location' => 'the-moon',
                'name' => 'priority',
            ],
            'signer_email' => 'neil@armstrong.serviceaccount.test',
            'handler_url' => 'https://moon.test/my-task',
        ],
    ];

    public function testItIsInitialisable(): void
    {
        $this->assertInstanceOf(TaskTypeConfigProvider::class, $this->newSubject());
    }

    public function testItThrowsOnUnknownTaskType(): void
    {
        $this->config = ['_default' => []];
        $subject = $this->newSubject();
        $this->expectException(UnderflowException::class);
        $subject->getConfig('unknown');
    }

    public function testItThrowsOnDirectAccessToDefault(): void
    {
        $this->config = ['_default' => []];
        $subject = $this->newSubject();
        $this->expectException(InvalidArgumentException::class);
        $subject->getConfig('_default');
    }

    public function testItThrowsOnAttemptToOverrideRetryableCodes(): void
    {
        $this->config['_default']['create_retry_settings']['retryableCodes'] = ['foo'];
        $this->config['any-task'] = [];

        $subject = $this->newSubject();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('retryableCodes');
        $subject->getConfig('any-task');
    }

    public function testItProvidesExplicitValuesForKnownTaskType(): void
    {
        $this->config = [
            'do-a-thing' => [
                'queue' => [
                    'project' => 'good-proj',
                    'location' => 'the-moon',
                    'name' => 'slow-stuff',
                ],
                'signer_email' => 'neil@armstrong.serviceaccount.test',
                'handler_url' => 'https://moon.test/my-task',
            ],
        ];
        $cfg = $this->newSubject()->getConfig('do-a-thing');
        $this->assertSame(
            [
                'type' => 'do-a-thing',
                'signer' => 'neil@armstrong.serviceaccount.test',
                'audience' => null,
            ],
            [
                'type' => $cfg->getTaskType(),
                'signer' => $cfg->getSignerEmail(),
                'audience' => $cfg->getCustomTokenAudience(),
            ],
        );
    }

    public function testItMergesDefaultConfigForKnownTaskType(): void
    {
        $this->config = [
            '_default' => [
                'queue' => [
                    'project' => 'good-proj',
                    'location' => 'the-moon',
                    'name' => 'priority',
                ],
                'signer_email' => 'neil@armstrong.serviceaccount.test',
                'handler_url' => 'https://moon.test/my-task',
            ],
            'background-thing' => [
                'queue' => [
                    'name' => 'unimportant',
                ],
                'handler_url' => 'https://glacier.test/background',
            ],
        ];

        $cfg = $this->newSubject()->getConfig('background-thing');

        $this->assertSame(
            [
                'queue_path' => CloudTasksClient::queueName('good-proj', 'the-moon', 'unimportant'),
                'handler' => 'https://glacier.test/background',
                'signer' => 'neil@armstrong.serviceaccount.test',
                'audience' => null,
            ],
            [
                'queue_path' => $cfg->getQueuePath(),
                'handler' => $cfg->getHandlerUrl(),
                'signer' => $cfg->getSignerEmail(),
                'audience' => $cfg->getCustomTokenAudience(),
            ]
        );
    }

    public function testItCanProvideCustomTokenAudienceIfConfigured(): void
    {
        $this->config = [
            'do-a-thing' => [
                'queue' => [
                    'project' => 'good-proj',
                    'location' => 'the-moon',
                    'name' => 'slow-stuff',
                ],
                'signer_email' => 'neil@armstrong.serviceaccount.test',
                'handler_url' => 'https://moon.test/my-task',
                'custom_token_audience' => 'pick me!',
            ],
        ];
        $cfg = $this->newSubject()->getConfig('do-a-thing');
        $this->assertSame('pick me!', $cfg->getCustomTokenAudience());
    }

    public function testItProvidesDefaultCreationRetryOptionsIfNoneProvided(): void
    {
        unset($this->config['_default']['create_retry_settings']);
        $this->config['my-task'] = [];

        $this->assertSame(
            [
                'initialRetryDelayMillis' => 100,
                'retryDelayMultiplier' => 1.3,
                'maxRetryDelayMillis' => 10000,
                'retryableCodes' => [ApiStatus::DEADLINE_EXCEEDED, ApiStatus::UNAVAILABLE],
                'retriesEnabled' => true,
            ],
            $this->newSubject()->getConfig('my-task')->getCreateRetrySettings()
        );
    }

    /**
     * @return array<int, array<string, array<array<string, int>|string>|bool|float|int>[]>
     */
    public static function provider_custom_retry(): array
    {
        return [
            [
                // Override one prop for all methods
                [
                    '_default' => ['create_retry_settings' => ['retryDelayMultiplier' => 5]],
                    'some-task' => [],
                ],
                [
                    'initialRetryDelayMillis' => 100,
                    'retryDelayMultiplier' => 5,
                    'maxRetryDelayMillis' => 10000,
                    'retryableCodes' => [ApiStatus::DEADLINE_EXCEEDED, ApiStatus::UNAVAILABLE],
                    'retriesEnabled' => true,
                ],
            ],
            [
                // Override for a single task type
                [
                    '_default' => ['create_retry_settings' => ['maxRetryDelayMillis' => 5000]],
                    'some-task' => ['create_retry_settings' => ['maxRetryDelayMillis' => 20000]],
                ],
                [
                    'initialRetryDelayMillis' => 100,
                    'retryDelayMultiplier' => 1.3,
                    'maxRetryDelayMillis' => 20000,
                    'retryableCodes' => [ApiStatus::DEADLINE_EXCEEDED, ApiStatus::UNAVAILABLE],
                    'retriesEnabled' => true,
                ],
            ],
        ];
    }

    #[DataProvider('provider_custom_retry')]
    public function testItCanCustomiseCreationRetryOptionsForAllTasksOrOneTask(array $config, array $expect): void
    {
        $this->config = $config;
        $this->config['_default']['queue'] = ['project' => 'a', 'location' => 'b', 'name' => 'c'];
        $this->config['_default']['handler_url'] = 'http://foo.com';

        $this->assertSame(
            $expect,
            $this->newSubject()->getConfig('some-task')->getCreateRetrySettings()
        );
    }

    protected function newSubject(): TaskTypeConfigProvider
    {
        return new TaskTypeConfigProvider($this->config);
    }
}

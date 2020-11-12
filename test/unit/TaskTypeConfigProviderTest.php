<?php


namespace Ingenerator\CloudTasksWrapper;


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
            $this->newSubject()->getConfig('do-a-thing')
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
            $this->newSubject()->getConfig('background-thing')
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

    protected function newSubject()
    {
        return new TaskTypeConfigProvider($this->config);
    }

}

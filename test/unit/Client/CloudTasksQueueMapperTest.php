<?php


namespace test\unit\Ingenerator\CloudTasksWrapper\Client;


use Ingenerator\CloudTasksWrapper\Client\CloudTasksQueueMapper;
use PHPUnit\Framework\TestCase;

class CloudTasksQueueMapperTest extends TestCase
{

    protected $config = [
        'default_project'  => 'dev',
        'default_location' => 'here',
        'default_signer'   => 'himthere@service.com',
        'queues'           => [
            'any-old-queue' => [],
        ],
    ];

    public function test_it_is_initialisable()
    {
        $this->assertInstanceOf(CloudTasksQueueMapper::class, $this->newSubject());
    }

    public function test_it_throws_if_queue_not_defined_at_all()
    {
        $subject = $this->newSubject();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('we-dont-queue');
        $subject->pathFor('we-dont-queue');
    }


    public function test_it_maps_explicitly_defined_task_to_full_name()
    {
        $this->config['queues']['my-funky-q'] = [
            'location' => 'america',
            'project'  => 'someone-else',
            'name'     => 'something-they-set',
        ];

        $this->assertSame(
            'projects/someone-else/locations/america/queues/something-they-set',
            $this->newSubject()->pathFor('my-funky-q')
        );
    }

    /**
     * @testWith [{"location": "america", "name": "some-queue"}, "projects/mine/locations/america/queues/some-queue"]
     *           [{"project": "prod", "name": "some-queue"}, "projects/prod/locations/derby/queues/some-queue"]
     *           [{"project": "prod"}, "projects/prod/locations/derby/queues/awesome-queue"]
     *           [{}, "projects/mine/locations/derby/queues/awesome-queue"]
     */
    public function test_it_merges_default_location_project_or_internal_name_if_not_set($map, $expect)
    {
        $this->config['default_location']        = 'derby';
        $this->config['default_project']         = 'mine';
        $this->config['queues']['awesome-queue'] = $map;
        $this->assertSame($expect, $this->newSubject()->pathFor('awesome-queue'));
    }

    public function test_it_throws_for_oidc_signer_if_queue_not_defined()
    {
        $subject = $this->newSubject();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not-a-queue-here');
        $subject->getOidcSignerEmail('not-a-queue-here');
    }

    public function test_it_throws_if_no_default_or_explicit_value()
    {
        $this->config['default_signer']   = NULL;
        $this->config['queues']['some-q'] = [];
        $subject                          = $this->newSubject();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('default_signer');
        $subject->getOidcSignerEmail('some-q');
    }

    /**
     * @testWith [{"signer": "jimbo@service.com"}, "bilbo@baggins.net", "jimbo@service.com"]
     *           [{}, "bilbo@baggins.net", "bilbo@baggins.net"]
     */
    public function test_it_provides_oidc_signer_or_default_signer($queue, $default, $expect)
    {
        $this->config['default_signer'] = $default;
        $this->config['queues']['my-q'] = $queue;
        $this->assertSame(
            $expect,
            $this->newSubject()->getOidcSignerEmail('my-q')
        );
    }


    protected function newSubject(): CloudTasksQueueMapper
    {
        return new CloudTasksQueueMapper($this->config);
    }

}

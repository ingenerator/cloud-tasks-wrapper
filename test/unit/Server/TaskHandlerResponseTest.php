<?php


namespace test\unit\Ingenerator\CloudTasksWrapper\Server;

use Ingenerator\CloudTasksWrapper\Server\TaskHandlerResponse;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

class TaskHandlerResponseTest extends TestCase
{

    public function test_it_is_initialisable_with_valid_data()
    {
        $this->assertInstanceOf(
            TaskHandlerResponse::class,
            new TaskHandlerResponse($this->getValidData())
        );
    }

    public function test_it_does_not_require_log_context()
    {
        $data = $this->getValidData();
        unset($data['log_context']);
        $subject = new TaskHandlerResponse($data);
        $this->assertSame([], $subject->getLogContext());
    }

    public function test_it_exposes_input_data()
    {
        $data                             = $this->getValidData();
        $data['log_context']['exception'] = new InvalidArgumentException('Test');

        $subject = new TaskHandlerResponse($data);
        $this->assertSame(
            $data,
            [
                'code'             => $subject->getCode(),
                'msg'              => $subject->getMsg(),
                'http_status'      => $subject->getHttpStatus(),
                'http_status_name' => $subject->getHttpStatusName(),
                'loglevel'         => $subject->getLoglevel(),
                'log_context'      => $subject->getLogContext(),
            ]
        );
    }

    public function test_it_exposes_original_data_as_array()
    {
        $data    = $this->getValidData();
        $subject = new TaskHandlerResponse($data);
        $this->assertSame($data, $subject->toArray());
    }

    protected function getValidData()
    {
        return [
            'code'             => 'anything',
            'msg'              => 'I go to tasks',
            'http_status'      => 200,
            'http_status_name' => 'OK',
            'loglevel'         => LogLevel::INFO,
            'log_context'      => [],
        ];
    }

}

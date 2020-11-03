<?php


namespace Ingenerator\CloudTasksWrapper\Server;


class TaskHandlerResponse
{

    protected string $code;

    protected string $msg;

    protected int $http_status;

    protected string $http_status_name;

    protected string $loglevel;

    protected array $log_context;

    public function __construct(array $data)
    {
        $this->code             = $data['code'];
        $this->msg              = $data['msg'];
        $this->http_status      = $data['http_status'];
        $this->http_status_name = $data['http_status_name'];
        $this->loglevel         = $data['loglevel'];
        $this->log_context      = $data['log_context'] ?? [];
    }

    /**
     * @return string
     */
    public function getMsg(): string
    {
        return $this->msg;
    }

    /**
     * @return int
     */
    public function getHttpStatus(): int
    {
        return $this->http_status;
    }

    /**
     * @return string
     */
    public function getHttpStatusName(): string
    {
        return $this->http_status_name;
    }

    /**
     * @return string
     */
    public function getLoglevel(): string
    {
        return $this->loglevel;
    }

    /**
     * @return array
     */
    public function getLogContext(): array
    {
        return $this->log_context;
    }

    /**
     * @return string
     */
    public function getCode(): string
    {
        return $this->code;
    }

    public function toArray(): array
    {
        return [
            'code'             => $this->code,
            'msg'              => $this->msg,
            'http_status'      => $this->http_status,
            'http_status_name' => $this->http_status_name,
            'loglevel'         => $this->loglevel,
            'log_context'      => $this->log_context,
        ];
    }

}

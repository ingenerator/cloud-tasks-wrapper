<?php


namespace Ingenerator\CloudTasksWrapper\Server;


class TaskHandlerResponse
{

    /**
     * @var string
     */
    protected $code;

    /**
     * @var string
     */
    protected $msg;

    /**
     * @var int
     */
    protected $http_status;

    /**
     * @var string
     */
    protected $http_status_name;

    /**
     * @var string
     */
    protected $loglevel;

    /**
     * @var array
     */
    protected $log_context;

    public function __construct(array $data)
    {
        // Don't allow invalid creation
        $this->setCode($data['code']);
        $this->setMsg($data['msg']);
        $this->setStatus($data['http_status']);
        $this->setStatusName($data['http_status_name']);
        $this->setLoglevel($data['loglevel']);
        $this->setLogContext($data['log_context'] ?? []);
    }

    /**
     * @param string $code
     */
    private function setCode(string $code): void
    {
        $this->code = $code;
    }

    private function setMsg(string $msg): void
    {
        $this->msg = $msg;
    }

    private function setStatus(int $status): void
    {
        $this->http_status = $status;
    }

    private function setStatusName(string $status_name): void
    {
        $this->http_status_name = $status_name;
    }

    private function setLoglevel(string $loglevel): void
    {
        $this->loglevel = $loglevel;
    }

    private function setLogContext(array $log_context): void
    {
        $this->log_context = $log_context;
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

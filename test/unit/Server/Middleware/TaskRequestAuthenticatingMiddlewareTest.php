<?php


namespace test\unit\Ingenerator\CloudTasksWrapper\Server\Middleware;


use PHPUnit\Framework\TestCase;

class TaskRequestAuthenticatingMiddlewareTest extends TestCase
{

    public function test_it_is_initialisable()
    {
        $this->markTestIncomplete();
    }

    public function test_it_returns_bad_method_if_request_not_post()
    {
        $this->markTestIncomplete();
    }

    public function test_it_returns_auth_not_present_if_no_auth_token()
    {
        $this->markTestIncomplete();
    }

    public function test_it_returns_auth_expired_if_auth_token_expired()
    {
        $this->markTestIncomplete();
    }

    public function test_it_returns_auth_failed_if_auth_token_not_for_authorised_user()
    {
        $this->markTestIncomplete();
    }

    public function test_it_returns_result_of_next_handler_if_auth_succeeds()
    {
        $this->markTestIncomplete();
    }

}

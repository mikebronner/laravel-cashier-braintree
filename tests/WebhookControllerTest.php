<?php

namespace Laravel\Cashier\Tests;

use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Laravel\Cashier\Http\Controllers\WebhookController;

class WebhookControllerTest extends TestCase
{
    public function test_proper_methods_are_called_based_on_braintree_event()
    {
        $_SERVER['__received'] = false;
        $request = Request::create('/', 'POST', [], [], [], [], json_encode([
            'kind' => 'charge_succeeded', 'id' => 'event-id',
        ]));

        (new WebhookControllerTestStub)->handleWebhook($request);

        $this->assertTrue($_SERVER['__received']);
    }

    public function test_normal_response_is_returned_if_method_is_missing()
    {
        $request = Request::create('/', 'POST', [], [], [], [], json_encode([
            'kind' => 'foo_bar', 'id' => 'event-id',
        ]));

        $response = (new WebhookControllerTestStub)->handleWebhook($request);

        $this->assertEquals(200, $response->getStatusCode());
    }
}

class WebhookControllerTestStub extends WebhookController
{
    public function handleChargeSucceeded()
    {
        $_SERVER['__received'] = true;
    }

    /**
     * Parse the given Braintree webhook notification request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Braintree\WebhookNotification
     */
    protected function parseBraintreeNotification($request)
    {
        return json_decode($request->getContent());
    }
}

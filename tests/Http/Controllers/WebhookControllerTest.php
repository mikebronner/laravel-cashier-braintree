<?php

namespace Laravel\Cashier\Tests\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Cashier\Tests\Fixtures\Http\Controllers\WebhookController;
use PHPUnit\Framework\TestCase;

class WebhookControllerTest extends TestCase
{
    public function test_proper_methods_are_called_based_on_braintree_event()
    {
        $_SERVER['__received'] = false;
        $request = Request::create('/', 'POST', [], [], [], [], json_encode([
            'kind' => 'charge_succeeded', 'id' => 'event-id',
        ]));

        (new WebhookController)->handleWebhook($request);

        $this->assertTrue($_SERVER['__received']);
    }

    public function test_normal_response_is_returned_if_method_is_missing()
    {
        $request = Request::create('/', 'POST', [], [], [], [], json_encode([
            'kind' => 'foo_bar', 'id' => 'event-id',
        ]));

        $response = (new WebhookController)->handleWebhook($request);

        $this->assertEquals(200, $response->getStatusCode());
    }
}

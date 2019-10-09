<?php

namespace Laravel\Cashier\Tests\Fixtures\Http\Controllers;

use Laravel\Cashier\Http\Controllers\WebhookController;

class CashierTestController extends WebhookController
{
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

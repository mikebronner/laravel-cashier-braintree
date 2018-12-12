<?php

namespace Laravel\Cashier\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Laravel\Cashier\Subscription;
use Braintree\WebhookNotification;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

class WebhookController extends Controller
{
    /**
     * Handle a Braintree webhook call.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handleWebhook(Request $request)
    {
        try {
            $webhook = $this->parseBraintreeNotification($request);
        } catch (Exception $e) {
            return;
        }

        $method = 'handle'.studly_case(str_replace('.', '_', $webhook->kind));

        if (method_exists($this, $method)) {
            return $this->{$method}($webhook);
        }

        return $this->missingMethod();
    }

    /**
     * Parse the given Braintree webhook notification request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Braintree\WebhookNotification
     */
    protected function parseBraintreeNotification($request)
    {
        return WebhookNotification::parse($request->bt_signature, $request->bt_payload);
    }

    /**
     * Handle a subscription cancellation notification from Braintree.
     *
     * @param  \Braintree\WebhookNotification  $webhook
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handleSubscriptionCanceled($webhook): Response
    {
        return $this->cancelSubscription($webhook->subscription->id);
    }

    /**
     * Handle a subscription expiration notification from Braintree.
     *
     * @param  \Braintree\WebhookNotification  $webhook
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handleSubscriptionExpired($webhook): Response
    {
        return $this->cancelSubscription($webhook->subscription->id);
    }

    /**
     * Handle a subscription cancellation notification from Braintree.
     *
     * @param  string  $subscriptionId
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function cancelSubscription($subscriptionId): Response
    {
        $subscription = $this->getSubscriptionById($subscriptionId);

        if ($subscription && (! $subscription->cancelled() || $subscription->onGracePeriod())) {
            $subscription->markAsCancelled();
        }

        return new Response('Webhook Handled', 200);
    }

    /**
     * Get the model for the given subscription ID.
     *
     * @param  string  $subscriptionId
     * @return \Laravel\Cashier\Subscription
     */
    protected function getSubscriptionById($subscriptionId): Subscription
    {
        return Subscription::where('braintree_id', $subscriptionId)->first();
    }

    /**
     * Handle calls to missing methods on the controller.
     *
     * @param  array  $parameters
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function missingMethod(array $parameters = []): Response
    {
        return new Response;
    }
}

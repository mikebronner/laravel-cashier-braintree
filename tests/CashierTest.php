<?php

namespace Laravel\Cashier\Tests;

use Carbon\Carbon;
use Braintree_Configuration;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Laravel\Cashier\Http\Controllers\WebhookController;

class CashierTest extends TestCase
{
    public static function setUpBeforeClass()
    {
        if (file_exists(__DIR__.'/../.env')) {
            $dotenv = new \Dotenv\Dotenv(__DIR__.'/../');
            $dotenv->load();
        }
    }

    public function setUp()
    {
        Braintree_Configuration::environment('sandbox');
        Braintree_Configuration::merchantId(getenv('BRAINTREE_MERCHANT_ID'));
        Braintree_Configuration::publicKey(getenv('BRAINTREE_PUBLIC_KEY'));
        Braintree_Configuration::privateKey(getenv('BRAINTREE_PRIVATE_KEY'));

        Eloquent::unguard();

        $db = new DB;
        $db->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        $db->bootEloquent();
        $db->setAsGlobal();

        $this->schema()->create('users', function ($table) {
            $table->increments('id');
            $table->string('email');
            $table->string('name');
            $table->string('braintree_id')->nullable();
            $table->string('paypal_email')->nullable();
            $table->string('card_brand')->nullable();
            $table->string('card_last_four')->nullable();
            $table->timestamps();
        });

        $this->schema()->create('subscriptions', function ($table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->string('name');
            $table->string('braintree_id');
            $table->string('braintree_plan');
            $table->integer('quantity');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });
    }

    public function tearDown()
    {
        $this->schema()->drop('users');
        $this->schema()->drop('subscriptions');
    }

    /**
     * Tests.
     */
    public function testSubscriptionsCanBeCreated()
    {
        $owner = User::create([
            'email' => 'taylor@laravel.com',
            'name' => 'Taylor Otwell',
        ]);

        // Create Subscription
        $owner->newSubscription('main', 'monthly-10-1')->create($this->getTestToken());

        $this->assertEquals(1, count($owner->subscriptions));
        $this->assertNotNull($owner->subscription('main')->braintree_id);

        $this->assertTrue($owner->subscribed('main'));
        $this->assertTrue($owner->subscribed('main', 'monthly-10-1'));
        $this->assertFalse($owner->subscribed('main', 'monthly-10-2'));
        $this->assertTrue($owner->subscription('main')->active());
        $this->assertFalse($owner->subscription('main')->cancelled());
        $this->assertFalse($owner->subscription('main')->onGracePeriod());

        // Cancel Subscription
        $subscription = $owner->subscription('main');
        $subscription->cancel();

        $this->assertTrue($subscription->active());
        $this->assertTrue($subscription->cancelled());
        $this->assertTrue($subscription->onGracePeriod());

        // Modify Ends Date To Past
        $oldGracePeriod = $subscription->ends_at;
        $subscription->fill(['ends_at' => Carbon::now()->subDays(5)])->save();

        $this->assertFalse($subscription->active());
        $this->assertTrue($subscription->cancelled());
        $this->assertFalse($subscription->onGracePeriod());

        $subscription->fill(['ends_at' => $oldGracePeriod])->save();

        // Resume Subscription
        $subscription->resume();

        $this->assertTrue($subscription->active());
        $this->assertFalse($subscription->cancelled());
        $this->assertFalse($subscription->onGracePeriod());

        // Swap Plan
        $subscription->swap('monthly-10-2');

        $this->assertEquals('monthly-10-2', $subscription->braintree_plan);

        // Invoice Tests
        $invoice = $owner->invoicesIncludingPending()[0];

        $foundInvoice = $owner->findInvoice($invoice->id);
        $this->assertEquals($invoice->id, $foundInvoice->id);

        $this->assertEquals('$10.00', $invoice->total());
        $this->assertFalse($invoice->hasDiscount());
        $this->assertEquals(0, count($invoice->coupons()));
        $this->assertInstanceOf(Carbon::class, $invoice->date());
    }

    public function test_creating_subscription_with_coupons()
    {
        $owner = User::create([
            'email' => 'taylor@laravel.com',
            'name' => 'Taylor Otwell',
        ]);

        // Create Subscription
        $owner->newSubscription('main', 'monthly-10-1')
                ->withCoupon('coupon-1')->create($this->getTestToken());

        $subscription = $owner->subscription('main');

        $this->assertTrue($owner->subscribed('main'));
        $this->assertTrue($owner->subscribed('main', 'monthly-10-1'));
        $this->assertFalse($owner->subscribed('main', 'monthly-10-2'));
        $this->assertTrue($subscription->active());
        $this->assertFalse($subscription->cancelled());
        $this->assertFalse($subscription->onGracePeriod());

        // Invoice Tests
        $invoice = $owner->invoicesIncludingPending()[0];

        $this->assertTrue($invoice->hasDiscount());
        $this->assertEquals('$5.00', $invoice->total());
        $this->assertEquals('$5.00', $invoice->amountOff());
    }

    public function test_creating_subscription_with_trial()
    {
        $owner = User::create([
            'email' => 'taylor@laravel.com',
            'name' => 'Taylor Otwell',
        ]);

        // Create Subscription
        $owner->newSubscription('main', 'monthly-10-1')
                ->trialDays(7)->create($this->getTestToken());

        $subscription = $owner->subscription('main');

        $this->assertTrue($subscription->active());
        $this->assertTrue($subscription->onTrial());
        $this->assertEquals(Carbon::today()->addDays(7)->day, $subscription->trial_ends_at->day);

        // Cancel Subscription
        $subscription->cancel();

        // Braintree trials are just cancelled out right since we have no good way to cancel them
        // and then later resume them
        $this->assertFalse($subscription->active());
        $this->assertFalse($subscription->onGracePeriod());
    }

    public function test_applying_coupons_to_existing_customers()
    {
        $owner = User::create([
            'email' => 'taylor@laravel.com',
            'name' => 'Taylor Otwell',
        ]);

        // Create Subscription
        $owner->newSubscription('main', 'monthly-10-1')
                ->create($this->getTestToken());

        $owner->applyCoupon('coupon-1', 'main');

        $subscription = $owner->subscription('main')->asBraintreeSubscription();

        foreach ($subscription->discounts as $discount) {
            if ($discount->id === 'coupon-1') {
                return;
            }
        }

        $this->fail('Coupon was not applied to existing customer.');
    }

    public function test_yearly_to_monthly_properly_prorates()
    {
        $owner = User::create([
            'email' => 'taylor@laravel.com',
            'name' => 'Taylor Otwell',
        ]);

        // Create Subscription
        $owner->newSubscription('main', 'yearly-100-1')->create($this->getTestToken());

        $this->assertEquals(1, count($owner->subscriptions));
        $this->assertNotNull($owner->subscription('main')->braintree_id);

        // Swap To Monthly
        $owner->subscription('main')->swap('monthly-10-1');

        $owner = $owner->fresh();

        $this->assertEquals(2, count($owner->subscriptions));
        $this->assertNotNull($owner->subscription('main')->braintree_id);
        $this->assertEquals('monthly-10-1', $owner->subscription('main')->braintree_plan);

        $braintreeSubscription = $owner->subscription('main')->asBraintreeSubscription();

        foreach ($braintreeSubscription->discounts as $discount) {
            if ($discount->id === 'plan-credit') {
                $this->assertEquals('10.00', $discount->amount);
                $this->assertEquals(9, $discount->numberOfBillingCycles);
                return;
            }
        }

        $this->fail('Proration when switching to yearly was not done properly.');
    }

    public function test_monthly_to_yearly_properly_prorates()
    {
        $owner = User::create([
            'email' => 'taylor@laravel.com',
            'name' => 'Taylor Otwell',
        ]);

        // Create Subscription
        $owner->newSubscription('main', 'yearly-100-1')->create($this->getTestToken());

        $this->assertEquals(1, count($owner->subscriptions));
        $this->assertNotNull($owner->subscription('main')->braintree_id);

        // Swap To Monthly
        $owner->subscription('main')->swap('monthly-10-1');
        $owner = $owner->fresh();

        // Swap Back To Yearly
        $owner->subscription('main')->swap('yearly-100-1');
        $owner = $owner->fresh();

        $this->assertEquals(3, count($owner->subscriptions));
        $this->assertNotNull($owner->subscription('main')->braintree_id);
        $this->assertEquals('yearly-100-1', $owner->subscription('main')->braintree_plan);

        $braintreeSubscription = $owner->subscription('main')->asBraintreeSubscription();

        foreach ($braintreeSubscription->discounts as $discount) {
            if ($discount->id === 'plan-credit') {
                $this->assertEquals('90.00', $discount->amount);
                $this->assertEquals(1, $discount->numberOfBillingCycles);
                return;
            }
        }

        $this->fail('Proration when switching to yearly was not done properly.');
    }

    public function test_marking_as_cancelled_from_webhook()
    {
        $owner = User::create([
            'email' => 'taylor@laravel.com',
            'name' => 'Taylor Otwell',
        ]);

        $owner->newSubscription('main', 'monthly-10-1')
                ->create($this->getTestToken());

        $subscription = $owner->subscription('main');

        $request = Request::create('/', 'POST', [], [], [], [], json_encode(['kind' => 'SubscriptionCanceled',
            'subscription' => [
                'id' => $subscription->braintree_id,
            ],
        ]));

        $controller = new CashierTestControllerStub;
        $response = $controller->handleWebhook($request);
        $this->assertEquals(200, $response->getStatusCode());

        $owner = $owner->fresh();
        $subscription = $owner->subscription('main');

        $this->assertTrue($subscription->cancelled());
    }

    public function test_marking_subscription_cancelled_on_grace_period_as_cancelled_now_from_webhook()
    {
        $owner = User::create([
            'email' => 'taylor@laravel.com',
            'name' => 'Taylor Otwell',
        ]);

        $owner->newSubscription('main', 'monthly-10-1')
                ->create($this->getTestToken());

        $subscription = $owner->subscription('main');

        $subscription->cancel();
        $this->assertTrue($subscription->onGracePeriod());

        $request = Request::create('/', 'POST', [], [], [], [], json_encode(['kind' => 'SubscriptionCanceled',
            'subscription' => [
                'id' => $subscription->braintree_id,
            ],
        ]));

        $controller = new CashierTestControllerStub;
        $response = $controller->handleWebhook($request);
        $this->assertEquals(200, $response->getStatusCode());

        $owner = $owner->fresh();
        $subscription = $owner->subscription('main');

        $this->assertFalse($subscription->onGracePeriod());
    }

    protected function getTestToken()
    {
        return 'fake-valid-nonce';
    }

    /**
     * Schema Helpers.
     */
    protected function schema()
    {
        return $this->connection()->getSchemaBuilder();
    }

    protected function connection()
    {
        return Eloquent::getConnectionResolver()->connection();
    }
}

class User extends Eloquent
{
    use \Laravel\Cashier\Billable;
}

class CashierTestControllerStub extends WebhookController
{
    /**
     * Parse the given Braintree webhook notification request.
     *
     * @param  Request  $request
     * @return WebhookNotification
     */
    protected function parseBraintreeNotification($request)
    {
        return json_decode($request->getContent());
    }
}

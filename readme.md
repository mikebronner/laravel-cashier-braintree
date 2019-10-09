> Laravel has ceased official support of this package. We will keep it going as long as possible, as we use it in our projects.

# Laravel Cashier - Braintree Edition
## Introduction

Laravel Cashier Braintree provides an expressive, fluent interface to [Braintree's](https://www.braintreepayments.com/) subscription billing services. It handles almost all of the boilerplate subscription billing code you are dreading writing. In addition to basic subscription management, Cashier Braintree can handle coupons, swapping subscription, cancellation grace periods, and even generate invoice PDFs.

## Testing

You will need to set the following details locally and on your Braintree account in order to run the library's tests.

### Local

#### Environment Variables

    BRAINTREE_MERCHANT_ID=
    BRAINTREE_PUBLIC_KEY=
    BRAINTREE_PRIVATE_KEY=
    BRAINTREE_MODEL=Laravel\Cashier\Tests\User
    
You can set these variables in the `phpunit.xml.dist` file.

### Braintree

#### Plans

    * Plan ID: monthly-10-1, Price: $10, Billing cycle of every month
    * Plan ID: monthly-10-2, Price: $10, Billing cycle of every month
    * Plan ID: yearly-100-1, Price: $100, Billing cycle of every 12 months

#### Discount

    * Discount ID: coupon-1, Price: $5
    * Discount ID: plan-credit, Price $1

#### Settings

- Disable duplicate checking:
    `Settings > Processing > Transactions`: Uncheck `Duplicate Transaction
    Checking`.

## Official Documentation

Documentation for Cashier Braintree can be found on the [Laravel website](https://laravel.com/docs/5.8/braintree).

## License

Laravel Cashier Braintree is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

# Documentation

Laravel Cashier provides an expressive, fluent interface to Stripe's and Braintree's subscription billing services. It handles almost all of the boilerplate subscription billing code you are dreading writing. In addition to basic subscription management, Cashier can handle coupons, swapping subscription, subscription "quantities", cancellation grace periods, and even generate invoice PDFs.


If you're only performing "one-off" charges and do not offer subscriptions, you should not use Cashier. Instead, use the Stripe and Braintree SDKs directly.
Upgrading Cashier

When upgrading to a new major version of the Cashier, it's important that you carefully review the upgrade guide.

## Configuration

### Braintree

#### Braintree Caveats

For many operations, the Stripe and Braintree implementations of Cashier function the same. Both services provide subscription billing with credit cards but Braintree also supports payments via PayPal. However, Braintree also lacks some features that are supported by Stripe. You should keep the following in mind when deciding to use Stripe or Braintree:

Braintree supports PayPal while Stripe does not.
Braintree does not support the increment and decrement methods on subscriptions. This is a Braintree limitation, not a Cashier limitation.
Braintree does not support percentage based discounts. This is a Braintree limitation, not a Cashier limitation.

#### Composer

First, add the Cashier package for Braintree to your dependencies:

```php
composer require "laravel/cashier-braintree":"^4.0"
```

#### Plan Credit Coupon

Before using Cashier with Braintree, you will need to define a plan-credit discount in your Braintree control panel. This discount will be used to properly prorate subscriptions that change from yearly to monthly billing, or from monthly to yearly billing.

The discount amount configured in the Braintree control panel can be any value you wish, as Cashier will override the defined amount with our own custom amount each time we apply the coupon. This coupon is needed since Braintree does not natively support prorating subscriptions across subscription frequencies.

#### Database Migrations

Before using Cashier, we'll need to prepare the database. We need to add several columns to your users table and create a new subscriptions table to hold all of our customer's subscriptions:

```php
Schema::table('users', function ($table) {
    $table->string('braintree_id')->nullable();
    $table->string('paypal_email')->nullable();
    $table->string('card_brand')->nullable();
    $table->string('card_last_four')->nullable();
    $table->timestamp('trial_ends_at')->nullable();
});

Schema::create('subscriptions', function ($table) {
    $table->increments('id');
    $table->unsignedInteger('user_id');
    $table->string('name');
    $table->string('braintree_id');
    $table->string('braintree_plan');
    $table->integer('quantity');
    $table->timestamp('trial_ends_at')->nullable();
    $table->timestamp('ends_at')->nullable();
    $table->timestamps();
});
```

Once the migrations have been created, run the migrate Artisan command.

#### Billable Model

Next, add the Billable trait to your model definition:

```php
use Laravel\Cashier\Billable;

class User extends Authenticatable
{
    use Billable;
}
```

#### API Keys

Next, you should configure the following options in your services.php file:

```php
'braintree' => [
    'model'  => App\User::class,
    'environment' => env('BRAINTREE_ENV'),
    'merchant_id' => env('BRAINTREE_MERCHANT_ID'),
    'public_key' => env('BRAINTREE_PUBLIC_KEY'),
    'private_key' => env('BRAINTREE_PRIVATE_KEY'),
],
```

Then you should add the following Braintree SDK calls to your AppServiceProvider service provider's boot method:

```php
\Braintree_Configuration::environment(config('services.braintree.environment'));
\Braintree_Configuration::merchantId(config('services.braintree.merchant_id'));
\Braintree_Configuration::publicKey(config('services.braintree.public_key'));
\Braintree_Configuration::privateKey(config('services.braintree.private_key'));
```

### Currency Configuration

The default Cashier currency is United States Dollars (USD). You can change the default currency by calling the Cashier::useCurrency method from within the boot method of one of your service providers. The useCurrency method accepts two string parameters: the currency and the currency's symbol:

```php
use Laravel\Cashier\Cashier;

Cashier::useCurrency('eur', '€');
```

## Subscriptions

### Creating Subscriptions

To create a subscription, first retrieve an instance of your billable model, which typically will be an instance of App\User. Once you have retrieved the model instance, you may use the newSubscription method to create the model's subscription:

```php
$user = User::find(1);

$user->newSubscription('main', 'premium')->create($stripeToken);
```

The first argument passed to the newSubscription method should be the name of the subscription. If your application only offers a single subscription, you might call this main or primary. The second argument is the specific Stripe / Braintree plan the user is subscribing to. This value should correspond to the plan's identifier in Stripe or Braintree.

The create method, which accepts a Stripe credit card / source token, will begin the subscription as well as update your database with the customer ID and other relevant billing information.

#### Additional User Details

If you would like to specify additional customer details, you may do so by passing them as the second argument to the create method:

```php
$user->newSubscription('main', 'monthly')->create($stripeToken, [
    'email' => $email,
]);
```

To learn more about the additional fields supported by Stripe or Braintree, check out Stripe's documentation on customer creation or the corresponding Braintree documentation.

#### Coupons

If you would like to apply a coupon when creating the subscription, you may use the withCoupon method:

```php
$user->newSubscription('main', 'monthly')
     ->withCoupon('code')
     ->create($stripeToken);
```
## Checking Subscription Status

Once a user is subscribed to your application, you may easily check their subscription status using a variety of convenient methods. First, the subscribed method returns true if the user has an active subscription, even if the subscription is currently within its trial period:

```php
if ($user->subscribed('main')) {
    //
}
```

The subscribed method also makes a great candidate for a route middleware, allowing you to filter access to routes and controllers based on the user's subscription status:

```php
public function handle($request, Closure $next)
{
    if ($request->user() && ! $request->user()->subscribed('main')) {
        // This user is not a paying customer...
        return redirect('billing');
    }

    return $next($request);
}
```

If you would like to determine if a user is still within their trial period, you may use the onTrial method. This method can be useful for displaying a warning to the user that they are still on their trial period:

```php
if ($user->subscription('main')->onTrial()) {
    //
}
```

The subscribedToPlan method may be used to determine if the user is subscribed to a given plan based on a given Stripe / Braintree plan ID. In this example, we will determine if the user's main subscription is actively subscribed to the monthly plan:

```php
if ($user->subscribedToPlan('monthly', 'main')) {
    //
}
```

### Cancelled Subscription Status

To determine if the user was once an active subscriber, but has cancelled their subscription, you may use the cancelled method:

```php
if ($user->subscription('main')->cancelled()) {
    //
}
```

You may also determine if a user has cancelled their subscription, but are still on their "grace period" until the subscription fully expires. For example, if a user cancels a subscription on March 5th that was originally scheduled to expire on March 10th, the user is on their "grace period" until March 10th. Note that the subscribed method still returns true during this time:

```php
if ($user->subscription('main')->onGracePeriod()) {
    //
}
```

### Changing Plans

After a user is subscribed to your application, they may occasionally want to change to a new subscription plan. To swap a user to a new subscription, pass the plan's identifier to the swap method:

```php
$user = App\User::find(1);

$user->subscription('main')->swap('provider-plan-id');
```

If the user is on trial, the trial period will be maintained. Also, if a "quantity" exists for the subscription, that quantity will also be maintained.

If you would like to swap plans and cancel any trial period the user is currently on, you may use the skipTrial method:

```php
$user->subscription('main')
        ->skipTrial()
        ->swap('provider-plan-id');
```

### Subscription Quantity

Subscription quantities are only supported by the Stripe edition of Cashier. Braintree does not have a feature that corresponds to Stripe's "quantity".
Sometimes subscriptions are affected by "quantity". For example, your application might charge $10 per month per user on an account. To easily increment or decrement your subscription quantity, use the incrementQuantity and decrementQuantity methods:

```php
$user = User::find(1);

$user->subscription('main')->incrementQuantity();

// Add five to the subscription's current quantity...
$user->subscription('main')->incrementQuantity(5);

$user->subscription('main')->decrementQuantity();

// Subtract five to the subscription's current quantity...
$user->subscription('main')->decrementQuantity(5);
```

Alternatively, you may set a specific quantity using the updateQuantity method:

```php
$user->subscription('main')->updateQuantity(10);
```

The noProrate method may be used to update the subscription's quantity without pro-rating the charges:

```php
$user->subscription('main')->noProrate()->updateQuantity(10);
```

For more information on subscription quantities, consult the Stripe documentation.

### Subscription Taxes

To specify the tax percentage a user pays on a subscription, implement the taxPercentage method on your billable model, and return a numeric value between 0 and 100, with no more than 2 decimal places.

```php
public function taxPercentage() {
    return 20;
}
```

The taxPercentage method enables you to apply a tax rate on a model-by-model basis, which may be helpful for a user base that spans multiple countries and tax rates.

The taxPercentage method only applies to subscription charges. If you use Cashier to make "one off" charges, you will need to manually specify the tax rate at that time.

#### Syncing Tax Percentages

When changing the hard-coded value returned by the taxPercentage method, the tax settings on any existing subscriptions for the user will remain the same. If you wish to update the tax value for existing subscriptions with the returned taxPercentage value, you should call the syncTaxPercentage method on the user's subscription instance:

```php
$user->subscription('main')->syncTaxPercentage();
```

### Subscription Anchor Date

Modifying the subscription anchor date is only supported by the Stripe edition of Cashier.
By default, the billing cycle anchor is the date the subscription was created, or if a trial period is used, the date that the trial ends. If you would like to modify the billing anchor date, you may use the anchorBillingCycleOn method:

```php
use App\User;
use Carbon\Carbon;

$user = User::find(1);

$anchor = Carbon::parse('first day of next month');

$user->newSubscription('main', 'premium')
            ->anchorBillingCycleOn($anchor->startOfDay())
            ->create($stripeToken);
```

For more information on managing subscription billing cycles, consult the Stripe billing cycle documentation

### Cancelling Subscriptions

To cancel a subscription, call the cancel method on the user's subscription:

```php
$user->subscription('main')->cancel();
```

When a subscription is cancelled, Cashier will automatically set the ends_at column in your database. This column is used to know when the subscribed method should begin returning false. For example, if a customer cancels a subscription on March 1st, but the subscription was not scheduled to end until March 5th, the subscribed method will continue to return true until March 5th.

You may determine if a user has cancelled their subscription but are still on their "grace period" using the onGracePeriod method:

```php
if ($user->subscription('main')->onGracePeriod()) {
    //
}
```

If you wish to cancel a subscription immediately, call the cancelNow method on the user's subscription:

```php
$user->subscription('main')->cancelNow();
```

### Resuming Subscriptions

If a user has cancelled their subscription and you wish to resume it, use the resume method. The user must still be on their grace period in order to resume a subscription:

```php
$user->subscription('main')->resume();
```

If the user cancels a subscription and then resumes that subscription before the subscription has fully expired, they will not be billed immediately. Instead, their subscription will be re-activated, and they will be billed on the original billing cycle.

## Subscription Trials

### With Credit Card Up Front

If you would like to offer trial periods to your customers while still collecting payment method information up front, you should use the trialDays method when creating your subscriptions:

```php
$user = User::find(1);

$user->newSubscription('main', 'monthly')
            ->trialDays(10)
            ->create($stripeToken);
```

This method will set the trial period ending date on the subscription record within the database, as well as instruct Stripe / Braintree to not begin billing the customer until after this date.

If the customer's subscription is not cancelled before the trial ending date they will be charged as soon as the trial expires, so you should be sure to notify your users of their trial ending date.
The trialUntil method allows you to provide a DateTime instance to specify when the trial period should end:

```php
use Carbon\Carbon;

$user->newSubscription('main', 'monthly')
            ->trialUntil(Carbon::now()->addDays(10))
            ->create($stripeToken);
```

You may determine if the user is within their trial period using either the onTrial method of the user instance, or the onTrial method of the subscription instance. The two examples below are identical:

```php
if ($user->onTrial('main')) {
    //
}

if ($user->subscription('main')->onTrial()) {
    //
}
```

### Without Credit Card Up Front

If you would like to offer trial periods without collecting the user's payment method information up front, you may set the trial_ends_at column on the user record to your desired trial ending date. This is typically done during user registration:

```php
$user = User::create([
    // Populate other user properties...
    'trial_ends_at' => now()->addDays(10),
]);
```

Be sure to add a date mutator for trial_ends_at to your model definition.
Cashier refers to this type of trial as a "generic trial", since it is not attached to any existing subscription. The onTrial method on the User instance will return true if the current date is not past the value of trial_ends_at:

```php
if ($user->onTrial()) {
    // User is within their trial period...
}
```

You may also use the onGenericTrial method if you wish to know specifically that the user is within their "generic" trial period and has not created an actual subscription yet:

```php
if ($user->onGenericTrial()) {
    // User is within their "generic" trial period...
}
```

Once you are ready to create an actual subscription for the user, you may use the newSubscription method as usual:

```php
$user = User::find(1);

$user->newSubscription('main', 'monthly')->create($stripeToken);
```

## Customers

### Creating Customers

Occasionally, you may wish to create a Stripe customer without beginning a subscription. You may accomplish this using the createAsStripeCustomer method:

```php
$user->createAsStripeCustomer();
```

Once the customer has been created in Stripe, you may begin a subscription at a later date.

The Braintree equivalent of this method is the createAsBraintreeCustomer method.

## Cards

### Retrieving Credit Cards

The cards method on the billable model instance returns a collection of Laravel\Cashier\Card instances:

```php
$cards = $user->cards();
```

To retrieve the default card, the defaultCard method may be used;

```php
$card = $user->defaultCard();
```

### Determining If A Card Is On File

You may check if a customer has a credit card attached to their account using the hasCardOnFile method:

```php
if ($user->hasCardOnFile()) {
    //
}
```

### Updating Credit Cards

The updateCard method may be used to update a customer's credit card information. This method accepts a Stripe token and will assign the new credit card as the default billing source:

```php
$user->updateCard($stripeToken);
```

To sync your card information with the customer's default card information in Stripe, you may use the updateCardFromStripe method:

```php
$user->updateCardFromStripe();
```

### Deleting Credit Cards

To delete a card, you should first retrieve the customer's cards with the cards method. Then, you may call the delete method on the card instance you wish to delete:

```php
foreach ($user->cards() as $card) {
    $card->delete();
}
```

If you delete the default card, please make sure that you sync the new default card with your database using the updateCardFromStripe method.
The deleteCards method will delete all of the card information stored by your application:

```php
$user->deleteCards();
```

If the user has an active subscription, you should consider preventing them from deleting the last remaining payment source.

## Handling Braintree Webhooks

Both Stripe and Braintree can notify your application of a variety of events via webhooks. To handle Braintree webhooks, define a route that points to Cashier's webhook controller. This controller will handle all incoming webhook requests and dispatch them to the proper controller method:

```php
Route::post(
    'braintree/webhook',
    '\Laravel\Cashier\Http\Controllers\WebhookController@handleWebhook'
);
```

Once you have registered your route, be sure to configure the webhook URL in your Braintree control panel settings.
By default, this controller will automatically handle cancelling subscriptions that have too many failed charges (as defined by your Braintree settings); however, as we'll soon discover, you can extend this controller to handle any webhook event you like.

#### Webhooks & CSRF Protection

Since Braintree webhooks need to bypass Laravel's CSRF protection, be sure to list the URI as an exception in your VerifyCsrfToken middleware or list the route outside of the web middleware group:

```php
protected $except = [
    'braintree/*',
];
```

### Defining Webhook Event Handlers

Cashier automatically handles subscription cancellation on failed charges, but if you have additional Braintree webhook events you would like to handle, extend the Webhook controller. Your method names should correspond to Cashier's expected convention, specifically, methods should be prefixed with handle and the "camel case" name of the Braintree webhook you wish to handle. For example, if you wish to handle the dispute_opened webhook, you should add a handleDisputeOpened method to the controller:

```php
<?php

namespace App\Http\Controllers;

use Braintree\WebhookNotification;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierController;

class WebhookController extends CashierController
{
    /**
     * Handle a new dispute.
     *
     * @param  \Braintree\WebhookNotification  $webhook
     * @return \Symfony\Component\HttpFoundation\Responses
     */
    public function handleDisputeOpened(WebhookNotification $webhook)
    {
        // Handle The Webhook...
    }
}
```

### Failed Subscriptions

What if a customer's credit card expires? No worries - Cashier includes a Webhook controller that can easily cancel the customer's subscription for you. Just point a route to the controller:

```php
Route::post(
    'braintree/webhook',
    '\Laravel\Cashier\Http\Controllers\WebhookController@handleWebhook'
);
```

That's it! Failed payments will be captured and handled by the controller. The controller will cancel the customer's subscription when Braintree determines the subscription has failed (normally after three failed payment attempts). Don't forget: you will need to configure the webhook URI in your Braintree control panel settings.

## Single Charges

### Simple Charge

When using Stripe, the charge method accepts the amount you would like to charge in the lowest denominator of the currency used by your application. However, when using Braintree, you should pass the full dollar amount to the charge method:
If you would like to make a "one off" charge against a subscribed customer's credit card, you may use the charge method on a billable model instance.

```php
// Stripe Accepts Charges In Cents...
$stripeCharge = $user->charge(100);

// Braintree Accepts Charges In Dollars...
$user->charge(1);
```

The charge method accepts an array as its second argument, allowing you to pass any options you wish to the underlying Stripe / Braintree charge creation. Consult the Stripe or Braintree documentation regarding the options available to you when creating charges:

```php
$user->charge(100, [
    'custom_option' => $value,
]);
```

The charge method will throw an exception if the charge fails. If the charge is successful, the full Stripe / Braintree response will be returned from the method:

```php
try {
    $response = $user->charge(100);
} catch (Exception $e) {
    //
}
```

### Charge With Invoice

Sometimes you may need to make a one-time charge but also generate an invoice for the charge so that you may offer a PDF receipt to your customer. The invoiceFor method lets you do just that. For example, let's invoice the customer $5.00 for a "One Time Fee":

```php
// Stripe Accepts Charges In Cents...
$user->invoiceFor('One Time Fee', 500);

// Braintree Accepts Charges In Dollars...
$user->invoiceFor('One Time Fee', 5);
```

The invoice will be charged immediately against the user's credit card. The invoiceFor method also accepts an array as its third argument. This array contains the billing options for the invoice item. The fourth argument accepted by the method is also an array. This final argument accepts the billing options for the invoice itself:

```php
$user->invoiceFor('Stickers', 500, [
    'quantity' => 50,
], [
    'tax_percent' => 21,
]);
```

If you are using Braintree as your billing provider, you must include a description option when calling the invoiceFor method:

```php
$user->invoiceFor('One Time Fee', 500, [
    'description' => 'your invoice description here',
]);
```

The invoiceFor method will create a Stripe invoice which will retry failed billing attempts. If you do not want invoices to retry failed charges, you will need to close them using the Stripe API after the first failed charge.

### Refunding Charges

If you need to refund a Stripe charge, you may use the refund method. This method accepts the Stripe charge ID as its only argument:

```php
$stripeCharge = $user->charge(100);

$user->refund($stripeCharge->id);
```

## Invoices

You may easily retrieve an array of a billable model's invoices using the invoices method:

```php
$invoices = $user->invoices();

// Include pending invoices in the results...
$invoices = $user->invoicesIncludingPending();
```

When listing the invoices for the customer, you may use the invoice's helper methods to display the relevant invoice information. For example, you may wish to list every invoice in a table, allowing the user to easily download any of them:

```php
<table>
    @foreach ($invoices as $invoice)
        <tr>
            <td>{{ $invoice->date()->toFormattedDateString() }}</td>
            <td>{{ $invoice->total() }}</td>
            <td><a href="/user/invoice/{{ $invoice->id }}">Download</a></td>
        </tr>
    @endforeach
</table>
```

### Generating Invoice PDFs

From within a route or controller, use the downloadInvoice method to generate a PDF download of the invoice. This method will automatically generate the proper HTTP response to send the download to the browser:

```php
use Illuminate\Http\Request;

Route::get('user/invoice/{invoice}', function (Request $request, $invoiceId) {
    return $request->user()->downloadInvoice($invoiceId, [
        'vendor'  => 'Your Company',
        'product' => 'Your Product',
    ]);
});
```

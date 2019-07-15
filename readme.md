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

## Official Documentation

Documentation for Cashier Braintree can be found on the [Laravel website](https://laravel.com/docs/5.8/braintree).

## License

Laravel Cashier Braintree is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

# Release Notes

## [v3.0.1 (2019-02-04)](https://github.com/laravel/cashier-braintree/compare/v3.0.0...v3.0.1)

### Changed
- Convert `or` to `??` ([#65](https://github.com/laravel/cashier-braintree/pull/65)) 
- Remove return types from WebhookController ([#68](https://github.com/laravel/cashier-braintree/pull/68))

### Fixed
- Fix return type for `getSubscriptionById` on the `WebhookController` ([#66](https://github.com/laravel/cashier-braintree/pull/66))

## [v3.0.0 (2018-12-13)](https://github.com/laravel/cashier-braintree/compare/v2.1.0...v3.0.0)

### Added
- Added support for PHP 7.3 ([#62](https://github.com/laravel/cashier-braintree/pull/62))

### Changed
- Minimum PHP version is now 7.1.3 ([#62](https://github.com/laravel/cashier-braintree/pull/62))
- Minimum Laravel version is now 5.7 ([#62](https://github.com/laravel/cashier-braintree/pull/62))
- The `hasAddon` on the `Invoice` object has been renamed to `hasAddOn` ([#62](https://github.com/laravel/cashier-braintree/pull/62))
- The `$storagePath` parameter was removed from the `downloadInvoice` method on the `Billable` trait ([#62](https://github.com/laravel/cashier-braintree/pull/62))
- Various return types were added ([#62](https://github.com/laravel/cashier-braintree/pull/62))

### Fixed
- Various DocBlocks were fixed ([#62](https://github.com/laravel/cashier-braintree/pull/62))

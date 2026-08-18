<?php

declare(strict_types=1);

namespace Univapay\Migrate;

/**
 * Composer package names referenced by `bin/univapay-migrate` and the Rector configuration.
 *
 * Centralized here so a future new-SDK package name change is a one-line edit.
 */
final class PackageNames
{
    /**
     * The legacy hand-written SDK being migrated away from.
     */
    public const OLD_SDK = 'univapay/php-sdk';

    /**
     * The runtime compat package (`require`, production) that reimplements OLD_SDK's public
     * surface on top of the new APIMatic-generated SDK.
     */
    public const COMPAT = 'univapay/univapay-sdk-compat';

    /**
     * The new APIMatic-generated SDK is published as `univapay/client-sdk`, sourced from
     * github.com/univapay/univapay-client-php-sdk. Do NOT use the repo name as the composer
     * name, and never publish under `univapay/univapay-client-php-sdk` — it would create a
     * second, competing package.
     *
     * The docs repo's generated sdk/php/composer.json still declares the APIMatic placeholder
     * `apimatic-sdks/univapaypublicapi` (never published); the compat package's own
     * composer.json `require` should be aligned to `univapay/client-sdk` once it stops using a
     * local path repository.
     */
    public const NEW_SDK = 'univapay/client-sdk';
}

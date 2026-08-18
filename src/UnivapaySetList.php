<?php

declare(strict_types=1);

namespace Univapay\Migrate;

/**
 * Rector set-list constants for consumer `rector.php` files, in the style of
 * `rector/rector`'s own `Rector\Set\ValueObject\SetList` convention.
 *
 * Usage in a consumer's rector-univapay.php (see config/rector-template.php):
 *
 *     use Univapay\Migrate\UnivapaySetList;
 *
 *     return static function (RectorConfig $rectorConfig): void {
 *         $rectorConfig->sets([UnivapaySetList::PHP_SDK_TO_COMPAT]);
 *     };
 */
final class UnivapaySetList
{
    /**
     * The one set that rewrites `univapay/php-sdk` usages to `univapay/univapay-sdk-compat`
     * equivalents: RenameClassRector (ClassMap::SUPPORTED) + SeparateMultiUseImportsRector
     * pre-pass + the string-FQCN rename rule and the flag rules.
     *
     * @var string
     */
    public const PHP_SDK_TO_COMPAT = __DIR__ . '/../config/sets/php-sdk-to-compat.php';

    /**
     * The second-hop set that flags (and, where NativeClassMap::SUPPORTED ever gains entries,
     * renames) `univapay/univapay-sdk-compat` usages against the native, APIMatic-generated
     * `univapay/client-sdk`. Review-assisted, not drop-in -- see NativeClassMap's own doc block.
     * Invoked via `bin/univapay-migrate --phase2` (a separate, non-mutating-by-default
     * invocation; see that script's own doc comment for the full step order).
     *
     * @var string
     */
    public const COMPAT_TO_NATIVE = __DIR__ . '/../config/sets/compat-to-native.php';
}

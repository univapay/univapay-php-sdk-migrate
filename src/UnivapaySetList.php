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
}

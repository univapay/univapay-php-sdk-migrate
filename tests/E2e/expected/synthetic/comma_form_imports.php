<?php

namespace UnivapayConsumer\Imports;

use Univapay\Compat\Enums\OnlineBrand;
use Univapay\Compat\Enums\ConvenienceStore;
use Univapay\Compat\Errors\UnivapayLogicError;
use Univapay\Compat\Errors\UnivapayValidationError;

/**
 * E2E synthetic fixture: comma-form multi-imports (`use A, B;`), handled by the built-in
 * SeparateMultiUseImportsRector pre-pass (registered ahead of the rename in
 * config/sets/php-sdk-to-compat.php), across a wider set (three classes across two namespaces)
 * than the isolated unit fixture covers.
 *
 * (Doc comment placed after the imports, not before -- see synthetic/internal_api_usage.php's
 * note on why.)
 */
class CommaFormImportsUser
{
    public function describe(): array
    {
        return [OnlineBrand::PAYPAY(), ConvenienceStore::LAWSON()];
    }

    public function guard(callable $fn): void
    {
        try {
            $fn();
        } catch (UnivapayLogicError $e) {
            error_log('logic error: ' . $e->getMessage());
        } catch (UnivapayValidationError $e) {
            error_log('validation error: ' . $e->getMessage());
        }
    }
}

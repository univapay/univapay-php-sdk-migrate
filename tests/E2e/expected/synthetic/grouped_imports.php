<?php

namespace UnivapayConsumer\Imports;

use Univapay\Compat\Enums\CardBrand;
use Univapay\Compat\Enums\CardType;
use Univapay\Compat\Enums\ChargeStatus;
use Univapay\Compat\Errors\UnivapayServerError;
use Univapay\Compat\Errors\UnivapayNotFoundError;

/**
 * E2E synthetic fixture: bracket-form group imports (`use Namespace\{A, B, C};`). Exercises
 * SeparateGroupUseImportsRector (a custom pre-pass -- no built-in Rector 2.6.2 rule handles this
 * node shape, see NOTES.md's GroupUse section) ahead of the rename, across a wider group (three
 * enums plus a mixed-depth group) than the isolated unit fixture covers: a file with more than one
 * group-use statement requires splicing them in reverse key order, or only the FIRST group-use
 * statement in the file gets split correctly (see NOTES.md).
 *
 * (Doc comment placed after the imports, not before -- see synthetic/internal_api_usage.php's
 * note on why.)
 */
class GroupedImportsUser
{
    public function classify(string $brand, string $type, string $status): array
    {
        return [
            $brand === CardBrand::VISA()->getValue(),
            $type === CardType::CREDIT()->getValue(),
            $status === ChargeStatus::SUCCESSFUL()->getValue(),
        ];
    }

    public function handle(callable $fn): void
    {
        try {
            $fn();
        } catch (UnivapayServerError | UnivapayNotFoundError $e) {
            error_log($e->getMessage());
        }
    }
}

<?php

namespace UnivapayConsumer\Unresolvable;

use Univapay\UnivapayClient;

/**
 * E2E synthetic fixture: unsupported-looking method calls on a receiver whose type cannot be
 * statically resolved (untyped parameter / mixed return from an unresolvable call chain) --
 * exercises the `(verify)` warning path, distinct from every other fixture in this corpus which
 * produces *confirmed* flags. The file references `UnivapayClient` elsewhere so the precision
 * gate (MarkerCommentTrait::currentFileReferencesUnivapayNamespace) is satisfied and these are
 * not silently dropped as "unrelated file" misses.
 *
 * (Doc comment placed after the imports, not before -- see synthetic/internal_api_usage.php's
 * note on why.)
 */
class GenericRepository
{
    /**
     * @param mixed $anything an untyped result from some other layer -- could be a Univapay
     *     resource, could be an unrelated repository object; Rector cannot tell statically.
     */
    public function process($anything): void
    {
        // Same method name as the unsupported UnivapayClient::getTransfer -- unresolvable
        // receiver, so this must be a `(verify)` warning, not a confirmed flag.
        $anything->getTransfer('some-id');
    }

    public function processDynamic($record): void
    {
        $record->listStatusChanges();
    }

    public function known(UnivapayClient $client, string $transferId): void
    {
        // A definite, correctly-typed receiver alongside the unresolvable ones above, so this
        // file exercises both the confirmed and (verify) branches together.
        $client->getTransfer($transferId);
    }
}

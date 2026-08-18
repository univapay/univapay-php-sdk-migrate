<?php

namespace UnivapayConsumer\Unsupported;

use Univapay\Resources\BankAccount;
use Univapay\Resources\Ledger;
use Univapay\Resources\Mixins\GetBankAccounts;
use Univapay\Resources\Mixins\GetLedgers;
use Univapay\Resources\Mixins\GetStatusChanges;
use Univapay\Resources\Mixins\GetTransfers;
use Univapay\Resources\PaymentMethod\ApplePayPayment;
use Univapay\Resources\Transfer;
use Univapay\Resources\TransferStatusChange;
use Univapay\Resources\Charge;
use Univapay\UnivapayClient;

/**
 * E2E synthetic fixture: exhaustive showcase of every entry in ClassMap::UNSUPPORTED_CLASSES and
 * ClassMap::UNSUPPORTED_METHODS, each on an explicitly-typed receiver so every flag below is a
 * *confirmed* unsupported-feature marker, never an unresolved-receiver ("verify") one -- the real
 * examples (examples/fetch_data.php) only exercise `listTransfers`/`getTransfer`/`listBankAccounts`/
 * `getBankAccount` on the client, leaving the rest of the unsupported surface (TransferStatusChange,
 * Ledger, the three transfer/ledger mixins, ApplePayPayment, qrMerchantToken, the GetBankAccounts
 * mixin, and BankAccount's typo'd `listBankAccountContextsByOptions`) uncovered end to end
 * otherwise.
 *
 * NOTE: deliberately not spelling out the literal marker text here (with its leading `@` sigil)
 * -- bin/univapay-migrate's report-section counters are a dumb per-line substring scan (see
 * postScan()), so writing the real marker string in a doc comment would inflate the reported
 * counts by matching this prose, not just the genuine generated markers below.
 *
 * (Doc comment placed after the imports, not before -- see synthetic/internal_api_usage.php's
 * note on why.)
 */
class TransferLikeThing
{
    use GetTransfers;
    use GetLedgers;
    use GetStatusChanges;
}

class BankAccountLikeThing
{
    use GetBankAccounts;
}

class UnsupportedFeaturesShowcase
{
    public function classReferences(
        Transfer $transfer,
        TransferStatusChange $statusChange,
        Ledger $ledger,
        ApplePayPayment $applePay,
        BankAccount $bankAccount
    ): void {
        // Bare class references alone (type hints) are flagged regardless of any method call --
        // see FlagUnsupportedFeatureRector's Name-node branch.
    }

    public function clientMethods(UnivapayClient $client): void
    {
        $client->getTransfer('11e756f4-ed34-6152-970d-77c75a0f7890');
        $client->listTransfers();
        $client->listTransfersByOptions(['limit' => 10]);
        $client->getBankAccount('11ef0000-0000-4000-8000-000000000050');
        $client->listBankAccounts();
        $client->listBankAccountContextsByOptions(['limit' => 10]);
    }

    public function transferMethods(Transfer $transfer): void
    {
        $transfer->listLedgers();
        $transfer->listLedgersByOptions(['limit' => 10]);
        $transfer->listStatusChanges();
        $transfer->listStatusChangesByOptions(['limit' => 10]);
    }

    public function qrMerchantToken(Charge $charge): void
    {
        // Charge itself is fully supported -- only this one deprecated method is not.
        $charge->qrMerchantToken();
    }

    public function applePayCreation(ApplePayPayment $applePay): void
    {
        // Constructing the payment-method value object works; only token *creation* against it
        // throws at runtime in the compat layer.
    }
}

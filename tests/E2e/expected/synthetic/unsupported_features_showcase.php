<?php

namespace UnivapayConsumer\Unsupported;

use Univapay\Compat\Resources\Mixins\GetTransfers;
use Univapay\Compat\Resources\Mixins\GetLedgers;
use Univapay\Compat\Resources\Mixins\GetStatusChanges;
use Univapay\Compat\Resources\Mixins\GetBankAccounts;
use Univapay\Compat\Resources\Transfer;
use Univapay\Compat\Resources\TransferStatusChange;
use Univapay\Compat\Resources\Ledger;
use Univapay\Compat\Resources\PaymentMethod\ApplePayPayment;
use Univapay\Compat\Resources\BankAccount;
use Univapay\Compat\UnivapayClient;
use Univapay\Compat\Resources\Charge;

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
    // @univapay-migrate:unsupported GetTransfers — this throws UnivapayUnsupportedFeatureError at runtime. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#unsupported-features
    use GetTransfers;
    // @univapay-migrate:unsupported GetLedgers — this throws UnivapayUnsupportedFeatureError at runtime. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#unsupported-features
    use GetLedgers;
    // @univapay-migrate:unsupported GetStatusChanges — this throws UnivapayUnsupportedFeatureError at runtime. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#unsupported-features
    use GetStatusChanges;
}

class BankAccountLikeThing
{
    // @univapay-migrate:unsupported GetBankAccounts — this throws UnivapayUnsupportedFeatureError at runtime. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#unsupported-features
    use GetBankAccounts;
}

class UnsupportedFeaturesShowcase
{
    // @univapay-migrate:unsupported Transfer — this throws UnivapayUnsupportedFeatureError at runtime. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#unsupported-features
    // @univapay-migrate:unsupported TransferStatusChange — this throws UnivapayUnsupportedFeatureError at runtime. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#unsupported-features
    // @univapay-migrate:unsupported Ledger — this throws UnivapayUnsupportedFeatureError at runtime. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#unsupported-features
    // @univapay-migrate:unsupported ApplePayPayment — this throws UnivapayUnsupportedFeatureError at runtime. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#unsupported-features
    // @univapay-migrate:unsupported BankAccount — this throws UnivapayUnsupportedFeatureError at runtime. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#unsupported-features
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
        // @univapay-migrate:unsupported getTransfer — this throws UnivapayUnsupportedFeatureError at runtime. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#unsupported-features
        $client->getTransfer('11e756f4-ed34-6152-970d-77c75a0f7890');
        // @univapay-migrate:unsupported listTransfers — this throws UnivapayUnsupportedFeatureError at runtime. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#unsupported-features
        $client->listTransfers();
        // @univapay-migrate:unsupported listTransfersByOptions — this throws UnivapayUnsupportedFeatureError at runtime. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#unsupported-features
        $client->listTransfersByOptions(['limit' => 10]);
        // @univapay-migrate:unsupported getBankAccount — this throws UnivapayUnsupportedFeatureError at runtime. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#unsupported-features
        $client->getBankAccount('11ef0000-0000-4000-8000-000000000050');
        // @univapay-migrate:unsupported listBankAccounts — this throws UnivapayUnsupportedFeatureError at runtime. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#unsupported-features
        $client->listBankAccounts();
        // @univapay-migrate:unsupported listBankAccountContextsByOptions — this throws UnivapayUnsupportedFeatureError at runtime. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#unsupported-features
        $client->listBankAccountContextsByOptions(['limit' => 10]);
    }

    // @univapay-migrate:unsupported Transfer — this throws UnivapayUnsupportedFeatureError at runtime. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#unsupported-features
    public function transferMethods(Transfer $transfer): void
    {
        // @univapay-migrate:unsupported listLedgers — this throws UnivapayUnsupportedFeatureError at runtime. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#unsupported-features
        $transfer->listLedgers();
        // @univapay-migrate:unsupported listLedgersByOptions — this throws UnivapayUnsupportedFeatureError at runtime. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#unsupported-features
        $transfer->listLedgersByOptions(['limit' => 10]);
        // @univapay-migrate:unsupported listStatusChanges — this throws UnivapayUnsupportedFeatureError at runtime. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#unsupported-features
        $transfer->listStatusChanges();
        // @univapay-migrate:unsupported listStatusChangesByOptions — this throws UnivapayUnsupportedFeatureError at runtime. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#unsupported-features
        $transfer->listStatusChangesByOptions(['limit' => 10]);
    }

    public function qrMerchantToken(Charge $charge): void
    {
        // Charge itself is fully supported -- only this one deprecated method is not.
        // @univapay-migrate:unsupported qrMerchantToken — this throws UnivapayUnsupportedFeatureError at runtime. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#unsupported-features
        $charge->qrMerchantToken();
    }

    // @univapay-migrate:unsupported ApplePayPayment — this throws UnivapayUnsupportedFeatureError at runtime. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#unsupported-features
    public function applePayCreation(ApplePayPayment $applePay): void
    {
        // Constructing the payment-method value object works; only token *creation* against it
        // throws at runtime in the compat layer.
    }
}

<?php

namespace UnivapayConsumer\Subscriptions;

use Univapay\Enums\InstallmentPlanType;
use Univapay\Enums\Period;
use Univapay\Enums\SubscriptionPlanType;
use Univapay\Enums\SubscriptionStatus;
use Univapay\Enums\TokenType;
use Univapay\Resources\Authentication\AppJWT;
use Univapay\Resources\PaymentData\Address;
use Univapay\Resources\PaymentData\PhoneNumber;
use Univapay\Resources\PaymentMethod\CardPayment;
use Univapay\Resources\Store;
use Univapay\Resources\Subscription;
use Univapay\Resources\Subscription\InstallmentPlan;
use Univapay\Resources\Subscription\ScheduleSettings;
use Univapay\Resources\Subscription\SubscriptionPlan;
use Univapay\UnivapayClient;
use Money\Money;

/**
 * E2E synthetic fixture: subscriptions with plans/schedule -- surface the real
 * examples/create_subscription.php and examples/3ds/create_subscription.php only exercise
 * partially. Adds InstallmentPlan (not used anywhere in the real examples/README) alongside
 * ScheduleSettings + SubscriptionPlan, plus Subscription-resource-level methods
 * (patch/cancel/isEditable) and the Store::getSubscription mixin path.
 *
 * (Doc comment placed after the imports, not before -- see synthetic/internal_api_usage.php's
 * note on why. `AppJWT` is used below via `makeClient()` specifically so this import isn't dead
 * weight: an entirely unused-but-real old-SDK import survives unrenamed and unflagged by Rector's
 * rename rule, which is a separate, already-documented finding this fixture avoids so it can stay
 * focused on its own subject.)
 */
class SubscriptionPlansAndScheduleUser
{
    public function makeClient(): UnivapayClient
    {
        return new UnivapayClient(AppJWT::createToken('token', 'secret'));
    }

    public function createWithFixedCycles(UnivapayClient $client): Subscription
    {
        $paymentMethod = new CardPayment(
            'test@test.com',
            'PHP e2e fixture',
            '4242424242424242',
            '02',
            '2030',
            '123',
            TokenType::SUBSCRIPTION(),
            null,
            new Address('line 1', 'line 2', 'tokyo', 'tokyo', 'jp', '101-1111'),
            new PhoneNumber(PhoneNumber::JP, '12910298309128')
        );
        $token = $client->createToken($paymentMethod);

        return $token->createSubscription(
            Money::JPY(20000),
            Period::MONTHLY(),
            Money::JPY(10000),
            new ScheduleSettings(
                date_create('+1 month'),
                new \DateTimeZone('Asia/Tokyo'),
                true, // preserveEndOfMonth
                new \DateInterval('P3D')
            ),
            new SubscriptionPlan(
                SubscriptionPlanType::FIXED_CYCLES(),
                12
            )
        );
    }

    public function createWithInstallmentPlan(UnivapayClient $client, string $tokenId): Subscription
    {
        // InstallmentPlan is never exercised in the real examples/ or README -- gap fixture.
        return $client->createSubscription(
            $tokenId,
            Money::JPY(50000),
            Period::QUARTERLY(),
            null,
            null,
            null,
            new InstallmentPlan(InstallmentPlanType::FIXED_CYCLE())
        );
    }

    public function manage(Subscription $subscription): Subscription
    {
        if ($subscription->status === SubscriptionStatus::CURRENT() && $subscription->isEditable()) {
            $subscription = $subscription->patch(Money::JPY(30000));
        }

        return $subscription->cancel();
    }

    public function fromStore(Store $store, string $subscriptionId): Subscription
    {
        // Store::getSubscription mixin path -- distinct receiver from UnivapayClient::getSubscription.
        return $store->getSubscription($subscriptionId);
    }
}

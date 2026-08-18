<?php

namespace UnivapayConsumer\Handlers;

use Univapay\Compat\Requests\Handlers\NetworkRetryHandler;
use Univapay\Compat\Errors\UnivapaySDKError;
use Throwable;
use WpOrg\Requests\Exception as HttpTransportException;

/**
 * E2E synthetic fixture: a consumer-authored custom retry handler catching
 * `WpOrg\Requests\Exception` directly -- the binding-amendment case (plan "Compat semantic-parity
 * amendments", blocker 4). The new transport never throws this exception type on network failure
 * (it throws the compat package's own `UnivapayNetworkError` instead), so a consumer catch block
 * matching on the old transport's exception type silently stops handling/retrying network errors
 * after migration. Distinct from the isolated unit fixture (wp_org_flag.php.inc) in shape: a
 * larger class with more surrounding code, a second catch on an unrelated (correctly renamed)
 * Univapay error type in the same try, and a standalone function (not just a class method).
 *
 * (Doc comment placed after the imports, not before -- see synthetic/internal_api_usage.php's
 * note on why.)
 */
class CustomNetworkRetryHandler extends NetworkRetryHandler
{
    public function handleFailure(callable $request, array $requestData)
    {
        try {
            return $request($requestData);
        } // @univapay-migrate:network-exception WpOrg\Requests\Exception — this exception type is never thrown by the new transport; network failures now throw UnivapayNetworkError instead. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#network-exceptions
        catch (HttpTransportException $e) {
            error_log('transport-level failure, retrying: ' . $e->getMessage());
            return null;
        } catch (UnivapaySDKError $e) {
            error_log('sdk-level failure, not retrying: ' . $e->getMessage());
            throw $e;
        }
    }
}

function isTransportException(Throwable $e): bool
{
    // @univapay-migrate:network-exception WpOrg\Requests\Exception — this exception type is never thrown by the new transport; network failures now throw UnivapayNetworkError instead. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#network-exceptions
    return $e instanceof HttpTransportException;
}

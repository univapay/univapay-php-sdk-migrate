<?php

namespace UnivapayConsumer;

use Univapay\Requests\Handlers\NetworkRetryHandler;
use WpOrg\Requests\Exception as RequestsException;

class WpOrgUser extends NetworkRetryHandler
{
    public function handleFailure(callable $request, array $requestData)
    {
        try {
            return $request($requestData);
        } catch (RequestsException $e) {
            return null;
        }
    }
}

?>
-----
<?php

namespace UnivapayConsumer;

use Univapay\Compat\Requests\Handlers\NetworkRetryHandler;
use WpOrg\Requests\Exception as RequestsException;

class WpOrgUser extends NetworkRetryHandler
{
    public function handleFailure(callable $request, array $requestData)
    {
        try {
            return $request($requestData);
        } // @univapay-migrate:network-exception WpOrg\Requests\Exception — this exception type is never thrown by the new transport; network failures now throw UnivapayNetworkError instead. See https://reference.univapay.com/#/http/onboarding-guides/guides/php-sdk-migration#network-exceptions
        catch (RequestsException $e) {
            return null;
        }
    }
}

?>

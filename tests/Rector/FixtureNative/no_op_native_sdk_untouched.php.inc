<?php

namespace UnivapayConsumer;

use UnivaPay\UnivapayClientSdkClientBuilder;
use UnivaPay\Authentication\BearerAuthCredentialsBuilder;
use UnivaPay\Models\ChargeStatus;

class NativeSdkOnlyUser
{
    public function run(): bool
    {
        $builder = UnivapayClientSdkClientBuilder::init();
        $credentials = BearerAuthCredentialsBuilder::init('secret', 'jwt');
        $status = ChargeStatus::SUCCESSFUL;
        return $status === ChargeStatus::SUCCESSFUL;
    }
}

?>

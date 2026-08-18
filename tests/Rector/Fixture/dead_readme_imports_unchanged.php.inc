<?php

namespace UnivapayConsumer;

// These three FQCNs look plausible from old README snippets but never existed in the real
// univapay/php-sdk tree (see bin/univapay-migrate's DEAD_IMPORTS constant). ClassMap::SUPPORTED
// deliberately has no entry for them, so
// RenameClassRector must leave every line below byte-for-byte untouched -- rewriting a dead
// import would either emit a `use` line for an FQCN with no real target, or (if it happened to
// collide with an already-correctly-imported class elsewhere) a duplicate `use` -- both compile
// errors. Reporting these is the post-scan's job (section (c)), not Rector's.
use Univapay\Client;
use Univapay\RequestsHandlers\RateLimitHandler;
use Univapay\PaymentMethod\CardPayment;

class DeadReadmeImportsUser
{
    public function run(): void
    {
    }
}

?>

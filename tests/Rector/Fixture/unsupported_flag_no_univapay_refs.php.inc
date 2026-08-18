<?php

namespace SomeOtherConsumer;

class UnrelatedOrmEntity
{
    public function run($somethingUntyped): void
    {
        $somethingUntyped->listTransfers();
    }
}

?>

<?php

namespace UnivapayConsumer;

class UnrelatedThing
{
    public $status;

    public function awaitResult(): self
    {
        return $this;
    }
}

class UnrelatedClassUser
{
    public function run(UnrelatedThing $thing): void
    {
        $thing->awaitResult();
        echo $thing->status;
    }
}

?>

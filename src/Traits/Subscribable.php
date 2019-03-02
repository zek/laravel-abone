<?php

namespace Zek\Abone\Traits;

use Zek\Abone\Models\Subscription;

trait Subscribable
{

    public function subscriptions()
    {
        return $this->morphMany(Subscription::class, 'subscribable');
    }

}
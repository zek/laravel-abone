<?php

namespace Zek\Abone\Tests\Fixtures;

use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Money\Currency;
use Money\Money;
use Zek\Abone\Contracts\Subscribable as SubscribableContract;
use Zek\Abone\Traits\HasFeatures;
use Zek\Abone\Traits\Subscribable;

class Plan extends Eloquent implements SubscribableContract
{
    use Subscribable;
    use HasFeatures;

    public function getSubscriptionInterval(): CarbonInterval
    {
        return CarbonInterval::fromString('1 month');
    }

    public function getSubscriptionPrice(): Money
    {
        return new Money($this->price, new Currency($this->currency));
    }

}

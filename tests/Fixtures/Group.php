<?php

namespace Zek\Abone\Tests\Fixtures;

use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Money\Currency;
use Money\Money;
use Zek\Abone\BasicFeature;
use Zek\Abone\Contracts\Subscribable as SubscribableContract;

class Group extends Eloquent implements SubscribableContract
{
    use \Zek\Abone\Traits\Subscribable;

    public static $multipleSubscriptions = true;

    public function getSubscriptionInterval(): CarbonInterval
    {
        return CarbonInterval::fromString('1 month');
    }

    public function getSubscriptionPrice(): Money
    {
        return new Money($this->price, new Currency($this->currency));
    }

    public function getFeatures()
    {
        return [
            'view' => BasicFeature::make('view', 'yes', '1 month'),
            'create' => BasicFeature::make('create', '3', '1 month'),
            'boost' => BasicFeature::make('boost', 'no', '1 month'),
        ];
    }

}

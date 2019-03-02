<?php

namespace Zek\Abone\Contracts;

use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Model;
use Money\Money;

/**
 * Interface Subscribable
 * @package Zek\Wallet\Contracts
 * @mixin Model
 */
interface Subscribable
{

    /**
     * Subscription interval.
     *
     * @return CarbonInterval
     */
    public function getSubscriptionInterval(): ?CarbonInterval;

    /**
     * @return Money
     */
    public function getSubscriptionPrice(): Money;

    /**
     * @return array
     */
    public function getFeatures();

}
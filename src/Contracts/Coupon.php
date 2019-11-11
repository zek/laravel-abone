<?php

namespace Zek\Abone\Contracts;

use Money\Money;

interface Coupon
{

    /**
     * is Coupon can be applied to next subscription intervals
     *
     * @return bool
     */
    public function isRecurring(): bool;

    /**
     * Return a percentage or discount amount as Money
     *
     * @param  Money  $price
     * @param  array  $options
     * @return Money
     */
    public function getDiscountAmount(Money $price, array $options = []): Money;

    /**
     * Mark coupon as used
     * @param  array  $options
     */
    public function markAsUsed(array $options = []): void;

}

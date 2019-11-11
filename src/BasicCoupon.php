<?php

namespace Zek\Abone;

use Money\Money;
use Zek\Abone\Contracts\Coupon;

class BasicCoupon implements Coupon
{
    /**
     * @var string|Money
     */
    protected $amount;

    /**
     * @var bool
     */
    protected $used = false;

    /**
     * @var bool
     */
    private $recurring;

    /**
     * @param  string|Money  $amount
     * @param  bool  $recurring
     */
    public function __construct($amount, bool $recurring = false)
    {
        $this->amount = $amount;
        $this->recurring = $recurring;
    }

    /**
     * is Coupon can be applied to next subscription intervals
     *
     * @return bool
     */
    public function isRecurring(): bool
    {
        return $this->recurring;
    }

    /**
     * Mark coupon as used
     * @param  array  $options
     */
    public function markAsUsed(array $options = []): void
    {
        $this->used = true;
    }

    /**
     * Check if coupon code is used
     *
     * @return bool
     */
    public function isUsed()
    {
        return $this->used;
    }

    /**
     * Return a percentage or discount amount as Money
     *
     * @param  Money  $price
     * @param  array  $options
     * @return Money
     */
    public function getDiscountAmount(Money $price, array $options = []): Money
    {
        $discount = $this->amount;
        if ($discount instanceof Money) {
            return $this->amount->greaterThan($price) ? $price : $discount;
        } else {
            $discount = rtrim($discount, '% ');
            $discount = (min(max($discount, 0), 100)) / 100;
            return $price->multiply($discount);
        }
    }
}

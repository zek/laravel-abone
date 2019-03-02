<?php

namespace Zek\Abone\Builders;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Money\Money;
use Zek\Abone\Contracts\Coupon as CouponContract;
use Zek\Abone\Contracts\Subscribable as SubscribableContract;
use Zek\Abone\Contracts\Subscriber as SubscriberContract;
use Zek\Abone\Exceptions\DowngradeError;
use Zek\Abone\Exceptions\SubscriptionError;
use Zek\Abone\Models\Subscription;

class SubscriptionBuilder
{
    /**
     * The model that is subscribing.
     *
     * @var Model|SubscriberContract
     */
    protected $subscriber;

    /**
     * The model that is being subscribed to.
     *
     * @var Model|SubscribableContract
     */
    protected $subscribable;

    /**
     * The coupon code being applied to the customer.
     *
     * @var CouponContract|null
     */
    protected $coupon;

    /**
     * The metadata to apply to the subscription.
     *
     * @var array|null
     */
    protected $metadata;

    /**
     * The date when subscription starts
     *
     * @var Carbon
     */
    protected $starts_at;

    /**
     * The date when subscription ends
     *
     * @var Carbon|null
     */
    protected $ends_at;

    /**
     * Exchange money if necessary
     *
     * @var boolean
     */
    protected $exchange;

    /**
     * Wallet to charge for payment
     *
     * @var boolean
     */
    protected $wallet;

    /**
     * Create a new subscription builder instance.
     *
     * @param SubscriberContract $subscriber
     * @param SubscribableContract $subscribable
     */
    public function __construct(SubscriberContract $subscriber, SubscribableContract $subscribable)
    {
        $this->subscriber = $subscriber;
        $this->subscribable = $subscribable;
    }


    /**
     * The coupon to apply to a new subscription.
     *
     * @param  CouponContract $coupon
     * @return $this
     */
    public function withCoupon(CouponContract $coupon)
    {
        $this->coupon = $coupon;

        return $this;
    }

    /**
     * Convert currency if currency exchange
     *
     * @param bool $exchange
     * @return $this
     */
    public function exchange($exchange = true)
    {
        $this->exchange = $exchange;
        return $this;
    }

    /**
     * @param string|null $wallet
     * @return $this
     */
    public function wallet(string $wallet = null)
    {
        $this->wallet = $wallet;
        return $this;
    }

    /**
     * The metadata to apply to a new subscription.
     *
     * @param  array $metadata
     * @return $this
     */
    public function withMetadata($metadata)
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * Sets subscription starting date.
     *
     * @param \Carbon\Carbon $starts_at
     * @return $this
     */
    public function startsAt(Carbon $starts_at)
    {
        $this->starts_at = $starts_at;

        return $this;
    }

    /**
     * Marks as subscription infinite
     *
     * @return $this
     */
    public function infinite()
    {
        $this->ends_at = null;

        return $this;
    }


    /**
     * Create a new Subscription.
     *
     * @return \Zek\Abone\Models\Subscription
     * @throws SubscriptionError
     */
    public function create()
    {
        $activeSubscription = $this->subscriber->subscription($this->subscribable);

        if ($activeSubscription) {
            if ($activeSubscription instanceof Collection) {
                if ($activeSubscription->firstWhere('id', $this->subscribable->getKey())) {
                    throw new SubscriptionError('You are already subscribed');
                }
            } else {
                if ($activeSubscription->subscribable->is($this->subscribable)) {
                    throw new SubscriptionError('You are already subscribed');
                }
            }
        }

        $class_name = get_class($this->subscribable);

        if ($activeSubscription && !($class_name::$multipleSubscriptions ?? false)) {
            $method = Str::camel('prorate_' . ($class_name::$subscriptionProrate ?? 'basic'));
            return call_user_func([$this, $method], $activeSubscription);
        }
        $price = $this->getDiscountedPrice();
        return $this->storeAndCharge($price);
    }

    /**
     * @return Subscription
     * @throws SubscriptionError
     */
    public function extend()
    {
        $activeSubscription = $this->subscriber->subscription($this->subscribable);
        if (!$activeSubscription || !$activeSubscription->exists()) {
            throw new SubscriptionError('Invalid subscription');
        }

        return DB::transaction(function () use ($activeSubscription) {
            $this->subscriber
                ->newTransaction($activeSubscription->getRenewalPrice())
                ->hint('subscription.extend')
                ->exchange($this->exchange)
                ->wallet($this->wallet)
                ->references($activeSubscription)
                ->charge();

            $activeSubscription->ends_at = $activeSubscription->ends_at
                ->add($activeSubscription->interval);
            $activeSubscription->save();
            return $activeSubscription;
        });
    }

    /**
     * @param Subscription $activeSub
     * @return \Zek\Abone\Models\Subscription
     * @throws DowngradeError
     */
    public function prorateBasic(Subscription $activeSub)
    {
        if ($this->subscribable->getSubscriptionPrice()->lessThan($activeSub->getRenewalPrice())) {
            throw new DowngradeError('New subscription must be higher then current one');
        }

        $usageLeft = $activeSub->usageLeft();
        $refundDiscount = $activeSub->lastPaid()->multiply($usageLeft);

        $price = $this->getDiscountedPrice();
        if ($refundDiscount->greaterThanOrEqual($price)) {
            $price = $price->multiply(0);
        } else {
            $price = $price->subtract($refundDiscount);
        }

        $activeSub->cancelNow();

        return $this->storeAndCharge($price, 'subscription.upgrade');
    }


    /**
     * @return \Zek\Abone\Models\Subscription
     */
    protected function store()
    {
        $starts_at = $this->starts_at ?? Carbon::now();
        $interval = $this->subscribable->getSubscriptionInterval();

        if ($this->coupon && $this->coupon->isRecurring()) {
            $renewal_price = $this->getDiscountedPrice();
        } else {
            $renewal_price = $this->subscribable->getSubscriptionPrice();
        }

        /** @var Subscription $subscription */
        $subscription = $this->subscriber->subscriptions()->create([
            'subscribable_type' => $this->subscribable->getMorphClass(),
            'subscribable_id' => $this->subscribable->getKey(),
            'renewal_amount' => $renewal_price->getAmount(),
            'currency' => $renewal_price->getCurrency(),
            'starts_at' => $starts_at,
            'interval' => $interval,
            'ends_at' => $this->ends_at ?? $starts_at->copy()->add($interval),
        ]);

        if ($this->coupon) {
            $this->coupon->markAsUsed([
                'subscribable' => $this->subscribable,
                'subscription' => $subscription,
                'subscriber' => $subscription,
            ]);
        }

        $this->subscriber->load('subscriptions');

        return $subscription;
    }


    /**
     * @return Money
     */
    public function getDiscountedPrice()
    {
        $price = $this->subscribable->getSubscriptionPrice();
        if ($this->coupon) {
            $price = $price->subtract(
                $this->coupon->getDiscountAmount($price, [
                    'subscribable' => $this->subscribable,
                    'subscriber' => $this->subscriber,
                ])
            );
        }
        return $price;
    }

    /**
     * @param Money $amount
     * @param string $hint
     * @return \Zek\Abone\Models\Subscription
     */
    protected function storeAndCharge(Money $amount, $hint = 'subscription.purchase')
    {
        return DB::transaction(function () use ($amount, $hint) {
            $subscription = $this->store();

            $this->subscriber->newTransaction($amount)
                ->hint($hint)
                ->exchange($this->exchange)
                ->wallet($this->wallet)
                ->references($subscription)
                ->charge();

            return $subscription;

        });
    }

}
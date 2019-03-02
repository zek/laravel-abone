<?php

namespace Zek\Abone\Models;

use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Money\Currency;
use Money\Money;
use Zek\Abone\Ability;
use Zek\Abone\Exceptions\SubscriptionError;

/**
 * @property integer id
 * @property boolean cancelled_immediately
 * @property \Money\Currency currency
 * @property \Carbon\CarbonInterval interval
 * @property integer renewal_amount
 * @property \Zek\Abone\Contracts\Subscribable subscribable
 * @property \Zek\Abone\Contracts\Subscriber subscriber
 * @property Carbon starts_at
 * @property Carbon ends_at
 * @property Carbon cancelled_at
 * @property Carbon updated_at
 * @property Carbon created_at
 */
class Subscription extends Model
{

    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'cancelled_immediately' => 'bool',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'starts_at', 'ends_at', 'cancelled_at',
        'created_at', 'updated_at',
    ];


    /**
     * Get the user that owns the subscription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function subscriber()
    {
        return $this->morphTo();
    }


    /**
     * Get the model subscribed.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function subscribable()
    {
        return $this->morphTo();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function usages()
    {
        return $this->hasMany(SubscriptionUsage::class);
    }

    /**
     * @param bool $full
     * @return mixed
     * @throws SubscriptionError
     */
    public function refund($full = false)
    {
        if (!$this->active() && !$this->onFuture()) {
            throw new SubscriptionError('Subscription is not refundable');
        }
        $amount = $this->lastPaid()->absolute();
        if (!$full) {
            $amount = $amount->multiply($this->usageLeft());
        }
        $this->subscriber
            ->newTransaction($amount)
            ->hint('subscription.refund')
            ->references($this)
            ->credit();
        $this->cancelNow();

        return $amount;
    }

    public function ability($name)
    {
        return new Ability($this, $name);
    }

    /**
     * Cancel the subscription at the end of the billing period.
     *
     * @return $this
     */
    public function cancel()
    {
        $this->fill(['cancelled_at' => Carbon::now()])->save();

        return $this;
    }

    /**
     * Cancel the subscription immediately.
     *
     * @return $this
     */
    public function cancelNow()
    {
        $this->fill([
            'ends_at' => Carbon::now(),
            'cancelled_at' => Carbon::now(),
            'cancelled_immediately' => true,
        ])->save();

        return $this;
    }

    public function transactions()
    {
        return $this->morphMany(Transaction::class, 'reference');
    }

    /**
     * Resume the cancelled subscription.
     *
     * @return $this
     * @throws \LogicException
     */
    public function resume()
    {
        if (!$this->onGracePeriod()) {
            throw new LogicException('Unable to resume subscription that is not within grace period.');
        }

        $this->fill(['cancelled_at' => null])->save();

        return $this;
    }

    /**
     * Determine if the subscription is active.
     *
     * @return bool
     */
    public function active()
    {
        return is_null($this->ends_at) || ($this->ends_at->isFuture() && !$this->starts_at->isFuture());
    }

    /**
     * Determine if the subscription is active.
     *
     * @return bool
     */
    public function onFuture()
    {
        return $this->starts_at->isFuture() && (is_null($this->ends_at) || $this->ends_at->isFuture());
    }

    /**
     * Determine if the subscription has ended and the grace period has expired.
     *
     * @return bool
     */
    public function ended()
    {
        return $this->cancelled() && !$this->onGracePeriod();
    }

    /**
     * Determine if the subscription is within its grace period after cancellation.
     *
     * @return bool
     */
    public function onGracePeriod()
    {
        return $this->cancelled_at && $this->ends_at->isFuture();
    }

    /**
     * Determine if the subscription is recurring.
     *
     * @return bool
     */
    public function recurring()
    {
        return !$this->cancelled() && ($this->active() || $this->onFuture());
    }

    /**
     * Determine if the subscription is no longer active.
     *
     * @return bool
     */
    public function cancelled()
    {
        return !is_null($this->cancelled_at) || !$this->ends_at->isFuture();
    }

    public function setCurrencyAttribute(Currency $currency)
    {
        $this->attributes['currency'] = $currency->getCode();
    }

    public function getCurrencyAttribute($currency)
    {
        return new Currency($currency);
    }

    /**
     * @return Money
     */
    public function getRenewalPrice()
    {
        return new Money($this->renewal_amount, $this->currency);
    }

    /**
     * @param Money $money
     * @return $this
     */
    public function setRenewalAmount(Money $money)
    {
        $this->renewal_amount = $money->getAmount();
        return $this;
    }

    /**
     * Filter query by recurring.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @return void
     */
    public function scopeRecurring($query)
    {
        $query->notCancelled()->active()->orWhere(function ($query) {
            $query->future();
        });
    }


    /**
     * Filter query by active.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @return void
     */
    public function scopeActive($query)
    {
        $query->whereNull('ends_at')->orWhere(function ($query) {
            $query->where('ends_at', '>', Carbon::now())
                ->where('starts_at', '<=', Carbon::now());
        });
    }

    /**
     * Filter query by active.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @return void
     */
    public function scopeFuture($query)
    {
        $query->where('starts_at', '>', Carbon::now());
    }


    /**
     * Filter query by on grace period.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @return void
     */
    public function scopeOnGracePeriod($query)
    {
        $query->whereNotNull('cancelled_at')->where('ends_at', '>', Carbon::now());
    }

    /**
     * Filter query by not on grace period.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @return void
     */
    public function scopeNotOnGracePeriod($query)
    {
        $query->whereNull('cancelled_at')->orWhere('ends_at', '<=', Carbon::now());
    }

    /**
     * Filter query by cancelled.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @return void
     */
    public function scopeCancelled($query)
    {
        $query->whereNotNull('cancelled_at')->orWhere(function ($query) {
            $query->where('ends_at', '<=', Carbon::now());
        });
    }

    /**
     * Filter query by not cancelled.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @return void
     */
    public function scopeNotCancelled($query)
    {
        $query->whereNull('cancelled_at')->where(function ($query) {
            $query->where('ends_at', '>=', Carbon::now());
        });
    }


    /**
     * Filter query by not cancelled.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param int $days
     * @return void
     */
    public function scopeExpiresInDays($query, $days = 1)
    {
        $query->active()->whereBetween('ends_at', [Carbon::createMidnightDate(), Carbon::createMidnightDate()->addDays($days)]);
    }

    /**
     * @param $interval
     * @return \Carbon\CarbonInterval
     */
    public function getIntervalAttribute($interval)
    {
        return CarbonInterval::fromString($interval);
    }

    public function setIntervalAttribute(CarbonInterval $interval)
    {
        $this->attributes['interval'] = $interval->forHumans();
    }


    /**
     * Filter query by ended.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @return void
     */
    public function scopeEnded($query)
    {
        $query->cancelled()->notOnGracePeriod();
    }

    /**
     * @return int
     */
    public function getDaysUsed()
    {
        if ($this->starts_at->isFuture()) {
            return 0;
        } else if (is_null($this->ends_at)) {
            return $this->starts_at->diffInDays();
        } else {
            return $this->ends_at->sub($this->interval)->diffInDays();
        }
    }

    /**
     * @return float|int
     */
    public function usageLeft()
    {
        return ($this->interval->totalDays - $this->getDaysUsed()) / $this->interval->totalDays;
    }

    /**
     * @return Money
     */
    public function lastPaid()
    {
        return $this->transactions()->where('amount', '<=', 0)->orderByDesc('id')->first()->amount->absolute();
    }

}

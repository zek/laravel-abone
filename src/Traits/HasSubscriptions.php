<?php

namespace Zek\Abone\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Zek\Abone\Builders\SubscriptionBuilder;
use Zek\Abone\Exceptions\SubscriptionError;
use Zek\Abone\Contracts\Subscribable as SubscribableContract;
use Zek\Abone\Models\Subscription;

trait HasSubscriptions
{
    /**
     * Begin creating a new subscription.
     *
     * @param SubscribableContract $subscribable
     * @return \Zek\Abone\Builders\SubscriptionBuilder
     */
    public function newSubscription(SubscribableContract $subscribable)
    {
        return new SubscriptionBuilder($this, $subscribable);
    }

    public function subscriptions()
    {
        return $this->morphMany(Subscription::class, 'subscriber');
    }

    /**
     * Get a subscription instance by class name.
     *
     * @param  string $subscribable
     * @return \Zek\Abone\Models\Subscription|Collection|null
     * @throws SubscriptionError
     */
    public function subscription($subscribable = null)
    {
        if (is_null($subscribable)) {
            throw new SubscriptionError('Invalid subscription');
        }

        $morphType = is_string($subscribable)
            ? (new $subscribable)->getMorphClass()
            : $subscribable->getMorphClass();

        $subscriptions = $this->subscriptions
            ->sortByDesc('id')
            ->filter(function ($value) use ($morphType) {
                return $value->active() && $value->subscribable_type === $morphType;
            });

        if ($subscribable::$multipleSubscriptions ?? false) {
            if (is_string($subscribable)) {
                return $subscriptions;
            } else {
                return $subscriptions->where('subscribable_id', $subscribable->getKey());
            }
        }

        return $subscriptions->first();
    }

    /**
     * Determine if the model has a given subscription.
     *
     * @param  string|Model|SubscribableContract $subscribable
     * @return bool|int
     * @throws SubscriptionError
     */
    public function subscribed($subscribable)
    {
        $subscription = $this->subscription($subscribable);

        if (is_null($subscription)) {
            return false;
        }

        if (is_string($subscribable)) {
            if ($subscribable::$multipleSubscriptions ?? false) {
                return $subscription->filter(function ($value) {
                    return $value->active();
                })->count();
            } else {
                return !is_null($subscription);
            }
        }

        return $subscription->active() &&
            (string)$subscription->subscribable_id === (string)$subscribable->getKey();
    }

}
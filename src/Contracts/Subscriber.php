<?php

namespace Zek\Abone\Contracts;

use Zek\Abone\Contracts\Subscribable as SubscribableContract;
use Zek\Abone\Contracts\HasWallets as HasWalletsContract;

interface Subscriber extends HasWalletsContract
{
    /**
     * Get all of the subscriptions
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function subscriptions();

    /**
     * Begin creating a new subscription.
     *
     * @param SubscribableContract $subscribable
     * @return \Zek\Abone\Builders\SubscriptionBuilder
     */
    public function newSubscription(SubscribableContract $subscribable);

    /**
     * Get a subscription instance by class name.
     *
     * @param  string|null $subscription
     * @return \Zek\Abone\Models\Subscription|null
     */
    public function subscription($subscription = null);

    /**
     * Determine if the model has a given subscription.
     *
     * @param  string|SubscribableContract $subscribable
     * @return bool|int
     */
    public function subscribed($subscribable);

}
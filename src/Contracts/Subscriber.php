<?php

namespace Zek\Abone\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Zek\Abone\Builders\SubscriptionBuilder;
use Zek\Abone\Contracts\HasWallets as HasWalletsContract;
use Zek\Abone\Contracts\Subscribable as SubscribableContract;
use Zek\Abone\Models\Subscription;

interface Subscriber extends HasWalletsContract
{
    /**
     * Get all of the subscriptions
     *
     * @return MorphMany
     */
    public function subscriptions();

    /**
     * Begin creating a new subscription.
     *
     * @param  SubscribableContract  $subscribable
     * @return SubscriptionBuilder
     */
    public function newSubscription(SubscribableContract $subscribable);

    /**
     * Get a subscription instance by class name.
     *
     * @param  string|null  $subscription
     * @return Subscription|null
     */
    public function subscription($subscription = null);

    /**
     * Determine if the model has a given subscription.
     *
     * @param  string|SubscribableContract  $subscribable
     * @return bool|int
     */
    public function subscribed($subscribable);

}

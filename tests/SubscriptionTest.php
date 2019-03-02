<?php

namespace Zek\Abone\Tests;

use Money\Money;
use Carbon\Carbon;
use Illuminate\Database\Schema\Builder;
use Illuminate\Database\ConnectionInterface;
use Zek\Abone\BasicCoupon;
use Zek\Abone\Exceptions\DowngradeError;
use Zek\Abone\Exceptions\SubscriptionError;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Zek\Abone\Tests\Fixtures\Group;
use Zek\Abone\Tests\Fixtures\Plan;
use Zek\Abone\Tests\Fixtures\User;

class SubscriptionTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();
    }

    public function test_subscriptions_can_be_created()
    {

        /** @var Plan $plan */
        $plan = Plan::create([
            'name' => 'Plan 1',
            'price' => 100,
            'currency' => 'USD',
        ]);

        /** @var Plan $plan2 */
        $plan2 = Plan::create([
            'name' => 'Plan 2',
            'price' => 500,
            'currency' => 'USD',
        ]);

        /** @var Group $group */
        $group = Group::create([
            'name' => 'Test',
            'price' => 500,
            'currency' => 'USD',
        ]);

        /** @var User $user */
        $user = User::find(1);

        $user->newTransaction(Money::USD(100))->credit();
        $this->assertEquals(Money::USD(100), $user->getWallet()->balance);

        $this->assertFalse($user->subscribed(Plan::class));
        $this->assertEquals(0, $user->subscribed(Group::class));

        // Create Plan (non-multiple) Subscription
        $user->newSubscription($plan)->create();

        $this->assertEquals(Money::USD(0), $user->getWallet()->balance);

        $this->assertEquals(1, count($user->subscriptions));
        $this->assertNotNull($user->subscription(Plan::class));
        $this->assertNotNull($user->subscription($plan));
        $this->assertTrue($user->subscribed(Plan::class));
        $this->assertTrue($user->subscribed($plan));


        // Create Group (multiple) Subscription
        $user->newTransaction(Money::USD(500))->credit();
        $this->assertEquals(Money::USD(500), $user->getWallet()->balance);
        $subscription = $user->newSubscription($group)->create();

        $this->assertCount(2, $user->subscriptions);

        $this->assertCount(1, $user->subscription(Group::class));
        $this->assertNotNull($user->subscription($group));

        $this->assertEquals(1, $user->subscribed(Group::class));
        $this->assertTrue($user->subscribed($plan));
        $this->assertFalse($user->subscribed($plan2));

        $this->assertTrue($user->subscription(Plan::class)->active());
        $this->assertFalse($user->subscription(Plan::class)->cancelled());
        $this->assertFalse($user->subscription(Plan::class)->onGracePeriod());
        $this->assertTrue($user->subscription(Plan::class)->recurring());
        $this->assertFalse($user->subscription(Plan::class)->ended());
        $this->assertFalse($user->subscription(Plan::class)->onFuture());


        // Cancel Subscription
        $subscription = $user->subscription(Plan::class);
        $subscription->cancel();

        $this->assertTrue($subscription->active());
        $this->assertTrue($subscription->cancelled());
        $this->assertTrue($subscription->onGracePeriod());
        $this->assertFalse($subscription->recurring());
        $this->assertFalse($subscription->ended());
        $this->assertFalse($subscription->onFuture());


        // Modify Ends Date To Past
        $oldGracePeriod = $subscription->ends_at;
        $subscription->fill(['ends_at' => Carbon::now()->subDays(5)])->save();

        $this->assertFalse($subscription->active());
        $this->assertTrue($subscription->cancelled());
        $this->assertFalse($subscription->onGracePeriod());
        $this->assertFalse($subscription->recurring());
        $this->assertTrue($subscription->ended());
        $this->assertFalse($subscription->onFuture());


        // Modify Starts At to Future
        $subscription->fill([
            'ends_at' => Carbon::now()->addDays(5),
            'starts_at' => Carbon::now()->addDays(2),
        ])->save();

        $this->assertFalse($subscription->active());
        $this->assertTrue($subscription->cancelled());
        $this->assertTrue($subscription->onGracePeriod());
        $this->assertFalse($subscription->recurring());
        $this->assertFalse($subscription->ended());
        $this->assertTrue($subscription->onFuture());

        $subscription->fill([
            'starts_at' => Carbon::now()->subDay(),
            'ends_at' => $oldGracePeriod,
        ])->save();

        // Resume Subscription
        $subscription->resume();

        $this->assertTrue($subscription->active());
        $this->assertFalse($subscription->cancelled());
        $this->assertFalse($subscription->onGracePeriod());
        $this->assertTrue($subscription->recurring());
        $this->assertFalse($subscription->ended());

        $this->assertEquals($plan->getKey(), $subscription->subscribable_id);
    }

    public function test_can_change_subscripton()
    {
        /** @var User $user */
        $user = User::first();

        $user->newTransaction(Money::USD(40))->credit();
        $this->assertEquals(Money::USD(40), $user->getWallet()->balance);

        /** @var Plan $plan1 */
        $plan1 = Plan::create([
            'name' => 'Plan 1',
            'price' => 10,
            'currency' => 'USD',
        ]);

        /** @var Plan $plan2 */
        $plan2 = Plan::create([
            'name' => 'Plan 2',
            'price' => 40,
            'currency' => 'USD',
        ]);

        // Check we have 0 subscriptions

        $this->assertFalse($user->subscribed(Plan::class));
        $this->assertCount(0, $user->subscriptions);

        // Create a new subscription
        $subscription = $user->newSubscription($plan1)->create();
        $this->assertEquals(Money::USD(30), $user->getWallet()->balance);
        $this->assertEquals(Money::USD(10), $subscription->lastPaid());


        // Check if we have 1 subscription

        $this->assertTrue($user->subscribed(Plan::class));
        $this->assertCount(1, $user->subscriptions);

        $subscription = $user->newSubscription($plan2)->create();

        $this->assertTrue($user->subscribed(Plan::class));
        $this->assertCount(2, $user->subscriptions);
        $this->assertEquals(Money::USD(0), $user->getWallet()->balance);
        $this->assertEquals(Money::USD(30), $subscription->lastPaid());

        // Check Subscriptions Counts for Plans

        $this->assertEquals(0, $plan1->subscriptions()->active()->count());
        $this->assertEquals(1, $plan2->subscriptions()->active()->count());


        $this->expectException(DowngradeError::class);
        $user->newSubscription($plan1)->create();
        $this->assertEquals($plan2, $user->subscription(Plan::class)->subscribable);
    }

    public function test_subscription_extending()
    {
        /** @var User $user */
        $user = User::first();
        $user->newTransaction(Money::USD(250))->credit();


        /** @var Plan $plan1 */
        $plan1 = Plan::create([
            'name' => 'Plan 1',
            'price' => 250,
            'currency' => 'USD',
        ]);

        Carbon::setTestNow(Carbon::create(2019, 1, 1));

        $this->assertFalse($user->subscribed($plan1));

        // Create a new subscription

        $sub = $user->newSubscription($plan1)->create();

        $this->assertTrue($user->subscribed($plan1));

        $this->assertEquals(0, $user->subscriptions()->expiresInDays(2)->count());

        Carbon::setTestNow(Carbon::create(2019, 1, 31));

        $this->assertTrue($user->subscribed($plan1));

        // Check if subscription expires today or tomorrow
        $this->assertEquals(1, $user->subscriptions()->expiresInDays(2)->count());

        // Extend subscription.

        $user->newTransaction(Money::USD(250))->credit();
        $subscription = $user->newSubscription($plan1)->extend();

        $this->assertTrue($user->subscribed($plan1));

        // Go to second cycle of subscription

        Carbon::setTestNow(Carbon::create(2019, 2, 2));

        $this->assertEquals(1, $user->subscriptions()->count());
        $this->assertTrue($user->subscribed($plan1));
        $this->assertEquals(
            $subscription->ends_at,
            $subscription->starts_at
                ->add($plan1->getSubscriptionInterval())
                ->add($plan1->getSubscriptionInterval())
        );

    }

    public function test_creating_subscription_with_coupons()
    {
        /** @var User $user */
        $user = User::first();
        $user->newTransaction(Money::USD(280))->credit();


        /** @var Plan $plan1 */
        $plan1 = Plan::create([
            'name' => 'Plan 1',
            'price' => 300,
            'currency' => 'USD',
        ]);

        // Create Subscription
        $user->newSubscription($plan1)
            ->withCoupon(new BasicCoupon(Money::USD('200')))
            ->create();


        $subscription = $user->subscription($plan1);

        $this->assertTrue($user->subscribed($plan1));
        $this->assertTrue($subscription->active());
        $this->assertFalse($subscription->cancelled());
        $this->assertFalse($subscription->onGracePeriod());
        $this->assertTrue($subscription->recurring());
        $this->assertFalse($subscription->ended());

        // Discount Test
        $charged = $subscription->lastPaid();

        $subscription->cancelNow();

        $this->assertEquals(Money::USD(100), $charged);
        $this->assertEquals(Money::USD(180), $user->getWallet()->balance);


        // Create Second Subscription
        $user->newSubscription($plan1)
            ->withCoupon(new BasicCoupon('40'))
            ->create();

        $this->assertEquals(Money::USD(0), $user->getWallet()->balance);

        $subscription = $user->subscription($plan1);

        $this->assertTrue($user->subscribed($plan1));
        $this->assertTrue($subscription->active());
        $this->assertFalse($subscription->cancelled());
        $this->assertFalse($subscription->onGracePeriod());
        $this->assertTrue($subscription->recurring());
        $this->assertFalse($subscription->ended());
        $this->assertEquals(Money::USD(180), $subscription->lastPaid());
    }

    public function test_refunds()
    {
        /** @var User $user */
        $user = User::first();
        $user->newTransaction(Money::USD(100))->credit();
        $this->assertEquals(Money::USD(100), $user->getWallet()->balance);

        /** @var Plan $plan1 */
        $plan1 = Plan::create([
            'name' => 'Plan 1',
            'price' => 100,
            'currency' => 'USD',
        ]);

        // Create a new subscription
        $subscription = $user->newSubscription($plan1)->create();

        $this->assertEquals(Money::USD(0), $user->getWallet()->balance);

        $amount = $subscription->refund();

        // Refund Tests
        $this->assertEquals(Money::USD(100), $user->getWallet()->balance);
        $this->assertEquals($plan1->getSubscriptionPrice(), $amount);

        $subscription->fill([
            'starts_at' => Carbon::createFromDate(2019, 1, 1),
            'ends_at' => Carbon::createFromDate(2019, 2, 1),
            'cancelled_at' => null,
        ])->save();

        Carbon::setTestNow(Carbon::createFromDate(2019, 1, 15));

        $amount = $subscription->refund();
        $this->assertEquals(Money::USD(150), $user->getWallet()->balance);
        $this->assertEquals($plan1->getSubscriptionPrice()->divide(2), $amount);

        Carbon::setTestNow();


        $subscription->fill([
            'starts_at' => Carbon::now()->addDays(30),
            'ends_at' => Carbon::now()->addDays(60),
            'cancelled_at' => null,
        ])->save();
        $amount = $subscription->refund();

        $this->assertEquals(Money::USD(250), $user->getWallet()->balance);
        $this->assertEquals($plan1->getSubscriptionPrice(), $amount);

        // Try to refund again

        $this->expectException(SubscriptionError::class);
        $subscription->refund();

    }

    public function test_subscribe_again_single()
    {
        /** @var User $user */
        $user = User::first();

        /** @var Plan $plan1 */
        $plan1 = Plan::create([
            'name' => 'Plan 1',
            'price' => 100,
            'currency' => 'USD',
        ]);

        $user->newTransaction(Money::USD(200))->credit();

        // Create a new subscription
        $user->newSubscription($plan1)->create();
        $this->expectException(SubscriptionError::class);
        $user->newSubscription($plan1)->create();

    }

    public function test_subscribe_again_multiple()
    {
        /** @var User $user */
        $user = User::first();

        /** @var Group $group */
        $group = Group::create([
            'name' => 'Test',
            'price' => 500,
            'currency' => 'USD',
        ]);

        // Create a new subscription

        $user->newTransaction(Money::USD(500))->credit();
        $this->assertEquals(Money::USD(500), $user->getWallet()->balance);

        $user->newSubscription($group)->create();
        $this->expectException(SubscriptionError::class);

        $user->newSubscription($group)->create();

    }

    public function test_subscription_state_scopes()
    {
        /** @var User $user */
        $user = User::first();

        $user->newTransaction(Money::USD(100))->credit();

        /** @var Plan $plan */
        $plan = Plan::create([
            'name' => 'Plan 1',
            'price' => 100,
            'currency' => 'USD',
        ]);


        $subscription = $user->newSubscription($plan)->infinite()->create();

        // subscription is active
        $this->assertTrue($user->subscriptions()->active()->exists());
        $this->assertTrue($user->subscriptions()->recurring()->exists());
        $this->assertFalse($user->subscriptions()->cancelled()->exists());
        $this->assertTrue($user->subscriptions()->notCancelled()->exists());
        $this->assertFalse($user->subscriptions()->onGracePeriod()->exists());
        $this->assertTrue($user->subscriptions()->notOnGracePeriod()->exists());
        $this->assertFalse($user->subscriptions()->ended()->exists());


        // set starts at tomorrow
        $subscription->update(['starts_at' => Carbon::now()->addDay()]);

        $this->assertFalse($user->subscriptions()->active()->exists());
        $this->assertTrue($user->subscriptions()->recurring()->exists());
        $this->assertFalse($user->subscriptions()->cancelled()->exists());
        $this->assertTrue($user->subscriptions()->notCancelled()->exists());
        $this->assertFalse($user->subscriptions()->onGracePeriod()->exists());
        $this->assertTrue($user->subscriptions()->notOnGracePeriod()->exists());
        $this->assertFalse($user->subscriptions()->ended()->exists());

        // set started at yesterday
        $subscription->update(['starts_at' => Carbon::now()->subDay()]);

        $this->assertTrue($user->subscriptions()->active()->exists());
        $this->assertTrue($user->subscriptions()->recurring()->exists());
        $this->assertFalse($user->subscriptions()->cancelled()->exists());
        $this->assertTrue($user->subscriptions()->notCancelled()->exists());
        $this->assertFalse($user->subscriptions()->onGracePeriod()->exists());
        $this->assertTrue($user->subscriptions()->notOnGracePeriod()->exists());
        $this->assertFalse($user->subscriptions()->ended()->exists());

        // put on grace period
        $subscription->update(['cancelled_at' => Carbon::now()]);

        $this->assertTrue($user->subscriptions()->active()->exists());
        $this->assertFalse($user->subscriptions()->recurring()->exists());
        $this->assertTrue($user->subscriptions()->cancelled()->exists());
        $this->assertFalse($user->subscriptions()->notCancelled()->exists());
        $this->assertTrue($user->subscriptions()->onGracePeriod()->exists());
        $this->assertFalse($user->subscriptions()->notOnGracePeriod()->exists());
        $this->assertFalse($user->subscriptions()->ended()->exists());


        // end subscription
        $subscription->update(['ends_at' => Carbon::now()->subDay()]);

        $this->assertFalse($user->subscriptions()->active()->exists());
        $this->assertFalse($user->subscriptions()->recurring()->exists());
        $this->assertTrue($user->subscriptions()->cancelled()->exists());
        $this->assertFalse($user->subscriptions()->notCancelled()->exists());
        $this->assertFalse($user->subscriptions()->onGracePeriod()->exists());
        $this->assertTrue($user->subscriptions()->notOnGracePeriod()->exists());
        $this->assertTrue($user->subscriptions()->ended()->exists());

        // resume subscription
        $subscription->update(['ends_at' => Carbon::now()->addDay()]);

        $this->assertTrue($user->subscriptions()->active()->exists());
        $this->assertFalse($user->subscriptions()->recurring()->exists());
        $this->assertTrue($user->subscriptions()->cancelled()->exists());
        $this->assertFalse($user->subscriptions()->notCancelled()->exists());
        $this->assertTrue($user->subscriptions()->onGracePeriod()->exists());
        $this->assertFalse($user->subscriptions()->notOnGracePeriod()->exists());
        $this->assertFalse($user->subscriptions()->ended()->exists());


    }

}
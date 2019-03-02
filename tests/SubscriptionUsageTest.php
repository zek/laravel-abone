<?php

namespace Zek\Abone\Tests;

use Carbon\Carbon;
use Illuminate\Database\Schema\Builder;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Zek\Abone\Tests\Fixtures\Plan;
use Zek\Abone\Tests\Fixtures\User;

class SubscriptionUsageTest extends TestCase
{

    public function test_consume_feature()
    {
        /** @var User $user */
        $user = User::first();

        /** @var Plan $plan */
        $plan = Plan::create([
            'name' => 'Plan 1',
            'price' => 0,
            'currency' => 'USD',
        ]);

        $plan->features()->create([
            'code' => 'create',
            'value' => '3',
            'interval' => '1 day',
        ]);
        $plan->features()->create([
            'code' => 'view',
            'value' => 'yes',
            'interval' => '1 day',
        ]);
        $plan->features()->create([
            'code' => 'boost',
            'value' => 'no',
            'interval' => '1 day',
        ]);


        $subscription = $user->newSubscription($plan)->create();

        $this->assertEquals("3", $subscription->ability('create'));
        $this->assertEquals("3", $subscription->ability('create')->value());
        $this->assertEquals(true, $subscription->ability('create')->canUse());
        $this->assertEquals(0, $subscription->ability('create')->consumed());
        $this->assertEquals(false, $subscription->ability('create')->enabled());
        $this->assertEquals(3, $subscription->ability('create')->remaining());
        $this->assertEquals(0, $subscription->ability('create')->remaining(3));

        $this->assertEquals("yes", $subscription->ability('view'));
        $this->assertEquals("yes", $subscription->ability('view')->value());
        $this->assertEquals(true, $subscription->ability('view')->canUse());
        $this->assertEquals(0, $subscription->ability('view')->consumed());
        $this->assertEquals(true, $subscription->ability('view')->enabled());
        $this->assertEquals(0, $subscription->ability('view')->remaining());

        $this->assertEquals("no", $subscription->ability('boost'));
        $this->assertEquals("no", $subscription->ability('boost')->value());
        $this->assertEquals(false, $subscription->ability('boost')->canUse());
        $this->assertEquals(0, $subscription->ability('boost')->consumed());
        $this->assertEquals(false, $subscription->ability('boost')->enabled());
        $this->assertEquals(0, $subscription->ability('boost')->remaining());


        $this->assertEquals("", $subscription->ability('other'));
        $this->assertEquals(null, $subscription->ability('other')->value());
        $this->assertEquals(false, $subscription->ability('other')->canUse());
        $this->assertEquals(0, $subscription->ability('other')->consumed());
        $this->assertEquals(false, $subscription->ability('other')->enabled());
        $this->assertEquals(0, $subscription->ability('other')->remaining());


        $this->assertEquals(1, $subscription->ability('create')->use(1));
        $this->assertEquals(3, $subscription->ability('create')->use(2));
        $this->assertEquals(3, $subscription->ability('create')->consumed());
        $this->assertEquals(false, $subscription->ability('create')->canUse());
        $this->assertEquals(false, $subscription->ability('create')->enabled());
        $this->assertEquals(0, $subscription->ability('create')->remaining());

        Carbon::setTestNow(Carbon::now()->addDay());
        $this->assertEquals(0, $subscription->ability('create')->consumed());
        $this->assertEquals(3, $subscription->ability('create')->remaining());

        $this->assertEquals(3, $subscription->ability('create')->use(3));
        $this->assertEquals(0, $subscription->ability('create')->remaining());

        $subscription->ability('create')->return(2);
        $this->assertEquals(2, $subscription->ability('create')->remaining());

        Carbon::setTestNow();

    }

}


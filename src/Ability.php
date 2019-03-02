<?php

namespace Zek\Abone;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Zek\Abone\Contracts\Feature;
use Zek\Abone\Models\Subscription;
use Zek\Abone\Models\SubscriptionUsage;

class Ability
{
    /**
     * @var Subscription
     */
    protected $subscription;

    /**
     * @var Feature|null
     */
    protected $value;

    /**
     * @var string|null
     */
    protected $code;

    /**
     * Create a new Subscription instance.
     *
     * @param Subscription $subscription
     * @param string $code
     */
    public function __construct(Subscription $subscription, ?string $code = null)
    {
        $this->subscription = $subscription;
        $this->code = $code;

        $this->value = $code ? array_get($subscription->subscribable->getFeatures(), $code) : null;
    }

    public function __toString()
    {
        return (string)$this->value();
    }


    /**
     * Determine if the feature is enabled and has
     * available uses.
     *
     * @return boolean
     */
    public function canUse()
    {
        $feature_value = $this->value();
        if (is_null($feature_value)) {
            return false;
        }
        // Match "booleans" type value
        if ($this->enabled() === true) {
            return true;
        }
        // If the feature value is zero, let's return false
        // since there's no uses available. (useful to disable
        // countable features)
        if ((float)$feature_value == 0) {
            return false;
        }
        // Check for available uses
        return $this->remaining() > 0;
    }

    /**
     * Get how many times the feature has been used.
     *
     * @param bool $value
     * @return float
     */
    public function consumed($value = true)
    {
        $record = $this->subscription->usages()->code($this->code)->valid()->orderBy('id', 'desc')->first();
        if ($value) {
            return optional($record)->used;
        } else {
            return $record;
        }
    }

    /**
     * Get the available uses.
     *
     * @param null $consumed
     * @return float
     */
    public function remaining($consumed = null)
    {
        return (float)$this->value() - ($consumed ?? (float)$this->consumed());
    }

    /**
     * Check if feature is enabled.
     *
     * @return bool
     */
    public function enabled()
    {
        $feature_value = $this->value();
        if (is_null($feature_value)) {
            return false;
        }
        // If value is one of the positive words configured then the feature is enabled.
        if (in_array(strtolower($feature_value), Abone::$positiveWords)) {
            return true;
        }
        return false;
    }

    /**
     * Get feature value.
     *
     * @param  mixed $default
     * @return mixed
     */
    public function value($default = null)
    {
        return optional($this->value)->getValue() ?? $default;
    }

    /**
     * @param float $amount
     * @param bool $incremental
     * @return float
     */
    public function use(float $amount = 0, $incremental = true)
    {
        /** @var SubscriptionUsage $record */
        $record = $this->consumed(false);

        $this->subscription->ends_at;
        if (is_null($record)) {
            $record = $this->subscription->usages()->firstOrNew([
                'code' => $this->code,
                'valid_until' => CarbonPeriod::since($this->subscription->created_at)
                    ->interval($this->value->interval())
                    ->until(Carbon::now())
                    ->last()
                    ->add($this->value->interval())
            ]);
        }
        $record->used = $incremental ? $record->used + $amount : $amount;
        $record->save();

        return $record->used;
    }

    /**
     * @param float $amount
     * @return float|boolean
     */
    public function return(float $amount = 0)
    {
        /** @var SubscriptionUsage $record */
        $record = $this->consumed(false);
        if (is_null($record) || $record->used <= 0) {
            return false;
        }

        $record->used = max($record->used - $amount, 0);
        $record->save();

        return $record->used;
    }

    public function clear($onlyCurrent = true)
    {
        $usages = $this->subscription->usages();
        if (!is_null($this->code)) {
            $usages = $usages->code($this->code);
        }
        if ($onlyCurrent) {
            $usages = $usages->valid();
        }
        $usages->delete();

        return $this;
    }
}

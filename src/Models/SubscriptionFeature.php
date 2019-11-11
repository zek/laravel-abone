<?php

namespace Zek\Abone\Models;

use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Zek\Abone\Contracts\Feature;

/**
 * @property integer id
 * @property string interval
 * @property string code
 * @property string value
 */
class SubscriptionFeature extends Model implements Feature
{
    /**
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @return MorphTo
     */
    public function subscribable()
    {
        return $this->morphTo();
    }

    /**
     * @return CarbonInterval|null
     */
    public function interval(): ?CarbonInterval
    {
        return CarbonInterval::fromString($this->interval);
    }
}

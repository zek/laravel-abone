<?php

namespace Zek\Abone\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Zek\Abone\Models\SubscriptionFeature;

trait HasFeatures
{
    /**
     * @return array
     */
    public function getFeatures()
    {
        return $this->features->pluck(null, 'code');
    }

    /**
     * @return MorphMany
     */
    public function features()
    {
        return $this->morphMany(SubscriptionFeature::class, 'subscribable');
    }
}

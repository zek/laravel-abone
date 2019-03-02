<?php

namespace Zek\Abone\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property integer id
 * @property string code
 * @property Subscription subscription
 * @property float used
 * @property Carbon valid_until
 */
class SubscriptionUsage extends Model
{
    /**
     * @var array
     */
    protected $guarded = [];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function scopeCode($query, $code)
    {
        $query->where('code', $code);
    }

    public function scopeValid($query)
    {
        $query->where('valid_until', '>', Carbon::now());
    }

}
<?php

namespace Zek\Abone\Models;

use Carbon\Carbon;
use Dyrynda\Database\Support\GeneratesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Money\Currency;
use Money\Money;

/**
 * @property integer id
 * @property Money amount
 * @property array meta
 * @property string uuid
 * @property string reference_id
 * @property string reference_type
 * @property string hint
 * @property Wallet wallet
 * @property Model|null reference
 * @property Currency currency
 * @property boolean confirmed
 * @property Carbon processed_at
 * @property Carbon updated_at
 * @property Carbon created_at
 */
class Transaction extends Model
{
    use GeneratesUuid;
    use SoftDeletes;

    /**
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * @var array
     */
    protected $dates = ['processed_at'];

    /**
     * @var array
     */
    protected $hidden = ['reference'];

    /**
     * @var array
     */
    protected $casts = [
        'uuid' => 'uuid',
        'confirmed' => 'bool',
        'meta' => 'array',
    ];

    /**
     * Boot event handlers to calculate wallet balance after any transaction
     *
     * @return void
     */
    protected static function boot()
    {

        parent::boot();
        static::saved(function (Transaction $transaction) {
            $transaction->wallet->calculateBalance();
        });
        static::deleted(function (Transaction $transaction) {
            $transaction->wallet->calculateBalance();
        });
    }

    /**
     * @return MorphTo
     */
    public function reference()
    {
        return $this->morphTo();
    }

    /**
     * @param  Model  $model
     */
    public function setReferenceAttribute(?Model $model)
    {
        if ($model) {
            $this->reference_id = $model->getKey();
            $this->reference_type = $model->getMorphClass();
        } else {
            $this->reference_id = null;
            $this->reference_type = null;
        }
    }

    /**
     * @return BelongsTo|Wallet
     */
    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * @param $amount
     * @return Money
     */
    public function getAmountAttribute($amount)
    {
        return new Money($amount, $this->currency);
    }

    /**
     * @param  Money  $money
     */
    public function setAmountAttribute(Money $money)
    {
        $this->attributes['amount'] = $money->getAmount();
        $this->setCurrencyAttribute($money->getCurrency());
    }

    /**
     * @param  Currency  $currency
     */
    public function setCurrencyAttribute(Currency $currency)
    {
        $this->attributes['currency'] = $currency->getCode();
    }

    /**
     * @param $currency
     * @return Currency
     */
    public function getCurrencyAttribute($currency)
    {
        return new Currency($currency);
    }

}

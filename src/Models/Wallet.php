<?php

namespace Zek\Abone\Models;

use Illuminate\Database\Eloquent\Model;
use Money\Currency;
use Money\Money;
use Zek\Abone\Contracts\HasWallets;

/**
 * @property integer id
 * @property string name
 * @property HasWallets|Model owner
 * @property Money balance
 * @property Currency currency
 * @property integer wallet_id
 */
class Wallet extends Model
{
    /**
     * @var array
     */
    static protected $_balances;

    /**
     * Get morphed model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function owner()
    {
        return $this->morphTo();
    }

    /**
     * @param Currency $currency
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


    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }


    /**
     * @return Money
     */
    public function getBalanceAttribute()
    {
        if (!isset(self::$_balances[$this->id])) {
            $this->calculateBalance();
        }
        return self::$_balances[$this->id];
    }

    /**
     * @param Money $money
     */
    public function setBalanceAttribute(Money $money)
    {
        self::$_balances[$this->id] = $money;
        $this->attributes['balance'] = $money->getAmount();
        $this->save();
    }

    /**
     * @return Money
     */
    public function calculateBalance()
    {
        $balance = $this->transactions()
            ->where('wallet_id', $this->getKey())
            ->where('confirmed', true)
            ->sum('amount');
        $this->attributes['balance'] = $balance;
        $this->save();
        $this->setBalanceAttribute(new Money($balance, $this->currency));
        return $this->getBalanceAttribute();
    }

}
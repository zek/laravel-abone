<?php

namespace Zek\Abone\Traits;

use Money\Currency;
use Money\Money;
use Zek\Abone\Abone;
use Zek\Abone\Builders\TransactionBuilder;
use Zek\Abone\Models\Wallet;

trait HasWallets
{
    protected $_wallets;

    public function getWallet($name = null)
    {
        $name = $name ?? Abone::usesCurrency();
        if (isset($this->_wallets[$name])) {
            return $this->_wallets[$name];
        }

        $wallets = $this->wallets();

        if (!is_null($name)) {
            $wallets = $wallets->where('name', $name);
        }

        $wallet = $wallets->first();
        if (!$wallet) {
            $currency = $this->walletCurrency ?? Abone::usesCurrency();

            $wallet = new Wallet();
            $wallet->owner()->associate($this);
            $wallet->name = $currency;
            $wallet->currency = new Currency($currency);
            $wallet->save();
            $this->_wallets[$wallet->name] = $wallet;
        }
        return $wallet;
    }

    public function wallets()
    {
        return $this->morphMany(Wallet::class, 'owner');
    }

    /**
     * @param Money $money
     * @return TransactionBuilder
     * @throws \Zek\Abone\Exceptions\InvalidAmount
     */
    public function newTransaction(Money $money)
    {
        return new TransactionBuilder($this, $money);
    }

}

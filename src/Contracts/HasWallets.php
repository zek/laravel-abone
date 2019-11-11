<?php

namespace Zek\Abone\Contracts;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Money\Money;
use Zek\Abone\Builders\TransactionBuilder;
use Zek\Abone\Models\Wallet;

interface HasWallets
{

    /**
     * @param  Money  $money
     * @return TransactionBuilder
     */
    public function newTransaction(Money $money);

    /**
     * Get all of the subscriptions
     *
     * @return HasMany
     */
    public function wallets();

    /**
     * Get all of the subscriptions
     *
     * @param  null  $name
     * @return Wallet
     */
    public function getWallet($name = null);

}

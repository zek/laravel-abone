<?php

namespace Zek\Abone\Contracts;

interface HasWallets
{

    /**
     * @param \Money\Money $money
     * @return \Zek\Abone\Builders\TransactionBuilder
     */
    public function newTransaction(\Money\Money $money);

    /**
     * Get all of the subscriptions
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function wallets();

    /**
     * Get all of the subscriptions
     *
     * @param null $name
     * @return \Zek\Abone\Models\Wallet
     */
    public function getWallet($name = null);

}
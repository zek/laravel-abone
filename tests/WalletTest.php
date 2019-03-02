<?php

namespace Zek\Abone\Tests;

use Money\Currency;
use Money\Money;
use Zek\Abone\Abone;
use Zek\Abone\Models\Transaction;
use Zek\Abone\Tests\Fixtures\User;

class WalletTest extends TestCase
{
    public function test_wallet_transactions()
    {
        /** @var User $user */
        $user = User::first();

        $currency = Abone::usesCurrency();

        $wallet = $user->getWallet();

        $this->assertEquals(1, $user->wallets()->count());

        $this->assertEquals($currency, $wallet->currency);

        $amount = new Money(100, new Currency($currency));

        // Check Crediting the Wallet

        /** @var Transaction $transaction */
        $transaction = $user->newTransaction($amount)->credit();

        $this->assertEquals($amount->getCurrency(), $transaction->currency);
        $this->assertEquals($amount, $transaction->amount);
        $this->assertEquals($amount, $wallet->balance);
        $this->assertEquals(1, $user->wallets()->count());
        $this->assertEquals(1, $wallet->transactions()->count());

        // Check Debiting from Wallet

        /** @var Transaction $transaction */
        $transaction = $user->newTransaction($amount)->charge();

        $this->assertEquals($amount->getCurrency(), $transaction->currency);
        $this->assertEquals($amount->negative(), $transaction->amount);
        $this->assertEquals(new Money(0, new Currency($currency)), $wallet->balance);
        $this->assertEquals(1, $user->wallets()->count());
        $this->assertEquals(2, $wallet->transactions()->count());


    }


    public function test_insufficient_balance()
    {
        /** @var User $user */
        $user = User::first();

        $wallet = $user->getWallet();

        $currency = new Currency(Abone::usesCurrency());
        $amount = new Money(100, $currency);
        $this->expectException(\Zek\Abone\Exceptions\InsufficientFunds::class);
        try {
            $transaction = $user->newTransaction($amount)->charge();
        } finally {
            $this->assertEquals(new Money(0, $currency), $wallet->balance);
            $this->assertEquals(1, $user->wallets()->count());
            $this->assertEquals(0, $wallet->transactions()->count());
        }
    }

    public function test_charging_debiting_in_different_currency()
    {
        /** @var User $user */
        $user = User::first();

        $wallet = $user->getWallet();


        $this->assertEquals('USD', Abone::usesCurrency());
        $this->assertEquals('USD', $wallet->currency->getCode());

        $transaction = $user
            ->newTransaction(Money::TRY(400))
            ->exchange()
            ->credit();

        $this->assertEquals([
            'exchanged' => [
                'currency' => 'TRY',
                'amount' => '400',
                'rate' => 4,
            ],
        ], $transaction->meta);

        $this->assertEquals(Money::USD(100), $transaction->amount);
        $this->assertEquals(Money::USD(100), $wallet->balance);

        $transaction = $user
            ->newTransaction(Money::TRY(400))
            ->exchange()
            ->charge();

        $this->assertEquals(Money::USD(-100), $transaction->amount);
        $this->assertEquals(Money::USD(0), $wallet->balance);

    }

    public function test_force_debit()
    {
        /** @var User $user */
        $user = User::first();

        $wallet = $user->getWallet();

        $currency = new Currency(Abone::usesCurrency());
        $amount = new Money(100, $currency);
        $transaction = $user->newTransaction($amount)->force()->charge();

        $this->assertEquals($amount->getCurrency(), $transaction->currency);
        $this->assertEquals($amount->negative(), $transaction->amount);
        $this->assertEquals(new Money(-100, $currency), $wallet->balance);
        $this->assertEquals(1, $user->wallets()->count());
        $this->assertEquals(1, $wallet->transactions()->count());

    }

    public function test_invalid_charge_amount()
    {
        /** @var User $user */
        $user = User::first();

        $currency = new Currency(Abone::usesCurrency());
        $amount = new Money(-100, $currency);

        $this->expectException(\Zek\Abone\Exceptions\InvalidAmount::class);

        $user->newTransaction($amount)->charge();
    }


    public function test_invalid_credit_amount()
    {
        /** @var User $user */
        $user = User::first();

        $currency = new Currency(Abone::usesCurrency());
        $amount = new Money(-100, $currency);

        $this->expectException(\Zek\Abone\Exceptions\InvalidAmount::class);

        $user->newTransaction($amount)->credit();
    }

}


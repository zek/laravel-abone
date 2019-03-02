<?php

namespace Zek\Abone\Builders;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Money\Money;
use Zek\Abone\Abone;
use Zek\Abone\Contracts\HasWallets;
use Zek\Abone\Exceptions\InsufficientFunds;
use Zek\Abone\Exceptions\InvalidAmount;
use Zek\Abone\Exceptions\WalletError;
use Zek\Abone\Models\Transaction;
use Zek\Abone\Models\Wallet;

class TransactionBuilder
{

    /**
     * @var Money
     */
    protected $amount;

    /**
     * @var boolean
     */
    protected $confirmed = true;

    /**
     * @var Model|null
     */
    protected $reference = null;

    /**
     * @var boolean
     */
    protected $exchange = false;

    /**
     * @var Carbon
     */
    protected $at;

    /**
     * @var HasWallets
     */
    protected $owner;

    /**
     * @var string|null
     */
    protected $wallet;

    /**
     * @var Wallet
     */
    protected $walletInstance;

    /**
     * @var string
     */
    protected $hint;

    /**
     * @var boolean
     */
    protected $force = false;

    /**
     * TransactionBuilder constructor.
     * @param HasWallets $owner
     * @param Money $amount
     * @throws InvalidAmount
     */
    public function __construct(HasWallets $owner, Money $amount)
    {
        if ($amount->isNegative()) {
            throw new InvalidAmount('Amount can\'t be negative');
        }
        $this->owner = $owner;
        $this->amount = $amount;
        $this->at = Carbon::now();
    }

    /**
     * Sets transaction as unconfirmed.
     *
     * @return TransactionBuilder
     */
    public function unconfirmed()
    {
        $this->confirmed = false;
        return $this;
    }

    /**
     * Sets a hint fon the transaction.
     *
     * @param string $hint
     * @return TransactionBuilder
     */
    public function hint(string $hint)
    {
        $this->hint = $hint;
        return $this;
    }

    /**
     * Sets transaction reference.
     *
     * @param Model|null $reference
     * @return TransactionBuilder
     */
    public function references(?Model $reference)
    {
        $this->reference = $reference;
        return $this;
    }

    /**
     * Sets transaction date.
     *
     * @param Carbon $at
     * @return TransactionBuilder
     */
    public function at(Carbon $at)
    {
        $this->at = $at;
        return $this;
    }

    /**
     * Disable checking if balance is negative after transaction.
     *
     * @param bool $force
     * @return TransactionBuilder
     */
    public function force($force = true)
    {
        $this->force = $force;
        return $this;
    }

    /**
     * Sets wallet by name.
     *
     * @param string|null $wallet
     * @return TransactionBuilder
     */
    public function wallet(string $wallet = null)
    {
        $this->wallet = $wallet;
        return $this;
    }

    /**
     * Creates a money out transaction.
     *
     * @param array $meta
     * @return \Zek\Abone\Models\Transaction
     */
    public function charge(array $meta = [])
    {
        return $this->store('charge', $meta);
    }

    /**
     * Creates a money entry transaction.
     *
     * @param array $meta
     * @return \Zek\Abone\Models\Transaction
     */
    public function credit(array $meta = [])
    {
        return $this->store('credit', $meta);
    }

    /**
     * @param Wallet $targetWallet
     * @param array $meta
     * @return \Zek\Abone\Models\Transaction
     */
    public function transfer(Wallet $targetWallet, array $meta = [])
    {
        return DB::transaction(function () use ($meta, $targetWallet) {

            $balance = $this->getWallet()->calculateBalance()->subtract($this->amount);

            if (!$this->force && $balance->isNegative()) {
                throw new InsufficientFunds('Insufficient funds');
            }

            /** @var Transaction $credit */
            $credit = $targetWallet->transactions()->create([
                'amount' => $this->amount,
                'processed_at' => $this->at,
                'confirmed' => $this->confirmed,
                'meta' => $meta,
                'hint' => $this->hint ?? 'wallet.transfer',
            ]);
            $charge = $this->getWallet()->transactions()->create([
                'amount' => $this->amount->negative(),
                'processed_at' => $this->at,
                'confirmed' => $this->confirmed,
                'reference' => $credit,
                'meta' => $meta,
                'hint' => $this->hint ?? 'wallet.transfer',
            ]);
            $credit->reference()->associate($charge)->save();
            return $charge;
        });
    }

    /**
     * Stores transaction in database.
     *
     * @param string $type
     * @param array $meta
     * @return \Zek\Abone\Models\Transaction
     */
    protected function store($type, array $meta = [])
    {
        return DB::transaction(function () use ($meta, $type) {

            $amount = $this->exchangedAmount($this->amount);

            if ($this->amount->isPositive() && !$amount->equals($this->amount)) {
                $meta['exchanged'] = [
                    'currency' => $this->amount->getCurrency()->getCode(),
                    'amount' => $this->amount->getAmount(),
                    'rate' => $this->amount->ratioOf($amount)
                ];
            }

            if ($type === 'charge') {
                $amount = $amount->negative();
            }

            $balance = $this->getWallet()->calculateBalance()->add($amount);

            if (!$this->force && $balance->isNegative()) {
                throw new InsufficientFunds('Insufficient funds');
            }

            $transaction = $this->getWallet()->transactions()->create([
                'amount' => $amount,
                'processed_at' => $this->at,
                'confirmed' => $this->confirmed,
                'reference' => $this->reference,
                'meta' => $meta,
                'hint' => $this->hint,
            ]);

            if ($this->confirmed) {
                $this->getWallet()->setBalanceAttribute($balance);
            }

            return $transaction;
        });
    }

    /**
     * Convert currency if currency exchange.
     *
     * @param bool $exchange
     * @return $this
     */
    public function exchange($exchange = true)
    {
        $this->exchange = $exchange;
        return $this;
    }

    /**
     * Returns exchanged Money object.
     *
     * @param Money $amount
     * @return Money
     * @throws WalletError
     */
    protected function exchangedAmount(Money $amount)
    {
        if ($amount->getCurrency()->equals($this->getWallet()->currency)) {
            return $amount;
        } else if ($this->exchange) {
            return Abone::exchangeMoney($this->amount, $this->getWallet()->currency);
        } else {
            throw new WalletError(sprintf(
                'Mismatching currencies %s %s',
                $amount->getCurrency(),
                $this->getWallet()->currency
            ));
        }
    }

    /**
     * Returns wallet instance.
     *
     * @return Wallet
     */
    protected function getWallet()
    {
        if (is_null($this->walletInstance)) {
            $this->walletInstance = $this->owner->getWallet($this->wallet);
        }
        return $this->walletInstance;
    }

}
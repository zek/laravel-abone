<?php

namespace Zek\Abone;


use Money\Currency;
use Money\Money;
use Zek\Abone\Exceptions\AboneError;
use Zek\Abone\Exceptions\WalletError;

class Abone
{
    /**
     * The money converter.
     *
     * @var callable
     */
    protected static $exchangeMoneyUsing;

    /**
     * The default wallet currency.
     *
     * @var string
     */
    protected static $currency = 'USD';

    /**
     * The default wallet currency.
     *
     * @var array
     */
    public static $positiveWords = ['yes', 'y', 'true', 'ok'];

    /**
     * Set the default currency to be used when creating non-existing default wallet.
     *
     * @param  string $currency
     * @return void
     * @throws \Exception
     */
    public static function useCurrency($currency)
    {
        static::$currency = $currency;
    }

    /**
     * Get the default wallet currency.
     *
     * @return string
     */
    public static function usesCurrency()
    {
        return static::$currency;
    }

    /**
     * Set the custom currency converter.
     *
     * @param  callable $callback
     * @return void
     */
    public static function exchangeMoneyUsing(callable $callback)
    {
        static::$exchangeMoneyUsing = $callback;
    }

    /**
     * Format the given amount into a displayable currency.
     *
     * @param  Money $money
     * @param Currency $currency
     * @return Money
     * @throws WalletError
     */
    public static function exchangeMoney(Money $money, Currency $currency): Money
    {
        if (static::$exchangeMoneyUsing) {
            return call_user_func(static::$exchangeMoneyUsing, $money, $currency);
        }

        throw new WalletError('No exchange method has been set');
    }


}
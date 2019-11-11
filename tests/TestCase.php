<?php

namespace Zek\Abone\Tests;

use Money\Converter;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Exchange\FixedExchange;
use Money\Exchange\ReversedCurrenciesExchange;
use Money\Money;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Zek\Abone\Abone;
use Zek\Abone\Tests\Fixtures\User;

abstract class TestCase extends BaseTestCase
{
    protected function setUp() : void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/database/');
        $this->loadLaravelMigrations(['--database' => 'testbench']);

        $this->artisan('migrate', ['--database' => 'testbench']);


        $this->setUpBaseModels();

        $exchange = new ReversedCurrenciesExchange(new FixedExchange([
            'USD' => [
                'TRY' => 4,
                'EUR' => 1.1,
            ],
        ]));
        $converter = new Converter(new ISOCurrencies(), $exchange);

        Abone::exchangeMoneyUsing(function (Money $money, Currency $currency) use (&$converter) {
            return $converter->convert($money, $currency);
        });

    }


    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        Eloquent::unguard();
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return ['Zek\Abone\AboneServiceProvider'];
    }

    protected function setUpBaseModels()
    {
        /** @var User $user */
        User::create([
            'email' => 'drtzack@gmail.com',
            'name' => 'Talha Zekeriya Durmuş',
            'password' => bcrypt('123456')
        ]);
        User::create([
            'email' => 'testk@test.com',
            'name' => 'Test Account',
            'password' => bcrypt('123456')
        ]);
    }
}

<?php

namespace Zek\Abone\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model as Eloquent;
use Zek\Abone\Contracts\Subscriber;
use Zek\Abone\Traits\HasSubscriptions;
use Zek\Abone\Traits\HasWallets;

class User extends Eloquent implements Subscriber
{

    use HasWallets;
    use HasSubscriptions;

    protected $defaultCurrency = 'EUR';

}
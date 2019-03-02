<?php

namespace Zek\Abone\Exceptions;

use Throwable;

class DowngradeError extends SubscriptionError
{
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
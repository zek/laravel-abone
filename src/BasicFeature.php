<?php

namespace Zek\Abone;

use Carbon\CarbonInterval;
use Zek\Abone\Contracts\Feature;

class BasicFeature implements Feature
{
    private $code;
    private $value;
    /**
     * @var null
     */
    private $interval;

    /**
     * @param $code
     * @param $value
     * @param  null  $interval
     */
    public function __construct($code, $value, $interval = null)
    {
        $this->code = $code;
        $this->value = $value;
        $this->interval = $interval;
    }

    static public function make($code, $value, $interval = null)
    {
        if (is_string($interval)) {
            $interval = CarbonInterval::fromString($interval);
        }
        return new self($code, $value, $interval);
    }

    public function getCode()
    {
        return $this->code;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function interval(): ?CarbonInterval
    {
        return $this->interval;
    }
}

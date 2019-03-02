<?php

namespace Zek\Abone\Contracts;


use Carbon\CarbonInterval;

interface Feature
{

    public function getCode();

    public function getValue();

    public function interval(): ?CarbonInterval;

}

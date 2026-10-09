<?php

namespace TorobPay\Facades;

use Illuminate\Support\Facades\Facade;

class TorobPay extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'torobpay';
    }
}
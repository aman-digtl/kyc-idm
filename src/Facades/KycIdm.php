<?php

namespace DigtlCo\KycIdm\Facades;

use Illuminate\Support\Facades\Facade;


class KycIdm extends Facade 
{

    protected static function getFacadeAccessor()
    {
        return 'kycIdm';
    }

}
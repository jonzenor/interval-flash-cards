<?php

use Illuminate\Support\Facades\Facade;

return [

    'aliases' => Facade::defaultAliases()->merge([
        'Alert' => RealRashid\SweetAlert\Facades\Alert::class,
        'Redis' => Illuminate\Support\Facades\Redis::class,
    ])->toArray(),

];

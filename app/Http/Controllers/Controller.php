<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{

    public function getAuthedUser()
    {
        $user = User::where('id', auth()->user()->id)->with('records')->first();

        return $user;
    }
}

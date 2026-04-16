<?php

namespace App\Http\Controllers;

use App\Models\User;

class Controller
{

    public function getAuthedUser()
    {
        $user = User::where('id', auth()->user()->id)->with('records')->first();

        return $user;
    }
}

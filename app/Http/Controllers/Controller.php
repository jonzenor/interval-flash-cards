<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests;

    public function getAuthedUser()
    {
        $user = User::where('id', auth()->user()->id)->with('records')->first();

        return $user;
    }
}

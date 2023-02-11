<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Test extends Model
{
    public function questions()
    {
        return $this->belongsToMany(\App\Question::class, 'test_question');
    }

    public function set()
    {
        return $this->belongsTo(\App\Set::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\User::class);
    }

    public function getEndAtAttribute($time)
    {
        $ended = new Carbon($time);
        $diff = $ended->diffForHumans();

        return $diff;
    }
}

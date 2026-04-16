<?php

namespace App\Actions\Instructors;

class GetInstructorPersonalities
{
    public static function execute()
    {
        $personalities = [];

        for ($i = 1; $i <= config('personalities.ai_count'); $i ++) {
            $personalities[$i] = config('personalities.ai.' . $i);
            $personalities[$i]['avatarUrl'] = asset('images/' . $personalities[$i]['avatar']);
        }

        return $personalities;
    }
}

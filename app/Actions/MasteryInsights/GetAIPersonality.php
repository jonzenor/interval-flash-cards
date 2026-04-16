<?php

namespace App\Actions\MasteryInsights;

class GetAIPersonality
{
    public static function execute($personality_id)
    {
        return config('personalities.ai.' . $personality_id);
    }
}

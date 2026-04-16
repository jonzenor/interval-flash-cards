<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Insight extends Model
{
    /**
 * @use HasFactory<\Database\Factories\InsightFactory>
*/
    use HasFactory;


    protected function casts(): array
    {
        return [
            'ai_personality' => 'integer',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}

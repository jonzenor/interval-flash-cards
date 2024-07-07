<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Answer extends Model
{
    protected $fillable = [
        'text',
        'question_id',
        'correct',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Question::class);
    }
}

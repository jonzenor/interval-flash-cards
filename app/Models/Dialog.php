<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dialog extends Model
{
    /**
 * @use HasFactory<\Database\Factories\DialogFactory>
*/
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'personality_id',
        'user_id',
        'message',
    ];
}

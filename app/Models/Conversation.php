<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    /**
 * @use HasFactory<\Database\Factories\ConversationFactory>
*/
    use HasFactory;

    protected $fillable = [
        'insight_id',
        'user_id',
        'title',
    ];

    public function insight(): BelongsTo
    {
        return $this->belongsTo(Insight::class);
    }

    public function dialogs(): HasMany
    {
        return $this->hasMany(Dialog::class);
    }
}

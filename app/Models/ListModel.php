<?php

namespace App\Models;

use Database\Factories\ListModelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * "ListModel" para evitar la colisión con la palabra reservada List.
 */
class ListModel extends Model
{
    /** @use HasFactory<ListModelFactory> */
    use HasFactory;

    protected $table = 'lists';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_ranked' => 'boolean',
            'is_public' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ListItem::class, 'list_id')->orderBy('position');
    }

    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}

<?php

namespace App\Models;

use Database\Factories\ListModelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    /** invite_token nunca por mass assignment: solo se genera desde el controller del dueño. */
    protected $guarded = ['id', 'invite_token'];

    /** El token es la llave de edición: no puede viajar al frontend por accidente. */
    protected $hidden = ['invite_token'];

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

    /** Usuarios invitados que pueden editar el contenido de la lista. */
    public function collaborators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'list_collaborators', 'list_id', 'user_id')->withTimestamps();
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && $this->user_id === $user->id;
    }

    public function isCollaborator(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $this->relationLoaded('collaborators')
            ? $this->collaborators->contains('id', $user->id)
            : $this->collaborators()->whereKey($user->id)->exists();
    }
}

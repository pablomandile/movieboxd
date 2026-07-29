<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LetterboxdImport extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'lists_meta' => 'array',
            'unmatched' => 'array',
            'summary' => 'array',
            'finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(LetterboxdImportItem::class, 'import_id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['pending', 'processing'], true);
    }

    public function wantsSection(string $section): bool
    {
        return (bool) ($this->options[$section] ?? false);
    }
}

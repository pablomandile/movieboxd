<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LetterboxdImportItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(LetterboxdImport::class, 'import_id');
    }

    public function title(): BelongsTo
    {
        return $this->belongsTo(Title::class);
    }
}

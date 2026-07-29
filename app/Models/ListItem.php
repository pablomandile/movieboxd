<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListItem extends Model
{
    protected $guarded = [];

    public function list(): BelongsTo
    {
        return $this->belongsTo(ListModel::class, 'list_id');
    }

    public function title(): BelongsTo
    {
        return $this->belongsTo(Title::class);
    }
}

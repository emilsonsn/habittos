<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Entry extends Model
{
    protected $fillable = ['category_id', 'description', 'type', 'amount_cents', 'date', 'paid', 'is_fixed', 'notes'];

    protected function casts(): array
    {
        return ['date' => 'date', 'paid' => 'boolean', 'is_fixed' => 'boolean', 'amount_cents' => 'integer'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}

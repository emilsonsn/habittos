<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Note extends Model
{
    protected $fillable = ['title', 'text', 'category_id'];

    protected function casts(): array
    {
        return ['category_id' => 'integer', 'is_favorite' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(NoteCategory::class, 'category_id');
    }

    public function excerpt(): string
    {
        $text = preg_replace('/<[^>]+>/', ' ', $this->text ?? '');

        return Str::limit(Str::squish(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')), 180);
    }
}

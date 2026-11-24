<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudyStep extends Model
{
    protected $fillable = ['study_roadmap_id', 'title', 'position', 'canvas_x', 'canvas_y'];

    protected function casts(): array
    {
        return ['canvas_x' => 'float', 'canvas_y' => 'float'];
    }

    public function topics(): HasMany
    {
        return $this->hasMany(StudyTopic::class)->orderBy('position')->orderBy('id');
    }
}

<?php

namespace App\Models;

use App\Enums\StudyStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudyTopic extends Model
{
    protected $fillable = ['study_roadmap_id', 'study_step_id', 'title', 'description', 'checkpoint', 'status', 'position'];

    protected function casts(): array
    {
        return ['status' => StudyStatus::class];
    }

    public function roadmap(): BelongsTo
    {
        return $this->belongsTo(StudyRoadmap::class, 'study_roadmap_id');
    }
}

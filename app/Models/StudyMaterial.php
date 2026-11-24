<?php

namespace App\Models;

use App\Enums\StudyMaterialStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudyMaterial extends Model
{
    protected $fillable = ['study_roadmap_id', 'study_topic_id', 'title', 'url', 'path', 'original_name', 'mime', 'size', 'status'];

    protected function casts(): array
    {
        return ['status' => StudyMaterialStatus::class, 'size' => 'integer'];
    }

    public function roadmap(): BelongsTo
    {
        return $this->belongsTo(StudyRoadmap::class, 'study_roadmap_id');
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(StudyTopic::class, 'study_topic_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudySession extends Model
{
    protected $fillable = ['study_roadmap_id', 'study_topic_id', 'user_id', 'topic_title', 'studied_on', 'minutes', 'notes'];

    protected function casts(): array
    {
        return ['studied_on' => 'immutable_date', 'minutes' => 'integer'];
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

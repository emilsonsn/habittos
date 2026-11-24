<?php

namespace App\Models;

use App\Enums\StudyStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudyRoadmap extends Model
{
    protected $fillable = ['title', 'description', 'color', 'status', 'due_date'];

    protected function casts(): array
    {
        return ['status' => StudyStatus::class, 'due_date' => 'immutable_date'];
    }

    public function steps(): HasMany
    {
        return $this->hasMany(StudyStep::class)->orderBy('position')->orderBy('id');
    }

    public function topics(): HasMany
    {
        return $this->hasMany(StudyTopic::class);
    }

    public function connections(): HasMany
    {
        return $this->hasMany(StudyConnection::class);
    }

    public function progress(): int
    {
        return $this->topics_count ? (int) round($this->completed_topics_count / $this->topics_count * 100) : 0;
    }
}

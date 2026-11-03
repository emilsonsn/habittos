<?php

namespace App\Models;

use App\Enums\GoalPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Goal extends Model
{
    protected $fillable = ['title', 'description', 'period', 'year', 'month', 'week', 'priority', 'category', 'status', 'due_date', 'progress_percent', 'parent_id', 'import_key'];

    protected function casts(): array
    {
        return ['year' => 'integer', 'month' => 'integer', 'week' => 'integer', 'due_date' => 'date', 'progress_percent' => 'decimal:2'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function periodLabel(): string
    {
        return match ($this->period) {
            GoalPeriod::Monthly->value => CarbonImmutable::create($this->year, $this->month, 1)->locale('pt_BR')->translatedFormat('F / Y'),
            GoalPeriod::Weekly->value => 'Semana '.$this->week.' / '.$this->year,
            default => (string) $this->year,
        };
    }
}

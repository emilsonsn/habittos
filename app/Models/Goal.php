<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Goal extends Model
{
    public const PERIODS = ['annual' => 'Anual', 'monthly' => 'Mensal', 'weekly' => 'Semanal'];

    public const PRIORITIES = ['high' => 'Alta', 'medium' => 'Média', 'low' => 'Baixa'];

    public const STATUSES = ['not_started' => 'Não iniciado', 'in_progress' => 'Em andamento', 'completed' => 'Concluído', 'not_done' => 'Não realizado'];

    protected $fillable = ['title', 'period', 'year', 'month', 'week', 'priority', 'category', 'status', 'due_date', 'progress_percent', 'parent_id', 'import_key'];

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
            'monthly' => CarbonImmutable::create($this->year, $this->month, 1)->locale('pt_BR')->translatedFormat('F / Y'),
            'weekly' => 'Semana '.$this->week.' / '.$this->year,
            default => (string) $this->year,
        };
    }
}

<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum StudyStatus: string
{
    use HasOptions;

    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Paused = 'paused';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Não iniciado', self::InProgress => 'Em andamento',
            self::Completed => 'Concluído', self::Paused => 'Pausado',
        };
    }
}

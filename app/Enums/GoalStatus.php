<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum GoalStatus: string
{
    use HasOptions;

    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case NotDone = 'not_done';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Não iniciado',
            self::InProgress => 'Em andamento',
            self::Completed => 'Concluído',
            self::NotDone => 'Não realizado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::NotStarted => '#8b96a9', self::InProgress => '#9589e8',
            self::Completed => '#62b99d', self::NotDone => '#c75c73',
        };
    }
}

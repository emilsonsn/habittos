<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum GoalPriority: string
{
    use HasOptions;

    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    public function label(): string
    {
        return match ($this) {
            self::High => 'Alta',
            self::Medium => 'Média',
            self::Low => 'Baixa',
        };
    }
}

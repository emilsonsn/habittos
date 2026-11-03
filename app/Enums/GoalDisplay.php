<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum GoalDisplay: string
{
    use HasOptions;

    case Table = 'table';
    case Kanban = 'kanban';

    public function label(): string
    {
        return match ($this) {
            self::Table => 'Tabela',
            self::Kanban => 'Kanban',
        };
    }
}

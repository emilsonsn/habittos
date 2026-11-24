<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum StudyMaterialStatus: string
{
    use HasOptions;

    case ToRead = 'to_read';
    case InUse = 'in_use';
    case Finished = 'finished';

    public function label(): string
    {
        return match ($this) {
            self::ToRead => 'Para ver', self::InUse => 'Em uso', self::Finished => 'Finalizado',
        };
    }
}

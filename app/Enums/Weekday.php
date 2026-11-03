<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum Weekday: int
{
    use HasOptions;

    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;
    case Sunday = 7;

    public function label(): string
    {
        return match ($this) {
            self::Monday => 'Segunda', self::Tuesday => 'Terça', self::Wednesday => 'Quarta',
            self::Thursday => 'Quinta', self::Friday => 'Sexta', self::Saturday => 'Sábado', self::Sunday => 'Domingo',
        };
    }
}

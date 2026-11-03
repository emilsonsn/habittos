<?php

namespace App\Enums\Concerns;

trait HasOptions
{
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return array_combine(self::values(), array_map(fn (self $case) => $case->label(), self::cases()));
    }
}

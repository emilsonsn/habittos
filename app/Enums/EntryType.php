<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum EntryType: string
{
    use HasOptions;

    case Expense = 'expense';
    case Income = 'income';
    case Investment = 'investment';

    public function label(): string
    {
        return match ($this) {
            self::Expense => 'Despesa',
            self::Income => 'Receita',
            self::Investment => 'Investimento',
        };
    }
}

<?php

namespace App\Enums;

enum ConfirmationTone: string
{
    case Danger = 'danger';
    case Warning = 'warning';

    public function label(): string
    {
        return match ($this) {
            self::Danger => 'CONFIRMAÇÃO NECESSÁRIA',
            self::Warning => 'ANTES DE CONTINUAR',
        };
    }
}

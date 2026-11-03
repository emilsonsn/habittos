<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum GoalPeriod: string
{
    use HasOptions;

    case Annual = 'annual';
    case Monthly = 'monthly';
    case Weekly = 'weekly';

    public function label(): string
    {
        return match ($this) {
            self::Annual => 'Anual',
            self::Monthly => 'Mensal',
            self::Weekly => 'Semanal',
        };
    }

    public function unit(): string
    {
        return match ($this) {
            self::Annual => 'Ano', self::Monthly => 'Mês', self::Weekly => 'Semana',
        };
    }

    public function plural(): string
    {
        return match ($this) {
            self::Annual => 'anuais', self::Monthly => 'mensais', self::Weekly => 'semanais',
        };
    }

    public function previousLabel(): string
    {
        return $this->unit().' anterior';
    }

    public function currentLabel(): string
    {
        return $this->unit().' atual';
    }

    public function nextLabel(): string
    {
        return match ($this) {
            self::Annual => 'Próximo ano', self::Monthly => 'Próximo mês', self::Weekly => 'Próxima semana',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Annual => 0, self::Monthly => 1, self::Weekly => 2,
        };
    }
}

<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum FinancialIndicator: string
{
    use HasOptions;

    case MonthlyLimit = 'monthly_limit';
    case Reserve = 'reserve';

    public function label(): string
    {
        return match ($this) {
            self::MonthlyLimit => 'Limite mensal',
            self::Reserve => 'Reserva planejada',
        };
    }

    public function editableColumn(): ?string
    {
        return $this === self::Reserve ? 'reserve_cents' : null;
    }

    public function description(): string
    {
        return match ($this) {
            self::MonthlyLimit => 'Receitas menos despesas fixas e reserva planejada. Orçamento para gastos variáveis.',
            self::Reserve => 'Valor separado para investir e metas do mês.',
        };
    }
}

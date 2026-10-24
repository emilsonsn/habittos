<?php

namespace App\Enums;

enum FinanceSection: string
{
    case Dashboard = 'dashboard';
    case FixedIncome = 'fixed-income';
    case FixedExpenses = 'fixed-expenses';
    case Entries = 'entries';

    public function label(): string
    {
        return match ($this) {
            self::Dashboard => 'Dashboard',
            self::FixedIncome => 'Receitas fixas',
            self::FixedExpenses => 'Gastos fixos',
            self::Entries => 'Lançamentos do mês',
        };
    }

    public function routeName(): string
    {
        return $this === self::Dashboard ? 'finance.index' : 'finance.'.$this->value;
    }

    public function path(): string
    {
        return match ($this) {
            self::Dashboard => '/financeiro',
            self::FixedIncome => '/financeiro/receitas-fixas',
            self::FixedExpenses => '/financeiro/gastos-fixos',
            self::Entries => '/financeiro/lancamentos',
        };
    }
}

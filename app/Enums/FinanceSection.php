<?php

namespace App\Enums;

enum FinanceSection: string
{
    case Dashboard = 'dashboard';
    case FixedIncome = 'fixed-income';
    case FixedExpenses = 'fixed-expenses';
    case Investments = 'investments';
    case Entries = 'entries';

    public function label(): string
    {
        return match ($this) {
            self::Dashboard => 'Dashboard',
            self::FixedIncome => 'Receitas fixas',
            self::FixedExpenses => 'Gastos fixos',
            self::Investments => 'Investimentos',
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
            self::Investments => '/financeiro/investimentos',
            self::Entries => '/financeiro/lancamentos',
        };
    }
}

<?php

namespace App\Enums;

enum Permission: string
{
    case FinanceView = 'finance.view';
    case FinanceCreate = 'finance.create';
    case FinanceUpdate = 'finance.update';
    case FinanceDelete = 'finance.delete';
    case BudgetManage = 'budget.manage';
    case GoalsView = 'goals.view';
    case GoalsManage = 'goals.manage';
    case NotesView = 'notes.view';
    case NotesManage = 'notes.manage';
    case RoutineView = 'routine.view';
    case RoutineManage = 'routine.manage';
    case MembersManage = 'members.manage';

    public function label(): string
    {
        return match ($this) {
            self::FinanceView => 'Visualizar financeiro',
            self::FinanceCreate => 'Criar lançamentos',
            self::FinanceUpdate => 'Editar lançamentos',
            self::FinanceDelete => 'Excluir lançamentos',
            self::BudgetManage => 'Gerenciar orçamento e categorias',
            self::GoalsView => 'Visualizar metas',
            self::GoalsManage => 'Gerenciar metas',
            self::NotesView => 'Visualizar notas',
            self::NotesManage => 'Gerenciar notas e pastas',
            self::RoutineView => 'Visualizar rotina',
            self::RoutineManage => 'Gerenciar rotina',
            self::MembersManage => 'Gerenciar membros e permissões',
        };
    }
}

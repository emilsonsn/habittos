<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum WorkspaceModule: string
{
    use HasOptions;

    case Home = 'home';
    case Finance = 'finance';
    case Goals = 'goals';
    case Notes = 'notes';
    case Members = 'members';
    case Settings = 'settings';

    public function label(): string
    {
        return match ($this) {
            self::Home => 'Home',
            self::Finance => 'Financeiro',
            self::Goals => 'Metas',
            self::Notes => 'Notas',
            self::Members => 'Pessoas e acessos',
            self::Settings => 'Configurações',
        };
    }

    public function permission(): ?Permission
    {
        return match ($this) {
            self::Finance => Permission::FinanceView, self::Goals => Permission::GoalsView,
            self::Notes => Permission::NotesView, self::Members => Permission::MembersManage,
            default => null,
        };
    }

    public static function selectionValues(): array
    {
        return [...self::values(), ...array_map(fn ($section) => $section->routeName(), [
            ...FinanceSection::cases(), ...GoalSection::cases(), ...NoteSection::cases(),
        ])];
    }
}

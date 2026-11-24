<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum WorkspaceModule: string
{
    use HasOptions;

    case Home = 'home';
    case Finance = 'finance';
    case Goals = 'goals';
    case Studies = 'studies';
    case Notes = 'notes';
    case Routine = 'routine';
    case Members = 'members';
    case Settings = 'settings';

    public function label(): string
    {
        return match ($this) {
            self::Home => 'Home',
            self::Finance => 'Financeiro',
            self::Goals => 'Metas',
            self::Studies => 'Estudos',
            self::Notes => 'Notas',
            self::Routine => 'Rotina diária',
            self::Members => 'Pessoas e acessos',
            self::Settings => 'Configurações',
        };
    }

    public function permission(): ?Permission
    {
        return match ($this) {
            self::Finance => Permission::FinanceView, self::Goals => Permission::GoalsView,
            self::Studies => Permission::StudiesView,
            self::Routine => Permission::RoutineView,
            self::Notes => Permission::NotesView, self::Members => Permission::MembersManage,
            default => null,
        };
    }

    public static function selectionValues(): array
    {
        return [...self::values(), 'routine.index', ...array_map(fn ($section) => $section->routeName(), [
            ...FinanceSection::cases(), ...GoalSection::cases(), ...StudySection::cases(), ...NoteSection::cases(),
        ])];
    }
}

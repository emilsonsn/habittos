<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum GoalCategory: string
{
    use HasOptions;

    case Health = 'Saúde';
    case Professional = 'Profissional';
    case PersonalLife = 'Vida pessoal';
    case Finances = 'Finanças';
    case Studies = 'Estudos';
    case Spirituality = 'Espiritualidade';
    case FamilyAndRelationships = 'Família e relacionamentos';
    case Home = 'Casa';
    case Leisure = 'Lazer';
    case PersonalProjects = 'Projetos pessoais';

    public function label(): string
    {
        return match ($this) {
            self::Health => 'Saúde',
            self::Professional => 'Profissional',
            self::PersonalLife => 'Vida pessoal',
            self::Finances => 'Finanças',
            self::Studies => 'Estudos',
            self::Spirituality => 'Espiritualidade',
            self::FamilyAndRelationships => 'Família e relacionamentos',
            self::Home => 'Casa',
            self::Leisure => 'Lazer',
            self::PersonalProjects => 'Projetos pessoais',
        };
    }
}

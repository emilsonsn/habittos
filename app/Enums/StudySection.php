<?php

namespace App\Enums;

enum StudySection: string
{
    case Dashboard = 'dashboard';
    case Roadmaps = 'roadmaps';
    case Library = 'library';
    case History = 'history';

    public function label(): string
    {
        return match ($this) {
            self::Dashboard => 'Visão geral', self::Roadmaps => 'Roadmaps',
            self::Library => 'Biblioteca', self::History => 'Histórico',
        };
    }

    public function routeName(): string
    {
        return $this === self::Dashboard ? 'studies.index' : 'studies.'.$this->value;
    }

    public function path(): string
    {
        return $this === self::Dashboard ? '/estudos' : '/estudos/'.match ($this) {
            self::Roadmaps => 'roadmaps', self::Library => 'biblioteca', self::History => 'historico',
        };
    }
}

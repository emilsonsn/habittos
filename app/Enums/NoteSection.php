<?php

namespace App\Enums;

enum NoteSection: string
{
    case Dashboard = 'dashboard';
    case Favorites = 'favorites';
    case Browse = 'browse';

    public function label(): string
    {
        return match ($this) {
            self::Dashboard => 'Dashboard', self::Favorites => 'Favoritos', self::Browse => 'Notas',
        };
    }

    public function routeName(): string
    {
        return match ($this) {
            self::Dashboard => 'notes.index', self::Favorites => 'notes.favorites', self::Browse => 'notes.browse',
        };
    }

    public function path(): string
    {
        return match ($this) {
            self::Dashboard => '/notas', self::Favorites => '/notas/favoritos', self::Browse => '/notas/lista',
        };
    }
}

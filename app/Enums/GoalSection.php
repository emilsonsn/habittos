<?php

namespace App\Enums;

enum GoalSection: string
{
    case Dashboard = 'dashboard';
    case Annual = 'annual';
    case Monthly = 'monthly';
    case Weekly = 'weekly';

    public function label(): string
    {
        return match ($this) {
            self::Dashboard => 'Dashboard', self::Annual => 'Metas anuais',
            self::Monthly => 'Metas mensais', self::Weekly => 'Metas semanais',
        };
    }

    public function routeName(): string
    {
        return $this === self::Dashboard ? 'goals.index' : 'goals.'.$this->value;
    }

    public function path(): string
    {
        return match ($this) {
            self::Dashboard => '/metas', self::Annual => '/metas/anuais',
            self::Monthly => '/metas/mensais', self::Weekly => '/metas/semanais',
        };
    }
}

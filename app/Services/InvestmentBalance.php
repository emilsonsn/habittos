<?php

namespace App\Services;

use App\Enums\EntryType;
use App\Models\Workspace;

class InvestmentBalance
{
    public function forWorkspace(Workspace $workspace): int
    {
        return (int) $workspace->entries()->where('type', EntryType::Investment->value)->sum('amount_cents');
    }
}

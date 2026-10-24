<?php

namespace App\Services;

use App\Models\Workspace;

class InvestmentBalance
{
    public function forWorkspace(Workspace $workspace): int
    {
        return (int) $workspace->entries()->where('type', 'investment')->sum('amount_cents');
    }
}

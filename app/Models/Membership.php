<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class Membership extends Pivot
{
    protected function casts(): array
    {
        return ['permissions' => 'array'];
    }
}

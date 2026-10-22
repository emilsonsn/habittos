<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    protected $fillable = ['month', 'weekly_limit_cents', 'reserve_cents'];

    protected function casts(): array
    {
        return ['weekly_limit_cents' => 'integer', 'reserve_cents' => 'integer'];
    }
}

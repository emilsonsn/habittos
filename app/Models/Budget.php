<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    protected $fillable = ['month', 'starts_on', 'monthly_limit_cents', 'weekly_limit_cents', 'reserve_cents', 'investment_balance_cents'];

    protected function casts(): array
    {
        return ['starts_on' => 'immutable_date', 'monthly_limit_cents' => 'integer', 'weekly_limit_cents' => 'integer', 'reserve_cents' => 'integer', 'investment_balance_cents' => 'integer'];
    }
}

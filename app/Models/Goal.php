<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Goal extends Model
{
    protected $fillable = ['title', 'period', 'year', 'month', 'target_cents', 'current_cents'];

    protected function casts(): array
    {
        return ['target_cents' => 'integer', 'current_cents' => 'integer'];
    }
}

<?php

namespace App\Models;

use App\Enums\Weekday;
use Illuminate\Database\Eloquent\Model;

class RoutineActivity extends Model
{
    protected $fillable = ['routine_task_id', 'week_start', 'weekday', 'title', 'completed_at', 'position'];

    protected function casts(): array
    {
        return ['position' => 'integer', 'weekday' => Weekday::class, 'week_start' => 'immutable_date', 'completed_at' => 'immutable_datetime'];
    }
}

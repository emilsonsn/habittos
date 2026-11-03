<?php

namespace App\Models;

use App\Enums\Weekday;
use Illuminate\Database\Eloquent\Model;

class RoutineTask extends Model
{
    protected $fillable = ['weekday', 'title', 'starts_on', 'ends_before'];

    protected function casts(): array
    {
        return ['weekday' => Weekday::class, 'starts_on' => 'immutable_date', 'ends_before' => 'immutable_date'];
    }
}

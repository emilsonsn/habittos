<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudyConnection extends Model
{
    protected $fillable = ['study_roadmap_id', 'source_step_id', 'target_step_id'];
}

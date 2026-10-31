<?php

declare(strict_types=1);

namespace App\Modules\Socle\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicWeeklySchedule extends Model
{
    protected $table = 'academic_weekly_schedules';

    protected $fillable = ['day_of_week', 'is_school_open', 'is_teaching_day', 'starts_at', 'ends_at'];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_school_open' => 'boolean',
        'is_teaching_day' => 'boolean',
    ];
}

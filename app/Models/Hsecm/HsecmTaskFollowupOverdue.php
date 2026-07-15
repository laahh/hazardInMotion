<?php

declare(strict_types=1);

namespace App\Models\Hsecm;

use Illuminate\Database\Eloquent\Model;

class HsecmTaskFollowupOverdue extends Model
{
    protected $table = 'hsecm_task_followup_overdue';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'no_task_number' => 'integer',
        'year_of_date_time' => 'integer',
    ];
}

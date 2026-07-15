<?php

declare(strict_types=1);

namespace App\Models\Hsecm;

use Illuminate\Database\Eloquent\Model;

class HsecmTaskFollowupSubmitted extends Model
{
    protected $table = 'hsecm_task_followup_submitted';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'no_task_number' => 'integer',
        'selisih_jam_dari_submit' => 'integer',
        'year_of_date_time' => 'integer',
    ];
}

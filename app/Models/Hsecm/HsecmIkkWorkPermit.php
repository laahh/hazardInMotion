<?php

declare(strict_types=1);

namespace App\Models\Hsecm;

use Illuminate\Database\Eloquent\Model;

class HsecmIkkWorkPermit extends Model
{
    protected $table = 'hsecm_ikk_work_permit';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'iso_year_of_start_date_convert' => 'integer',
        'pct_compliance_ikk' => 'integer',
    ];
}

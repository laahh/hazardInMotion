<?php

declare(strict_types=1);

namespace App\Models\Hsecm;

use Illuminate\Database\Eloquent\Model;

class HsecmCoverageAreaCctv extends Model
{
    protected $table = 'hsecm_coverage_area_cctv';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'year_of_date' => 'integer',
        'pct_tercover' => 'integer',
    ];
}

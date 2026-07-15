<?php

declare(strict_types=1);

namespace App\Models\Hsecm;

use Illuminate\Database\Eloquent\Model;

class HsecmAggregatorPengisian extends Model
{
    protected $table = 'hsecm_aggregator_pengisian';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'year_of_tanggal_date' => 'integer',
        'pct_pengisian_aggregator' => 'integer',
    ];
}

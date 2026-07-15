<?php

declare(strict_types=1);

namespace App\Models\Hsecm;

use Illuminate\Database\Eloquent\Model;

class HsecmFatigueManagementCheck extends Model
{
    protected $table = 'hsecm_fatigue_management_check';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'jumlah_jam_tidur' => 'float',
        'year_of_tanggal_date' => 'integer',
    ];
}

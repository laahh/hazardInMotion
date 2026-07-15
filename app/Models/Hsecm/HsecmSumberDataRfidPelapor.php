<?php

declare(strict_types=1);

namespace App\Models\Hsecm;

use Illuminate\Database\Eloquent\Model;

class HsecmSumberDataRfidPelapor extends Model
{
    protected $table = 'hsecm_sumber_data_rfid_pelapor';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'year_of_date' => 'integer',
    ];
}

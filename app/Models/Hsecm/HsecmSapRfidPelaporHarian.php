<?php

declare(strict_types=1);

namespace App\Models\Hsecm;

use Illuminate\Database\Eloquent\Model;

class HsecmSapRfidPelaporHarian extends Model
{
    protected $table = 'hsecm_sap_rfid_pelapor_harian';

    public $timestamps = false;

    protected $guarded = [];
}

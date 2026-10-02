<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ValidasiTbc extends Model
{
    protected $table = 'validasi_tbc';

    protected $fillable = [
        'tasklist',
        'to_be_concerned_hazard',
        'gr',
        'catatan',
        'blindspot_terlapor_bc',
        'no_item_pspp',
        'kategori_gr',
        'sid_pekerja_terlibat',
        'nama_pekerja_terlibat',
    ];
}

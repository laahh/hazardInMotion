<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SidRosterBannedMaster extends Model
{
    protected $table = 'sid_roster_banned_master';

    protected $fillable = [
        'nik',
        'sid',
        'nama',
        'perusahaan',
        'site_dedicated',
        'alasan_pelanggaran',
        'tanggal_pelanggaran',
        'created_by',
    ];

    protected $casts = [
        'tanggal_pelanggaran' => 'date',
    ];
}

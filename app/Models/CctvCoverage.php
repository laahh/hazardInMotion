<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CctvCoverage extends Model
{
    use HasFactory;

    protected $table = 'cctv_coverage';

    protected $fillable = [
        'id_cctv',
        'coverage_lokasi',
        'coverage_detail_lokasi',
    ];

    /**
     * Relasi ke CctvData
     */
    public function cctvData()
    {
        return $this->belongsTo(CctvData::class, 'id_cctv', 'id');
    }
}


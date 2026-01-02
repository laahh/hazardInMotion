<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PjaCctv extends Model
{
    use HasFactory;

    protected $table = 'pja_cctv';

    protected $fillable = [
        'id_pja',
        'id_cctv',
    ];

    /**
     * Relasi ke CctvData
     */
    public function cctvData()
    {
        return $this->belongsTo(CctvData::class, 'id_cctv', 'id');
    }
}


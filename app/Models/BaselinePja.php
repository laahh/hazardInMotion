<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BaselinePja extends Model
{
    use HasFactory;

    protected $table = 'baseline_pja';

    protected $fillable = [
        'site',
        'perusahaan',
        'id_lokasi',
        'lokasi',
        'id_pja',
        'pja',
        'tipe_pja',
    ];
}


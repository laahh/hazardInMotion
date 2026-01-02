<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PjaCctvDedicated extends Model
{
    use HasFactory;

    protected $table = 'pja_cctv_dedicated';

    protected $fillable = [
        'no',
        'pja',
        'cctv_dedicated',
    ];
}


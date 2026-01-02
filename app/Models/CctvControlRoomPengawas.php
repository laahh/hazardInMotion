<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CctvControlRoomPengawas extends Model
{
    use HasFactory;

    protected $table = 'cctv_control_room_pengawas';

    protected $fillable = [
        'control_room',
        'nama_pengawas',
        'email_pengawas',
        'no_hp_pengawas',
        'keterangan',
    ];
}


<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GrTable extends Model
{
    use HasFactory;

    protected $table = 'gr_table';

    protected $fillable = [
        'tasklist',
        'gr',
        'catatan',
    ];
}


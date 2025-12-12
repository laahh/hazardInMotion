<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HazardValidation extends Model
{
    use HasFactory;

    protected $table = 'hazard_validations';

    protected $fillable = [
        'validator',
        'tasklist',
        'tobe_concerned_hazard',
        'gr',
        'catatan',
    ];
}


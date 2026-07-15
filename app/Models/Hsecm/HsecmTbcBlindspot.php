<?php

declare(strict_types=1);

namespace App\Models\Hsecm;

use Illuminate\Database\Eloquent\Model;

class HsecmTbcBlindspot extends Model
{
    protected $table = 'hsecm_tbc_blindspot';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'year_of_date_for_join' => 'integer',
    ];
}

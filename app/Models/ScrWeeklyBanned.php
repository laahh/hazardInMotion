<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ScrWeeklyBanned extends Model
{
    protected $table = 'scr_weekly_banned';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'scraped_at' => 'datetime',
    ];

    public function bannedLogWeekly(): HasOne
    {
        return $this->hasOne(SidBannedLogWeekly::class, 'scr_weekly_banned_id');
    }

    public function unbanRequests(): HasMany
    {
        return $this->hasMany(AutoBannedUnbanRequest::class, 'scr_weekly_banned_id');
    }
}

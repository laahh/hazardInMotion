<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AutoBannedSidAutomationStatus;
use App\Support\AutoBanned\ScrWeeklyBannedColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SidBannedLogWeekly extends Model
{
    protected $table = 'sid_banned_log_weekly';

    protected $guarded = [];

    protected $casts = [
        'filter_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'automation_status' => AutoBannedSidAutomationStatus::class,
    ];

    public function scrWeeklyBanned(): BelongsTo
    {
        return $this->belongsTo(ScrWeeklyBanned::class, 'scr_weekly_banned_id');
    }

    public function getDisplaySiteAttribute(): string
    {
        $site = trim((string) ($this->site_dedicated ?? ''));
        if ($site !== '') {
            return $site;
        }

        return trim((string) ($this->scrWeeklyBanned?->{ScrWeeklyBannedColumns::SITE} ?? ''));
    }
}

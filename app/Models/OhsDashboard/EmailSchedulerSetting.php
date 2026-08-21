<?php

declare(strict_types=1);

namespace App\Models\OhsDashboard;

use Illuminate\Database\Eloquent\Model;

class EmailSchedulerSetting extends Model
{
    protected $table = 'ohs_email_scheduler_settings';

    public $timestamps = false;

    protected $fillable = [
        'enabled',
        'frequency',
        'schedule_days',
        'send_hour',
        'send_minute',
        'recipients',
        'cc',
        'bcc',
        'portal_url',
        'overview_team',
        'overview_site',
        'include_leave_summary',
        'include_tracker_summary',
        'include_leaderboard',
        'subject_prefix',
        'event_reminder_days',
        'include_previous_days',
        'last_scheduled_key',
        'last_run_at',
        'last_run_status',
        'last_email_count',
        'updated_at',
        'updated_by',
        'overdue_reminder_last_key',
        'overdue_reminder_last_run_at',
        'overdue_reminder_last_count',
        'hse_sync_last_key',
        'hse_sync_last_run_at',
        'hse_sync_last_count',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'include_leave_summary' => 'boolean',
        'include_tracker_summary' => 'boolean',
        'include_leaderboard' => 'boolean',
        'send_hour' => 'integer',
        'send_minute' => 'integer',
        'include_previous_days' => 'integer',
        'last_email_count' => 'integer',
        'overdue_reminder_last_count' => 'integer',
        'hse_sync_last_count' => 'integer',
        'last_run_at' => 'datetime',
        'updated_at' => 'datetime',
        'overdue_reminder_last_run_at' => 'datetime',
        'hse_sync_last_run_at' => 'datetime',
    ];

    public static function instance(): self
    {
        $row = self::query()->orderBy('id')->first();

        if ($row instanceof self) {
            return $row;
        }

        return self::query()->create([
            'enabled' => false,
            'frequency' => 'SELECTED_DAYS',
            'schedule_days' => 'MON,TUE,WED,THU,FRI',
            'send_hour' => 7,
            'send_minute' => 0,
            'recipients' => '',
            'cc' => '',
            'bcc' => '',
            'portal_url' => '',
            'overview_team' => 'All Teams',
            'overview_site' => 'All Sites',
            'include_leave_summary' => true,
            'include_tracker_summary' => true,
            'include_leaderboard' => true,
            'subject_prefix' => '[OHS Portal]',
            'event_reminder_days' => '0,1,3,7',
            'include_previous_days' => 7,
            'last_scheduled_key' => '',
            'last_run_status' => 'Belum pernah dijalankan.',
            'last_email_count' => 0,
            'updated_by' => '',
            'overdue_reminder_last_key' => '',
            'overdue_reminder_last_count' => 0,
            'hse_sync_last_key' => '',
            'hse_sync_last_count' => 0,
        ]);
    }
}

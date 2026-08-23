<?php

declare(strict_types=1);

namespace App\Models\EmergencyResponse\MasterData;

use App\Models\EmergencyResponse\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmailTemplate extends Model
{
    use HasUuids, SoftDeletes, LogsAuditTrail;

    protected $table = 'er_email_templates';

    protected $fillable = [
        'code', 'name', 'subject', 'body_html', 'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}

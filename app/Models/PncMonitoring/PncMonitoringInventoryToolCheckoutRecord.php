<?php

declare(strict_types=1);

namespace App\Models\PncMonitoring;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PncMonitoringInventoryToolCheckoutRecord extends Model
{
    public $timestamps = false;

    protected $table = 'pnc_monitoring_inventory_tool_checkout_records';

    protected $primaryKey = 'record_id';

    protected $fillable = [
        'asset_id',
        'borrower_user_id',
        'checkout_date',
        'expected_return_date',
        'actual_return_date',
        'condition_out',
        'condition_in',
    ];

    protected function casts(): array
    {
        return [
            'checkout_date' => 'date',
            'expected_return_date' => 'date',
            'actual_return_date' => 'date',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(PncMonitoringInventoryToolAsset::class, 'asset_id', 'asset_id');
    }

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'borrower_user_id');
    }
}

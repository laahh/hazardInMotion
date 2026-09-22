<?php

declare(strict_types=1);

namespace App\Models\PncMonitoring;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PncMonitoringInventoryTool extends Model
{
    protected $table = 'pnc_monitoring_inventory_tools';

    /**
     * @var list<string>
     */
    public const CATEGORIES = [
        'Common Tools',
        'Special Tools',
        'Small Equipment',
        'Lifting Gear',
        'Supporting Tools',
    ];

    /**
     * @var list<string>
     */
    public const STATUSES = [
        'Available',
        'Checked-out',
        'In Repair',
        'Quarantine',
        'Scrapped',
    ];

    /**
     * @var list<string>
     */
    public const CONDITIONS = [
        'Good',
        'Fair',
        'Poor',
        'Damaged',
    ];

    /**
     * @var list<string>
     */
    public const FILLABLE_FIELDS = [
        'upsert_key',
        'category',
        'asset_id',
        'nama_alat',
        'sub_kategori',
        'brand',
        'model',
        'serial_number',
        'tahun_pembuatan',
        'power_source',
        'kapasitas_rating',
        'site',
        'lokasi_detail',
        'status_ketersediaan',
        'condition',
        'pic',
        'qty_on_hand',
        'calibration_required',
        'last_calibration_date',
        'calibration_due_date',
        'inspection_required',
        'last_inspection_date',
        'next_inspection_due',
        'inspection_result',
        'pm_required',
        'last_pm_date',
        'next_pm_due',
        'catatan',
    ];

    protected $fillable = [
        ...self::FILLABLE_FIELDS,
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qty_on_hand' => 'integer',
            'calibration_required' => 'boolean',
            'last_calibration_date' => 'date',
            'calibration_due_date' => 'date',
            'inspection_required' => 'boolean',
            'last_inspection_date' => 'date',
            'next_inspection_due' => 'date',
            'pm_required' => 'boolean',
            'last_pm_date' => 'date',
            'next_pm_due' => 'date',
        ];
    }

    public static function buildUpsertKey(string $category, string $namaAlat, ?string $assetId, ?string $serialNumber): string
    {
        $category = trim($category);
        $assetId = trim((string) $assetId);
        if ($assetId !== '') {
            return $category.'|ASSET:'.$assetId;
        }

        return $category.'|NAME:'.trim($namaAlat).'|SN:'.trim((string) $serialNumber);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

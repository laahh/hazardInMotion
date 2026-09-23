<?php

declare(strict_types=1);

namespace App\Models\PncMonitoring;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PncMonitoringInventoryToolMaster extends Model
{
    protected $table = 'pnc_monitoring_inventory_tool_master';

    protected $primaryKey = 'tool_master_id';

    protected $fillable = [
        'category_id',
        'standard_name',
        'sub_category',
        'main_function',
        'criticality',
        'risk_class',
        'is_regulated',
        'image_url',
    ];

    protected function casts(): array
    {
        return [
            'is_regulated' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PncMonitoringInventoryCategory::class, 'category_id', 'category_id');
    }

    public function functions(): HasMany
    {
        return $this->hasMany(PncMonitoringInventoryToolFunction::class, 'tool_master_id', 'tool_master_id')->orderBy('sequence');
    }

    public function inspectionMethods(): HasMany
    {
        return $this->hasMany(PncMonitoringInventoryToolInspectionMethod::class, 'tool_master_id', 'tool_master_id');
    }

    public function safetyFeatures(): HasMany
    {
        return $this->hasMany(PncMonitoringInventoryToolSafetyFeature::class, 'tool_master_id', 'tool_master_id');
    }

    public function standards(): HasMany
    {
        return $this->hasMany(PncMonitoringInventoryToolStandard::class, 'tool_master_id', 'tool_master_id');
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(PncMonitoringInventoryToolChecklistItem::class, 'tool_master_id', 'tool_master_id')->orderBy('sequence');
    }

    public function usageRules(): HasMany
    {
        return $this->hasMany(PncMonitoringInventoryToolUsageRule::class, 'tool_master_id', 'tool_master_id')->orderBy('sequence');
    }

    public function attributes(): HasMany
    {
        return $this->hasMany(PncMonitoringInventoryToolAttribute::class, 'tool_master_id', 'tool_master_id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(PncMonitoringInventoryToolAsset::class, 'tool_master_id', 'tool_master_id');
    }
}

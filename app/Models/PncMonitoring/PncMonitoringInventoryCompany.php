<?php

declare(strict_types=1);

namespace App\Models\PncMonitoring;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PncMonitoringInventoryCompany extends Model
{
    public $timestamps = false;

    protected $table = 'pnc_monitoring_inventory_companies';

    protected $primaryKey = 'company_id';

    protected $fillable = [
        'name',
        'type',
    ];

    public function assets(): HasMany
    {
        return $this->hasMany(PncMonitoringInventoryToolAsset::class, 'owner_company_id', 'company_id');
    }
}

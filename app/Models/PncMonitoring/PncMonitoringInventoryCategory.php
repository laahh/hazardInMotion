<?php

declare(strict_types=1);

namespace App\Models\PncMonitoring;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PncMonitoringInventoryCategory extends Model
{
    public $timestamps = false;

    protected $table = 'pnc_monitoring_inventory_categories';

    protected $primaryKey = 'category_id';

    protected $fillable = [
        'code',
        'name',
        'description',
    ];

    public function toolMasters(): HasMany
    {
        return $this->hasMany(PncMonitoringInventoryToolMaster::class, 'category_id', 'category_id');
    }
}

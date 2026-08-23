<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\MasterData;

use App\Http\Controllers\EmergencyResponse\MasterData\Concerns\SimpleLookupController;
use App\Models\EmergencyResponse\MasterData\MaintenanceType;

class MaintenanceTypeController extends SimpleLookupController
{
    protected string $model = MaintenanceType::class;

    protected string $routeName = 'emergency-response.master-data.maintenance-types';

    protected string $pageTitle = 'Jenis Maintenance';
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\MasterData;

use App\Http\Controllers\EmergencyResponse\MasterData\Concerns\SimpleLookupController;
use App\Models\EmergencyResponse\MasterData\IncidentType;

class IncidentTypeController extends SimpleLookupController
{
    protected string $model = IncidentType::class;

    protected string $routeName = 'emergency-response.master-data.incident-types';

    protected string $pageTitle = 'Jenis Insiden';
}

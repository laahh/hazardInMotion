<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\MasterData;

use App\Http\Controllers\EmergencyResponse\MasterData\Concerns\SimpleLookupController;
use App\Models\EmergencyResponse\MasterData\SafetyDeviceType;

class SafetyDeviceTypeController extends SimpleLookupController
{
    protected string $model = SafetyDeviceType::class;

    protected string $routeName = 'emergency-response.master-data.safety-device-types';

    protected string $pageTitle = 'Jenis Safety Device';
}

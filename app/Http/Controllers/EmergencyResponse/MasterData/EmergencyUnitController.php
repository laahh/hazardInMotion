<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\MasterData;

use App\Http\Controllers\EmergencyResponse\MasterData\Concerns\SimpleLookupController;
use App\Models\EmergencyResponse\MasterData\EmergencyUnit;

class EmergencyUnitController extends SimpleLookupController
{
    protected string $model = EmergencyUnit::class;

    protected string $routeName = 'emergency-response.master-data.emergency-units';

    protected string $pageTitle = 'Unit Emergency';
}

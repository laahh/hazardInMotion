<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\MasterData;

use App\Http\Controllers\EmergencyResponse\MasterData\Concerns\SimpleLookupController;
use App\Models\EmergencyResponse\MasterData\TrainingType;

class TrainingTypeController extends SimpleLookupController
{
    protected string $model = TrainingType::class;

    protected string $routeName = 'emergency-response.master-data.training-types';

    protected string $pageTitle = 'Jenis Training';
}

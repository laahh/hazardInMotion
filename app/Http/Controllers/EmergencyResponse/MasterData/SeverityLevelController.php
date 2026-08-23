<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\MasterData;

use App\Http\Controllers\EmergencyResponse\MasterData\Concerns\LeveledLookupController;
use App\Models\EmergencyResponse\MasterData\SeverityLevel;

class SeverityLevelController extends LeveledLookupController
{
    protected string $model = SeverityLevel::class;

    protected string $routeName = 'emergency-response.master-data.severity-levels';

    protected string $pageTitle = 'Tingkat Keparahan';
}

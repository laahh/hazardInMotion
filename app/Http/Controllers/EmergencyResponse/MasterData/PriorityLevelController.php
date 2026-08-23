<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\MasterData;

use App\Http\Controllers\EmergencyResponse\MasterData\Concerns\LeveledLookupController;
use App\Models\EmergencyResponse\MasterData\PriorityLevel;

class PriorityLevelController extends LeveledLookupController
{
    protected string $model = PriorityLevel::class;

    protected string $routeName = 'emergency-response.master-data.priority-levels';

    protected string $pageTitle = 'Tingkat Prioritas';
}

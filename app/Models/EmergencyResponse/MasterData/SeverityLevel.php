<?php

declare(strict_types=1);

namespace App\Models\EmergencyResponse\MasterData;

class SeverityLevel extends LeveledLookup
{
    protected $table = 'er_severity_levels';
}

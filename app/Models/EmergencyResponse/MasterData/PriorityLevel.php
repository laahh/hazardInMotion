<?php

declare(strict_types=1);

namespace App\Models\EmergencyResponse\MasterData;

class PriorityLevel extends LeveledLookup
{
    protected $table = 'er_priority_levels';
}

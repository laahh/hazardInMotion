<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\MasterData;

use App\Http\Controllers\EmergencyResponse\MasterData\Concerns\SimpleLookupController;
use App\Models\EmergencyResponse\MasterData\Department;

class DepartmentController extends SimpleLookupController
{
    protected string $model = Department::class;

    protected string $routeName = 'emergency-response.master-data.departments';

    protected string $pageTitle = 'Departemen';
}

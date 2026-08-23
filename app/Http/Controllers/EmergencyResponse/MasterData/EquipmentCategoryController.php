<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\MasterData;

use App\Http\Controllers\EmergencyResponse\MasterData\Concerns\SimpleLookupController;
use App\Models\EmergencyResponse\MasterData\EquipmentCategory;

class EquipmentCategoryController extends SimpleLookupController
{
    protected string $model = EquipmentCategory::class;

    protected string $routeName = 'emergency-response.master-data.equipment-categories';

    protected string $pageTitle = 'Kategori Equipment';
}

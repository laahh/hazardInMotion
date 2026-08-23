<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\MasterData;

use App\Http\Controllers\EmergencyResponse\MasterData\Concerns\SimpleLookupController;
use App\Models\EmergencyResponse\MasterData\CertificationType;

class CertificationTypeController extends SimpleLookupController
{
    protected string $model = CertificationType::class;

    protected string $routeName = 'emergency-response.master-data.certification-types';

    protected string $pageTitle = 'Jenis Sertifikasi';
}

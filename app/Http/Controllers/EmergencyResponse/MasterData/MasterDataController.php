<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\MasterData;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class MasterDataController extends Controller
{
    public function index(): View
    {
        $groups = [
            'Lokasi & Organisasi' => [
                ['label' => 'Site', 'route' => 'emergency-response.master-data.sites.index'],
                ['label' => 'Departemen', 'route' => 'emergency-response.master-data.departments.index'],
                ['label' => 'Unit Emergency', 'route' => 'emergency-response.master-data.emergency-units.index'],
            ],
            'Equipment & Insiden' => [
                ['label' => 'Kategori Equipment', 'route' => 'emergency-response.master-data.equipment-categories.index'],
                ['label' => 'Jenis Safety Device', 'route' => 'emergency-response.master-data.safety-device-types.index'],
                ['label' => 'Jenis Insiden', 'route' => 'emergency-response.master-data.incident-types.index'],
                ['label' => 'Tingkat Keparahan', 'route' => 'emergency-response.master-data.severity-levels.index'],
                ['label' => 'Tingkat Prioritas', 'route' => 'emergency-response.master-data.priority-levels.index'],
                ['label' => 'Template Checklist', 'route' => 'emergency-response.master-data.checklist-templates.index'],
            ],
            'Maintenance & Vendor' => [
                ['label' => 'Jenis Maintenance', 'route' => 'emergency-response.master-data.maintenance-types.index'],
                ['label' => 'Vendor', 'route' => 'emergency-response.master-data.vendors.index'],
            ],
            'Manpower' => [
                ['label' => 'Jenis Training', 'route' => 'emergency-response.master-data.training-types.index'],
                ['label' => 'Jenis Sertifikasi', 'route' => 'emergency-response.master-data.certification-types.index'],
                ['label' => 'Shift', 'route' => 'emergency-response.master-data.shifts.index'],
            ],
            'SLA & Notifikasi' => [
                ['label' => 'SLA', 'route' => 'emergency-response.master-data.slas.index'],
                ['label' => 'Escalation Matrix', 'route' => 'emergency-response.master-data.escalation-matrices.index'],
                ['label' => 'Template Email', 'route' => 'emergency-response.master-data.email-templates.index'],
                ['label' => 'Template Notifikasi', 'route' => 'emergency-response.master-data.notification-templates.index'],
            ],
        ];

        return view('EmergencyResponse.master-data.index', ['groups' => $groups]);
    }
}

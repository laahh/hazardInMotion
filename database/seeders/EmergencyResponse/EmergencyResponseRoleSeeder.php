<?php

declare(strict_types=1);

namespace Database\Seeders\EmergencyResponse;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Seeds the 7 roles from spec §3 and a base permission set for the
 * Emergency Response & Safety Management System module, reusing the
 * app-wide Role/Permission tables (no new RBAC system introduced).
 * Idempotent: safe to re-run (firstOrCreate by slug).
 */
class EmergencyResponseRoleSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'er.equipment.manage' => 'Kelola Emergency Equipment',
            'er.safety-device.manage' => 'Kelola Safety Device',
            'er.inspection.perform' => 'Melakukan Inspeksi',
            'er.inspection.approve' => 'Approval Hasil Inspeksi',
            'er.incident.report' => 'Membuat Laporan Insiden',
            'er.incident.dispatch' => 'Dispatch & Kelola Response Insiden',
            'er.maintenance.manage' => 'Kelola Maintenance',
            'er.work-order.manage' => 'Kelola Work Order (Approval/Assign)',
            'er.work-order.execute' => 'Eksekusi Work Order (Technician)',
            'er.manpower.manage' => 'Kelola Data Manpower',
            'er.master-data.manage' => 'Kelola Master Data',
            'er.report.view' => 'Lihat Laporan & Dashboard',
            'er.audit-log.view' => 'Lihat Audit Log',
            'er.settings.manage' => 'Kelola Pengaturan Modul',
        ];

        foreach ($permissions as $slug => $name) {
            Permission::firstOrCreate(['slug' => $slug], ['name' => $name]);
        }

        $roles = [
            'super-admin' => [
                'name' => 'Super Admin',
                'description' => 'Akses penuh ke seluruh sistem Emergency Response & Safety Management.',
                'permissions' => array_keys($permissions),
            ],
            'hse-admin' => [
                'name' => 'HSE/Emergency Admin',
                'description' => 'Mengelola equipment, inspeksi, insiden, maintenance, dan manpower.',
                'permissions' => [
                    'er.equipment.manage', 'er.safety-device.manage', 'er.inspection.approve',
                    'er.incident.report', 'er.incident.dispatch', 'er.maintenance.manage',
                    'er.work-order.manage', 'er.manpower.manage', 'er.master-data.manage',
                    'er.report.view', 'er.audit-log.view', 'er.settings.manage',
                ],
            ],
            'dispatcher' => [
                'name' => 'Dispatcher / Tim On-Duty',
                'description' => 'Menerima laporan, mengubah status insiden, menentukan unit & personel yang dikerahkan.',
                'permissions' => ['er.incident.dispatch', 'er.report.view'],
            ],
            'inspector' => [
                'name' => 'Inspector',
                'description' => 'Melakukan inspeksi peralatan: checklist, temuan, foto, tindak lanjut.',
                'permissions' => ['er.inspection.perform', 'er.report.view'],
            ],
            'technician' => [
                'name' => 'Technician',
                'description' => 'Menerima & mengerjakan work order: aktivitas perbaikan, spare part, hasil pekerjaan.',
                'permissions' => ['er.work-order.execute', 'er.report.view'],
            ],
            'reporter' => [
                'name' => 'Employee / Reporter',
                'description' => 'Membuat laporan insiden dan melihat perkembangan laporan yang dibuatnya.',
                'permissions' => ['er.incident.report'],
            ],
            'manager-viewer' => [
                'name' => 'Manager / Viewer',
                'description' => 'Melihat dashboard, laporan, grafik, dan KPI. Tidak dapat mengubah data operasional.',
                'permissions' => ['er.report.view', 'er.audit-log.view'],
            ],
        ];

        foreach ($roles as $slug => $definition) {
            $role = Role::firstOrCreate(
                ['slug' => $slug],
                ['name' => $definition['name'], 'description' => $definition['description']],
            );

            foreach ($definition['permissions'] as $permissionSlug) {
                $role->assignPermission($permissionSlug);
            }
        }
    }
}

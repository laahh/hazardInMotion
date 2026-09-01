<?php

declare(strict_types=1);

namespace Database\Seeders\Isc;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

final class IscRoleSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'isc.intervention.manage' => 'Kelola intervensi ISC (PIC)',
            'isc.intervention.verify' => 'Verifikasi intervensi ISC',
            'isc.report.view' => 'Lihat rekap post-event ISC',
        ];
        foreach ($permissions as $slug => $name) {
            Permission::firstOrCreate(['slug' => $slug], ['name' => $name]);
        }

        $roles = [
            'isc-pic' => [
                'name' => 'ISC PIC',
                'description' => 'Person in charge intervensi zona berbahaya ISC.',
                'permissions' => ['isc.intervention.manage', 'isc.report.view'],
            ],
            'isc-verifier' => [
                'name' => 'ISC Verifier',
                'description' => 'Memverifikasi intervensi ISC. Tidak boleh memverifikasi event miliknya sendiri.',
                'permissions' => ['isc.intervention.verify', 'isc.report.view'],
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

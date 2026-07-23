<?php

declare(strict_types=1);

namespace App\Services\SportEvaluation;

use App\Models\User;

/**
 * Kebijakan akses & scoping untuk modul Evaluasi Olahraga & Aktivitas.
 *
 * Scoping: Super Admin / HR Pusat melihat seluruh data; peran Manajer dapat
 * dibatasi per divisi lewat scopeFor(). Saat ini pemetaan Manajer->divisi
 * belum tersedia di panel, sehingga scopeFor() mengembalikan array kosong
 * (tanpa pembatasan). Tinggal isi mapping di sini bila dibutuhkan.
 */
final class SportEvaluationAccessService
{
    /**
     * Peran yang boleh mengakses modul (selain admin).
     *
     * @var array<int,string>
     */
    private const ALLOWED_ROLES = [
        'super-admin',
        'hr-pusat',
        'sport-evaluator',
        'admin-hazard-motion',
    ];

    public function canAccessModule(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        foreach (self::ALLOWED_ROLES as $role) {
            if ($user->hasRole($role)) {
                return true;
            }
        }

        // Fallback aman: pengguna terautentikasi diperbolehkan (panel internal).
        // Ketatkan dengan menghapus baris ini bila peran sudah di-seed.
        return true;
    }

    /**
     * Scope wajib (tidak bisa ditimpa user) berdasarkan peran.
     *
     * @return array{divisi?:string,perusahaan?:string,site?:string}
     */
    public function scopeFor(?User $user): array
    {
        return [];
    }
}

<?php

declare(strict_types=1);

namespace App\Services\SportEvaluation;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Kebijakan akses & scoping untuk modul Evaluasi Olahraga & Aktivitas.
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

    /**
     * @var array<int, string>
     */
    private const MITRA_MANAGER_ROLES = [
        'super-admin',
        'hr-pusat',
    ];

    public function __construct(
        private readonly SportEvaluationMitraAssignmentService $mitraAssignmentService,
    ) {}

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

    public function isMitraManager(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        foreach (self::MITRA_MANAGER_ROLES as $role) {
            if ($user->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    public function canManageAssignments(?User $user): bool
    {
        return $this->isMitraManager($user);
    }

    /**
     * User Mitra Kerja (punya assignment aktif) yang bukan Admin/HR.
     * Hanya boleh mengakses fitur /evaluasi-well/mitra.
     */
    public function isMitraOnlyUser(?User $user): bool
    {
        if ($user === null || $this->isMitraManager($user)) {
            return false;
        }

        $scope = $this->scopeFor($user);

        return isset($scope['site'], $scope['perusahaan'])
            && trim((string) $scope['site']) !== ''
            && trim((string) $scope['perusahaan']) !== '';
    }

    /**
     * Route yang diizinkan untuk user Mitra-only.
     */
    public function isMitraAllowedRoute(?string $routeName): bool
    {
        if ($routeName === null || $routeName === '') {
            return false;
        }

        if (str_starts_with($routeName, 'evaluasi-well.mitra.')) {
            return true;
        }

        return in_array($routeName, [
            'evaluasi-well.employees.show',
            'logout',
            'password.confirm',
            'password.update',
        ], true);
    }

    /**
     * Scope wajib dari assignment aktif user (kosong untuk manager tanpa assignment).
     *
     * @return array{site?:string,perusahaan?:string}
     */
    public function scopeFor(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $assignment = $this->mitraAssignmentService->findActiveForUser((int) $user->id);
        $scope = $this->mitraAssignmentService->scopeFromAssignment($assignment);

        return $scope ?? [];
    }

    /**
     * Resolve scope untuk halaman/API Mitra Kerja.
     *
     * - User dengan assignment: pakai assignment (abaikan query string).
     * - Manager: boleh pilih via request site + perusahaan.
     * - Selain itu tanpa assignment: null (tampilkan empty state).
     *
     * @return array{site:string,perusahaan:string}|null
     */
    public function resolveMitraScope(?User $user, ?Request $request = null): ?array
    {
        if ($user === null) {
            return null;
        }

        $assignmentScope = $this->scopeFor($user);
        if ($assignmentScope !== []
            && isset($assignmentScope['site'], $assignmentScope['perusahaan'])
            && $assignmentScope['site'] !== ''
            && $assignmentScope['perusahaan'] !== ''
        ) {
            // Non-manager selalu terkunci ke assignment.
            // Manager dengan assignment juga terkunci (satu mapping per user).
            if (! $this->isMitraManager($user) || ! $this->requestWantsOverride($request)) {
                return [
                    'site' => (string) $assignmentScope['site'],
                    'perusahaan' => (string) $assignmentScope['perusahaan'],
                ];
            }
        }

        if (! $this->isMitraManager($user)) {
            return $assignmentScope !== []
                ? [
                    'site' => (string) ($assignmentScope['site'] ?? ''),
                    'perusahaan' => (string) ($assignmentScope['perusahaan'] ?? ''),
                ]
                : null;
        }

        $site = $this->readScopeValue($request?->input('site'));
        $perusahaan = $this->readScopeValue($request?->input('perusahaan', $request?->input('company')));

        if ($site === '' || $perusahaan === '') {
            return null;
        }

        return [
            'site' => $site,
            'perusahaan' => $perusahaan,
        ];
    }

    private function requestWantsOverride(?Request $request): bool
    {
        if ($request === null) {
            return false;
        }

        $site = $this->readScopeValue($request->input('site'));
        $perusahaan = $this->readScopeValue($request->input('perusahaan', $request->input('company')));

        return $site !== '' && $perusahaan !== '';
    }

    private function readScopeValue(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return mb_substr(trim($value), 0, 180);
    }
}

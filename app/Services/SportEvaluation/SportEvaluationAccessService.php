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
     * Hanya boleh mengakses fitur /evaluasi-well/mitra dan /evaluasi-well/pvt.
     */
    public function isMitraOnlyUser(?User $user): bool
    {
        if ($user === null || $this->isMitraManager($user)) {
            return false;
        }

        $scope = $this->scopeFor($user);

        return $this->mitraAssignmentService->hasScope($scope);
    }

    /**
     * Route yang diizinkan untuk user Mitra-only.
     */
    public function isMitraAllowedRoute(?string $routeName): bool
    {
        if ($routeName === null || $routeName === '') {
            return false;
        }

        if (str_starts_with($routeName, 'evaluasi-well.mitra.')
            || str_starts_with($routeName, 'evaluasi-well.pvt.')
        ) {
            return true;
        }

        return in_array($routeName, [
            'evaluasi-well.employees.show',
            'evaluasi-well.not-installed.data',
            'evaluasi-well.not-installed.export',
            'evaluasi-well.install-stats.export',
            'logout',
            'password.confirm',
            'password.update',
        ], true);
    }

    /**
     * Scope wajib dari assignment aktif user (kosong untuk manager tanpa assignment).
     *
     * @return array{
     *     site?: string,
     *     perusahaan?: string,
     *     pairs?: list<array{site: string, perusahaan: string}>,
     *     companies?: list<array{perusahaan: string, sites: list<string>}>
     * }
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
     * @return array{
     *     site: string,
     *     perusahaan: string,
     *     pairs: list<array{site: string, perusahaan: string}>,
     *     companies: list<array{perusahaan: string, sites: list<string>}>
     * }|null
     */
    public function resolveMitraScope(?User $user, ?Request $request = null): ?array
    {
        if ($user === null) {
            return null;
        }

        $assignmentScope = $this->scopeFor($user);
        if ($this->mitraAssignmentService->hasScope($assignmentScope)) {
            // Non-manager selalu terkunci ke assignment.
            // Manager dengan assignment juga terkunci, kecuali override via query.
            if (! $this->isMitraManager($user) || ! $this->requestWantsOverride($request)) {
                return $this->mitraAssignmentService->normalizeScope($assignmentScope);
            }
        }

        if (! $this->isMitraManager($user)) {
            return $this->mitraAssignmentService->hasScope($assignmentScope)
                ? $this->mitraAssignmentService->normalizeScope($assignmentScope)
                : null;
        }

        $site = $this->readScopeValue($request?->input('site'));
        $perusahaan = $this->readScopeValue($request?->input('perusahaan', $request?->input('company')));

        if ($site === '' || $perusahaan === '') {
            return null;
        }

        return $this->mitraAssignmentService->normalizeScope([
            'site' => $site,
            'perusahaan' => $perusahaan,
        ]);
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

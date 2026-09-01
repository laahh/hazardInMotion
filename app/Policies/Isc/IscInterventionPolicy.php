<?php

declare(strict_types=1);

namespace App\Policies\Isc;

use App\Models\Isc\IscIntervention;
use App\Models\User;

final class IscInterventionPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isStaff($user);
    }

    public function view(User $user, IscIntervention $intervention): bool
    {
        return $this->isStaff($user);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->hasRole('isc-pic');
    }

    public function uploadEvidence(User $user, IscIntervention $intervention): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->hasRole('isc-pic') && (int) $user->id === (int) $intervention->pic_user_id;
    }

    public function verify(User $user, IscIntervention $intervention): bool
    {
        if ((int) $user->id === (int) $intervention->pic_user_id) {
            return false;
        }

        return $user->isAdmin() || $user->hasRole('isc-verifier');
    }

    private function isStaff(User $user): bool
    {
        return $user->isAdmin() || $user->hasRole('isc-pic') || $user->hasRole('isc-verifier');
    }
}

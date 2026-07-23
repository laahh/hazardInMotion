<?php

declare(strict_types=1);

namespace App\Http\Controllers\SportEvaluation;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Profil karyawan — desain layout dulu (tanpa query DB).
 */
class SportEmployeeController extends Controller
{
    public function show(int $userId): View
    {
        return view('evaluasi-well.employees.show', [
            'userId' => $userId,
        ]);
    }
}

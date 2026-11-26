<?php

declare(strict_types=1);

namespace App\Http\Controllers\PetaRisiko;

use App\Http\Controllers\Controller;
use App\Services\PetaRisiko\Drm\Master\DrmMasterDb;
use App\Services\PetaRisiko\Drm\Master\DrmMasterGrup;
use Illuminate\Contracts\View\View;

/**
 * Tabel ringkas "lokasi → PJA → PIC Perusahaan" (/peta-risiko-pja/master/pic): satu baris = satu lokasi fisik
 * (site + lokasi), murni untuk dilihat (tidak bisa diedit di sini — edit PJA/PIC lewat sinkronisasi master).
 */
final class PetaRisikoMasterPicController extends Controller
{
    public function __construct(private readonly DrmMasterGrup $grup)
    {
    }

    public function index(): View
    {
        $siap = DrmMasterDb::terisi();

        return view('peta-risiko-pja.drm.master-pic', [
            'siap' => $siap,
            'rows' => $siap ? $this->grup->semuaLokasiPjaDanPic() : [],
            'site' => $siap ? $this->grup->daftarSite() : [],
        ]);
    }
}

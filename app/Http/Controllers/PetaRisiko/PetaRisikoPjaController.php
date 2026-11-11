<?php

namespace App\Http\Controllers\PetaRisiko;

use App\Http\Controllers\Controller;
use App\Services\PetaRisiko\PetaRisikoRepository;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PetaRisikoPjaController extends Controller
{
    private const PERIODE = ['wk', 'ytd'];

    public function __construct(private readonly PetaRisikoRepository $repo)
    {
    }

    public function index(Request $request): View
    {
        $meta = $this->repo->meta();

        return view('peta-risiko-pja.index', [
            'meta' => $meta,
            'periode' => $this->periode($request),
            'site' => (string) $request->query('site', ''),
        ]);
    }

    public function show(Request $request, string $pja): View
    {
        $meta = $this->repo->meta();
        $data = $meta ? $this->repo->pja($pja) : null;
        abort_if($data === null, 404, 'PJA tidak ditemukan di snapshot.');

        return view('peta-risiko-pja.show', [
            'meta' => $meta,
            'pja' => $data,
            'periode' => $this->periode($request),
        ]);
    }

    private function periode(Request $request): string
    {
        $p = (string) $request->query('periode', 'wk');

        return in_array($p, self::PERIODE, true) ? $p : 'wk';
    }
}

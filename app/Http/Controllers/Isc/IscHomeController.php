<?php

declare(strict_types=1);

namespace App\Http\Controllers\Isc;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

final class IscHomeController extends Controller
{
    public function index(): View
    {
        return view('isc.home', [
            'heroImage' => asset('isc-assets/isc-home-hero.png'),
            'controlRoomImage' => asset('isc-assets/isc-home-control-room.png'),
            'overviewUrl' => route('isc.overview'),
            'mapsUrl' => route('isc.maps.index'),
            'interventionsUrl' => route('isc.interventions.index'),
            'cctvUrl' => route('isc.maps.cctv'),
        ]);
    }
}

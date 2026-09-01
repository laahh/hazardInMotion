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
            'heroImage' => asset('isc-assets/home.png'),
            'mapsUrl' => route('isc.maps.index'),
            'interventionsUrl' => route('isc.interventions.index'),
            'postEventUrl' => route('isc.post-event.index'),
        ]);
    }
}

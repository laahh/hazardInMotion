<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\Shared;

use App\Http\Controllers\Controller;
use App\Models\EmergencyResponse\Equipment\EmergencyEquipment;
use App\Models\EmergencyResponse\SafetyDevice\SafetyDevice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * QR codes printed for equipment/safety-device encode a URL to this
 * controller's `show` route — scanning with any phone camera app opens
 * this URL directly (auth middleware bounces to login first if needed,
 * then Laravel's intended-URL redirect lands back here).
 */
class ScanController extends Controller
{
    public function index(): View
    {
        return view('EmergencyResponse.shared.scan');
    }

    public function lookup(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);

        return redirect()->route('emergency-response.scan.show', ['code' => $request->input('code')]);
    }

    public function show(string $code): RedirectResponse
    {
        $equipment = EmergencyEquipment::query()->where('code', $code)->first();
        if ($equipment) {
            return redirect()->route('emergency-response.equipment.show', $equipment);
        }

        $safetyDevice = SafetyDevice::query()->where('code', $code)->first();
        if ($safetyDevice) {
            return redirect()->route('emergency-response.safety-device.show', $safetyDevice);
        }

        return redirect()->route('emergency-response.scan.index')->with('error', "Kode \"{$code}\" tidak ditemukan.");
    }
}

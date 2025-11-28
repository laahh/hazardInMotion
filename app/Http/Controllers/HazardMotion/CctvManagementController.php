<?php

namespace App\Http\Controllers\HazardMotion;

use App\Http\Controllers\Controller;
use App\Models\CctvData;
use Illuminate\Http\Request;

class CctvManagementController extends Controller
{
    /**
     * Display CCTV status monitoring page
     */
    public function status()
    {
        // Ambil semua data CCTV
        $cctvData = CctvData::all();

        // Format data untuk status monitoring
        $cctvStatus = $cctvData->map(function ($cctv) {
            return [
                'id' => $cctv->id,
                'no_cctv' => $cctv->no_cctv ?? 'CCTV-' . $cctv->id,
                'nama_cctv' => $cctv->nama_cctv ?? 'CCTV ' . $cctv->id,
                'site' => $cctv->site ?? 'Unknown',
                'perusahaan' => $cctv->perusahaan ?? 'Unknown',
                'status' => $cctv->status ?? 'Unknown',
                'kondisi' => $cctv->kondisi ?? 'Unknown',
                'connected' => $cctv->connected ?? 'Unknown',
                'lokasi_pemasangan' => $cctv->lokasi_pemasangan ?? '',
                'control_room' => $cctv->control_room ?? '',
                'link_akses' => $cctv->link_akses ?? '',
                'longitude' => $cctv->longitude,
                'latitude' => $cctv->latitude,
                'last_checked' => $cctv->updated_at ? $cctv->updated_at->format('Y-m-d H:i:s') : 'Never',
            ];
        })->toArray();

        // Statistics
        $stats = [
            'total_cctv' => count($cctvStatus),
            'online_cctv' => count(array_filter($cctvStatus, fn($c) => ($c['status'] === 'Live View' || $c['connected'] === 'Yes' || $c['kondisi'] === 'Baik'))),
            'offline_cctv' => count(array_filter($cctvStatus, fn($c) => ($c['status'] !== 'Live View' && $c['connected'] !== 'Yes' && $c['kondisi'] !== 'Baik'))),
            'with_coordinates' => count(array_filter($cctvStatus, fn($c) => $c['longitude'] && $c['latitude'])),
        ];

        return view('HazardMotion.admin.cctv-status', compact('cctvStatus', 'stats'));
    }
}


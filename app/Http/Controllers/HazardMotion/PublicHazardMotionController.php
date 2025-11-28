<?php

namespace App\Http\Controllers\HazardMotion;

use App\Http\Controllers\Controller;
use App\Models\CctvData;

class PublicHazardMotionController extends Controller
{
    public function index()
    {
        // Ambil data CCTV dari database yang memiliki longitude dan latitude
        $cctvData = CctvData::whereNotNull('longitude')
            ->whereNotNull('latitude')
            ->get();

        // Format data untuk JavaScript
        $cctvLocations = $cctvData->map(function ($cctv) {
            return [
                'id' => $cctv->no_cctv ?? 'CCTV-' . $cctv->id,
                'name' => $cctv->nama_cctv ?? 'CCTV ' . $cctv->id,
                'location' => [(float) $cctv->longitude, (float) $cctv->latitude],
                'status' => $cctv->kondisi ?? $cctv->status ?? 'Unknown',
                'description' => $cctv->lokasi_pemasangan ?? $cctv->coverage_detail_lokasi ?? '',
                'type' => $cctv->jenis ?? 'FIXED',
                'brand' => $this->extractBrandFromTipe($cctv->tipe_cctv),
                'model' => $cctv->tipe_cctv ?? '',
                'viewType' => $cctv->fungsi_cctv ?? '',
                'area' => $cctv->coverage_lokasi ?? '',
                'areaType' => $cctv->kategori_area_tercapture ?? '',
                'activity' => $cctv->kategori_aktivitas_tercapture ?? '',
                'controlRoom' => $cctv->control_room ?? '',
                'liveView' => $cctv->status ?? '',
                'link_akses' => $cctv->link_akses ?? '',
                'user_name' => $cctv->user_name ?? '',
                'password' => $cctv->password ?? '',
                'connected' => $cctv->connected ?? '',
                'mirrored' => $cctv->mirrored ?? '',
                'site' => $cctv->site ?? '',
                'perusahaan' => $cctv->perusahaan ?? '',
                'kategori' => $cctv->kategori ?? '',
                'radius_pengawasan' => $cctv->radius_pengawasan ?? '',
                'keterangan' => $cctv->keterangan ?? '',
                'no_cctv' => $cctv->no_cctv ?? '',
                'id_lokasi' => $cctv->id ?? '',
                'lokasi' => $cctv->coverage_lokasi ?? '',
                'id_detail_lokasi' => $cctv->id ?? '',
                'detail_lokasi' => $cctv->coverage_detail_lokasi ?? '',
                'pja' => $cctv->keterangan ?? '',
            ];
        })->toArray();

        // Mock data untuk demo (akan diganti dengan data real dari database)
        $hazardNotifications = [
            ['id' => 'CE-6163-CK', 'name' => 'Akbar Wahyu Pratama', 'distance' => '49mtr CE'],
            ['id' => 'CO-4175-CK', 'name' => 'DV-7003-CK', 'distance' => '25mtr Dewater'],
            ['id' => 'CD-2349-CK', 'name' => 'HT-Fadili-CX', 'distance' => '15mtr'],
            ['id' => 'XCD-1297-CK', 'name' => 'HT-Sehas Al Kholik-CX', 'distance' => '25mtr'],
        ];

        $equipmentManpower = [
            ['type' => 'Crane Truck', 'count' => 4],
            ['type' => 'Dozer', 'count' => 1],
            ['type' => 'Excavator', 'count' => 15],
            ['type' => 'Grader', 'count' => 1],
            ['type' => 'HD', 'count' => 8],
        ];

        $stats = [
            'manusia' => 17,
            'alat' => 31,
            'alert' => 5,
            'weather' => 'Berawan',
            'temperature' => '29.4°C',
        ];

        return view('HazardMotion.users.index', compact('cctvLocations', 'hazardNotifications', 'equipmentManpower', 'stats'));
    }

    /**
     * Extract brand from tipe_cctv field
     */
    private function extractBrandFromTipe($tipe)
    {
        if (!$tipe) {
            return '';
        }

        $tipeLower = strtolower($tipe);
        
        if (strpos($tipeLower, 'hikvision') !== false || strpos($tipeLower, 'hik') !== false) {
            return 'HIKVision';
        }
        if (strpos($tipeLower, 'ezviz') !== false) {
            return 'Ezviz';
        }
        if (strpos($tipeLower, 'dahua') !== false) {
            return 'Dahua';
        }
        
        return '';
    }
}

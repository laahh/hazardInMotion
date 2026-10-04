<?php

declare(strict_types=1);

use App\Http\Controllers\OhsScoreCard\BerecordController;
use App\Http\Controllers\OhsScoreCard\BlindspotGrController;
use App\Http\Controllers\OhsScoreCard\BlindspotRealTimeController;
use App\Http\Controllers\OhsScoreCard\BlindspotTbcController;
use App\Http\Controllers\OhsScoreCard\BlindspotTbcPicSubcontController;
use App\Http\Controllers\OhsScoreCard\FitToWorkAwalShiftController;
use App\Http\Controllers\OhsScoreCard\GoldenTimeEmergencyController;
use App\Http\Controllers\OhsScoreCard\GrSeatbeltController;
use App\Http\Controllers\OhsScoreCard\IncidentGapCctvDmsController;
use App\Http\Controllers\OhsScoreCard\KinerjaControlRoomDmsController;
use App\Http\Controllers\OhsScoreCard\LeadtimeAlertBedmsController;
use App\Http\Controllers\OhsScoreCard\OhsScoreCardDashboardController;
use App\Http\Controllers\OhsScoreCard\PelaksanaanEdukasiController;
use App\Http\Controllers\OhsScoreCard\PelanggaranOverspeedController;
use App\Http\Controllers\OhsScoreCard\PengawasanBerjarakController;
use App\Http\Controllers\OhsScoreCard\PenggunaanHpController;
use App\Http\Controllers\OhsScoreCard\PerulanganRekomendasiController;
use App\Http\Controllers\OhsScoreCard\RasioKelayakanKerjaController;
use App\Http\Controllers\OhsScoreCard\RatioTbcGrController;
use App\Http\Controllers\OhsScoreCard\RoadSummaryController;
use App\Http\Controllers\OhsScoreCard\SpeakUpFatigueController;
use App\Http\Controllers\OhsScoreCard\ValidasiTbcTabController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Modul OHS Score Card
|--------------------------------------------------------------------------
| Dashboard OHS Score Card. Datanya berasal dari bewell_db (sama dengan modul
| Evaluasi Olahraga & Aktivitas), jadi gate aksesnya ikut 'evaluasi-well.access'.
| Di-require di dalam grup middleware 'auth' pada routes/web.php.
*/

Route::middleware('evaluasi-well.access')
    ->prefix('ohs-score-card')
    ->name('ohs-score-card.')
    ->group(function (): void {
        Route::get('/', [OhsScoreCardDashboardController::class, 'index'])->name('index');

        // Parameter SGI — "Jalan sesuai standar" (app_mixer.road_summary)
        Route::get('/jalan-sesuai-standar', [RoadSummaryController::class, 'index'])
            ->name('jalan-sesuai-standar.index');
        Route::get('/jalan-sesuai-standar/data', [RoadSummaryController::class, 'data'])
            ->name('jalan-sesuai-standar.data');
        Route::get('/jalan-sesuai-standar/export', [RoadSummaryController::class, 'export'])
            ->name('jalan-sesuai-standar.export');
        Route::get('/jalan-sesuai-standar/overview', [RoadSummaryController::class, 'overview'])
            ->name('jalan-sesuai-standar.overview');

        // Parameter SOD "Ratio TBC & GR" — minecon & subcon
        Route::get('/ratio-tbc-gr', [RatioTbcGrController::class, 'index'])
            ->name('ratio-tbc-gr.index');
        // {dataset}: minecon (lead_ratio_pelapor_tbc) atau subcon
        // (lead_subcont_ratio_pelapor_tbc); nama lain ditolak di sini.
        Route::get('/ratio-tbc-gr/{dataset}/overview', [RatioTbcGrController::class, 'overview'])
            ->whereIn('dataset', ['minecon', 'subcon'])
            ->name('ratio-tbc-gr.overview');
        Route::get('/ratio-tbc-gr/{dataset}/data', [RatioTbcGrController::class, 'data'])
            ->whereIn('dataset', ['minecon', 'subcon'])
            ->name('ratio-tbc-gr.data');
        Route::get('/ratio-tbc-gr/{dataset}/export', [RatioTbcGrController::class, 'export'])
            ->whereIn('dataset', ['minecon', 'subcon'])
            ->name('ratio-tbc-gr.export');

        // Parameter SOD "Incident dengan Gap Coverage CCTV & Gap pada DMS"
        Route::get('/incident-gap-cctv-dms', [IncidentGapCctvDmsController::class, 'index'])
            ->name('incident-gap-cctv-dms.index');
        Route::get('/incident-gap-cctv-dms/overview', [IncidentGapCctvDmsController::class, 'overview'])
            ->name('incident-gap-cctv-dms.overview');
        Route::get('/incident-gap-cctv-dms/data', [IncidentGapCctvDmsController::class, 'data'])
            ->name('incident-gap-cctv-dms.data');
        Route::get('/incident-gap-cctv-dms/export', [IncidentGapCctvDmsController::class, 'export'])
            ->name('incident-gap-cctv-dms.export');

        // Parameter SOD "GR Seatbelt"
        Route::get('/gr-seatbelt', [GrSeatbeltController::class, 'index'])
            ->name('gr-seatbelt.index');
        Route::get('/gr-seatbelt/overview', [GrSeatbeltController::class, 'overview'])
            ->name('gr-seatbelt.overview');
        Route::get('/gr-seatbelt/data', [GrSeatbeltController::class, 'data'])
            ->name('gr-seatbelt.data');
        Route::get('/gr-seatbelt/export', [GrSeatbeltController::class, 'export'])
            ->name('gr-seatbelt.export');

        // Parameter SOD "Tidak ada temuan penggunaan HP"
        Route::get('/penggunaan-hp', [PenggunaanHpController::class, 'index'])
            ->name('penggunaan-hp.index');
        Route::get('/penggunaan-hp/overview', [PenggunaanHpController::class, 'overview'])
            ->name('penggunaan-hp.overview');
        Route::get('/penggunaan-hp/data', [PenggunaanHpController::class, 'data'])
            ->name('penggunaan-hp.data');
        Route::get('/penggunaan-hp/export', [PenggunaanHpController::class, 'export'])
            ->name('penggunaan-hp.export');

        // Parameter SOD "% Pengawasan Berjarak"
        Route::get('/pengawasan-berjarak', [PengawasanBerjarakController::class, 'index'])
            ->name('pengawasan-berjarak.index');
        Route::get('/pengawasan-berjarak/overview', [PengawasanBerjarakController::class, 'overview'])
            ->name('pengawasan-berjarak.overview');
        Route::get('/pengawasan-berjarak/data', [PengawasanBerjarakController::class, 'data'])
            ->name('pengawasan-berjarak.data');
        Route::get('/pengawasan-berjarak/export', [PengawasanBerjarakController::class, 'export'])
            ->name('pengawasan-berjarak.export');

        // Parameter SOD "% Blindspot temuan Real Time"
        Route::get('/blindspot-real-time', [BlindspotRealTimeController::class, 'index'])
            ->name('blindspot-real-time.index');
        Route::get('/blindspot-real-time/overview', [BlindspotRealTimeController::class, 'overview'])
            ->name('blindspot-real-time.overview');
        Route::get('/blindspot-real-time/data', [BlindspotRealTimeController::class, 'data'])
            ->name('blindspot-real-time.data');
        Route::get('/blindspot-real-time/export', [BlindspotRealTimeController::class, 'export'])
            ->name('blindspot-real-time.export');

        // Parameter SOD "% Blindspot TBC dengan PIC Subcontractor"
        Route::get('/blindspot-tbc-pic-subcont', [BlindspotTbcPicSubcontController::class, 'index'])
            ->name('blindspot-tbc-pic-subcont.index');
        Route::get('/blindspot-tbc-pic-subcont/overview', [BlindspotTbcPicSubcontController::class, 'overview'])
            ->name('blindspot-tbc-pic-subcont.overview');
        Route::get('/blindspot-tbc-pic-subcont/data', [BlindspotTbcPicSubcontController::class, 'data'])
            ->name('blindspot-tbc-pic-subcont.data');
        Route::get('/blindspot-tbc-pic-subcont/export', [BlindspotTbcPicSubcontController::class, 'export'])
            ->name('blindspot-tbc-pic-subcont.export');

        // Parameter SOD "Blindspot GR"
        Route::get('/blindspot-gr', [BlindspotGrController::class, 'index'])
            ->name('blindspot-gr.index');
        Route::get('/blindspot-gr/overview', [BlindspotGrController::class, 'overview'])
            ->name('blindspot-gr.overview');
        Route::get('/blindspot-gr/data', [BlindspotGrController::class, 'data'])
            ->name('blindspot-gr.data');
        Route::get('/blindspot-gr/export', [BlindspotGrController::class, 'export'])
            ->name('blindspot-gr.export');

        // Parameter SOD "Blindspot TBC" — minecon & subcon
        Route::get('/blindspot-tbc', [BlindspotTbcController::class, 'index'])
            ->name('blindspot-tbc.index');
        Route::get('/blindspot-tbc/{dataset}/overview', [BlindspotTbcController::class, 'overview'])
            ->whereIn('dataset', ['minecon', 'subcon'])
            ->name('blindspot-tbc.overview');
        Route::get('/blindspot-tbc/{dataset}/data', [BlindspotTbcController::class, 'data'])
            ->whereIn('dataset', ['minecon', 'subcon'])
            ->name('blindspot-tbc.data');
        Route::get('/blindspot-tbc/{dataset}/export', [BlindspotTbcController::class, 'export'])
            ->whereIn('dataset', ['minecon', 'subcon'])
            ->name('blindspot-tbc.export');

        // Parameter SIRC "Perulangan rekomendasi hasil investigasi"
        Route::get('/perulangan-rekomendasi', [PerulanganRekomendasiController::class, 'index'])
            ->name('perulangan-rekomendasi.index');
        Route::get('/perulangan-rekomendasi/overview', [PerulanganRekomendasiController::class, 'overview'])
            ->name('perulangan-rekomendasi.overview');
        Route::get('/perulangan-rekomendasi/data', [PerulanganRekomendasiController::class, 'data'])
            ->name('perulangan-rekomendasi.data');
        Route::get('/perulangan-rekomendasi/export', [PerulanganRekomendasiController::class, 'export'])
            ->name('perulangan-rekomendasi.export');

        // Parameter wellbeing "Pemeriksaan Fit to Work awal shift pekerja"
        Route::get('/fit-to-work-awal-shift', [FitToWorkAwalShiftController::class, 'index'])
            ->name('fit-to-work-awal-shift.index');
        Route::get('/fit-to-work-awal-shift/overview', [FitToWorkAwalShiftController::class, 'overview'])
            ->name('fit-to-work-awal-shift.overview');
        Route::get('/fit-to-work-awal-shift/data', [FitToWorkAwalShiftController::class, 'data'])
            ->name('fit-to-work-awal-shift.data');
        Route::get('/fit-to-work-awal-shift/export', [FitToWorkAwalShiftController::class, 'export'])
            ->name('fit-to-work-awal-shift.export');

        // Parameter wellbeing "Rasio Kelayakan Kerja"
        Route::get('/rasio-kelayakan-kerja', [RasioKelayakanKerjaController::class, 'index'])
            ->name('rasio-kelayakan-kerja.index');
        // {dataset}: minecon (lead_ratio_kelayakan_kerja) atau subcon
        // (lead_subcont_ratio_kelayakan_kerja); nama lain ditolak di sini.
        Route::get('/rasio-kelayakan-kerja/{dataset}/overview', [RasioKelayakanKerjaController::class, 'overview'])
            ->whereIn('dataset', ['minecon', 'subcon'])
            ->name('rasio-kelayakan-kerja.overview');
        Route::get('/rasio-kelayakan-kerja/{dataset}/data', [RasioKelayakanKerjaController::class, 'data'])
            ->whereIn('dataset', ['minecon', 'subcon'])
            ->name('rasio-kelayakan-kerja.data');
        Route::get('/rasio-kelayakan-kerja/{dataset}/export', [RasioKelayakanKerjaController::class, 'export'])
            ->whereIn('dataset', ['minecon', 'subcon'])
            ->name('rasio-kelayakan-kerja.export');

        // Parameter SOD "Leadtime Alert DMS masuk ke Server"
        Route::get('/leadtime-alert-bedms', [LeadtimeAlertBedmsController::class, 'index'])
            ->name('leadtime-alert-bedms.index');
        Route::get('/leadtime-alert-bedms/overview', [LeadtimeAlertBedmsController::class, 'overview'])
            ->name('leadtime-alert-bedms.overview');
        Route::get('/leadtime-alert-bedms/data', [LeadtimeAlertBedmsController::class, 'data'])
            ->name('leadtime-alert-bedms.data');
        Route::get('/leadtime-alert-bedms/export', [LeadtimeAlertBedmsController::class, 'export'])
            ->name('leadtime-alert-bedms.export');

        // Parameter SOD "Kinerja Pengawasan Control Room DMS"
        Route::get('/kinerja-control-room-dms', [KinerjaControlRoomDmsController::class, 'index'])
            ->name('kinerja-control-room-dms.index');
        Route::get('/kinerja-control-room-dms/overview', [KinerjaControlRoomDmsController::class, 'overview'])
            ->name('kinerja-control-room-dms.overview');
        Route::get('/kinerja-control-room-dms/data', [KinerjaControlRoomDmsController::class, 'data'])
            ->name('kinerja-control-room-dms.data');
        Route::get('/kinerja-control-room-dms/export', [KinerjaControlRoomDmsController::class, 'export'])
            ->name('kinerja-control-room-dms.export');

        // Parameter SIRM "Deviasi Rekayasa Engineering Overspeed"
        Route::get('/pelanggaran-overspeed', [PelanggaranOverspeedController::class, 'index'])
            ->name('pelanggaran-overspeed.index');
        Route::get('/pelanggaran-overspeed/overview', [PelanggaranOverspeedController::class, 'overview'])
            ->name('pelanggaran-overspeed.overview');
        Route::get('/pelanggaran-overspeed/data', [PelanggaranOverspeedController::class, 'data'])
            ->name('pelanggaran-overspeed.data');
        Route::get('/pelanggaran-overspeed/export', [PelanggaranOverspeedController::class, 'export'])
            ->name('pelanggaran-overspeed.export');

        // Parameter Emergency Response "Tidak ada pelaporan melewati batas golden time"
        Route::get('/golden-time-emergency', [GoldenTimeEmergencyController::class, 'index'])
            ->name('golden-time-emergency.index');
        Route::get('/golden-time-emergency/overview', [GoldenTimeEmergencyController::class, 'overview'])
            ->name('golden-time-emergency.overview');
        Route::get('/golden-time-emergency/data', [GoldenTimeEmergencyController::class, 'data'])
            ->name('golden-time-emergency.data');
        Route::get('/golden-time-emergency/export', [GoldenTimeEmergencyController::class, 'export'])
            ->name('golden-time-emergency.export');

        // Parameter HSECT — "Peer Pressure" (hse_automation: bcsid.mv_berecord)
        Route::get('/peer-pressure', [BerecordController::class, 'index'])
            ->name('peer-pressure.index');
        Route::get('/peer-pressure/data', [BerecordController::class, 'data'])
            ->name('peer-pressure.data');
        Route::get('/peer-pressure/export', [BerecordController::class, 'export'])
            ->name('peer-pressure.export');

        // Tab Speak Up (app_mixer.speak_up_fatigue)
        Route::get('/peer-pressure/speak-up/data', [SpeakUpFatigueController::class, 'data'])
            ->name('peer-pressure.speak-up.data');
        Route::get('/peer-pressure/speak-up/export', [SpeakUpFatigueController::class, 'export'])
            ->name('peer-pressure.speak-up.export');

        // Tab Blindspot TBC (app_mixer.validasi_tbc)
        Route::get('/peer-pressure/blindspot-tbc/data', [ValidasiTbcTabController::class, 'data'])
            ->name('peer-pressure.blindspot-tbc.data');
        Route::get('/peer-pressure/blindspot-tbc/export', [ValidasiTbcTabController::class, 'export'])
            ->name('peer-pressure.blindspot-tbc.export');

        // Tab Pelaksanaan Peer Pressure (kejadian + peserta edukasi)
        Route::get('/peer-pressure/pelaksanaan/data', [PelaksanaanEdukasiController::class, 'data'])
            ->name('peer-pressure.pelaksanaan.data');
        Route::get('/peer-pressure/pelaksanaan/export', [PelaksanaanEdukasiController::class, 'export'])
            ->name('peer-pressure.pelaksanaan.export');
    });

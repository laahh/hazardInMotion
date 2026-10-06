<?php

declare(strict_types=1);

use App\Http\Controllers\OhsScoreCard\BerecordController;
use App\Http\Controllers\OhsScoreCard\BlindspotGrController;
use App\Http\Controllers\OhsScoreCard\BlindspotRealTimeController;
use App\Http\Controllers\OhsScoreCard\BlindspotTbcController;
use App\Http\Controllers\OhsScoreCard\BlindspotTbcPicSubcontController;
use App\Http\Controllers\OhsScoreCard\CoverageAreaDailyController;
use App\Http\Controllers\OhsScoreCard\CoverageAreaKritisController;
use App\Http\Controllers\OhsScoreCard\ComplianceIkkController;
use App\Http\Controllers\OhsScoreCard\CoverageDailyAreaKritisSafetyController;
use App\Http\Controllers\OhsScoreCard\FitToWorkAwalShiftController;
use App\Http\Controllers\OhsScoreCard\GoldenTimeEmergencyController;
use App\Http\Controllers\OhsScoreCard\GrSeatbeltController;
use App\Http\Controllers\OhsScoreCard\IncidentDeepDiveController;
use App\Http\Controllers\OhsScoreCard\IncidentGapCctvDmsController;
use App\Http\Controllers\OhsScoreCard\IncidentLeadingIndicatorController;
use App\Http\Controllers\OhsScoreCard\IncidentManagementDashboardController;
use App\Http\Controllers\OhsScoreCard\IncidentMasterDataController;
use App\Http\Controllers\OhsScoreCard\KesiapanAlatEmergencyController;
use App\Http\Controllers\OhsScoreCard\KinerjaControlRoomDmsController;
use App\Http\Controllers\OhsScoreCard\LaporanPerizinanUsahaJasaController;
use App\Http\Controllers\OhsScoreCard\SpipCommissioningController;
use App\Http\Controllers\OhsScoreCard\LeadtimeAlertBedmsController;
use App\Http\Controllers\OhsScoreCard\OhsScoreCardDashboardController;
use App\Http\Controllers\OhsScoreCard\PelaksanaanEdukasiController;
use App\Http\Controllers\OhsScoreCard\PelanggaranOverspeedController;
use App\Http\Controllers\OhsScoreCard\PemenuhanRegulasiController;
use App\Http\Controllers\OhsScoreCard\PengawasanBerjarakController;
use App\Http\Controllers\OhsScoreCard\PenggunaanHpController;
use App\Http\Controllers\OhsScoreCard\PerulanganRekomendasiController;
use App\Http\Controllers\OhsScoreCard\RasioKelayakanKerjaController;
use App\Http\Controllers\OhsScoreCard\RatioTbcGrController;
use App\Http\Controllers\OhsScoreCard\RoadSummaryController;
use App\Http\Controllers\OhsScoreCard\PenuntasanRekayasaController;
use App\Http\Controllers\OhsScoreCard\SertifikasiPengawasTeknisController;
use App\Http\Controllers\OhsScoreCard\SobrietyTestController;
use App\Http\Controllers\OhsScoreCard\UtilisasiBesigmaController;
use App\Http\Controllers\OhsScoreCard\SertifikasiTenagaTeknisController;
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
        Route::get('/score-card-parameter', [OhsScoreCardDashboardController::class, 'scoreCardParameter'])
            ->name('score-card-parameter');

        // Parameter SGI — "Jalan sesuai standar" (app_mixer.road_summary)
        Route::get('/jalan-sesuai-standar', [RoadSummaryController::class, 'index'])
            ->name('jalan-sesuai-standar.index');
        Route::get('/jalan-sesuai-standar/detail-bulan', [RoadSummaryController::class, 'detailBulan'])
            ->name('jalan-sesuai-standar.detail-bulan');
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
        Route::get('/ratio-tbc-gr/{dataset}/detail-bulan', [RatioTbcGrController::class, 'detailBulan'])
            ->whereIn('dataset', ['minecon', 'subcon'])
            ->name('ratio-tbc-gr.detail-bulan');
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
        Route::get('/incident-gap-cctv-dms/detail-bulan', [IncidentGapCctvDmsController::class, 'detailBulan'])
            ->name('incident-gap-cctv-dms.detail-bulan');
        Route::get('/incident-gap-cctv-dms/data', [IncidentGapCctvDmsController::class, 'data'])
            ->name('incident-gap-cctv-dms.data');
        Route::get('/incident-gap-cctv-dms/export', [IncidentGapCctvDmsController::class, 'export'])
            ->name('incident-gap-cctv-dms.export');

        // Parameter SOD "GR Seatbelt"
        Route::get('/gr-seatbelt', [GrSeatbeltController::class, 'index'])
            ->name('gr-seatbelt.index');
        Route::get('/gr-seatbelt/overview', [GrSeatbeltController::class, 'overview'])
            ->name('gr-seatbelt.overview');
        Route::get('/gr-seatbelt/detail-bulan', [GrSeatbeltController::class, 'detailBulan'])
            ->name('gr-seatbelt.detail-bulan');
        Route::get('/gr-seatbelt/data', [GrSeatbeltController::class, 'data'])
            ->name('gr-seatbelt.data');
        Route::get('/gr-seatbelt/export', [GrSeatbeltController::class, 'export'])
            ->name('gr-seatbelt.export');

        // Parameter SOD "Tidak ada temuan penggunaan HP"
        Route::get('/penggunaan-hp', [PenggunaanHpController::class, 'index'])
            ->name('penggunaan-hp.index');
        Route::get('/penggunaan-hp/overview', [PenggunaanHpController::class, 'overview'])
            ->name('penggunaan-hp.overview');
        Route::get('/penggunaan-hp/detail-bulan', [PenggunaanHpController::class, 'detailBulan'])
            ->name('penggunaan-hp.detail-bulan');
        Route::get('/penggunaan-hp/data', [PenggunaanHpController::class, 'data'])
            ->name('penggunaan-hp.data');
        Route::get('/penggunaan-hp/export', [PenggunaanHpController::class, 'export'])
            ->name('penggunaan-hp.export');

        // Parameter SOD "% Pengawasan Berjarak"
        Route::get('/pengawasan-berjarak', [PengawasanBerjarakController::class, 'index'])
            ->name('pengawasan-berjarak.index');
        Route::get('/pengawasan-berjarak/overview', [PengawasanBerjarakController::class, 'overview'])
            ->name('pengawasan-berjarak.overview');
        Route::get('/pengawasan-berjarak/detail-bulan', [PengawasanBerjarakController::class, 'detailBulan'])
            ->name('pengawasan-berjarak.detail-bulan');
        Route::get('/pengawasan-berjarak/data', [PengawasanBerjarakController::class, 'data'])
            ->name('pengawasan-berjarak.data');
        Route::get('/pengawasan-berjarak/export', [PengawasanBerjarakController::class, 'export'])
            ->name('pengawasan-berjarak.export');

        // Parameter SOD "% Blindspot temuan Real Time"
        Route::get('/blindspot-real-time', [BlindspotRealTimeController::class, 'index'])
            ->name('blindspot-real-time.index');
        Route::get('/blindspot-real-time/overview', [BlindspotRealTimeController::class, 'overview'])
            ->name('blindspot-real-time.overview');
        Route::get('/blindspot-real-time/detail-bulan', [BlindspotRealTimeController::class, 'detailBulan'])
            ->name('blindspot-real-time.detail-bulan');
        Route::get('/blindspot-real-time/data', [BlindspotRealTimeController::class, 'data'])
            ->name('blindspot-real-time.data');
        Route::get('/blindspot-real-time/export', [BlindspotRealTimeController::class, 'export'])
            ->name('blindspot-real-time.export');

        // Parameter SOD "% Blindspot TBC dengan PIC Subcontractor"
        Route::get('/blindspot-tbc-pic-subcont', [BlindspotTbcPicSubcontController::class, 'index'])
            ->name('blindspot-tbc-pic-subcont.index');
        Route::get('/blindspot-tbc-pic-subcont/overview', [BlindspotTbcPicSubcontController::class, 'overview'])
            ->name('blindspot-tbc-pic-subcont.overview');
        Route::get('/blindspot-tbc-pic-subcont/detail-bulan', [BlindspotTbcPicSubcontController::class, 'detailBulan'])
            ->name('blindspot-tbc-pic-subcont.detail-bulan');
        Route::get('/blindspot-tbc-pic-subcont/data', [BlindspotTbcPicSubcontController::class, 'data'])
            ->name('blindspot-tbc-pic-subcont.data');
        Route::get('/blindspot-tbc-pic-subcont/export', [BlindspotTbcPicSubcontController::class, 'export'])
            ->name('blindspot-tbc-pic-subcont.export');

        // Parameter SOD "Blindspot GR"
        Route::get('/blindspot-gr', [BlindspotGrController::class, 'index'])
            ->name('blindspot-gr.index');
        Route::get('/blindspot-gr/overview', [BlindspotGrController::class, 'overview'])
            ->name('blindspot-gr.overview');
        Route::get('/blindspot-gr/detail-bulan', [BlindspotGrController::class, 'detailBulan'])
            ->name('blindspot-gr.detail-bulan');
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
        Route::get('/blindspot-tbc/{dataset}/detail-bulan', [BlindspotTbcController::class, 'detailBulan'])
            ->whereIn('dataset', ['minecon', 'subcon'])
            ->name('blindspot-tbc.detail-bulan');
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
        Route::get('/perulangan-rekomendasi/detail-bulan', [PerulanganRekomendasiController::class, 'detailBulan'])
            ->name('perulangan-rekomendasi.detail-bulan');
        Route::get('/perulangan-rekomendasi/data', [PerulanganRekomendasiController::class, 'data'])
            ->name('perulangan-rekomendasi.data');
        Route::get('/perulangan-rekomendasi/export', [PerulanganRekomendasiController::class, 'export'])
            ->name('perulangan-rekomendasi.export');

        // Parameter wellbeing "Pemeriksaan Fit to Work awal shift pekerja"
        Route::get('/fit-to-work-awal-shift', [FitToWorkAwalShiftController::class, 'index'])
            ->name('fit-to-work-awal-shift.index');
        Route::get('/fit-to-work-awal-shift/overview', [FitToWorkAwalShiftController::class, 'overview'])
            ->name('fit-to-work-awal-shift.overview');
        Route::get('/fit-to-work-awal-shift/detail-bulan', [FitToWorkAwalShiftController::class, 'detailBulan'])
            ->name('fit-to-work-awal-shift.detail-bulan');
        Route::get('/fit-to-work-awal-shift/data', [FitToWorkAwalShiftController::class, 'data'])
            ->name('fit-to-work-awal-shift.data');
        Route::get('/fit-to-work-awal-shift/export', [FitToWorkAwalShiftController::class, 'export'])
            ->name('fit-to-work-awal-shift.export');

        // Parameter wellbeing "Speak up fatigue"
        Route::get('/speak-up-fatigue', [SpeakUpFatigueController::class, 'index'])
            ->name('speak-up-fatigue.index');
        Route::get('/speak-up-fatigue/overview', [SpeakUpFatigueController::class, 'overview'])
            ->name('speak-up-fatigue.overview');
        Route::get('/speak-up-fatigue/detail-bulan', [SpeakUpFatigueController::class, 'detailBulan'])
            ->name('speak-up-fatigue.detail-bulan');
        Route::get('/speak-up-fatigue/data', [SpeakUpFatigueController::class, 'data'])
            ->name('speak-up-fatigue.data');
        Route::get('/speak-up-fatigue/export', [SpeakUpFatigueController::class, 'export'])
            ->name('speak-up-fatigue.export');

        // Parameter wellbeing "Rasio Kelayakan Kerja"
        Route::get('/rasio-kelayakan-kerja', [RasioKelayakanKerjaController::class, 'index'])
            ->name('rasio-kelayakan-kerja.index');
        // {dataset}: minecon (lead_ratio_kelayakan_kerja) atau subcon
        // (lead_subcont_ratio_kelayakan_kerja); nama lain ditolak di sini.
        Route::get('/rasio-kelayakan-kerja/{dataset}/overview', [RasioKelayakanKerjaController::class, 'overview'])
            ->whereIn('dataset', ['minecon', 'subcon'])
            ->name('rasio-kelayakan-kerja.overview');
        Route::get('/rasio-kelayakan-kerja/{dataset}/detail-bulan', [RasioKelayakanKerjaController::class, 'detailBulan'])
            ->name('rasio-kelayakan-kerja.detail-bulan');
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
        Route::get('/leadtime-alert-bedms/detail-bulan', [LeadtimeAlertBedmsController::class, 'detailBulan'])
            ->name('leadtime-alert-bedms.detail-bulan');
        Route::get('/leadtime-alert-bedms/data', [LeadtimeAlertBedmsController::class, 'data'])
            ->name('leadtime-alert-bedms.data');
        Route::get('/leadtime-alert-bedms/export', [LeadtimeAlertBedmsController::class, 'export'])
            ->name('leadtime-alert-bedms.export');

        // Parameter SOD "Kinerja Pengawasan Control Room DMS"
        Route::get('/kinerja-control-room-dms', [KinerjaControlRoomDmsController::class, 'index'])
            ->name('kinerja-control-room-dms.index');
        Route::get('/kinerja-control-room-dms/overview', [KinerjaControlRoomDmsController::class, 'overview'])
            ->name('kinerja-control-room-dms.overview');
        Route::get('/kinerja-control-room-dms/detail-bulan', [KinerjaControlRoomDmsController::class, 'detailBulan'])
            ->name('kinerja-control-room-dms.detail-bulan');
        Route::get('/kinerja-control-room-dms/data', [KinerjaControlRoomDmsController::class, 'data'])
            ->name('kinerja-control-room-dms.data');
        Route::get('/kinerja-control-room-dms/export', [KinerjaControlRoomDmsController::class, 'export'])
            ->name('kinerja-control-room-dms.export');

        // Parameter OC "% SPIP yang dilakukan Commissioning"
        Route::get('/spip-commissioning', [SpipCommissioningController::class, 'index'])
            ->name('spip-commissioning.index');
        Route::get('/spip-commissioning/overview', [SpipCommissioningController::class, 'overview'])
            ->name('spip-commissioning.overview');
        Route::get('/spip-commissioning/detail-bulan', [SpipCommissioningController::class, 'detailBulan'])
            ->name('spip-commissioning.detail-bulan');
        Route::get('/spip-commissioning/data', [SpipCommissioningController::class, 'data'])
            ->name('spip-commissioning.data');
        Route::get('/spip-commissioning/export', [SpipCommissioningController::class, 'export'])
            ->name('spip-commissioning.export');

        // Incident Management — dashboard korelasi insiden & IPLS
        // (OBDS: bcbeats.mv_investigasi, bukan app_mixer)
        Route::get('/incident-management/dashboard', [IncidentManagementDashboardController::class, 'index'])
            ->name('incident-management.dashboard.index');
        Route::get('/incident-management/dashboard/data', [IncidentManagementDashboardController::class, 'data'])
            ->name('incident-management.dashboard.data');

        // Tab Deep Dive: satu insiden ditelusuri lintas tujuh materialized view
        Route::get('/incident-management/deep-dive/daftar', [IncidentDeepDiveController::class, 'daftar'])
            ->name('incident-management.deep-dive.daftar');
        Route::get('/incident-management/deep-dive/{insiden}', [IncidentDeepDiveController::class, 'detail'])
            ->whereNumber('insiden')
            ->name('incident-management.deep-dive.detail');

        // Tab Leading Indicator: agregasi mingguan per site
        Route::get('/incident-management/leading-indicator', [IncidentLeadingIndicatorController::class, 'data'])
            ->name('incident-management.leading-indicator');

        // Master Data: baris mentah mv_investigasi, tanpa penyaringan
        Route::get('/incident-management/master-data', [IncidentMasterDataController::class, 'index'])
            ->name('incident-management.master-data.index');
        Route::get('/incident-management/master-data/data', [IncidentMasterDataController::class, 'data'])
            ->name('incident-management.master-data.data');
        Route::get('/incident-management/master-data/export', [IncidentMasterDataController::class, 'export'])
            ->name('incident-management.master-data.export');

        // Parameter SOD "Coverage Daily"
        Route::get('/coverage-area-daily', [CoverageAreaDailyController::class, 'index'])
            ->name('coverage-area-daily.index');
        Route::get('/coverage-area-daily/overview', [CoverageAreaDailyController::class, 'overview'])
            ->name('coverage-area-daily.overview');
        Route::get('/coverage-area-daily/detail-bulan', [CoverageAreaDailyController::class, 'detailBulan'])
            ->name('coverage-area-daily.detail-bulan');
        Route::get('/coverage-area-daily/data', [CoverageAreaDailyController::class, 'data'])
            ->name('coverage-area-daily.data');
        Route::get('/coverage-area-daily/export', [CoverageAreaDailyController::class, 'export'])
            ->name('coverage-area-daily.export');

        // Parameter SOD "Coverage Area Kritis Pengawas Suptend up"
        Route::get('/coverage-area-kritis', [CoverageAreaKritisController::class, 'index'])
            ->name('coverage-area-kritis.index');
        Route::get('/coverage-area-kritis/overview', [CoverageAreaKritisController::class, 'overview'])
            ->name('coverage-area-kritis.overview');
        Route::get('/coverage-area-kritis/detail-bulan', [CoverageAreaKritisController::class, 'detailBulan'])
            ->name('coverage-area-kritis.detail-bulan');
        Route::get('/coverage-area-kritis/data', [CoverageAreaKritisController::class, 'data'])
            ->name('coverage-area-kritis.data');
        Route::get('/coverage-area-kritis/export', [CoverageAreaKritisController::class, 'export'])
            ->name('coverage-area-kritis.export');

        // Parameter SOD "Coverage Daily Area Kritis Pengawas Safety"
        Route::get('/coverage-daily-area-kritis-safety', [CoverageDailyAreaKritisSafetyController::class, 'index'])
            ->name('coverage-daily-area-kritis-safety.index');
        Route::get('/coverage-daily-area-kritis-safety/overview', [CoverageDailyAreaKritisSafetyController::class, 'overview'])
            ->name('coverage-daily-area-kritis-safety.overview');
        Route::get('/coverage-daily-area-kritis-safety/detail-bulan', [CoverageDailyAreaKritisSafetyController::class, 'detailBulan'])
            ->name('coverage-daily-area-kritis-safety.detail-bulan');
        Route::get('/coverage-daily-area-kritis-safety/data', [CoverageDailyAreaKritisSafetyController::class, 'data'])
            ->name('coverage-daily-area-kritis-safety.data');
        Route::get('/coverage-daily-area-kritis-safety/export', [CoverageDailyAreaKritisSafetyController::class, 'export'])
            ->name('coverage-daily-area-kritis-safety.export');

        // Parameter HSECT — "Pemenuhan Sertifikasi Pengawas Teknis"
        Route::get('/sertifikasi-pengawas-teknis', [SertifikasiPengawasTeknisController::class, 'index'])
            ->name('sertifikasi-pengawas-teknis.index');
        Route::get('/sertifikasi-pengawas-teknis/overview', [SertifikasiPengawasTeknisController::class, 'overview'])
            ->name('sertifikasi-pengawas-teknis.overview');
        Route::get('/sertifikasi-pengawas-teknis/detail-sel', [SertifikasiPengawasTeknisController::class, 'detailSel'])
            ->name('sertifikasi-pengawas-teknis.detail-sel');
        Route::get('/sertifikasi-pengawas-teknis/data', [SertifikasiPengawasTeknisController::class, 'data'])
            ->name('sertifikasi-pengawas-teknis.data');
        Route::get('/sertifikasi-pengawas-teknis/export', [SertifikasiPengawasTeknisController::class, 'export'])
            ->name('sertifikasi-pengawas-teknis.export');

        // Parameter HSECT — "Pemenuhan Sertifikasi Tenaga Teknis"
        Route::get('/sertifikasi-tenaga-teknis', [SertifikasiTenagaTeknisController::class, 'index'])
            ->name('sertifikasi-tenaga-teknis.index');
        Route::get('/sertifikasi-tenaga-teknis/overview', [SertifikasiTenagaTeknisController::class, 'overview'])
            ->name('sertifikasi-tenaga-teknis.overview');
        Route::get('/sertifikasi-tenaga-teknis/detail-sel', [SertifikasiTenagaTeknisController::class, 'detailSel'])
            ->name('sertifikasi-tenaga-teknis.detail-sel');
        Route::get('/sertifikasi-tenaga-teknis/data', [SertifikasiTenagaTeknisController::class, 'data'])
            ->name('sertifikasi-tenaga-teknis.data');
        Route::get('/sertifikasi-tenaga-teknis/export', [SertifikasiTenagaTeknisController::class, 'export'])
            ->name('sertifikasi-tenaga-teknis.export');

        // Parameter "Utilisasi BeSigma"
        Route::get('/utilisasi-besigma', [UtilisasiBesigmaController::class, 'index'])
            ->name('utilisasi-besigma.index');
        Route::get('/utilisasi-besigma/overview', [UtilisasiBesigmaController::class, 'overview'])
            ->name('utilisasi-besigma.overview');
        Route::get('/utilisasi-besigma/detail-bulan', [UtilisasiBesigmaController::class, 'detailBulan'])
            ->name('utilisasi-besigma.detail-bulan');
        Route::get('/utilisasi-besigma/data', [UtilisasiBesigmaController::class, 'data'])
            ->name('utilisasi-besigma.data');
        Route::get('/utilisasi-besigma/export', [UtilisasiBesigmaController::class, 'export'])
            ->name('utilisasi-besigma.export');

        // Parameter "Penuntasan pengendalian rekayasa"
        Route::get('/penuntasan-pengendalian-rekayasa', [PenuntasanRekayasaController::class, 'index'])
            ->name('penuntasan-pengendalian-rekayasa.index');
        Route::get('/penuntasan-pengendalian-rekayasa/overview', [PenuntasanRekayasaController::class, 'overview'])
            ->name('penuntasan-pengendalian-rekayasa.overview');
        Route::get('/penuntasan-pengendalian-rekayasa/detail-bulan', [PenuntasanRekayasaController::class, 'detailBulan'])
            ->name('penuntasan-pengendalian-rekayasa.detail-bulan');
        Route::get('/penuntasan-pengendalian-rekayasa/data', [PenuntasanRekayasaController::class, 'data'])
            ->name('penuntasan-pengendalian-rekayasa.data');
        Route::get('/penuntasan-pengendalian-rekayasa/export', [PenuntasanRekayasaController::class, 'export'])
            ->name('penuntasan-pengendalian-rekayasa.export');

        // Parameter "Pelaksanaan Sobriety Test"
        Route::get('/sobriety-test', [SobrietyTestController::class, 'index'])
            ->name('sobriety-test.index');
        Route::get('/sobriety-test/overview', [SobrietyTestController::class, 'overview'])
            ->name('sobriety-test.overview');
        Route::get('/sobriety-test/detail-bulan', [SobrietyTestController::class, 'detailBulan'])
            ->name('sobriety-test.detail-bulan');
        Route::get('/sobriety-test/data', [SobrietyTestController::class, 'data'])
            ->name('sobriety-test.data');
        Route::get('/sobriety-test/export', [SobrietyTestController::class, 'export'])
            ->name('sobriety-test.export');



        // Parameter OC "Kesesuaian Implementasi IKK"
        Route::get('/compliance-ikk', [ComplianceIkkController::class, 'index'])
            ->name('compliance-ikk.index');
        Route::get('/compliance-ikk/overview', [ComplianceIkkController::class, 'overview'])
            ->name('compliance-ikk.overview');
        Route::get('/compliance-ikk/detail-bulan', [ComplianceIkkController::class, 'detailBulan'])
            ->name('compliance-ikk.detail-bulan');
        Route::get('/compliance-ikk/data', [ComplianceIkkController::class, 'data'])
            ->name('compliance-ikk.data');
        Route::get('/compliance-ikk/export', [ComplianceIkkController::class, 'export'])
            ->name('compliance-ikk.export');

        // Parameter SIRM "Deviasi Rekayasa Engineering Overspeed"
        Route::get('/pelanggaran-overspeed', [PelanggaranOverspeedController::class, 'index'])
            ->name('pelanggaran-overspeed.index');
        Route::get('/pelanggaran-overspeed/overview', [PelanggaranOverspeedController::class, 'overview'])
            ->name('pelanggaran-overspeed.overview');
        Route::get('/pelanggaran-overspeed/detail-bulan', [PelanggaranOverspeedController::class, 'detailBulan'])
            ->name('pelanggaran-overspeed.detail-bulan');
        Route::get('/pelanggaran-overspeed/data', [PelanggaranOverspeedController::class, 'data'])
            ->name('pelanggaran-overspeed.data');
        Route::get('/pelanggaran-overspeed/export', [PelanggaranOverspeedController::class, 'export'])
            ->name('pelanggaran-overspeed.export');

        // Parameter Emergency Response "Tidak ada pelaporan melewati batas golden time"
        Route::get('/golden-time-emergency', [GoldenTimeEmergencyController::class, 'index'])
            ->name('golden-time-emergency.index');
        Route::get('/golden-time-emergency/overview', [GoldenTimeEmergencyController::class, 'overview'])
            ->name('golden-time-emergency.overview');
        Route::get('/golden-time-emergency/detail-bulan', [GoldenTimeEmergencyController::class, 'detailBulan'])
            ->name('golden-time-emergency.detail-bulan');
        Route::get('/golden-time-emergency/data', [GoldenTimeEmergencyController::class, 'data'])
            ->name('golden-time-emergency.data');
        Route::get('/golden-time-emergency/export', [GoldenTimeEmergencyController::class, 'export'])
            ->name('golden-time-emergency.export');

        // Parameter Emergency Response — "Kesiapan alat Emergency".
        // Baseline emergency_equipment_inventory, pemeriksaan bulanan
        // emergency_equipment_daily_inspection.
        Route::get('/kesiapan-alat-emergency', [KesiapanAlatEmergencyController::class, 'index'])
            ->name('kesiapan-alat-emergency.index');
        Route::get('/kesiapan-alat-emergency/overview', [KesiapanAlatEmergencyController::class, 'overview'])
            ->name('kesiapan-alat-emergency.overview');
        Route::get('/kesiapan-alat-emergency/detail-bulan', [KesiapanAlatEmergencyController::class, 'detailBulan'])
            ->name('kesiapan-alat-emergency.detail-bulan');
        Route::get('/kesiapan-alat-emergency/data', [KesiapanAlatEmergencyController::class, 'data'])
            ->name('kesiapan-alat-emergency.data');
        Route::get('/kesiapan-alat-emergency/export', [KesiapanAlatEmergencyController::class, 'export'])
            ->name('kesiapan-alat-emergency.export');

        // Parameter "Pemenuhan Regulasi".
        // Ringkasan per site & perusahaan regulatory_compliance_summary,
        // daftar regulasinya regulatory_compliance_detail. Keduanya potret
        // satu waktu -- tidak ada kolom bulan -- jadi tidak ada rute per bulan.
        Route::get('/pemenuhan-regulasi', [PemenuhanRegulasiController::class, 'index'])
            ->name('pemenuhan-regulasi.index');
        Route::get('/pemenuhan-regulasi/overview', [PemenuhanRegulasiController::class, 'overview'])
            ->name('pemenuhan-regulasi.overview');
        Route::get('/pemenuhan-regulasi/detail-sel', [PemenuhanRegulasiController::class, 'detailSel'])
            ->name('pemenuhan-regulasi.detail-sel');
        Route::get('/pemenuhan-regulasi/data', [PemenuhanRegulasiController::class, 'data'])
            ->name('pemenuhan-regulasi.data');
        Route::get('/pemenuhan-regulasi/export', [PemenuhanRegulasiController::class, 'export'])
            ->name('pemenuhan-regulasi.export');

        // Parameter "Laporan Perizinan Usaha Jasa".
        // scr_business_license_performance berformat lebar: bulan sebagai
        // kolom, Januari-September 2026. Angkanya PROPORSI DEVIASI -- nol
        // adalah hasil terbaik.
        Route::get('/laporan-perizinan-usaha-jasa', [LaporanPerizinanUsahaJasaController::class, 'index'])
            ->name('laporan-perizinan-usaha-jasa.index');
        Route::get('/laporan-perizinan-usaha-jasa/overview', [LaporanPerizinanUsahaJasaController::class, 'overview'])
            ->name('laporan-perizinan-usaha-jasa.overview');
        Route::get('/laporan-perizinan-usaha-jasa/detail-bulan', [LaporanPerizinanUsahaJasaController::class, 'detailBulan'])
            ->name('laporan-perizinan-usaha-jasa.detail-bulan');
        Route::get('/laporan-perizinan-usaha-jasa/data', [LaporanPerizinanUsahaJasaController::class, 'data'])
            ->name('laporan-perizinan-usaha-jasa.data');
        Route::get('/laporan-perizinan-usaha-jasa/export', [LaporanPerizinanUsahaJasaController::class, 'export'])
            ->name('laporan-perizinan-usaha-jasa.export');

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

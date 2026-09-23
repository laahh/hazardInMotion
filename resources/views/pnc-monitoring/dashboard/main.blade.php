@extends('pnc-monitoring.layouts.app')

@section('title', 'Dashboard Utama')

@section('css')
<link rel="stylesheet" href="{{ asset('evaluasi-well-assets/css/lib/jquery-jvectormap-2.0.5.css') }}">
@endsection

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <h6 class="fw-semibold mb-0">Dashboard</h6>
  <ul class="d-flex align-items-center gap-2">
    <li class="fw-medium">
      <a href="index.html" class="d-flex align-items-center gap-1 hover-text-primary">
        <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
        Dashboard
      </a>
    </li>
    <li>-</li>
    <li class="fw-medium">eCommerce</li>
  </ul>
</div>
    
    <div class="row gy-4">
        <div class="col-xxl-9">
            <div class="card radius-8 border-0">
                <div class="row">
                    <div class="col-xxl-6 pe-xxl-0">
                        <div class="card-body p-24">
                            @php
                                $pct = fn (?float $v) => $v === null ? 'N/A' : number_format($v * 100, 1) . '%';
                                $ikkKpis = $payload['ikk']['kpis'] ?? [];
                                $ipkPerf = $ikkKpis['ipkPerformance'] ?? null;
                                $ipkLabel = $ipkPerf === null ? 'N/A' : number_format($ipkPerf * 100, 1) . '%';
                                $ipkBadgeClass = $ipkPerf === null
                                    ? 'bg-neutral-200 text-secondary-light'
                                    : ($ipkPerf >= 0.9995 ? 'bg-success-focus text-success-main' : ($ipkPerf >= 0.9 ? 'bg-warning-focus text-warning-main' : 'bg-danger-focus text-danger-main'));
                            @endphp
                            <div class="d-flex align-items-center flex-wrap gap-2 justify-content-between">
                                <div class="d-flex align-items-center gap-12">
                                    <span class="w-44-px h-44-px text-primary-600 bg-primary-light border border-primary-light-white flex-shrink-0 d-flex justify-content-center align-items-center radius-8 h6 mb-0">
                                        <iconify-icon icon="solar:document-text-bold" class="icon"></iconify-icon>
                                    </span>
                                    <div>
                                        <span class="text-secondary-light fw-medium text-sm d-block mb-2">Total IKK</span>
                                        <h5 class="fw-bold mb-0 text-primary-light" id="ikk-period-total">{{ number_format($ikkKpis['ikkCount'] ?? 0, 0, ',', '.') }}</h5>
                                    </div>
                                    <span class="px-12 py-4 rounded-pill fw-semibold text-sm {{ $ipkBadgeClass }}" id="ikk-period-badge">IPK {{ $ipkLabel }}</span>
                                </div>
                                <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
                                    <select id="ikk-chart-mode" class="form-select form-select-sm w-auto bg-base border text-secondary-light">
                                        <option value="year">Tahunan</option>
                                        <option value="month">Bulanan</option>
                                        <option value="week">Mingguan</option>
                                    </select>
                                    <select id="ikk-chart-year" class="form-select form-select-sm w-auto bg-base border text-secondary-light"></select>
                                    <select id="ikk-chart-month" class="form-select form-select-sm w-auto bg-base border text-secondary-light d-none"></select>
                                    <select id="ikk-chart-week" class="form-select form-select-sm w-auto bg-base border text-secondary-light d-none"></select>
                                </div>
                            </div>
                            <div class="mt-40">
                                <div id="paymentStatusChart" class="margin-16-minus"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xxl-6">
                        @php
                            $perfBadge = function (?float $rate): array {
                                if ($rate === null) {
                                    return ['bg-neutral-200 text-secondary-light', 'N/A'];
                                }
                                $cls = $rate >= 0.9995 ? 'bg-success-focus text-success-main' : ($rate >= 0.9 ? 'bg-warning-focus text-warning-main' : 'bg-danger-focus text-danger-main');

                                return [$cls, number_format($rate * 100, 1).'%'];
                            };
                            [$ipkBadgeCls] = $perfBadge($ikkKpis['ipkPerformance'] ?? null);
                            [$iaBadgeCls] = $perfBadge($ikkKpis['iaPerformance'] ?? null);
                            [$okkL1BadgeCls] = $perfBadge($ikkKpis['okkL1Performance'] ?? null);
                            [$okkL2BadgeCls] = $perfBadge($ikkKpis['okkL2UpPerformance'] ?? null);
                        @endphp
                        <div class="row h-100 g-0">
                            <div class="col-6 p-0 m-0">
                                <div class="card-body p-24 h-100 d-flex flex-column justify-content-center border border-top-0">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-1 mb-8">
                                        <div>
                                            <span class="mb-12 w-44-px h-44-px text-primary-600 bg-primary-light border border-primary-light-white flex-shrink-0 d-flex justify-content-center align-items-center radius-8 h6 mb-12">
                                                <iconify-icon icon="solar:clipboard-check-bold" class="icon"></iconify-icon>
                                            </span>
                                            <span class="mb-1 fw-bold text-secondary-light text-md">IPK IKK Aktif</span>
                                            <h6 class="fw-semibold text-primary-light mb-1" id="ikk-metric-ipk-value">{{ $pct($ikkKpis['ipkPerformance'] ?? null) }}</h6>
                                        </div>
                                    </div>
                                    <p class="text-sm mb-0"><span class="{{ $ipkBadgeCls }} px-1 rounded-2 fw-medium text-sm" id="ikk-metric-ipk-sub">{{ number_format($ikkKpis['ipkActual'] ?? 0) }} / {{ number_format($ikkKpis['ipkDenominator'] ?? 0) }}</span> IKK comply</p>
                                </div>
                            </div>
                            <div class="col-6 p-0 m-0">
                                <div class="card-body p-24 h-100 d-flex flex-column justify-content-center border border-top-0 border-start-0 border-end-0">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-1 mb-8">
                                        <div>
                                            <span class="mb-12 w-44-px h-44-px text-yellow bg-yellow-light border border-yellow-light-white flex-shrink-0 d-flex justify-content-center align-items-center radius-8 h6 mb-12">
                                                <iconify-icon icon="solar:eye-bold" class="icon"></iconify-icon>
                                            </span>
                                            <span class="mb-1 fw-bold text-secondary-light text-md">OKK IKK Aktif</span>
                                            <h6 class="fw-semibold text-primary-light mb-1" id="ikk-metric-ia-value">{{ $pct($ikkKpis['iaPerformance'] ?? null) }}</h6>
                                        </div>
                                    </div>
                                    <p class="text-sm mb-0"><span class="{{ $iaBadgeCls }} px-1 rounded-2 fw-medium text-sm" id="ikk-metric-ia-sub">{{ number_format($ikkKpis['iaEffective'] ?? 0) }} / {{ number_format($ikkKpis['iaRequired'] ?? 0) }}</span> IA efektif</p>
                                </div>
                            </div>
                            <div class="col-6 p-0 m-0">
                                <div class="card-body p-24 h-100 d-flex flex-column justify-content-center border border-top-0 border-bottom-0">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-1 mb-8">
                                        <div>
                                            <span class="mb-12 w-44-px h-44-px text-lilac bg-lilac-light border border-lilac-light-white flex-shrink-0 d-flex justify-content-center align-items-center radius-8 h6 mb-12">
                                                <iconify-icon icon="solar:eye-scan-bold" class="icon"></iconify-icon>
                                            </span>
                                            <span class="mb-1 fw-bold text-secondary-light text-md">OKK Layer 1</span>
                                            <h6 class="fw-semibold text-primary-light mb-1" id="ikk-metric-okkl1-value">{{ $pct($ikkKpis['okkL1Performance'] ?? null) }}</h6>
                                        </div>
                                    </div>
                                    <p class="text-sm mb-0"><span class="{{ $okkL1BadgeCls }} px-1 rounded-2 fw-medium text-sm" id="ikk-metric-okkl1-sub">{{ number_format($ikkKpis['okkAchieved'] ?? 0) }} / {{ number_format($ikkKpis['okkPlan'] ?? 0) }}</span> OKK L1</p>
                                </div>
                            </div>
                            <div class="col-6 p-0 m-0">
                                <div class="card-body p-24 h-100 d-flex flex-column justify-content-center border border-top-0 border-start-0 border-end-0 border-bottom-0">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-1 mb-8">
                                        <div>
                                            <span class="mb-12 w-44-px h-44-px text-pink bg-pink-light border border-pink-light-white flex-shrink-0 d-flex justify-content-center align-items-center radius-8 h6 mb-12">
                                                <iconify-icon icon="solar:shield-check-bold" class="icon"></iconify-icon>
                                            </span>
                                            <span class="mb-1 fw-bold text-secondary-light text-md">OKK Layer 2</span>
                                            <h6 class="fw-semibold text-primary-light mb-1" id="ikk-metric-okkl2-value">{{ $pct($ikkKpis['okkL2UpPerformance'] ?? null) }}</h6>
                                        </div>
                                    </div>
                                    <p class="text-sm mb-0"><span class="{{ $okkL2BadgeCls }} px-1 rounded-2 fw-medium text-sm" id="ikk-metric-okkl2-sub">{{ number_format($ikkKpis['layer2Achieved'] ?? 0) }} / {{ number_format($ikkKpis['layer2Required'] ?? 0) }}</span> OKK L2 Up</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-lg-6">
            <div class="card h-100 radius-8 border-0">
                <div class="card-body p-24">
                    @php
                        $cancelCount = (int) ($ikkKpis['cancelCount'] ?? 0);
                        $totalIkkForCancel = (int) ($ikkKpis['ikkCount'] ?? 0);
                        $activeCount = max($totalIkkForCancel - $cancelCount, 0);
                        $cancelPct = $totalIkkForCancel > 0 ? ($cancelCount / $totalIkkForCancel * 100) : 0;
                        $activePct = $totalIkkForCancel > 0 ? ($activeCount / $totalIkkForCancel * 100) : 0;
                    @endphp
                    <div class="d-flex align-items-center flex-wrap gap-2 justify-content-between">
                        <h6 class="mb-2 fw-bold text-lg">Persentase Cancel IKK</h6>
                        <div class="">
                        <select class="form-select form-select-sm w-auto bg-base border text-secondary-light">
                            <option>Yearly</option>
                            <option>Monthly</option>
                            <option>Weekly</option>
                            <option>Today</option>
                        </select>
                        </div>
                    </div>

                    <div class="position-relative">
                        <span class="w-80-px h-80-px bg-base shadow text-danger-main fw-semibold text-xl d-flex justify-content-center align-items-center rounded-circle position-absolute end-0 top-0 z-1">{{ number_format($cancelPct, 1) }}%</span>
                        <div id="statisticsDonutChart" class="mt-36 flex-grow-1 apexcharts-tooltip-z-none title-style circle-none"></div>
                        <span class="w-80-px h-80-px bg-base shadow text-primary-light fw-semibold text-xl d-flex justify-content-center align-items-center rounded-circle position-absolute start-0 bottom-0 z-1">{{ number_format($activePct, 1) }}%</span>
                    </div>

                    <ul class="d-flex flex-wrap align-items-center justify-content-between mt-3 gap-3">
                        <li class="d-flex align-items-center gap-2">
                            <span class="w-12-px h-12-px radius-2 bg-danger-main"></span>
                            <span class="text-secondary-light text-sm fw-normal">Cancel:
                                <span class="text-primary-light fw-bold">{{ number_format($cancelCount, 0, ',', '.') }}</span>
                            </span>
                        </li>
                        <li class="d-flex align-items-center gap-2">
                            <span class="w-12-px h-12-px radius-2 bg-primary-600"></span>
                            <span class="text-secondary-light text-sm fw-normal">Aktif:
                                <span class="text-primary-light fw-bold">{{ number_format($activeCount, 0, ',', '.') }}</span>
                            </span>
                        </li>
                    </ul>

                </div>
            </div>
        </div>
        <div class="col-xxl-9 col-lg-6">
            <div class="card h-100 wc-card">
                <div class="card-body p-24 d-flex flex-column h-100">
                    @php
                        $ikkHeatmap = $payload['ikk']['heatmap'] ?? [];
                        $heatmapOverallRate = $ikkHeatmap['overallComplianceRate'] ?? null;
                    @endphp
                    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-16 flex-shrink-0">
                        <div class="d-flex align-items-start gap-3 min-w-0">
                            <span class="wc-card__head-icon">
                                <iconify-icon icon="mdi:calendar-month-outline"></iconify-icon>
                            </span>
                            <div class="min-w-0">
                                <h6 class="mb-1 fw-bold text-lg wc-card__title">Pola IKK Harian &amp; Kepatuhan</h6>
                                <span class="wc-card__subtitle">Kapan IKK dibuat, dan seberapa patuh (IPK) setiap harinya?</span>
                            </div>
                        </div>
                        <div class="wc-card__badge">
                            <iconify-icon icon="mdi:file-document-multiple"></iconify-icon>
                            <span>Terbanyak {{ number_format($ikkHeatmap['peakDayCount'] ?? 0) }} IKK · {{ $ikkHeatmap['peakDayLabel'] ?? '-' }}</span>
                        </div>
                    </div>

                    <div id="ikkHeatmapChart" class="activity-pattern-heatmap flex-grow-1 mb-8" aria-label="Heatmap kepatuhan IKK harian"></div>

                    <div class="d-flex align-items-center flex-wrap gap-3 mb-16 flex-shrink-0">
                        <span class="text-xs fw-medium" style="color:#64748B;">Tingkat kepatuhan (IPK)</span>
                        <span class="d-inline-flex align-items-center gap-1 text-xs" style="color:#64748B;"><span class="rounded-1" style="width:14px;height:14px;background:#E2E8F0;"></span>Tidak ada IKK</span>
                        <span class="d-inline-flex align-items-center gap-1 text-xs" style="color:#64748B;"><span class="rounded-1" style="width:14px;height:14px;background:#FCA5A5;"></span>&lt;50%</span>
                        <span class="d-inline-flex align-items-center gap-1 text-xs" style="color:#64748B;"><span class="rounded-1" style="width:14px;height:14px;background:#FDE68A;"></span>50–74%</span>
                        <span class="d-inline-flex align-items-center gap-1 text-xs" style="color:#64748B;"><span class="rounded-1" style="width:14px;height:14px;background:#A7F3D0;"></span>75–89%</span>
                        <span class="d-inline-flex align-items-center gap-1 text-xs" style="color:#64748B;"><span class="rounded-1" style="width:14px;height:14px;background:#34D399;"></span>90–99%</span>
                        <span class="d-inline-flex align-items-center gap-1 text-xs" style="color:#64748B;"><span class="rounded-1" style="width:14px;height:14px;background:#059669;"></span>100%</span>
                    </div>

                    <div class="wc-tip wc-tip--green mb-16 flex-shrink-0">
                        <iconify-icon icon="solar:calendar-bold" class="flex-shrink-0"></iconify-icon>
                        <span>{{ $ikkHeatmap['insight'] ?? 'Belum ada data IKK untuk ditampilkan.' }}</span>
                    </div>

                    <div class="row g-3 flex-shrink-0 activity-pattern-metrics">
                        <div class="col-sm-6 col-xl-4">
                            <div class="activity-pattern-metric h-100">
                                <span class="activity-pattern-metric__icon"><iconify-icon icon="solar:calendar-mark-bold"></iconify-icon></span>
                                <div class="activity-pattern-metric__body">
                                    <div class="activity-pattern-metric__label">Hari Terbanyak</div>
                                    <div class="activity-pattern-metric__value">{{ $ikkHeatmap['peakDayLabel'] ?? '-' }}</div>
                                    <div class="activity-pattern-metric__sub">{{ number_format($ikkHeatmap['peakDayCount'] ?? 0) }} IKK</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-4">
                            <div class="activity-pattern-metric h-100">
                                <span class="activity-pattern-metric__icon"><iconify-icon icon="solar:chart-2-bold"></iconify-icon></span>
                                <div class="activity-pattern-metric__body">
                                    <div class="activity-pattern-metric__label">Rata-rata Harian</div>
                                    <div class="activity-pattern-metric__value">{{ number_format($ikkHeatmap['avgDaily'] ?? 0) }}</div>
                                    <div class="activity-pattern-metric__sub">IKK / hari (hari dengan data)</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-4">
                            <div class="activity-pattern-metric h-100">
                                <span class="activity-pattern-metric__icon"><iconify-icon icon="solar:shield-check-bold"></iconify-icon></span>
                                <div class="activity-pattern-metric__body">
                                    <div class="activity-pattern-metric__label">Kepatuhan Keseluruhan</div>
                                    <div class="activity-pattern-metric__value">{{ $heatmapOverallRate === null ? 'N/A' : number_format($heatmapOverallRate, 1) . '%' }}</div>
                                    <div class="activity-pattern-metric__sub">IPK comply / total IKK</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <style>
        .wc-card { border: 1px solid #E2E8F0 !important; border-radius: 16px !important; box-shadow: 0 4px 18px rgba(15, 23, 42, 0.06) !important; background: #fff; overflow: hidden; position: relative; }
        .wc-card .card-body { z-index: 1; position: relative; }
        .wc-card__head-icon { width: 44px; height: 44px; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 22px; background: #16A34A; color: #fff; }
        .wc-card__title { color: #0F172A; }
        .wc-card__subtitle { display: block; font-size: 13px; color: #64748B; line-height: 1.35; }
        .wc-card__badge { display: inline-flex; align-items: center; gap: 8px; background: #ECFDF5; color: #166534; border: 1px solid #BBF7D0; border-radius: 999px; padding: 8px 14px; font-size: 12px; font-weight: 600; line-height: 1.35; max-width: 100%; }
        .wc-card__badge iconify-icon { font-size: 16px; color: #16A34A; flex-shrink: 0; }
        .wc-tip { display: flex; align-items: flex-start; gap: 10px; border-radius: 12px; padding: 12px 14px; font-size: 13px; font-weight: 500; line-height: 1.45; }
        .wc-tip iconify-icon { font-size: 18px; margin-top: 1px; }
        .wc-tip--green { background: #ECFDF5; color: #166534; border: 1px solid #BBF7D0; }
        .activity-pattern-card { min-height: 100%; }
        .activity-pattern-metric { border: 1px solid #E2E8F0; background: #F8FFFC; border-radius: 12px; padding: 16px 14px; display: flex; flex-direction: row; align-items: center; gap: 12px; }
        .activity-pattern-metric__icon { width: 44px; height: 44px; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 20px; background: #ECFDF5; color: #10B981; }
        .activity-pattern-metric__body { min-width: 0; display: flex; flex-direction: column; gap: 2px; }
        .activity-pattern-metric__label { font-size: 12px; font-weight: 500; color: #64748B; line-height: 1.25; }
        .activity-pattern-metric__value { font-size: 18px; font-weight: 700; color: #0F172A; line-height: 1.25; letter-spacing: -0.01em; }
        .activity-pattern-metric__sub { margin-top: 0; font-size: 12px; color: #64748B; line-height: 1.3; }
        .activity-pattern-heatmap { position: relative; }
        .activity-pattern-heatmap .ap-heatmap-scroll { width: 100%; overflow-x: auto; overflow-y: hidden; padding-bottom: 4px; }
        .activity-pattern-heatmap .ap-heatmap { display: grid; grid-template-columns: 58px minmax(0, 1fr); grid-template-rows: repeat(7, minmax(18px, 1fr)) 28px; gap: 3px 8px; align-items: stretch; overflow: visible; height: 100%; min-height: 240px; min-width: max(100%, calc(58px + var(--ap-cols) * 14px)); }
        .activity-pattern-heatmap .ap-heatmap--calendar { max-width: none; width: 100%; }
        #ikkHeatmapChart.activity-pattern-heatmap { overflow: visible; min-height: 240px; display: flex; flex-direction: column; }
        #ikkHeatmapChart.activity-pattern-heatmap .ap-heatmap { flex: 1 1 auto; }
        .activity-pattern-heatmap .ap-heatmap-corner { min-height: 24px; }
        .activity-pattern-heatmap .ap-heatmap-xlabels { display: grid; grid-template-columns: repeat(var(--ap-cols), minmax(0, 1fr)); gap: 3px; min-height: 24px; align-items: center; overflow: visible; }
        .activity-pattern-heatmap .ap-heatmap-xlabel { display: flex; align-items: center; justify-content: flex-start; min-width: 0; overflow: visible; }
        .activity-pattern-heatmap .ap-heatmap-xlabel span { display: inline-block; font-size: 10px; line-height: 1.2; color: #64748B; font-weight: 500; white-space: nowrap; transform: none; margin: 0; }
        .activity-pattern-heatmap .ap-heatmap-xlabel.is-muted span { opacity: 0; }
        .activity-pattern-heatmap .ap-heatmap-ylabel { display: flex; align-items: center; justify-content: flex-end; padding-right: 2px; font-size: 11px; font-weight: 500; color: #64748B; white-space: nowrap; }
        .activity-pattern-heatmap .ap-heatmap-row { display: grid; grid-template-columns: repeat(var(--ap-cols), minmax(0, 1fr)); gap: 3px; height: 100%; min-height: 16px; }
        .activity-pattern-heatmap .ap-heatmap-cell { height: 100%; min-height: 16px; width: 100%; justify-self: stretch; border-radius: 3px; border: 1px solid rgba(255,255,255,.75); cursor: default; transition: transform .12s ease, box-shadow .12s ease; }
        .activity-pattern-heatmap .ap-heatmap-cell:not(.is-empty):hover { transform: scale(1.08); box-shadow: 0 0 0 2px rgba(5, 150, 105, 0.35); z-index: 1; position: relative; }
        .activity-pattern-heatmap .ap-heatmap-cell.is-empty { background: #E2E8F0 !important; border: 1px solid rgba(255,255,255,.75); box-shadow: none; cursor: default; }
        .activity-pattern-heatmap .ap-heatmap-cell.is-empty:hover { transform: none; box-shadow: none; }
        .activity-pattern-heatmap .ap-heatmap-tooltip { position: absolute; z-index: 20; transform: translate(-50%, -100%); background: #0F172A; color: #F8FAFC; font-size: 12px; font-weight: 500; line-height: 1.35; padding: 8px 12px; border-radius: 8px; white-space: nowrap; pointer-events: none; box-shadow: 0 8px 20px rgba(15, 23, 42, 0.18); }
        .activity-pattern-heatmap .ap-heatmap-tooltip::after { content: ''; position: absolute; left: 50%; top: 100%; transform: translateX(-50%); border: 6px solid transparent; border-top-color: #0F172A; }
        @media (max-width: 768px) {
          .activity-pattern-heatmap .ap-heatmap { grid-template-columns: 52px minmax(0, 1fr); gap: 4px 8px; min-height: 220px; max-width: 100%; }
          .activity-pattern-heatmap .ap-heatmap-cell { min-height: 20px; border-radius: 4px; }
          .activity-pattern-heatmap .ap-heatmap-xlabel span { font-size: 10px; }
          .activity-pattern-heatmap .ap-heatmap-ylabel { font-size: 11px; }
          .activity-pattern-metric__value { font-size: 16px; }
        }
        </style>
        <div class="col-xxl-3">
            <div class="card h-100">

                <div class="card-body">
                  <div class="d-flex align-items-center flex-wrap gap-2 justify-content-between">
                    <h6 class="mb-2 fw-bold text-lg">IKK Tidak Comply</h6>
                    <a href="{{ route('pnc-monitoring.ikk-records.index') }}" class="text-primary-600 hover-text-primary d-flex align-items-center gap-1">
                        Lihat Semua
                        <iconify-icon icon="solar:alt-arrow-right-linear" class="icon"></iconify-icon>
                    </a>
                </div>

                  <div class="mt-32 overflow-y-auto scroll-sm" style="max-height: 380px;">
                    @php $nonCompliant = $payload['ikk']['nonCompliant'] ?? []; @endphp
                    @forelse ($nonCompliant as $i => $nc)
                      <div class="d-flex align-items-center justify-content-between gap-3 {{ $i === count($nonCompliant) - 1 ? '' : 'mb-32' }}">
                        <div class="d-flex align-items-center gap-2">
                          <span class="w-40-px h-40-px radius-8 flex-shrink-0 bg-danger-focus text-danger-main d-flex justify-content-center align-items-center">
                            <iconify-icon icon="solar:close-circle-bold"></iconify-icon>
                          </span>
                          <div class="flex-grow-1">
                            <h6 class="text-md mb-0 fw-normal">{{ $nc['nomor'] }}</h6>
                            <span class="text-sm text-secondary-light fw-normal">{{ $nc['site'] ?? '-' }} · {{ $nc['perusahaan'] ?? '-' }}</span>
                          </div>
                        </div>
                        <span class="text-danger-main text-sm fw-medium text-end">{{ $nc['tanggal'] ?? '-' }}</span>
                      </div>
                    @empty
                      <p class="text-secondary-light text-sm mb-0 text-center py-24">Tidak ada IKK yang cancel/tidak comply. 🎉</p>
                    @endforelse
                  </div>
                </div>
            </div>
        </div>
        <!-- homeThreeChart.js still targets #recent-orders on load; keep a hidden placeholder
             so it renders into it harmlessly instead of throwing on a null selector. -->
        <div id="recent-orders" class="d-none"></div>
        <div class="col-xxl-6">
            <div class="card h-100">
                <div class="card-body p-24">
                    <h6 class="mb-2 fw-bold text-lg">Finding IA &amp; Verlap per Site</h6>
                    <p class="text-sm text-secondary-light mb-12">Nilai finding dijumlahkan dari kolom Finding IA / Finding Verlap.</p>
                    <div id="chart-finding-site"></div>
                </div>
            </div>
        </div>
        <div class="col-xxl-6">
            <div class="card h-100">
                <div class="card-body p-24">
                    <h6 class="mb-2 fw-bold text-lg">Cancel IKK/IPK per Site</h6>
                    <p class="text-sm text-secondary-light mb-12">Seluruh IPK = 0 maupun IPK blank dihitung sebagai satu kategori Cancel.</p>
                    <div id="chart-cancel-site"></div>
                </div>
            </div>
        </div>
        <div class="col-xxl-6">
            <div class="card h-100">
                <div class="card-body p-24">
                    <h6 class="mb-2 fw-bold text-lg">Top Perusahaan — Cancel IKK/IPK</h6>
                    <div id="chart-cancel-company" class="mt-12"></div>
                </div>
            </div>
        </div>
        <div class="col-xxl-6">
            <div class="card h-100">
                <div class="card-body p-24">
                    <h6 class="mb-2 fw-bold text-lg">Top Perusahaan — Finding IA</h6>
                    <div id="chart-findingia-company" class="mt-12"></div>
                </div>
            </div>
        </div>
        <div class="col-xxl-12">
          <div class="card h-100">
              <div class="card-body p-24">
                  <div class="d-flex align-items-center flex-wrap gap-2 justify-content-between mb-20">
                      <h6 class="mb-2 fw-bold text-lg mb-0">Data IKK Terbaru</h6>
                      <a href="{{ route('pnc-monitoring.ikk-records.index') }}" class="text-primary-600 hover-text-primary d-flex align-items-center gap-1">
                          Lihat Semua
                          <iconify-icon icon="solar:alt-arrow-right-linear" class="icon"></iconify-icon>
                      </a>
                  </div>
                  <div class="table-responsive scroll-sm">
                      <table class="table bordered-table mb-0">
                        <thead>
                            <tr>
                              <th scope="col">Nomor IKK</th>
                              <th scope="col">Jenis</th>
                              <th scope="col">Site</th>
                              <th scope="col">Perusahaan</th>
                              <th scope="col">Tanggal</th>
                              <th scope="col" class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($payload['ikk']['recent'] ?? [] as $rec)
                            <tr>
                              <td><span class="text-secondary-light fw-semibold">{{ $rec['nomor'] }}</span></td>
                              <td>{{ $rec['jenis'] ?? '-' }}</td>
                              <td>{{ $rec['site'] ?? '-' }}</td>
                              <td>{{ $rec['perusahaan'] ?? '-' }}</td>
                              <td>{{ $rec['tanggal'] ?? '-' }}</td>
                              <td class="text-center">
                                @if ($rec['comply'])
                                  <span class="bg-success-focus text-success-main px-32 py-4 rounded-pill fw-medium text-sm">Comply</span>
                                @else
                                  <span class="bg-danger-focus text-danger-main px-24 py-4 rounded-pill fw-medium text-sm">Tidak Comply</span>
                                @endif
                              </td>
                            </tr>
                            @empty
                            <tr>
                              <td colspan="6" class="text-center text-secondary-light py-24">Belum ada data IKK.</td>
                            </tr>
                            @endforelse
                        </tbody>
                      </table>
                  </div>
              </div>
          </div>
        </div>
    </div>
@endsection

@section('page-scripts')
<script src="{{ asset('evaluasi-well-assets/js/lib/jquery-jvectormap-2.0.5.min.js') }}"></script>
<script src="{{ asset('evaluasi-well-assets/js/lib/jquery-jvectormap-world-mill-en.js') }}"></script>
<script src="{{ asset('evaluasi-well-assets/js/homeThreeChart.js') }}"></script>
@endsection

@section('scripts')
<script>
(() => {
  // homeThreeChart.js already rendered a dummy Male/Female donut into #statisticsDonutChart
  // on page load — replace it with the real IKK cancel-vs-active breakdown.
  const cancelCount = {{ $cancelCount }};
  const activeCount = {{ $activeCount }};
  const totalIkk = cancelCount + activeCount;
  const el = document.querySelector('#statisticsDonutChart');
  if (el) {
    el.innerHTML = '';
    const chart = new ApexCharts(el, {
      series: totalIkk > 0 ? [cancelCount, activeCount] : [1],
      colors: totalIkk > 0 ? ['#F8285A', '#487FFF'] : ['#E4E7EC'],
      labels: totalIkk > 0 ? ['Cancel', 'Aktif'] : ['Belum ada data'],
      legend: { show: false },
      chart: { type: 'donut', height: 230, sparkline: { enabled: true } },
      stroke: { width: 0 },
      dataLabels: { enabled: false },
      tooltip: { y: { formatter: (v) => v.toLocaleString('id-ID') } },
    });
    chart.render();
  }
})();
</script>
<script>
(function () {
  // homeThreeChart.js already rendered a dummy Revenue Report bar chart into
  // #paymentStatusChart on page load — replace it with a real, filterable IKK chart
  // (Tahunan/Bulanan/Mingguan, minggu dihitung Minggu → Sabtu).
  var chartEl = document.querySelector('#paymentStatusChart');
  var modeEl = document.querySelector('#ikk-chart-mode');
  var yearEl = document.querySelector('#ikk-chart-year');
  var monthEl = document.querySelector('#ikk-chart-month');
  var weekEl = document.querySelector('#ikk-chart-week');
  var totalEl = document.querySelector('#ikk-period-total');
  var badgeEl = document.querySelector('#ikk-period-badge');
  if (!chartEl || !modeEl || !yearEl || !monthEl || !weekEl) {
    return;
  }

  var dailySeries = @json($payload['ikk']['dailySeries'] ?? []);
  var dailyMap = {};
  dailySeries.forEach(function (row) {
    dailyMap[row.date] = row;
  });

  var monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
  var dayNamesSundayFirst = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
  var monthShort = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

  function pad2(n) { return String(n).padStart(2, '0'); }
  function toIso(d) { return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate()); }
  function shortLabel(d) { return d.getDate() + ' ' + monthShort[d.getMonth()]; }
  function addDays(d, n) { var r = new Date(d); r.setDate(r.getDate() + n); return r; }
  // Cut-off minggu: Minggu (Sunday) s/d Sabtu (Saturday).
  function startOfWeekSunday(d) { var r = new Date(d); r.setHours(0, 0, 0, 0); r.setDate(r.getDate() - r.getDay()); return r; }

  var emptyCell = { total: 0, compliant: 0, ipkActual: 0, ipkDenominator: 0, iaEffective: 0, planOkk: 0, okkAchieved: 0, layer2Achieved: 0 };

  function dayCell(dateObj) {
    return dailyMap[toIso(dateObj)] || emptyCell;
  }

  function newAccumulator() {
    return { total: 0, compliant: 0, ipkActual: 0, ipkDenominator: 0, iaEffective: 0, planOkk: 0, okkAchieved: 0, layer2Achieved: 0 };
  }

  function addCellInto(acc, cell) {
    acc.total += cell.total;
    acc.compliant += cell.compliant;
    acc.ipkActual += cell.ipkActual;
    acc.ipkDenominator += cell.ipkDenominator;
    acc.iaEffective += cell.iaEffective;
    acc.planOkk += cell.planOkk;
    acc.okkAchieved += cell.okkAchieved;
    acc.layer2Achieved += cell.layer2Achieved;
  }

  function sumRange(startDate, endDate) {
    var acc = newAccumulator();
    var cursor = new Date(startDate);
    while (cursor <= endDate) {
      addCellInto(acc, dayCell(cursor));
      cursor = addDays(cursor, 1);
    }
    return acc;
  }

  function weekStartsInMonth(year, month) {
    var lastDay = new Date(year, month, 0).getDate();
    var starts = [];
    var seen = {};
    for (var d = 1; d <= lastDay; d++) {
      var ws = startOfWeekSunday(new Date(year, month - 1, d));
      var key = toIso(ws);
      if (!seen[key]) {
        seen[key] = true;
        starts.push(ws);
      }
    }
    return starts;
  }

  var availableYears = Array.from(new Set(dailySeries.map(function (r) { return Number(r.date.slice(0, 4)); }))).sort(function (a, b) { return b - a; });
  if (!availableYears.length) {
    availableYears = [new Date().getFullYear()];
  }

  yearEl.innerHTML = availableYears.map(function (y) { return '<option value="' + y + '">' + y + '</option>'; }).join('');
  monthEl.innerHTML = monthNames.map(function (name, idx) { return '<option value="' + (idx + 1) + '">' + name + '</option>'; }).join('');

  // Default: tahun & bulan yang benar-benar punya data terbaru (kalau ada), fallback ke hari ini.
  var latest = dailySeries.length ? dailySeries[dailySeries.length - 1].date : toIso(new Date());
  yearEl.value = String(Number(latest.slice(0, 4)));
  monthEl.value = String(Number(latest.slice(5, 7)));

  function refreshWeekOptions() {
    var year = Number(yearEl.value);
    var month = Number(monthEl.value);
    var starts = weekStartsInMonth(year, month);
    weekEl.innerHTML = starts.map(function (ws) {
      var we = addDays(ws, 6);
      return '<option value="' + toIso(ws) + '">' + shortLabel(ws) + ' – ' + shortLabel(we) + '</option>';
    }).join('');
    if (starts.length) {
      weekEl.value = toIso(starts[starts.length - 1]);
    }
  }

  function toggleControls() {
    var mode = modeEl.value;
    monthEl.classList.toggle('d-none', mode === 'year');
    weekEl.classList.toggle('d-none', mode !== 'week');
  }

  var chart = null;
  function drawChart(categories, totals, compliants) {
    var opts = {
      series: [
        { name: 'Total IKK', data: totals },
        { name: 'Comply IKK', data: compliants },
      ],
      colors: ['#487FFF', '#45B369'],
      legend: { show: false },
      chart: { type: 'bar', height: 250, toolbar: { show: false } },
      grid: { show: true, borderColor: '#D1D5DB', strokeDashArray: 4, position: 'back' },
      plotOptions: { bar: { borderRadius: 4, columnWidth: totals.length > 8 ? '55%' : '35%' } },
      dataLabels: { enabled: false },
      stroke: { show: true, width: 2, colors: ['transparent'] },
      xaxis: { categories: categories },
      fill: { opacity: 1 },
    };
    if (chart) {
      chart.destroy();
    }
    chart = new ApexCharts(chartEl, opts);
    chart.render();
  }

  function updateHeader(total, compliant) {
    if (totalEl) {
      totalEl.textContent = total.toLocaleString('id-ID');
    }
    if (badgeEl) {
      var rate = total > 0 ? (compliant / total * 100) : null;
      var label = rate === null ? 'N/A' : rate.toFixed(1) + '%';
      badgeEl.textContent = 'IPK ' + label;
      badgeEl.className = 'px-12 py-4 rounded-pill fw-semibold text-sm '
        + (rate === null ? 'bg-neutral-200 text-secondary-light'
          : rate >= 99.95 ? 'bg-success-focus text-success-main'
          : rate >= 90 ? 'bg-warning-focus text-warning-main'
          : 'bg-danger-focus text-danger-main');
    }
  }

  function badgeClassFor(rate) {
    return rate === null ? 'bg-neutral-200 text-secondary-light'
      : rate >= 99.95 ? 'bg-success-focus text-success-main'
      : rate >= 90 ? 'bg-warning-focus text-warning-main'
      : 'bg-danger-focus text-danger-main';
  }

  function updateMetric(prefix, actual, denominator) {
    var valueEl = document.querySelector('#ikk-metric-' + prefix + '-value');
    var subEl = document.querySelector('#ikk-metric-' + prefix + '-sub');
    var rate = denominator > 0 ? (actual / denominator * 100) : null;
    if (valueEl) {
      valueEl.textContent = rate === null ? 'N/A' : rate.toFixed(1) + '%';
    }
    if (subEl) {
      subEl.textContent = actual.toLocaleString('id-ID') + ' / ' + denominator.toLocaleString('id-ID');
      subEl.className = 'px-1 rounded-2 fw-medium text-sm ' + badgeClassFor(rate);
    }
  }

  // Kartu IPK/OKK IKK Aktif/OKK Layer 1/OKK Layer 2 mengikuti filter Total IKK yang sama.
  function updateSideMetrics(acc) {
    updateMetric('ipk', acc.ipkActual, acc.ipkDenominator);
    updateMetric('ia', acc.iaEffective, acc.total);
    updateMetric('okkl1', acc.okkAchieved, acc.planOkk);
    updateMetric('okkl2', acc.layer2Achieved, acc.planOkk);
  }

  function render() {
    var mode = modeEl.value;
    var year = Number(yearEl.value);
    var month = Number(monthEl.value);
    var categories = [];
    var totals = [];
    var compliants = [];
    var period = newAccumulator();

    if (mode === 'year') {
      for (var m = 1; m <= 12; m++) {
        var start = new Date(year, m - 1, 1);
        var end = new Date(year, m, 0);
        var sum = sumRange(start, end);
        categories.push(monthNames[m - 1]);
        totals.push(sum.total);
        compliants.push(sum.compliant);
        addCellInto(period, sum);
      }
    } else if (mode === 'month') {
      weekStartsInMonth(year, month).forEach(function (ws) {
        var we = addDays(ws, 6);
        var sum = sumRange(ws, we);
        categories.push(shortLabel(ws) + '–' + shortLabel(we));
        totals.push(sum.total);
        compliants.push(sum.compliant);
        addCellInto(period, sum);
      });
    } else {
      var weekStartVal = weekEl.value;
      var weekStart = weekStartVal ? new Date(weekStartVal + 'T00:00:00') : startOfWeekSunday(new Date());
      for (var i = 0; i < 7; i++) {
        var day = addDays(weekStart, i);
        var cell = dayCell(day);
        categories.push(dayNamesSundayFirst[i]);
        totals.push(cell.total);
        compliants.push(cell.compliant);
        addCellInto(period, cell);
      }
    }

    drawChart(categories, totals, compliants);
    updateHeader(period.total, period.compliant);
    updateSideMetrics(period);
  }

  modeEl.addEventListener('change', function () {
    toggleControls();
    if (modeEl.value === 'week') {
      refreshWeekOptions();
    }
    render();
  });
  yearEl.addEventListener('change', function () {
    refreshWeekOptions();
    render();
  });
  monthEl.addEventListener('change', function () {
    refreshWeekOptions();
    render();
  });
  weekEl.addEventListener('change', render);

  refreshWeekOptions();
  toggleControls();
  render();
})();
</script>
<script>
(function () {
  var el = document.querySelector('#ikkHeatmapChart');
  if (!el) {
    return;
  }

  var series = @json($ikkHeatmap['series'] ?? []);
  var categories = @json($ikkHeatmap['categories'] ?? []);

  function formatNumber(value) {
    return Number(value || 0).toLocaleString('id-ID');
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  // Warna berdasarkan persentase kepatuhan (IPK), bukan volume — merah = rendah, hijau = tinggi.
  function colorFor(rate) {
    if (rate === null || rate === undefined) return '#E2E8F0';
    var n = Number(rate);
    if (n < 50) return '#FCA5A5';
    if (n < 75) return '#FDE68A';
    if (n < 90) return '#A7F3D0';
    if (n < 100) return '#34D399';
    return '#059669';
  }

  if (!series.length || !categories.length) {
    el.innerHTML = '<p class="text-secondary-light text-sm mb-0 text-center py-40">Belum ada data IKK untuk ditampilkan.</p>';
    return;
  }

  // Calendar heatmap: Senin di atas → Minggu di bawah; kolom = minggu.
  var ordered = series.slice();
  var preferred = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
  ordered.sort(function (a, b) {
    return preferred.indexOf(a.name) - preferred.indexOf(b.name);
  });

  var colCount = categories.length;
  var html = '';
  html += '<div class="ap-heatmap-scroll">';
  html += '<div class="ap-heatmap ap-heatmap--calendar" style="--ap-cols:' + colCount + ';">';

  ordered.forEach(function (row) {
    html += '<div class="ap-heatmap-ylabel">' + escapeHtml(row.name) + '</div>';
    html += '<div class="ap-heatmap-row">';
    (row.data || []).forEach(function (cell) {
      var isEmpty = !cell || cell.empty === true;
      if (isEmpty) {
        html += '<div class="ap-heatmap-cell is-empty" aria-hidden="true"></div>';
        return;
      }
      var rate = cell.y;
      var total = cell.total || 0;
      var compliant = cell.compliant || 0;
      var dateLabel = cell.date_label || cell.x || '';
      var rateLabel = rate === null || rate === undefined ? '-' : rate + '%';
      var tip = escapeHtml(dateLabel) + ' | ' + formatNumber(total) + ' IKK · ' + formatNumber(compliant) + ' comply (' + rateLabel + ')';
      html += '<div class="ap-heatmap-cell" style="background:' + colorFor(rate) + ';"'
        + ' data-tip="' + tip + '"'
        + ' role="img"'
        + ' aria-label="' + tip + '">'
        + '</div>';
    });
    html += '</div>';
  });

  html += '<div class="ap-heatmap-corner"></div>';
  html += '<div class="ap-heatmap-xlabels">';
  var prevMonth = '';
  categories.forEach(function (label, idx) {
    var parts = String(label).split(/\s+/);
    var month = parts[1] || '';
    var show = idx === 0 || month !== prevMonth;
    prevMonth = month || prevMonth;
    var text = show ? escapeHtml(label) : '';
    html += '<div class="ap-heatmap-xlabel' + (show ? '' : ' is-muted') + '" title="Minggu mulai ' + escapeHtml(label) + '"><span>' + text + '</span></div>';
  });
  html += '</div>';
  html += '</div>';
  html += '</div>';
  html += '<div class="ap-heatmap-tooltip" id="ikk-heatmap-tooltip" hidden></div>';

  el.innerHTML = html;

  var tipEl = el.querySelector('#ikk-heatmap-tooltip');
  el.querySelectorAll('.ap-heatmap-cell:not(.is-empty)').forEach(function (cell) {
    cell.addEventListener('mouseenter', function () {
      if (!tipEl) return;
      tipEl.textContent = cell.getAttribute('data-tip') || '';
      tipEl.hidden = false;
      var rect = cell.getBoundingClientRect();
      var host = el.getBoundingClientRect();
      tipEl.style.left = (rect.left - host.left + rect.width / 2) + 'px';
      tipEl.style.top = (rect.top - host.top - 8) + 'px';
    });
    cell.addEventListener('mouseleave', function () {
      if (tipEl) tipEl.hidden = true;
    });
  });
})();
</script>
<script>
(function () {
  var bySite = @json($payload['ikk']['bySite'] ?? []);
  var rankings = @json($payload['ikk']['rankings'] ?? ['cancelSite' => [], 'cancelCompany' => [], 'findingIACompany' => []]);

  function drawBar(id, categories, series, horizontal, colors) {
    var el = document.querySelector(id);
    if (!el) return;
    var chart = new ApexCharts(el, {
      chart: { type: 'bar', height: 320, toolbar: { show: false } },
      series: series,
      xaxis: { categories: categories },
      plotOptions: { bar: { horizontal: !!horizontal, borderRadius: 4, columnWidth: '55%', dataLabels: { position: horizontal ? 'top' : 'center' } } },
      dataLabels: {
        enabled: true,
        offsetX: horizontal ? 16 : 0,
        style: { colors: ['#334155'] },
        formatter: function (v) { return Number(v || 0).toLocaleString('id-ID'); },
      },
      colors: colors,
    });
    chart.render();
  }

  drawBar('#chart-finding-site', bySite.map(function (x) { return x.name; }), [
    { name: 'Finding IA', data: bySite.map(function (x) { return x.findingIA || 0; }) },
    { name: 'Finding Verlap', data: bySite.map(function (x) { return x.findingVerlap || 0; }) },
  ], false, ['#F9A825', '#F8285A']);

  drawBar('#chart-cancel-site', (rankings.cancelSite || []).map(function (x) { return x.name; }), [
    { name: 'Cancel', data: (rankings.cancelSite || []).map(function (x) { return x.value || 0; }) },
  ], true, ['#487FFF']);

  drawBar('#chart-cancel-company', (rankings.cancelCompany || []).map(function (x) { return x.name; }), [
    { name: 'Cancel', data: (rankings.cancelCompany || []).map(function (x) { return x.value || 0; }) },
  ], true, ['#487FFF']);

  drawBar('#chart-findingia-company', (rankings.findingIACompany || []).map(function (x) { return x.name; }), [
    { name: 'Finding IA', data: (rankings.findingIACompany || []).map(function (x) { return x.value || 0; }) },
  ], true, ['#487FFF']);
})();
</script>
@endsection

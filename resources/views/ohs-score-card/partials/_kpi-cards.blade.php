@php
    $cards = [
        [
            'label' => 'Karyawan Aktif Olahraga',
            'value' => number_format($summary['aktif_olahraga'] ?? 0),
            'sub' => ($summary['aktif_pct'] ?? 0) . '% dari ' . number_format($summary['total_karyawan'] ?? 0) . ' karyawan',
            'icon' => 'mingcute:user-follow-fill',
            'bg' => 'bg-primary-600',
            'gradient' => 'bg-gradient-end-1',
        ],
        [
            'label' => 'Total Sesi (Strava)',
            'value' => number_format($summary['total_sesi'] ?? 0),
            'sub' => number_format($summary['total_menit'] ?? 0) . ' menit total',
            'icon' => 'solar:dumbbell-bold',
            'bg' => 'bg-success-main',
            'gradient' => 'bg-gradient-end-2',
        ],
        [
            'label' => 'Total Jarak',
            'value' => number_format($summary['total_km'] ?? 0, 1) . ' km',
            'sub' => number_format($summary['total_kalori'] ?? 0) . ' kkal terbakar',
            'icon' => 'solar:route-bold',
            'bg' => 'bg-yellow',
            'gradient' => 'bg-gradient-end-3',
        ],
        [
            'label' => 'Koneksi Strava',
            'value' => number_format($summary['strava_connected'] ?? 0),
            'sub' => ($summary['connect_rate'] ?? 0) . '% karyawan terhubung',
            'icon' => 'solar:link-circle-bold',
            'bg' => 'bg-purple',
            'gradient' => 'bg-gradient-end-4',
        ],
        [
            'label' => 'Active Min vs Target',
            'value' => number_format($summary['avg_active_min'] ?? 0) . ' / ' . number_format($summary['avg_target_min'] ?? 0),
            'sub' => 'Menit/hari (skor ' . ($summary['avg_exercise_score'] ?? 0) . ')',
            'icon' => 'solar:clock-circle-bold',
            'bg' => 'bg-pink',
            'gradient' => 'bg-gradient-end-5',
        ],
    ];
@endphp
<div class="row gy-4">
    @foreach($cards as $card)
        <div class="col-xxl col-xl-4 col-sm-6">
            <div class="card p-3 shadow-2 radius-8 border input-form-light h-100 {{ $card['gradient'] }}">
                <div class="card-body p-0">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-1 mb-8">
                        <div class="d-flex align-items-center gap-2">
                            <span class="mb-0 w-48-px h-48-px {{ $card['bg'] }} flex-shrink-0 text-white d-flex justify-content-center align-items-center rounded-circle h6 mb-0">
                                <iconify-icon icon="{{ $card['icon'] }}" class="icon"></iconify-icon>
                            </span>
                            <div>
                                <span class="mb-2 fw-medium text-secondary-light text-sm">{{ $card['label'] }}</span>
                                <h6 class="fw-semibold mb-0">{{ $card['value'] }}</h6>
                            </div>
                        </div>
                    </div>
                    <p class="text-sm mb-0">{{ $card['sub'] }}</p>
                </div>
            </div>
        </div>
    @endforeach
</div>

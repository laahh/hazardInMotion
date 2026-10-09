@php
    $maxGoldenRule = max(1, ...(array_column($highlight['goldenRules'], 'count') ?: [0]));
    $blindspotPct = $highlight['blindspotTotal'] > 0
        ? round(($highlight['blindspotCount'] / $highlight['blindspotTotal']) * 100, 1)
        : 0;
@endphp

<div class="ocr-widget-charts">
    <div class="ocr-card">
        <div class="ocr-card-header">
            <div>
                <h6>Pareto Jam Laporan</h6>
                <p class="ocr-card-kicker">Garis putus 80% kumulatif</p>
            </div>
            <div class="ocr-seg" role="group" aria-label="Pilih shift Pareto">
                <button type="button" class="is-active" data-pareto="s1">S1</button>
                <button type="button" data-pareto="s2">S2</button>
            </div>
        </div>
        <div class="ocr-card-body"><div id="chart-pareto"></div></div>
    </div>

    <div class="ocr-card">
        <div class="ocr-card-header">
            <div>
                <h6>Highlight Temuan</h6>
                <p class="ocr-card-kicker">Golden Rule · Blindspot · TBC</p>
            </div>
        </div>
        <div class="ocr-card-body">
            <div class="ocr-gr-list">
                @forelse ($highlight['goldenRules'] as $gr)
                    <button type="button" class="ocr-gr-row" data-highlight-kind="golden_rule" data-highlight-name="{{ $gr['name'] }}" aria-haspopup="dialog" aria-controls="ocr-highlight-modal">
                        <span>{{ $gr['name'] }}</span>
                        <strong>{{ $gr['count'] }}</strong>
                        <div class="ocr-track is-primary"><span style="width: {{ ($gr['count'] / $maxGoldenRule) * 100 }}%"></span></div>
                    </button>
                @empty
                    <p class="text-secondary-light mb-0">Tidak ada nama Golden Rule pada laporan minggu ini.</p>
                @endforelse
            </div>
            <!-- <div class="ocr-highlight-metrics">
                <button type="button" class="ocr-metric-mini is-clickable" data-highlight-kind="blindspot" aria-haspopup="dialog" aria-controls="ocr-highlight-modal">
                    <p class="ocr-kpi-label">Blindspot</p>
                    <p class="ocr-kpi-value">{{ $highlight['blindspotCount'] }} <span class="fs-6 fw-normal text-secondary-light">/ {{ $highlight['blindspotTotal'] }}</span></p>
                    <div class="ocr-track is-danger"><span style="width: {{ $blindspotPct }}%"></span></div>
                </button>
                <button type="button" class="ocr-metric-mini is-clickable" data-highlight-kind="tbc" aria-haspopup="dialog" aria-controls="ocr-highlight-modal">
                    <p class="ocr-kpi-label">Ratio TBC</p>
                    <p class="ocr-kpi-value">{{ $highlight['tbcPercentage'] === null ? '—' : number_format($highlight['tbcPercentage'], 1).'%' }}</p>
                    <div class="ocr-track is-warning"><span style="width: {{ min(100, $highlight['tbcPercentage'] ?? 0) }}%"></span></div>
                </button>
            </div> -->
    </div>
</div>
    </div>

<div class="row gy-4">
    <div class="col-lg-7">
        <div class="ocr-card h-100">
            <div class="ocr-card-header">
                <div>
                    <h6>Kualitas Temuan per Personil</h6>
                    <p class="ocr-card-kicker">Total = jumlah kartu SAP unik seperti di modal Detail (satu laporan = satu), digabung dari semua hari jaga tanpa dihitung dua kali. Kategori = sub ketidaksesuaian · Variasi = kategori unik / jumlah laporan unik. TBC = valid TBC ÷ Hazard+Inspeksi.</p>
                </div>
            </div>
            <div class="ocr-card-body ocr-card-body--flush">
    <div class="table-responsive">
                    <table class="ocr-table">
            <thead>
                <tr>
                    <th>Nama</th>
                                <th class="text-center">Total</th>
                                <th class="text-center">Kategori</th>
                                <th>Variasi</th>
                                <th class="text-center">TBC</th>
                                <th class="text-center">GR</th>
                                <th class="text-center">Blindspot</th>
                </tr>
            </thead>
            <tbody>
                            @forelse ($quality as $row)
                                <tr>
                        <td>{{ $row['name'] }}</td>
                                    <td class="text-center">{{ $row['total_findings'] }}</td>
                                    <td class="text-center">{{ $row['distinct_categories'] }}</td>
                                    <td>
                                        @if ($row['variety_score'] === null)
                                            <span class="text-secondary-light">—</span>
                                        @else
                                            <div class="ocr-variety">
                                                <div class="ocr-track is-primary"><span style="width: {{ $row['variety_score'] * 100 }}%"></span></div>
                                                <span>{{ number_format((float) $row['variety_score'], 2) }}</span>
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if ($row['tbc'] === null || (int) ($row['tbc_basis'] ?? -1) === 0)
                                            —
                                        @elseif (array_key_exists('tbc_basis', $row) && $row['tbc_basis'] !== null)
                                            {{ $row['tbc'] }}/{{ $row['tbc_basis'] }}
                                        @else
                                            {{ $row['tbc'] }}
                                        @endif
                                    </td>
                                    <td class="text-center">{{ $row['gr'] === null ? '—' : $row['gr'] }}</td>
                                    <td class="text-center">{{ $row['blindspot'] === null ? '—' : $row['blindspot'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-secondary-light">{{ $qualityEmpty ?? 'Belum ada personil jadwal pada minggu yang dipilih.' }}</td>
                    </tr>
                            @endforelse
            </tbody>
        </table>
    </div>
</div>
    </div>
    </div>
    <div class="col-lg-5">
        <div class="ocr-card h-100">
            <div class="ocr-card-header">
                <div>
                    <h6>Volume vs Variasi</h6>
                    <p class="ocr-card-kicker">Kanan-atas = banyak temuan saat jaga &amp; sub ketidaksesuaian beragam</p>
                </div>
            </div>
            <div class="ocr-card-body"><div id="chart-quality-scatter"></div></div>
</div>
    </div>
</div>

{{-- Metrik Wellness: Durasi / Intensitas / Frekuensi / Kalori / Makronutrien --}}
<div class="col-12">
  <div class="row gy-4">
    <div class="col-xxl col-lg-4 col-sm-6">
      <div class="card p-3 shadow-2 radius-8 border input-form-light h-100 bg-gradient-end-1" id="wellness-durasi-card">
        <div class="card-body p-0">
          <div class="d-flex flex-wrap align-items-center justify-content-between gap-1 mb-8">
            <div class="d-flex align-items-center gap-2">
              <span class="mb-0 w-48-px h-48-px bg-primary-600 text-white flex-shrink-0 d-flex justify-content-center align-items-center rounded-circle h6">
                <iconify-icon icon="mdi:timer-outline" class="icon"></iconify-icon>
              </span>
              <div>
                <span class="mb-2 fw-medium text-secondary-light text-sm">Durasi</span>
                <h6 class="fw-semibold mb-0" id="wellness-kpi-durasi">{{ number_format($wellnessDurasiTotal ?? 0, 1) }} <span class="text-sm fw-medium text-secondary-light">menit</span></h6>
              </div>
            </div>
          </div>
          <p class="text-sm mb-0">Increase by <span class="bg-success-focus px-1 rounded-2 fw-medium text-success-main text-sm" id="wellness-kpi-durasi-inc">+{{ number_format($wellnessDurasiIncrease ?? 0, 1) }} ({{ number_format($wellnessDurasiIncreasePercent ?? 0, 1) }}%)</span> this week</p>
        </div>
      </div>
    </div>

    <div class="col-xxl col-lg-4 col-sm-6">
      <div class="card p-3 shadow-2 radius-8 border input-form-light h-100 bg-gradient-end-2" id="wellness-intensitas-card">
        <div class="card-body p-0">
          <div class="d-flex flex-wrap align-items-center justify-content-between gap-1 mb-8">
            <div class="d-flex align-items-center gap-2">
              <span class="mb-0 w-48-px h-48-px bg-success-main text-white flex-shrink-0 d-flex justify-content-center align-items-center rounded-circle h6">
                <iconify-icon icon="mdi:heart-pulse" class="icon"></iconify-icon>
              </span>
              <div>
                <span class="mb-2 fw-medium text-secondary-light text-sm">Intensitas</span>
                <h6 class="fw-semibold mb-0" id="wellness-kpi-intensitas">{{ number_format($wellnessIntensitasAvgHr ?? 0, 1) }} <span class="text-sm fw-medium text-secondary-light">bpm</span></h6>
              </div>
            </div>
          </div>
          <p class="text-xs text-secondary-light mb-4" id="wellness-kpi-intensitas-dist">
            Low {{ number_format($wellnessIntensitasLow ?? 0) }} · Med {{ number_format($wellnessIntensitasMed ?? 0) }} · High {{ number_format($wellnessIntensitasHigh ?? 0) }}
          </p>
          <p class="text-sm mb-0">Increase by <span class="bg-success-focus px-1 rounded-2 fw-medium text-success-main text-sm" id="wellness-kpi-intensitas-inc">+{{ number_format($wellnessIntensitasIncrease ?? 0, 1) }} ({{ number_format($wellnessIntensitasIncreasePercent ?? 0, 1) }}%)</span> this week</p>
        </div>
      </div>
    </div>

    <div class="col-xxl col-lg-4 col-sm-6">
      <div class="card p-3 shadow-2 radius-8 border input-form-light h-100 bg-gradient-end-3" id="wellness-frekuensi-card">
        <div class="card-body p-0">
          <div class="d-flex flex-wrap align-items-center justify-content-between gap-1 mb-8">
            <div class="d-flex align-items-center gap-2">
              <span class="mb-0 w-48-px h-48-px bg-yellow text-white flex-shrink-0 d-flex justify-content-center align-items-center rounded-circle h6">
                <iconify-icon icon="mdi:repeat" class="icon"></iconify-icon>
              </span>
              <div>
                <span class="mb-2 fw-medium text-secondary-light text-sm">Frekuensi</span>
                <h6 class="fw-semibold mb-0" id="wellness-kpi-frekuensi">{{ number_format($wellnessFrekuensiTotal ?? 0) }} <span class="text-sm fw-medium text-secondary-light">sesi</span></h6>
              </div>
            </div>
          </div>
          <p class="text-sm mb-0">Increase by <span class="bg-success-focus px-1 rounded-2 fw-medium text-success-main text-sm" id="wellness-kpi-frekuensi-inc">+{{ number_format($wellnessFrekuensiIncrease ?? 0) }} ({{ number_format($wellnessFrekuensiIncreasePercent ?? 0, 1) }}%)</span> this week</p>
        </div>
      </div>
    </div>

    <div class="col-xxl col-lg-4 col-sm-6">
      <div class="card p-3 shadow-2 radius-8 border input-form-light h-100 bg-gradient-end-4" id="wellness-kalori-card">
        <div class="card-body p-0">
          <div class="d-flex flex-wrap align-items-center justify-content-between gap-1 mb-8">
            <div class="d-flex align-items-center gap-2">
              <span class="mb-0 w-48-px h-48-px bg-purple text-white flex-shrink-0 d-flex justify-content-center align-items-center rounded-circle h6">
                <iconify-icon icon="mdi:fire" class="icon"></iconify-icon>
              </span>
              <div>
                <span class="mb-2 fw-medium text-secondary-light text-sm">Kalori</span>
                <h6 class="fw-semibold mb-0" id="wellness-kpi-kalori-out">{{ number_format($wellnessKaloriOut ?? 0, 1) }} <span class="text-sm fw-medium text-secondary-light">kkal out</span></h6>
                <span class="text-xs text-secondary-light" id="wellness-kpi-kalori-in">In {{ number_format($wellnessKaloriIn ?? 0, 1) }} kkal</span>
              </div>
            </div>
          </div>
          <p class="text-sm mb-0">Increase by <span class="bg-success-focus px-1 rounded-2 fw-medium text-success-main text-sm" id="wellness-kpi-kalori-inc">+{{ number_format($wellnessKaloriIncrease ?? 0, 1) }} ({{ number_format($wellnessKaloriIncreasePercent ?? 0, 1) }}%)</span> this week</p>
        </div>
      </div>
    </div>

    <div class="col-xxl col-lg-4 col-sm-6">
      <div class="card p-3 shadow-2 radius-8 border input-form-light h-100 bg-gradient-end-5" id="wellness-makro-card">
        <div class="card-body p-0">
          <div class="d-flex flex-wrap align-items-center justify-content-between gap-1 mb-8">
            <div class="d-flex align-items-center gap-2">
              <span class="mb-0 w-48-px h-48-px bg-pink text-white flex-shrink-0 d-flex justify-content-center align-items-center rounded-circle h6">
                <iconify-icon icon="mdi:food-apple" class="icon"></iconify-icon>
              </span>
              <div>
                <span class="mb-2 fw-medium text-secondary-light text-sm">Makronutrien</span>
                <h6 class="fw-semibold mb-0" id="wellness-kpi-makro-protein">{{ number_format($wellnessMakroProtein ?? 0, 1) }} <span class="text-sm fw-medium text-secondary-light">g protein</span></h6>
                <span class="text-xs text-secondary-light" id="wellness-kpi-makro-pcf">
                  Karbo {{ number_format($wellnessMakroCarbs ?? 0, 1) }} g · Lemak {{ number_format($wellnessMakroFats ?? 0, 1) }} g
                </span>
              </div>
            </div>
          </div>
          <p class="text-sm mb-0">Increase by <span class="bg-success-focus px-1 rounded-2 fw-medium text-success-main text-sm" id="wellness-kpi-makro-inc">+{{ number_format($wellnessMakroIncrease ?? 0, 1) }} ({{ number_format($wellnessMakroIncreasePercent ?? 0, 1) }}%)</span> this week</p>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Chart distribusi wellness (desain kartu insight) --}}
<div class="col-12">
  <div class="row gy-4" id="wellness-charts-section">
    {{-- Top 5 Olahraga --}}
    <div class="col-xxl-6 col-xl-6">
      <div class="card h-100 radius-12 border-0 shadow-sm wc-card wc-card--sports">
        <div class="card-body p-24 d-flex flex-column">
          <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-16">
            <div class="d-flex align-items-start gap-3 min-w-0">
              <span class="wc-card__head-icon" style="background:#14532D;color:#fff;">
                <iconify-icon icon="mdi:run"></iconify-icon>
              </span>
              <div class="min-w-0">
                <h6 class="mb-1 fw-bold text-lg" style="color:#0F172A;">Top 5 Olahraga</h6>
                <span class="text-sm d-block" style="color:#64748B;">Olahraga paling umum · % dari karyawan aktif</span>
              </div>
            </div>
            <span class="wc-card__badge" id="wellness-chart-top-sports-badge">
              <iconify-icon icon="mdi:account-group"></iconify-icon>
              <span>Total partisipasi olahraga –</span>
            </span>
          </div>
          <div id="wellness-chart-top-sports" class="wc-top-sports flex-grow-1" style="min-height: 260px;"></div>
          <p id="wellness-chart-top-sports-empty" class="text-secondary-light text-sm mb-0 text-center py-40 d-none">Belum ada data olahraga minggu ini.</p>
          <div class="wc-axis-label mt-8">Jumlah Karyawan</div>
        </div>
      </div>
    </div>

    {{-- Durasi Olahraga --}}
    <div class="col-xxl-6 col-xl-6">
      <div class="card h-100 radius-12 border-0 shadow-sm wc-card">
        <div class="card-body p-24 d-flex flex-column">
          <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-12">
            <div class="d-flex align-items-start gap-3 min-w-0">
              <span class="wc-card__head-icon" style="background:#16A34A;color:#fff;">
                <iconify-icon icon="mdi:clock-outline"></iconify-icon>
              </span>
              <div class="min-w-0">
                <h6 class="mb-1 fw-bold text-lg" style="color:#0F172A;">Durasi Olahraga</h6>
                <span class="text-sm d-block" style="color:#64748B;">Menit/minggu (Minggu–Sabtu)</span>
              </div>
            </div>
            <span class="wc-card__badge" id="wellness-chart-duration-badge">
              <iconify-icon icon="mdi:account-group"></iconify-icon>
              <span>Total Karyawan –</span>
            </span>
          </div>
          <div class="row align-items-center g-3 flex-grow-1">
            <div class="col-sm-6">
              <div id="wellness-chart-duration" style="min-height: 220px;"></div>
            </div>
            <div class="col-sm-6">
              <ul class="list-unstyled mb-0 wc-legend" id="wellness-chart-duration-legend"></ul>
            </div>
          </div>
          <div class="wc-tip wc-tip--green mt-16">
            <iconify-icon icon="mdi:run" class="flex-shrink-0"></iconify-icon>
            <span>Yuk, luangkan waktu minimal 150 menit aktivitas fisik setiap minggu untuk hidup lebih sehat!</span>
          </div>
        </div>
      </div>
    </div>

    {{-- Frekuensi Olahraga --}}
    <div class="col-xxl-6 col-xl-6">
      <div class="card h-100 radius-12 border-0 shadow-sm wc-card wc-card--freq">
        <div class="card-body p-24 d-flex flex-column">
          <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-12">
            <div class="d-flex align-items-start gap-3 min-w-0">
              <span class="wc-card__head-icon" style="background:#16A34A;color:#fff;">
                <iconify-icon icon="mdi:calendar-month-outline"></iconify-icon>
              </span>
              <div class="min-w-0">
                <h6 class="mb-1 fw-bold text-lg" style="color:#0F172A;">Frekuensi Olahraga</h6>
                <span class="text-sm d-block" style="color:#64748B;">Hari unik workout / minggu</span>
              </div>
            </div>
            <span class="wc-card__badge" id="wellness-chart-frequency-badge">
              <iconify-icon icon="mdi:account-group"></iconify-icon>
              <span>Total Karyawan –</span>
            </span>
          </div>
          <div class="row align-items-center g-3 flex-grow-1">
            <div class="col-sm-6">
              <div id="wellness-chart-frequency" style="min-height: 220px;"></div>
            </div>
            <div class="col-sm-6">
              <ul class="list-unstyled mb-0 wc-legend" id="wellness-chart-frequency-legend"></ul>
            </div>
          </div>
          <div class="wc-tip wc-tip--slate mt-16">
            <iconify-icon icon="mdi:format-quote-close" class="flex-shrink-0" style="color:#16A34A;"></iconify-icon>
            <span>Konsistensi kecil setiap hari, membawa perubahan besar.</span>
          </div>
        </div>
      </div>
    </div>

    {{-- Kalori Makanan --}}
    <div class="col-xxl-6 col-xl-6">
      <div class="card h-100 radius-12 border-0 shadow-sm wc-card wc-card--calorie">
        <div class="card-body p-24 d-flex flex-column">
          <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-12">
            <div class="d-flex align-items-start gap-3 min-w-0">
              <span class="wc-card__head-icon" style="background:#EA580C;color:#fff;">
                <iconify-icon icon="mdi:fire"></iconify-icon>
              </span>
              <div class="min-w-0">
                <h6 class="mb-1 fw-bold text-lg" style="color:#0F172A;">Kalori Makanan</h6>
                <span class="text-sm d-block" style="color:#64748B;">Rata-rata harian vs target</span>
              </div>
            </div>
            <span class="wc-card__badge" id="wellness-chart-calorie-badge">
              <iconify-icon icon="mdi:account-group"></iconify-icon>
              <span>Total Karyawan –</span>
            </span>
          </div>
          <div class="row align-items-center g-3 flex-grow-1">
            <div class="col-sm-6">
              <div id="wellness-chart-calorie" style="min-height: 220px;"></div>
            </div>
            <div class="col-sm-6">
              <ul class="list-unstyled mb-0 wc-legend" id="wellness-chart-calorie-legend"></ul>
            </div>
          </div>
          <div class="wc-tip wc-tip--rose mt-16">
            <iconify-icon icon="mdi:silverware-fork-knife" class="flex-shrink-0"></iconify-icon>
            <span>Penuhi kebutuhan kalori seimbang untuk energi dan performa yang lebih baik.</span>
          </div>
        </div>
      </div>
    </div>

    {{-- Makronutrien --}}
    <div class="col-12">
      <div class="card h-100 radius-12 border-0 shadow-sm wc-card wc-card--macro">
        <div class="card-body p-24">
          <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-16">
            <div class="d-flex align-items-start gap-3 min-w-0">
              <span class="wc-card__head-icon" style="background:#16A34A;color:#fff;">
                <iconify-icon icon="mdi:leaf"></iconify-icon>
              </span>
              <div class="min-w-0">
                <h6 class="mb-1 fw-bold text-lg" style="color:#0F172A;">Makronutrien</h6>
                <span class="text-sm d-block" style="color:#64748B;">% karyawan yang rata-rata harian memenuhi target</span>
              </div>
            </div>
            <div class="d-flex align-items-center flex-wrap gap-3" id="wellness-chart-macro-legend">
              <span class="d-inline-flex align-items-center gap-2 text-sm" style="color:#475569;">
                <span class="rounded-1" style="width:14px;height:14px;background:#16A34A;"></span>Memenuhi target
              </span>
              <span class="d-inline-flex align-items-center gap-2 text-sm" style="color:#475569;">
                <span class="rounded-1" style="width:14px;height:14px;background:#E2E8F0;"></span>Belum memenuhi target
              </span>
            </div>
          </div>
          <div class="row g-4 align-items-stretch">
            <div class="col-lg-8">
              <div id="wellness-chart-macro" class="wc-macro-chart" style="min-height: 280px;"></div>
              <div class="wc-axis-label mt-8">% Karyawan</div>
            </div>
            <div class="col-lg-4">
              <div class="wc-insight h-100">
                <div class="d-flex align-items-center gap-2 mb-10">
                  <span class="wc-insight__icon">
                    <iconify-icon icon="mdi:chart-bar"></iconify-icon>
                  </span>
                  <h6 class="mb-0 fw-bold" style="color:#0F172A;">Insight</h6>
                </div>
                <p class="mb-0 text-sm lh-base" style="color:#475569;" id="wellness-chart-macro-insight">
                  Sebagian besar karyawan belum memenuhi target makronutrien harian. Dorong kebiasaan makan seimbang agar asupan protein, karbohidrat, lemak, dan serat lebih optimal.
                </p>
                <p class="mb-0 mt-16 fw-semibold text-sm" style="color:#16A34A;font-style:italic;">Better Nutrition · Brighter Tomorrow</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="col-12">
  <div class="card radius-8 border-0 shadow-sm">
    <div class="card-header border-bottom bg-base py-16 px-24">
      <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
        <div>
          <div class="d-flex align-items-center gap-2 mb-4">
            <h6 class="text-lg fw-semibold mb-0">Detail Metrik Wellness</h6>
            <span id="wellness-total-badge" class="bg-primary-50 text-primary-600 text-sm fw-medium px-12 py-2 rounded-pill">{{ number_format($wellnessUserCount ?? 0) }}</span>
          </div>
          <p class="text-sm text-secondary-light mb-0">
            Per karyawan · minggu <span id="wellness-week-label">{{ $wellnessWeek['label'] ?? 'Minggu–Sabtu' }}</span>
            · Durasi dari workout · Intensitas dari avg HR (Low &lt;120 · Med 120–149 · High ≥150)
          </p>
        </div>
        <a id="wellness-export-btn"
           href="{{ ($ajaxRoutes['wellnessMetricsExport'] ?? route('evaluasi-well.wellness-metrics.export')) }}"
           class="btn btn-sm btn-success-600 d-inline-flex align-items-center gap-1">
          <iconify-icon icon="solar:file-download-bold" class="icon"></iconify-icon>
          Download Excel
        </a>
      </div>
    </div>
    <div class="card-body p-24">
      <div class="bg-neutral-50 border radius-8 p-16 mb-20">
        <div class="row g-3 align-items-end">
          <div class="col-xl-3 col-md-4 col-sm-6">
            <label for="wellness-week" class="form-label text-sm fw-medium mb-6">Minggu (Minggu–Sabtu)</label>
            <select id="wellness-week" class="form-select form-select-sm">
              @forelse (($wellnessWeekOptions ?? []) as $opt)
                <option value="{{ $opt['start'] }}" @selected(($wellnessWeek['start'] ?? '') === $opt['start'])>{{ $opt['label'] }}</option>
              @empty
                <option value="">Minggu ini</option>
              @endforelse
            </select>
          </div>
          <div class="col-xl-2 col-md-4 col-sm-6">
            <label for="wellness-site" class="form-label text-sm fw-medium mb-6">Site</label>
            <select id="wellness-site" class="form-select form-select-sm">
              <option value="">Semua Site</option>
              @foreach (($wellnessSites ?? []) as $site)
                <option value="{{ $site }}">{{ $site }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-xl-3 col-md-4 col-sm-6">
            <label for="wellness-company" class="form-label text-sm fw-medium mb-6">Perusahaan</label>
            <select id="wellness-company" class="form-select form-select-sm">
              <option value="">Semua Perusahaan</option>
              @foreach (($wellnessCompanies ?? []) as $company)
                <option value="{{ $company }}">{{ $company }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-xl-4 col-md-12 d-flex flex-wrap gap-2">
            <button type="button" id="wellness-apply-btn" class="btn btn-sm btn-primary-600 radius-8">Terapkan</button>
            <button type="button" id="wellness-reset-btn" class="btn btn-sm btn-outline-secondary-600 radius-8">Reset</button>
          </div>
        </div>
      </div>

      <div class="table-responsive scroll-sm">
        <table class="table bordered-table mb-0 wellness-metrics-datatable" id="wellnessMetricsTable" style="width:100%">
          <thead>
            <tr>
              <th>Nama</th>
              <th>Site</th>
              <th>Perusahaan</th>
              <th>Jabatan</th>
              <th>Durasi (menit)</th>
              <th>Avg HR</th>
              <th>Intensitas</th>
              <th>Frekuensi</th>
              <th>Kalori Out</th>
              <th>Kalori In</th>
              <th>Protein (g)</th>
              <th>Karbo (g)</th>
              <th>Lemak (g)</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

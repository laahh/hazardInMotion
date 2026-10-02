@php
  $rows = $rows ?? [];
  $attr = $attr ?? 'data-pvt-site';
  $selected = $selected ?? '';
  $totalCheckin = max(0, (int) ($totalCheckin ?? 0));
@endphp
<div class="card-body p-20 pt-8 pvt-breakdown-list scroll-sm">
  @forelse ($rows as $row)
    @php
      $name = (string) ($row['name'] ?? '-');
      $checkin = (int) ($row['checkin'] ?? 0);
      $lulus = (int) ($row['lulus'] ?? 0);
      $tidak = (int) ($row['tidak_lulus'] ?? 0);
      $belum = (int) ($row['belum_pvt'] ?? 0);
      $sudah = (int) ($row['sudah_pvt'] ?? 0);
      $pctPvt = (float) ($row['pct_pvt'] ?? 0);
      $pctLulusOfSudah = (float) ($row['pct_lulus'] ?? 0);
      $den = $checkin > 0 ? $checkin : 1;
      $wLulus = round($lulus / $den * 100, 2);
      $wTidak = round($tidak / $den * 100, 2);
      $wBelum = max(0, round(100 - $wLulus - $wTidak, 2));
      $share = $totalCheckin > 0 ? round($checkin / $totalCheckin * 100, 1) : 0.0;
      $pctClass = $pctPvt >= 75 ? 'text-success-600' : ($pctPvt >= 25 ? 'text-warning-600' : 'text-danger-600');
      $isSelected = $selected === $name;
    @endphp
    <div
      class="pvt-breakdown-row radius-8 px-12 py-12 {{ $isSelected ? 'is-selected' : '' }}"
      role="button"
      tabindex="0"
      {{ $attr }}="{{ $name }}"
      title="Klik untuk filter {{ $name }}"
    >
      <div class="d-flex align-items-start justify-content-between gap-2 mb-8">
        <div class="min-w-0">
          <div class="fw-semibold text-sm text-truncate">{{ $name }}</div>
          <div class="text-xs text-secondary-light">
            {{ number_format($checkin) }} masuk
            @if ($totalCheckin > 0)
              · {{ number_format($share, 1) }}% dari total
            @endif
          </div>
        </div>
        <div class="text-end flex-shrink-0">
          <div class="fw-bold text-sm {{ $pctClass }}">{{ number_format($pctPvt, 1) }}%</div>
          <div class="text-xs text-secondary-light">sudah PVT</div>
        </div>
      </div>

      <div class="pvt-stack-bar" role="img" aria-label="Komposisi PVT {{ $name }}">
        @if ($checkin > 0)
          <span class="pvt-stack-seg is-lulus" style="width: {{ $wLulus }}%"></span>
          <span class="pvt-stack-seg is-tidak" style="width: {{ $wTidak }}%"></span>
          <span class="pvt-stack-seg is-belum" style="width: {{ $wBelum }}%"></span>
        @else
          <span class="pvt-stack-seg is-empty" style="width: 100%"></span>
        @endif
      </div>

      <div class="d-flex flex-wrap align-items-center gap-2 mt-8 text-xs">
        <span class="pvt-mini-stat">
          <span class="pvt-dot is-lulus"></span>
          Lulus {{ number_format($lulus) }}
        </span>
        <span class="pvt-mini-stat">
          <span class="pvt-dot is-tidak"></span>
          Tidak lulus {{ number_format($tidak) }}
        </span>
        <span class="pvt-mini-stat">
          <span class="pvt-dot is-belum"></span>
          Belum {{ number_format($belum) }}
        </span>
        @if ($sudah > 0)
          <span class="ms-auto text-secondary-light">Kelulusan tes {{ number_format($pctLulusOfSudah, 0) }}%</span>
        @endif
      </div>
    </div>
  @empty
    <p class="text-secondary-light text-sm text-center py-24 mb-0">Belum ada check-in operator.</p>
  @endforelse
</div>

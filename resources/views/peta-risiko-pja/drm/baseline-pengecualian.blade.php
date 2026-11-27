@extends('peta-risiko-pja._layout-drm')

@section('title', 'Riwayat Pengecualian Baseline · Peta Risiko PJA')

@section('hero')
  <p class="pr-kicker"><span>Dashboard</span><span class="material-symbols-outlined tp-crumb-sep">chevron_right</span><span>Peta Risiko PJA</span><span class="material-symbols-outlined tp-crumb-sep">chevron_right</span><span class="tp-kini">Riwayat Pengecualian</span></p>
  <h1 class="pr-title">Riwayat Pengecualian Baseline</h1>
  <p class="pr-sub">Baris aktivitas &times; bahaya-risiko yang pernah dinonaktifkan dari kartu "Tidak Teridentifikasi (Open Risk)" dkk di Overview (langsung berlaku, tanpa menunggu <code>peta-risiko:sync</code>). Baris yang dikembalikan aktif tetap tercatat di sini sebagai riwayat.</p>
@endsection

@section('content')
<style>
  .bp-tbl { width: 100%; border-collapse: collapse; font-size: 13px; }
  .bp-tbl th { position: sticky; top: 0; background: #f8fafc; text-align: left; font-size: 10.5px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #595c5e; padding: 10px 12px; border-bottom: 1px solid rgba(171, 173, 175, .3); }
  .bp-tbl td { vertical-align: top; padding: 10px 12px; border-bottom: 1px solid rgba(171, 173, 175, .18); }
  .bp-tbl td.akt { min-width: 220px; }
  .bp-tbl td.akt small { display: block; color: #8a8d90; font-size: 11.5px; margin-top: 2px; }
  .bp-status { display: inline-block; padding: 2px 9px; border-radius: 999px; font-size: 11px; font-weight: 600; }
  .bp-status.aktif { background: #fdecef; color: #9b1c31; }
  .bp-status.kembali { background: #e9f7ee; color: #146c34; }
  .bp-scroll { max-height: 72vh; overflow: auto; }
  .bp-btn-kembali { border: 1px solid rgba(171, 173, 175, .45); background: #fff; color: #3952bc; font-size: 12px; font-weight: 600; padding: 5px 12px; border-radius: 999px; cursor: pointer; }
  .bp-btn-kembali:hover { background: #f1f4fd; }
  .bp-kosong { color: #b0b3b6; font-size: 12px; font-style: italic; }
</style>

<div class="pr-card" style="padding:0">
  <div class="bp-scroll">
    <table class="bp-tbl" style="width:100%">
      <thead><tr>
        <th>Aktivitas &middot; Bahaya</th><th>Kelompok</th><th>Alasan</th><th>Status</th><th>Dibuat</th><th>Dikembalikan</th>@if ($bolehUbah)<th></th>@endif
      </tr></thead>
      <tbody>
        @forelse ($riwayat as $r)
          <tr data-id="{{ $r->id }}">
            <td class="akt"><b>{{ $r->aktivitas !== '' ? $r->aktivitas : '(tanpa teks aktivitas)' }}</b>
              @if ($r->sub !== '')<small>{{ $r->sub }}</small>@endif
              <small>{{ $r->bahaya }}</small></td>
            <td>{{ $r->kel }}</td>
            <td>{{ $r->alasan }}</td>
            <td>
              @if ($r->aktif)
                <span class="bp-status aktif">Dikecualikan</span>
              @else
                <span class="bp-status kembali">Dikembalikan aktif</span>
              @endif
            </td>
            <td>{{ $r->dibuat_oleh ?: '—' }}<br><small class="bp-kosong">{{ $r->created_at }}</small></td>
            <td>
              @if ($r->dikembalikan_pada)
                {{ $r->dikembalikan_oleh ?: '—' }}<br><small class="bp-kosong">{{ $r->dikembalikan_pada }}</small>
              @else
                <span class="bp-kosong">—</span>
              @endif
            </td>
            @if ($bolehUbah)
              <td>
                @if ($r->aktif)
                  <button type="button" class="bp-btn-kembali" data-id="{{ $r->id }}">Kembalikan aktif</button>
                @endif
              </td>
            @endif
          </tr>
        @empty
          <tr><td colspan="7" class="pr-empty">Belum ada baris yang dinonaktifkan.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 24px;font-size:13px;color:#595c5e">
    <span>{{ number_format($riwayat->total(), 0, ',', '.') }} baris</span>
    <span style="display:flex;gap:8px">
      @if ($riwayat->previousPageUrl()) <a class="pr-back" href="{{ $riwayat->previousPageUrl() }}">&lsaquo; Sebelumnya</a> @endif
      @if ($riwayat->nextPageUrl()) <a class="pr-back" href="{{ $riwayat->nextPageUrl() }}">Berikutnya &rsaquo;</a> @endif
    </span>
  </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function () {
  const toast = Swal.mixin({ toast: true, position: 'top-end', timer: 2200, showConfirmButton: false });
  const CSRF = document.querySelector('meta[name="csrf-token"]').content;
  const URL_KEMBALIKAN = @json(route('peta-risiko-pja.baseline-pengecualian.kembalikan', ['id' => '__ID__']));

  document.querySelectorAll('.bp-btn-kembali').forEach((btn) => btn.addEventListener('click', () => {
    if (!confirm('Kembalikan baris ini jadi aktif (dihitung lagi di Open Risk setelah sync berikutnya)?')) return;
    btn.disabled = true;
    fetch(URL_KEMBALIKAN.replace('__ID__', btn.dataset.id), {
      method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF },
    })
      .then((r) => r.json().then((j) => (r.ok ? j : Promise.reject(j))))
      .then((j) => { toast.fire({ icon: 'success', title: j.pesan }); setTimeout(() => location.reload(), 900); })
      .catch((j) => { toast.fire({ icon: 'error', title: (j && j.pesan) || 'Gagal, coba lagi.' }); btn.disabled = false; });
  }));
})();
</script>
@endsection

@extends('dms.layouts.app')

@section('title', 'Edit Roster — '.$upload->judul)

@section('css')
@include('dms.roster-master._grid-css')
<style>
  /* ── Palet kode (kuas) ── */
  .re-alat { position: sticky; top: 0; z-index: 6; background: #fff; border-bottom: 1px solid #EEF2F7; padding: 14px 24px; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; }
  .re-palet { display: flex; flex-wrap: wrap; gap: 4px; align-items: center; }
  .re-palet__label { font-size: 12px; font-weight: 600; color: #475569; margin-right: 4px; }
  .re-kuas { border: 1.5px solid transparent; border-radius: 7px; min-width: 36px; height: 26px; padding: 0 6px; font-size: 11px; font-weight: 700; cursor: pointer; }
  .re-kuas.is-aktif { border-color: #0F172A; box-shadow: 0 0 0 2px #fff inset; }
  .re-kuas:focus-visible { outline: 2px solid #4F46E5; outline-offset: 1px; }
  .re-kuas.rm-kosong { background: #fff; border: 1.5px dashed #CBD5E1; color: #64748B; }
  .re-kuas.rm-kosong.is-aktif { border-style: solid; border-color: #0F172A; }
  .re-simpan { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
  .re-hitung { font-size: 12px; color: #64748B; }
  .re-hitung b { color: #4338CA; }

  /* ── Sel yang bisa diedit ── */
  .rm-xl button.rm-sel { border: 0; width: 100%; cursor: pointer; display: block; }
  .rm-xl button.rm-sel.rm-kosong { border: 1px dashed #E2E8F0; }
  .rm-xl button.rm-sel:hover { filter: brightness(.96); box-shadow: inset 0 0 0 1px #94A3B8; }
  .rm-xl button.rm-sel:focus-visible { outline: 2px solid #4F46E5; outline-offset: 1px; }
  .rm-xl .is-ubah { box-shadow: inset 0 0 0 2px #4F46E5 !important; }
  .re-melukis, .re-melukis * { user-select: none; }
  .re-jam { width: 74px; height: 26px; padding: 0 4px; font-size: 12px; border: 1px solid #E2E8F0; border-radius: 6px; text-align: center; background: #fff; }
  .re-jam.re-total { width: 54px; }
  .re-jam.is-ubah { border-color: #4F46E5; box-shadow: 0 0 0 1px #4F46E5; }
  .re-pesan { margin: 0 24px 16px; }
  .re-riwayat td, .re-riwayat th { font-size: 12.5px; }
  @media (max-width: 575.98px) { .re-alat { padding: 12px; } .re-simpan, .re-simpan .btn { width: 100%; } }
</style>
@endsection

@section('content')
@php
  $badge = ['PENDING' => ['Menunggu approval', 'warning'], 'APPROVED' => ['Disetujui', 'success']];
  [$label, $warna] = $badge[$upload->status] ?? [$upload->status, 'secondary'];
  $hariSingkat = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
  $bulanSingkat = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
  $nomorAwal = ($halaman - 1) * $perHalaman;
  $jamTeks = static fn (?float $j): string => $j === null ? '' : rtrim(rtrim(number_format($j, 2, '.', ''), '0'), '.');
@endphp

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <div>
    <h6 class="fw-semibold mb-0">Edit: {{ $upload->judul }}</h6>
    <p class="text-sm text-secondary-light mb-0 mt-4">
      <span class="badge bg-{{ $warna }}-focus text-{{ $warna }}-main">{{ $label }}</span>
      {{ $upload->periode_mulai->format('d/m/Y') }} – {{ $upload->periode_selesai->format('d/m/Y') }}
      &middot; perusahaan: {{ implode(', ', $assignment) }}
    </p>
  </div>
  <ul class="d-flex align-items-center gap-2">
    <li class="fw-medium"><a href="{{ route('dms.master-roster.edit.index') }}" class="hover-text-primary">Edit Roster</a></li>
    <li>-</li>
    <li class="fw-medium">Editor</li>
  </ul>
</div>

@include('dms.roster-master._alert')

<div class="card radius-8 border-0 shadow-sm" id="reEditor"
     data-url="{{ route('dms.master-roster.edit.update', $upload->id, absolute: false) }}">
  <div class="card-header border-bottom bg-base py-16 px-24">
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
      <div class="rm-kepala-teks">
        <h6 class="text-lg fw-semibold mb-4">Jadwal per karyawan</h6>
        <p class="text-sm text-secondary-light mb-0">
          Pilih kode di palet, lalu klik atau seret di sel tanggal. Jam Kerja Awal/Akhir/Total berlaku untuk semua hari kerja karyawan itu.
          Sel berbingkai ungu = belum disimpan.
        </p>
      </div>
      <form method="GET" action="{{ route('dms.master-roster.edit', $upload->id, absolute: false) }}" class="rm-cari" data-re-cari>
        <input type="search" name="q" value="{{ $cari }}" class="form-control form-control-sm" placeholder="Cari SID / nama… (Enter)" aria-label="Cari SID atau nama">
      </form>
    </div>
  </div>

  <div class="re-alat">
    <div class="re-palet" role="toolbar" aria-label="Pilih kode">
      <span class="re-palet__label">Kode:</span>
      @foreach (array_keys(\App\Services\Dms\Roster\DmsRosterMasterService::KODE) as $kode)
        <button type="button" class="re-kuas rm-{{ $kode }}" data-kuas="{{ $kode }}" title="{{ \App\Services\Dms\Roster\DmsRosterMasterService::KODE[$kode][0] }}">{{ $kode }}</button>
      @endforeach
      <button type="button" class="re-kuas rm-kosong" data-kuas="" title="Kosongkan (tidak dijadwalkan)">Kosong</button>
    </div>
    <div class="re-simpan">
      <span class="re-hitung" data-re-hitung>Belum ada perubahan</span>
      <button type="button" class="btn btn-sm btn-outline-secondary radius-8" data-re-batal disabled>Batalkan</button>
      <button type="button" class="btn btn-sm btn-primary-600 radius-8 d-inline-flex align-items-center gap-1" data-re-simpan disabled>
        <iconify-icon icon="solar:diskette-linear"></iconify-icon><span>Simpan perubahan</span>
      </button>
    </div>
  </div>
  <div class="re-pesan d-none mt-16" data-re-pesan role="status" aria-live="polite"></div>

  <div class="card-body rm-isi">
    <div class="table-responsive rm-gulir">
      <table class="rm-xl" data-re-tabel>
        <thead>
          <tr>
            <th rowspan="3" class="rm-h-id rm-s1 rm-hp-sembunyi">No</th>
            <th rowspan="3" class="rm-h-id rm-s2 rm-hp-sembunyi">SID</th>
            <th rowspan="3" class="rm-h-id rm-s3">Nama Karyawan</th>
            <th rowspan="3" class="rm-h-id rm-opsional rm-w-pt">Perusahaan</th>
            <th rowspan="3" class="rm-h-id rm-opsional rm-w-jab">Jabatan</th>
            <th rowspan="3" class="rm-h-id rm-opsional rm-w-kecil">Site</th>
            <th rowspan="3" class="rm-h-id rm-w-kecil">Jam Kerja Awal</th>
            <th rowspan="3" class="rm-h-id rm-w-kecil">Jam Kerja Akhir</th>
            <th rowspan="3" class="rm-h-id rm-w-kecil">Total Jam Kerja</th>
            @foreach ($tanggal as $t)
              <th class="rm-h-tgl {{ $t->dayOfWeek === 0 ? 'rm-minggu' : '' }} {{ $t->day === 1 && ! $loop->first ? 'rm-bulan' : '' }}" title="{{ $t->format('d/m/Y') }}">{{ $t->format('d') }}</th>
            @endforeach
          </tr>
          <tr>
            @foreach ($tanggal as $t)
              <th class="rm-h-hari {{ $t->dayOfWeek === 0 ? 'rm-minggu' : '' }} {{ $t->day === 1 && ! $loop->first ? 'rm-bulan' : '' }}">{{ $hariSingkat[$t->dayOfWeek] }}</th>
            @endforeach
          </tr>
          <tr>
            @foreach ($tanggal as $t)
              <th class="rm-h-bln {{ $t->dayOfWeek === 0 ? 'rm-minggu' : '' }} {{ $t->day === 1 && ! $loop->first ? 'rm-bulan' : '' }}">{{ $bulanSingkat[$t->month] }}</th>
            @endforeach
          </tr>
        </thead>
        <tbody>
          @forelse ($grid['karyawan'] as $k)
            <tr data-re-sid="{{ $k['sid'] }}">
              <td class="rm-c-id rm-c-no rm-s1 rm-hp-sembunyi">{{ $nomorAwal + $loop->iteration }}</td>
              <td class="rm-c-id rm-c-sid rm-s2 rm-hp-sembunyi">{{ $k['sid'] }}</td>
              <td class="rm-c-id rm-c-nama rm-s3">
                <span class="rm-nama" title="{{ $k['nama'] }}">{{ $k['nama'] ?? '—' }}</span>
                <span class="rm-ringkas"><span class="rm-ringkas-sid">{{ $k['sid'] }} &middot; </span>{{ $k['jabatan'] ?? '—' }} &middot; {{ $k['site'] ?? '—' }}</span>
              </td>
              <td class="rm-c-id rm-opsional"><span class="rm-potong rm-w-pt" title="{{ $k['perusahaan'] }}">{{ $k['perusahaan'] ?? '—' }}</span></td>
              <td class="rm-c-id rm-opsional"><span class="rm-potong rm-w-jab" title="{{ $k['jabatan'] }}">{{ $k['jabatan'] ?? '—' }}</span></td>
              <td class="rm-c-id rm-opsional">{{ $k['site'] ?? '—' }}</td>
              <td class="rm-c-id rm-c-jam"><input type="time" class="re-jam" data-re-jam="masuk" value="{{ $k['jam_awal'] ?? '' }}" data-asli="{{ $k['jam_awal'] ?? '' }}" aria-label="Jam kerja awal {{ $k['nama'] }}"></td>
              <td class="rm-c-id rm-c-jam"><input type="time" class="re-jam" data-re-jam="keluar" value="{{ $k['jam_akhir'] ?? '' }}" data-asli="{{ $k['jam_akhir'] ?? '' }}" aria-label="Jam kerja akhir {{ $k['nama'] }}"></td>
              @php $total = $k['jam_awal'] ? $jamTeks($k['jam_total']) : ''; @endphp
              <td class="rm-c-id rm-c-jam"><input type="text" inputmode="decimal" class="re-jam re-total" data-re-jam="total" value="{{ $total }}" data-asli="{{ $total }}" placeholder="{{ $k['jam_awal'] ? '' : $jamTeks($k['jam_total']) }}" aria-label="Total jam kerja {{ $k['nama'] }}" title="Jam kerja efektif per hari; kosong = Akhir − Awal"></td>
              @foreach ($tanggal as $t)
                @php $kode = $k['jadwal'][$t->toDateString()]['kode'] ?? ''; @endphp
                <td class="rm-c-kode {{ $t->day === 1 && ! $loop->first ? 'rm-bulan' : '' }}"><button type="button" class="rm-sel {{ $kode === '' ? 'rm-kosong' : (isset(\App\Services\Dms\Roster\DmsRosterMasterService::KODE[$kode]) ? 'rm-'.$kode : 'rm-lain') }}" data-tgl="{{ $t->toDateString() }}" data-asli="{{ $kode }}" title="{{ $t->format('d/m/Y') }}">{{ $kode }}</button></td>
              @endforeach
            </tr>
          @empty
            <tr><td colspan="{{ count($tanggal) + 9 }}" class="text-secondary-light py-16">Tidak ada karyawan perusahaan Anda yang cocok di roster ini.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mt-16">
      <span class="text-sm text-secondary-light">{{ number_format($grid['total'], 0, ',', '.') }} karyawan · hal {{ $halaman }}/{{ $totalHalaman }} · simpan dulu sebelum pindah halaman</span>
      <div class="d-flex gap-2 rm-hal">
        <a class="btn btn-sm btn-outline-primary-600 radius-8 {{ $halaman <= 1 ? 'disabled' : '' }}"
           href="{{ request()->fullUrlWithQuery(['hal' => $halaman - 1]) }}">&laquo; Sebelumnya</a>
        <a class="btn btn-sm btn-outline-primary-600 radius-8 {{ $halaman >= $totalHalaman ? 'disabled' : '' }}"
           href="{{ request()->fullUrlWithQuery(['hal' => $halaman + 1]) }}">Berikutnya &raquo;</a>
      </div>
    </div>
  </div>
</div>

<div class="card radius-8 border-0 shadow-sm mt-24">
  <div class="card-header border-bottom bg-base py-16 px-24">
    <h6 class="text-lg fw-semibold mb-4">Riwayat edit</h6>
    <p class="text-sm text-secondary-light mb-0">30 perubahan terakhir di roster ini.</p>
  </div>
  <div class="card-body p-24">
    @if (! $adaLog)
      <span class="text-sm text-secondary-light">Riwayat edit belum aktif — tabel <code>dms_roster_master_edit_log</code> belum dimigrasi. Perubahan tetap tersimpan.</span>
    @elseif ($riwayat === [])
      <span class="text-sm text-secondary-light">Belum ada perubahan.</span>
    @else
      <div class="table-responsive">
        <table class="table bordered-table mb-0 re-riwayat">
          <thead><tr><th>Waktu</th><th>Oleh</th><th>SID</th><th>Tanggal</th><th>Perubahan</th></tr></thead>
          <tbody>
            @foreach ($riwayat as $r)
              <tr>
                <td class="text-nowrap">{{ \Carbon\CarbonImmutable::parse($r->created_at)->format('d/m/Y H:i') }}</td>
                <td>{{ $r->diubah_oleh }}</td>
                <td>{{ $r->kode_sid }}</td>
                <td class="text-nowrap">{{ $r->tanggal ? \Carbon\CarbonImmutable::parse($r->tanggal)->format('d/m/Y') : 'jam kerja' }}</td>
                <td>{{ $r->nilai_lama ?? 'kosong' }} → <b>{{ $r->nilai_baru ?? 'kosong' }}</b></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>
</div>
@endsection

@section('page-scripts')
<script>
// Editor roster: palet kode sebagai kuas (klik/seret di sel), jam per
// karyawan, lalu simpan semua perubahan sekaligus (JSON).
(function () {
    'use strict';

    var editor = document.getElementById('reEditor');
    if (!editor) { return; }

    var KODE = @json(array_keys(\App\Services\Dms\Roster\DmsRosterMasterService::KODE));
    var tabel = editor.querySelector('[data-re-tabel]');
    var tombolSimpan = editor.querySelector('[data-re-simpan]');
    var tombolBatal = editor.querySelector('[data-re-batal]');
    var hitung = editor.querySelector('[data-re-hitung]');
    var pesan = editor.querySelector('[data-re-pesan]');
    var kuas = null;
    var melukis = false;
    var PESAN_SIMPAN = 'dms-roster-edit-pesan';

    function kelasKode(kode) { return kode === '' ? 'rm-kosong' : (KODE.indexOf(kode) !== -1 ? 'rm-' + kode : 'rm-lain'); }

    function isi(sel, kode) {
        sel.className = 'rm-sel ' + kelasKode(kode) + (kode !== sel.dataset.asli ? ' is-ubah' : '');
        sel.textContent = kode;
        perbarui();
    }

    function selBerubah() { return tabel.querySelectorAll('button.rm-sel.is-ubah'); }
    function jamBerubah() { return tabel.querySelectorAll('.re-jam.is-ubah'); }

    function perbarui() {
        var n = selBerubah().length;
        var nJam = 0;
        tabel.querySelectorAll('tr[data-re-sid]').forEach(function (tr) { if (tr.querySelector('.re-jam.is-ubah')) { nJam++; } });
        var ada = n + nJam > 0;
        hitung.innerHTML = ada ? '<b>' + n + '</b> sel' + (nJam ? ' · <b>' + nJam + '</b> jam karyawan' : '') + ' belum disimpan' : 'Belum ada perubahan';
        tombolSimpan.disabled = !ada;
        tombolBatal.disabled = !ada;
    }

    function tampilPesan(html, jenis) {
        pesan.className = 're-pesan mt-16 alert radius-8 px-16 py-12 text-sm mb-0 ' + (jenis === 'ok'
            ? 'alert-success bg-success-100 text-success-600 border-success-100'
            : 'alert-danger bg-danger-100 text-danger-600 border-danger-100');
        pesan.innerHTML = html;
    }

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // ── Palet: pilih kuas ──
    editor.querySelectorAll('[data-kuas]').forEach(function (b) {
        b.addEventListener('click', function () {
            var sama = kuas === b.dataset.kuas;
            kuas = sama ? null : b.dataset.kuas;
            editor.querySelectorAll('[data-kuas]').forEach(function (x) { x.classList.toggle('is-aktif', !sama && x === b); });
        });
    });

    // ── Melukis sel: klik, atau tekan & seret ──
    function selDari(target) { return target && target.closest ? target.closest('button.rm-sel[data-tgl]') : null; }
    tabel.addEventListener('mousedown', function (e) {
        var sel = selDari(e.target);
        if (!sel || kuas === null || e.button !== 0) { return; }
        e.preventDefault();
        melukis = true;
        document.body.classList.add('re-melukis');
        isi(sel, kuas);
    });
    tabel.addEventListener('mouseover', function (e) {
        var sel = selDari(e.target);
        if (melukis && sel) { isi(sel, kuas); }
    });
    document.addEventListener('mouseup', function () { melukis = false; document.body.classList.remove('re-melukis'); });
    // Keyboard & sentuh: klik biasa.
    tabel.addEventListener('click', function (e) {
        var sel = selDari(e.target);
        if (!sel) { return; }
        if (kuas === null) {
            tampilPesan('Pilih kode di palet dulu, lalu klik sel yang ingin diubah.', 'galat');
            return;
        }
        if (e.detail === 0) { isi(sel, kuas); }
    });
    tabel.addEventListener('touchstart', function (e) {
        var sel = selDari(e.target);
        if (sel && kuas !== null) { isi(sel, kuas); }
    }, { passive: true });

    // ── Jam per karyawan; Total ikut Akhir − Awal bila belum diisi sendiri ──
    function menit(j) { return j ? parseInt(j.slice(0, 2), 10) * 60 + parseInt(j.slice(3, 5), 10) : null; }
    tabel.querySelectorAll('.re-jam').forEach(function (inp) {
        inp.addEventListener('input', function () {
            var tr = inp.closest('tr');
            if (inp.dataset.reJam !== 'total') {
                var m = menit(tr.querySelector('[data-re-jam="masuk"]').value);
                var k = menit(tr.querySelector('[data-re-jam="keluar"]').value);
                var total = tr.querySelector('[data-re-jam="total"]');
                if (m !== null && k !== null && !total.dataset.manual) {
                    var d = k - m; if (d <= 0) { d += 1440; }
                    total.value = String(Math.round(d / 60 * 100) / 100);
                }
            } else {
                inp.dataset.manual = inp.value.trim() === '' ? '' : '1';
            }
            tr.querySelectorAll('.re-jam').forEach(function (x) { x.classList.toggle('is-ubah', x.value.trim() !== x.dataset.asli); });
            perbarui();
        });
    });

    // ── Batalkan & simpan ──
    tombolBatal.addEventListener('click', function () {
        selBerubah().forEach(function (sel) { isi(sel, sel.dataset.asli); });
        jamBerubah().forEach(function (inp) { inp.value = inp.dataset.asli; inp.classList.remove('is-ubah'); inp.dataset.manual = ''; });
        perbarui();
        pesan.classList.add('d-none');
    });

    tombolSimpan.addEventListener('click', function () {
        var sel = [];
        selBerubah().forEach(function (b) {
            sel.push({ sid: b.closest('tr').dataset.reSid, tanggal: b.dataset.tgl, kode: b.textContent.trim() });
        });
        var jam = [];
        tabel.querySelectorAll('tr[data-re-sid]').forEach(function (tr) {
            if (!tr.querySelector('.re-jam.is-ubah')) { return; }
            jam.push({
                sid: tr.dataset.reSid,
                masuk: tr.querySelector('[data-re-jam="masuk"]').value,
                keluar: tr.querySelector('[data-re-jam="keluar"]').value,
                total: tr.querySelector('[data-re-jam="total"]').value,
            });
        });

        tombolSimpan.disabled = true;
        tombolSimpan.querySelector('span').textContent = 'Menyimpan…';
        fetch(editor.dataset.url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ sel: sel, jam: jam }),
        })
            .then(function (r) { return r.json().catch(function () { return { ok: false, galat: ['Respons server tidak valid (' + r.status + ').'] }; }); })
            .then(function (d) {
                if (d.ok) {
                    try { sessionStorage.setItem(PESAN_SIMPAN, d.pesan); } catch (e) { /* tanpa storage: tetap muat ulang */ }
                    window.removeEventListener('beforeunload', cegahKeluar);
                    location.reload();
                    return;
                }
                var daftar = d.galat || (d.errors ? Object.values(d.errors).flat() : [d.message || 'Gagal menyimpan.']);
                tampilPesan('<b>Perubahan belum disimpan:</b><ul class="mb-0 ps-18 mt-4">' + daftar.map(function (g) { return '<li>' + esc(g) + '</li>'; }).join('') + '</ul>', 'galat');
            })
            .catch(function () { tampilPesan('Gagal terhubung ke server. Perubahan Anda masih ada di layar — coba simpan lagi.', 'galat'); })
            .finally(function () {
                tombolSimpan.querySelector('span').textContent = 'Simpan perubahan';
                perbarui();
            });
    });

    function cegahKeluar(e) {
        if (selBerubah().length + jamBerubah().length > 0) { e.preventDefault(); e.returnValue = ''; }
    }
    window.addEventListener('beforeunload', cegahKeluar);

    try {
        var tersimpan = sessionStorage.getItem(PESAN_SIMPAN);
        if (tersimpan) {
            sessionStorage.removeItem(PESAN_SIMPAN);
            tampilPesan(esc(tersimpan), 'ok');
        }
    } catch (e) { /* tanpa storage: tanpa pesan */ }
})();
</script>
@endsection

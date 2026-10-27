@extends('dms.layouts.app')

@section('title', 'Assign Role')

@section('css')
<style>
  .ar-label { font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 6px; display: block; }
  .ar-pt { max-height: 340px; overflow-y: auto; border: 1px solid #E2E8F0; border-radius: 10px; padding: 6px; }
  .ar-pt label { display: flex; align-items: center; gap: 10px; padding: 7px 10px; border-radius: 8px; font-size: 13px; color: #1E293B; cursor: pointer; margin: 0; }
  .ar-pt label:hover { background: #F1F5F9; }
  .ar-pt input:checked + span { font-weight: 600; color: #3730A3; }
  .ar-pt .ar-kosong { padding: 12px; font-size: 13px; color: #94A3B8; text-align: center; }
  .ar-chip { display: inline-block; font-size: 12px; font-weight: 500; background: #EEF2FF; color: #3730A3; border-radius: 999px; padding: 3px 10px; margin: 2px 4px 2px 0; }
  .ar-tabel td { font-size: 13px; vertical-align: top; }
  .ar-tabel th { font-size: 12px; color: #64748B; font-weight: 600; white-space: nowrap; }
  .ar-aturan { font-size: 13px; color: #475569; margin: 0; padding-left: 18px; }
  .ar-aturan li { margin-bottom: 4px; }
</style>
@endsection

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <h6 class="fw-semibold mb-0">Assign Role</h6>
  <ul class="d-flex align-items-center gap-2">
    <li class="fw-medium">
      <a href="{{ route('dms.index') }}" class="d-flex align-items-center gap-1 hover-text-primary">
        <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>DMS
      </a>
    </li>
    <li>-</li>
    <li class="fw-medium">Master Roster</li>
    <li>-</li>
    <li class="fw-medium">Assign Role</li>
  </ul>
</div>

@include('dms.roster-master._alert')

@if (! $siap)
  <div class="alert alert-warning bg-warning-100 text-warning-600 border-warning-100 px-24 py-13 mb-24 radius-8" role="alert">
    Tabel <code>dms_roster_master_akses</code> belum ada. Jalankan migrasi
    <code>2026_10_27_100000_create_dms_roster_master_akses_table.php</code> dulu.
    Sampai saat itu semua user tetap melihat semua roster di Approval.
  </div>
@endif

<div class="row g-4">
  {{-- ══════════ FORM ASSIGN ══════════ --}}
  <div class="col-xl-5">
    <form method="POST" action="{{ route('dms.master-roster.assign-role.store') }}" class="card radius-8 border-0 shadow-sm h-100" id="arForm">
      @csrf
      <div class="card-header border-bottom bg-base py-16 px-24">
        <h6 class="text-lg fw-semibold mb-4">Assign perusahaan ke user</h6>
        <p class="text-sm text-secondary-light mb-0">User hanya bisa melihat &amp; meng-approve roster perusahaan yang dipilih di sini.</p>
      </div>
      <div class="card-body p-24 d-flex flex-column gap-3">
        <div>
          <label class="ar-label" for="arUser">User</label>
          <select name="user_id" id="arUser" class="form-select" required>
            <option value="">— Pilih user —</option>
            @foreach ($users as $u)
              <option value="{{ $u->id }}" @selected((string) old('user_id') === (string) $u->id)>
                {{ $u->name }}{{ $u->email ? ' · '.$u->email : '' }}
              </option>
            @endforeach
          </select>
        </div>

        <div>
          <div class="d-flex align-items-center justify-content-between mb-6">
            <label class="ar-label mb-0">Perusahaan <span class="text-secondary-light fw-normal" data-ar-jumlah>(0 dipilih)</span></label>
            <button type="button" class="btn btn-link btn-sm p-0 text-primary-600" data-ar-bersih>Kosongkan</button>
          </div>
          <input type="search" class="form-control form-control-sm mb-8" placeholder="Cari perusahaan…" data-ar-cari-pt aria-label="Cari perusahaan">
          <div class="ar-pt" data-ar-pt>
            @forelse ($pilihanPt as $pt)
              <label data-ar-nama="{{ mb_strtolower($pt) }}">
                <input type="checkbox" class="form-check-input m-0" name="perusahaan[]" value="{{ $pt }}"
                       @checked(in_array($pt, (array) old('perusahaan', []), true))>
                <span>{{ $pt }}</span>
              </label>
            @empty
              <div class="ar-kosong">Daftar perusahaan belum tersedia. Coba lagi setelah sinkronisasi karyawan aktif berikutnya.</div>
            @endforelse
            <div class="ar-kosong d-none" data-ar-tidak-ada>Tidak ada perusahaan yang cocok.</div>
          </div>
        </div>

        <ul class="ar-aturan">
          <li>Menyimpan akan <b>mengganti</b> seluruh perusahaan user tersebut.</li>
          <li>Roster yang memuat beberapa perusahaan hanya bisa di-approve user yang di-assign ke <b>semua</b> perusahaannya.</li>
          <li>Admin tanpa assign tetap melihat semua roster. User biasa tanpa assign tidak melihat roster apa pun.</li>
        </ul>

        <div class="mt-auto d-flex gap-2">
          <button type="submit" class="btn btn-primary-600 radius-8 px-20" @disabled(! $siap)>Simpan</button>
          <button type="reset" class="btn btn-outline-secondary radius-8 px-20" data-ar-reset>Batal</button>
        </div>
      </div>
    </form>
  </div>

  {{-- ══════════ DAFTAR ASSIGNMENT ══════════ --}}
  <div class="col-xl-7">
    <div class="card radius-8 border-0 shadow-sm h-100">
      <div class="card-header border-bottom bg-base py-16 px-24">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
          <div>
            <h6 class="text-lg fw-semibold mb-4">User yang sudah di-assign</h6>
            <p class="text-sm text-secondary-light mb-0">{{ count($daftar) }} user</p>
          </div>
          <form method="GET" action="{{ route('dms.master-roster.assign-role') }}">
            <input type="search" name="q" value="{{ $cari }}" class="form-control form-control-sm" placeholder="Cari user / perusahaan… (Enter)" aria-label="Cari user atau perusahaan">
          </form>
        </div>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table mb-0 ar-tabel">
            <thead>
              <tr>
                <th class="ps-24">User</th>
                <th>Perusahaan</th>
                <th>Diubah</th>
                <th class="pe-24 text-end"></th>
              </tr>
            </thead>
            <tbody>
              @forelse ($daftar as $d)
                <tr>
                  <td class="ps-24">
                    <span class="fw-semibold text-primary-light">{{ $d['nama'] }}</span>
                    @if ($d['admin'])<span class="badge bg-primary-focus text-primary-main ms-1">admin</span>@endif
                    <span class="d-block text-xs text-secondary-light">{{ $d['email'] }}</span>
                  </td>
                  <td>
                    @foreach ($d['perusahaan'] as $pt)<span class="ar-chip">{{ $pt }}</span>@endforeach
                  </td>
                  <td class="text-nowrap text-xs text-secondary-light">
                    {{ $d['diubah'] ? \Carbon\CarbonImmutable::parse($d['diubah'])->format('d/m/Y H:i') : '—' }}
                    @if ($d['oleh'])<span class="d-block">oleh {{ $d['oleh'] }}</span>@endif
                  </td>
                  <td class="pe-24 text-end text-nowrap">
                    <button type="button" class="btn btn-sm btn-outline-primary-600 radius-8"
                            data-ar-ubah="{{ $d['user_id'] }}" data-ar-pt-user="{{ json_encode($d['perusahaan']) }}">Ubah</button>
                    <form action="{{ route('dms.master-roster.assign-role.destroy', $d['user_id']) }}" method="POST" class="d-inline"
                          onsubmit="return confirm('Hapus seluruh assign perusahaan {{ addslashes($d['nama']) }}?');">
                      @csrf
                      @method('DELETE')
                      <button type="submit" class="btn btn-sm btn-link text-danger-600">Hapus</button>
                    </form>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="4" class="text-center text-secondary-light py-24">
                    {{ $cari !== '' ? 'Tidak ada yang cocok dengan "'.$cari.'".' : 'Belum ada user yang di-assign.' }}
                  </td>
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
<script>
// Assign Role: cari perusahaan, hitung terpilih, tombol "Ubah" mengisi form dari baris tabel.
(function () {
    'use strict';
    var form = document.getElementById('arForm');
    if (!form) { return; }
    var user = document.getElementById('arUser');
    var kotak = function () { return form.querySelectorAll('[data-ar-pt] input[type=checkbox]'); };
    var jumlah = form.querySelector('[data-ar-jumlah]');
    var hitung = function () {
        var n = 0;
        kotak().forEach(function (c) { if (c.checked) { n++; } });
        jumlah.textContent = '(' + n + ' dipilih)';
    };

    form.querySelector('[data-ar-cari-pt]').addEventListener('input', function () {
        var q = this.value.trim().toLowerCase();
        var ada = 0;
        form.querySelectorAll('[data-ar-nama]').forEach(function (l) {
            var cocok = q === '' || l.dataset.arNama.indexOf(q) !== -1;
            l.classList.toggle('d-none', !cocok);
            if (cocok) { ada++; }
        });
        form.querySelector('[data-ar-tidak-ada]').classList.toggle('d-none', ada > 0);
    });
    form.addEventListener('change', hitung);
    form.querySelector('[data-ar-bersih]').addEventListener('click', function () {
        kotak().forEach(function (c) { c.checked = false; });
        hitung();
    });
    form.querySelector('[data-ar-reset]').addEventListener('click', function () { setTimeout(hitung, 0); });

    document.querySelectorAll('[data-ar-ubah]').forEach(function (b) {
        b.addEventListener('click', function () {
            var pilih = JSON.parse(b.dataset.arPtUser || '[]').map(function (p) { return p.toUpperCase(); });
            user.value = b.dataset.arUbah;
            kotak().forEach(function (c) { c.checked = pilih.indexOf(c.value.toUpperCase()) !== -1; });
            hitung();
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
    hitung();
})();
</script>
@endsection

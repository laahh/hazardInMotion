{{--
  Modal detail empat aturan "Penegakan Aturan & Alur Banned/Unban" PER HARI
  INI, dihitung mundur langsung dari scan RFID (bcsid.mv_checkinout_rfid),
  tidak lewat dms_roster_pola. Pembukanya adalah empat tile di kartu utama
  _roster-overview (data-bs-target="#roModalTemuan-{id}", data-ro-tab).

  Definisi aturan dari DmsRosterAturanKartu (dipakai bersama tile), angka dari
  $regTerkiniLive (DmsRosterRegLiveService::ambil()). Daftar nama dimuat lewat
  JSON saat modal dibuka, dan baris daftar membuka timeline tap RFID karyawan
  itu (public/dms-assets/roster-compliance/roster-temuan.js).
--}}
@php
    $temuanKini = $regTerkiniLive ?? null;
@endphp

@foreach (\App\Services\Dms\Roster\DmsRosterAturanKartu::daftar() as $kartu)
  @if ($temuanKini)
    <div class="modal fade" id="roModalTemuan-{{ $kartu['id'] }}" tabindex="-1" aria-labelledby="roModalTemuanJudul-{{ $kartu['id'] }}" aria-hidden="true"
         data-ro-timeline-url="{{ route('dms.roster-compliance.terkini.timeline', ['sid' => '__SID__']) }}">
      <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content radius-8">
          <div class="modal-header">
            <div>
              <h6 class="modal-title fw-bold" id="roModalTemuanJudul-{{ $kartu['id'] }}">
                {{ $kartu['judul'] }} {{ $kartu['ambang'] }}
                <span class="badge bg-{{ $kartu['warna'] }}-focus text-{{ $kartu['warna'] }}-main ms-1">{{ $kartu['perlakuan'] }}</span>
              </h6>
              <span class="text-secondary-light text-sm">
                Per {{ $temuanKini['per'] }} &middot; RFID diambil {{ $temuanKini['diambil'] }} &middot; {{ $kartu['catatan'] }}
              </span>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
          </div>
          <div class="modal-body">
            {{-- Tampilan detail satu karyawan (timeline RFID), diisi JS. --}}
            <div class="d-none" data-ro-view-detail></div>

            <div data-ro-view-daftar>
              <ul class="nav nav-pills gap-2 mb-16 {{ count($kartu['item']) === 1 ? 'd-none' : '' }}" role="tablist">
                @foreach ($kartu['item'] as $kode => [$label, $definisi, $kolomNilai, $kolomMulai, $kolomSelesai])
                  <li class="nav-item" role="presentation">
                    <button type="button" class="nav-link radius-8 px-16 py-8 text-sm" role="tab"
                            data-ro-tab-btn="{{ $kode }}"
                            data-ro-definisi="{{ $definisi }}"
                            data-ro-kolom="{{ json_encode([$kolomNilai, $kolomMulai, $kolomSelesai]) }}"
                            data-ro-url="{{ route('dms.roster-compliance.terkini.detail', ['kode' => $kode]) }}"
                            data-ro-unduh="{{ route('dms.roster-compliance.terkini.unduh', ['kode' => $kode]) }}">
                      {{ $label }}
                      <span class="badge bg-neutral-200 text-secondary-light ms-1">{{ number_format(count($temuanKini['daftar'][$kode]), 0, ',', '.') }}</span>
                    </button>
                  </li>
                @endforeach
              </ul>

              <p class="text-secondary-light text-sm mb-12" data-ro-definisi-teks></p>

              <div class="d-flex flex-wrap gap-2 justify-content-between mb-16">
                <input type="search" class="form-control form-control-sm w-auto" style="min-width:260px"
                       placeholder="Cari nama, SID, site, PT…" data-ro-cari>
                <a href="#" class="btn btn-sm btn-outline-danger radius-8 d-inline-flex align-items-center gap-1" data-ro-unduh-btn>
                  <iconify-icon icon="solar:file-download-bold" class="icon"></iconify-icon>
                  Unduh CSV
                </a>
              </div>

              <div class="table-responsive">
                <table class="table bordered-table sm-table mb-0">
                  <thead>
                    <tr>
                      <th>No</th>
                      <th>Nama</th>
                      <th>PT</th>
                      <th>Site</th>
                      <th class="text-end" data-ro-th="0">Nilai</th>
                      <th data-ro-th="1">Mulai</th>
                      <th data-ro-th="2">Selesai</th>
                      <th>Keterangan</th>
                      <th>Scan terakhir</th>
                    </tr>
                  </thead>
                  <tbody data-ro-tbody></tbody>
                </table>
                <p class="text-secondary-light text-sm text-center my-16 d-none" data-ro-pesan></p>
              </div>
              <p class="text-secondary-light text-xs mt-8 mb-0">Klik baris untuk melihat timeline tap RFID karyawan tersebut (gate, jam check-in/out, durasi per hari).</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  @endif
@endforeach

{{-- Modal: daftar per aturan (JSON) dan timeline tap RFID per karyawan. --}}
<script src="{{ asset('dms-assets/roster-compliance/roster-temuan.js') }}?v=4"></script>

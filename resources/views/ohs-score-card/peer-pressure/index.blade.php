@extends('ohs-score-card.layouts.app')

@section('title', 'Peer Pressure')

@php
  /**
   * Definisi tab. Header tabel ditulis di sini sekali; kolom DataTable-nya
   * didefinisikan di blok script dengan urutan yang sama persis.
   */
  $tabs = [
      'berecord' => [
          'label' => 'BeRecord',
          'icon' => 'solar:clipboard-list-outline',
          'sumber' => 'bcsid.mv_berecord (hse_automation)',
          'headers' => [
              'Kode SID', 'Nama Karyawan', 'Perusahaan', 'Site', 'Jabatan',
              'Kategori', 'Tipe', 'Golden Rules', 'Mulai', 'Selesai',
              'Status', 'Status Proses', 'Permit', 'Deskripsi',
          ],
      ],
      'speakup' => [
          'label' => 'Speak Up',
          'icon' => 'solar:user-speak-outline',
          'sumber' => 'speak_up_fatigue',
          'headers' => ['Tanggal', 'Waktu', 'Site', 'Perusahaan', 'SID', 'Nama'],
      ],
      'blindspot' => [
          'label' => 'Blindspot TBC',
          'icon' => 'solar:eye-closed-outline',
          'sumber' => 'validasi_tbc',
          'headers' => [
              'Tasklist', 'To Be Concerned Hazard', 'GR', 'Kategori GR',
              'No Item PSPP', 'Blindspot Terlapor BC', 'SID Pekerja', 'Nama Pekerja', 'Catatan',
          ],
      ],
      'pelaksanaan' => [
          'label' => 'Pelaksanaan Peer Pressure',
          'icon' => 'solar:users-group-two-rounded-outline',
          'sumber' => 'peer_pressure_kejadian_edukasi + peserta',
          'headers' => [
              'Tanggal Edukasi', 'Site', 'Perusahaan', 'Kategori Deviasi', 'Lokasi Edukasi',
              'Pemimpin Edukasi', 'Durasi', 'Status', 'Peserta', 'Pelanggar', 'Peer',
          ],
      ],
  ];
@endphp

@section('css')
<style>
  .pp-badge {
    display: inline-block; padding: 3px 10px; border-radius: 999px;
    font-size: 11px; font-weight: 700; line-height: 1.5; white-space: nowrap;
  }
  .pp-badge--ok    { background: #ECFDF5; color: #166534; border: 1px solid #BBF7D0; }
  .pp-badge--warn  { background: #FEFCE8; color: #854D0E; border: 1px solid #FDE68A; }
  .pp-badge--bad   { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; }
  .pp-badge--muted { background: #F1F5F9; color: #64748B; border: 1px solid #E2E8F0; }

  /* Kolom teks panjang dipotong; teks penuh ada di atribut title. */
  .pp-clip {
    display: block; max-width: 300px;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
  }
  .pp-name { font-weight: 600; color: #0F172A; }
  .pp-sub  { font-size: 11px; color: #64748B; display: block; }

  .pp-tabs .nav-link {
    border: 1px solid transparent; border-radius: 10px;
    font-weight: 600; font-size: 14px; color: #64748B;
    display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px;
  }
  .pp-tabs .nav-link:hover { color: #2563EB; background: #F8FAFC; }
  .pp-tabs .nav-link.active {
    color: #fff; background: #487FFF; border-color: #487FFF;
    box-shadow: 0 4px 12px rgba(72, 127, 255, 0.25);
  }
  .pp-tabs .nav-link .pp-count {
    font-size: 11px; font-weight: 700; padding: 1px 8px;
    border-radius: 999px; background: rgba(100, 116, 139, 0.14);
  }
  .pp-tabs .nav-link.active .pp-count { background: rgba(255, 255, 255, 0.28); }

  .pp-source { font-size: 12px; color: #94A3B8; }

  table.pp-table { width: 100% !important; }
  table.pp-table th, table.pp-table td { vertical-align: middle; white-space: nowrap; }
  table.pp-table thead th { font-weight: 600; }

  .dt-layout-row {
    display: flex; flex-wrap: wrap; align-items: center;
    justify-content: space-between; gap: 0.75rem; margin: 0.75rem 0;
  }
  .dt-paging {
    display: flex; flex-wrap: wrap; align-items: center;
    justify-content: flex-end; gap: 0.375rem;
  }
  .dt-paging .dt-paging-button {
    width: auto !important; min-width: 2rem; height: 2rem;
    padding: 0 0.625rem !important; display: inline-flex !important;
    align-items: center; justify-content: center;
    line-height: 1 !important; border-radius: 6px !important;
  }
  .dt-search input { margin-left: 0.5rem; min-width: 220px; width: auto; display: inline-block; }
  .dt-length select { margin: 0 0.375rem; width: auto; display: inline-block; }
  .dt-length label, .dt-search label {
    display: inline-flex; align-items: center; margin-bottom: 0;
    font-size: 0.875rem; font-weight: 500; color: var(--text-secondary-light);
  }
</style>
@endsection

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
  <div>
    <h6 class="fw-semibold mb-0">Peer Pressure</h6>
    <div class="text-secondary-light text-sm mt-4">
      beRecord, Speak Up, Blindspot TBC, dan pelaksanaan edukasi peer pressure
    </div>
  </div>
  <ul class="d-flex align-items-center gap-2">
    <li class="fw-medium">
      <a href="{{ route('ohs-score-card.index') }}" class="d-flex align-items-center gap-1 hover-text-primary">
        <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
        Dashboard
      </a>
    </li>
    <li class="text-secondary-light">/</li>
    <li class="fw-medium text-primary-600">Peer Pressure</li>
  </ul>
</div>

@unless ($connectionUp)
  <div class="alert alert-warning radius-8 d-flex align-items-start gap-2 mb-24">
    <iconify-icon icon="solar:danger-triangle-outline" class="text-xl flex-shrink-0 mt-1"></iconify-icon>
    <div>
      <strong>Database hse_automation tidak terjangkau.</strong>
      <div class="text-sm mt-1">
        Hanya tab <strong>BeRecord</strong> yang terpengaruh — tab lainnya memakai database aplikasi
        dan tetap berfungsi. Dari jaringan lokal biasanya perlu tunnel SSH aktif.
      </div>
    </div>
  </div>
@endunless

<div class="card radius-8 border">
  <div class="card-body p-24">

    <ul class="nav pp-tabs gap-2 mb-20" role="tablist">
      @foreach ($tabs as $key => $tab)
        <li class="nav-item" role="presentation">
          <button class="nav-link {{ $loop->first ? 'active' : '' }}"
                  id="pp-tab-{{ $key }}"
                  data-bs-toggle="tab"
                  data-bs-target="#pp-pane-{{ $key }}"
                  data-tab-key="{{ $key }}"
                  type="button" role="tab">
            <iconify-icon icon="{{ $tab['icon'] }}"></iconify-icon>
            {{ $tab['label'] }}
            <span class="pp-count" id="pp-count-{{ $key }}">–</span>
          </button>
        </li>
      @endforeach
    </ul>

    <div class="tab-content">
      @foreach ($tabs as $key => $tab)
        <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}"
             id="pp-pane-{{ $key }}" role="tabpanel">

          <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-12">
            <span class="pp-source">Sumber: <code>{{ $tab['sumber'] }}</code></span>
            <div class="d-flex align-items-center gap-2">
              <span class="text-sm text-secondary-light" id="pp-hint-{{ $key }}"></span>
              <button type="button" class="btn btn-sm btn-success-600 radius-8 pp-export"
                      data-tab-key="{{ $key }}" data-format="xlsx">
                <iconify-icon icon="mdi:microsoft-excel" class="icon"></iconify-icon> Excel
              </button>
              <button type="button" class="btn btn-sm btn-outline-success radius-8 pp-export"
                      data-tab-key="{{ $key }}" data-format="csv">
                <iconify-icon icon="mdi:file-delimited-outline" class="icon"></iconify-icon> CSV
              </button>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table bordered-table mb-0 pp-table" id="pp-table-{{ $key }}">
              <thead>
                <tr>
                  @foreach ($tab['headers'] as $header)
                    <th>{{ $header }}</th>
                  @endforeach
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>

        </div>
      @endforeach
    </div>

  </div>
</div>
@endsection

@section('page-scripts')
<script>
(function () {
    if (typeof DataTable === 'undefined') {
        return;
    }
    if (DataTable.ext) {
        DataTable.ext.errMode = 'none';
    }

    // ---- helper render -----------------------------------------------------
    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function formatNumber(value) {
        return Number(value || 0).toLocaleString('id-ID');
    }

    function plain(value) {
        return escapeHtml(value === null || value === undefined || value === '' ? '–' : value);
    }

    // Tanggal datang sebagai 'YYYY-MM-DD' (kolom date).
    function formatDate(value) {
        if (!value) { return '–'; }
        var parts = String(value).slice(0, 10).split('-');
        if (parts.length !== 3) { return escapeHtml(value); }
        var bulan = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
        return parts[2] + ' ' + (bulan[Number(parts[1]) - 1] || parts[1]) + ' ' + parts[0];
    }

    function dateCol(key) {
        return { data: key, render: function (d, type) { return type === 'display' ? formatDate(d) : (d || ''); } };
    }

    function textCol(key) {
        return { data: key, render: function (d) { return plain(d); } };
    }

    function nameCol(key) {
        return {
            data: key,
            render: function (d, type) {
                return type === 'display' ? '<span class="pp-name">' + escapeHtml(d || '–') + '</span>' : d;
            }
        };
    }

    function clipCol(key) {
        return {
            data: key, orderable: false,
            render: function (d, type) {
                if (type !== 'display') { return d; }
                if (!d) { return '–'; }
                return '<span class="pp-clip" title="' + escapeHtml(d) + '">' + escapeHtml(d) + '</span>';
            }
        };
    }

    function numCol(key) {
        return {
            data: key, orderable: false, className: 'text-center',
            render: function (d) { return formatNumber(d); }
        };
    }

    function badge(value, cls) {
        if (value === null || value === undefined || value === '') {
            return '<span class="pp-badge pp-badge--muted">–</span>';
        }
        return '<span class="pp-badge ' + cls + '">' + escapeHtml(value) + '</span>';
    }

    // Aturan sama dengan controller: "Not Banned" dikecualikan lebih dulu.
    function isBanned(tipe) {
        var t = String(tipe || '').toLowerCase();
        return t.indexOf('banned') !== -1 && t.indexOf('not banned') === -1;
    }

    var LANG = {
        processing: 'Memuat...',
        search: 'Cari:',
        lengthMenu: 'Tampilkan _MENU_ data',
        info: 'Menampilkan _START_–_END_ dari _TOTAL_ data',
        infoEmpty: 'Tidak ada data',
        infoFiltered: '(difilter dari _MAX_ total data)',
        zeroRecords: 'Tidak ada data untuk tab ini.',
        paginate: { first: '«', last: '»', next: '›', previous: '‹' }
    };

    // ---- definisi tiap tab (urutan kolom WAJIB sama dengan header blade) ----
    var TABS = {
        berecord: {
            dataUrl: @json(route('ohs-score-card.peer-pressure.data')),
            exportUrl: @json(route('ohs-score-card.peer-pressure.export')),
            order: [[8, 'desc']],
            columns: [
                textCol('kode_sid'),
                {
                    data: 'nama_karyawan',
                    render: function (d, type, row) {
                        if (type !== 'display') { return d; }
                        return '<span class="pp-name">' + escapeHtml(d || '–') + '</span>'
                            + '<span class="pp-sub">' + escapeHtml(row.jabatan_struktural || '') + '</span>';
                    }
                },
                textCol('perusahaan'),
                textCol('site'),
                textCol('jabatan_fungsional'),
                textCol('kategori_berecord'),
                {
                    data: 'tipe_berecord',
                    render: function (d, type) {
                        return type === 'display' ? badge(d, isBanned(d) ? 'pp-badge--bad' : 'pp-badge--ok') : d;
                    }
                },
                textCol('golden_rules'),
                dateCol('tanggal_mulai_berecord'),
                dateCol('tanggal_selesai_berecord'),
                {
                    data: 'status_berecord',
                    render: function (d, type) {
                        return type === 'display' ? badge(d, d === 'Masih Berlaku' ? 'pp-badge--warn' : 'pp-badge--muted') : d;
                    }
                },
                textCol('status_proses_berecord'),
                {
                    data: 'status_permit',
                    render: function (d, type) {
                        return type === 'display' ? badge(d, d === 'PASSED' ? 'pp-badge--ok' : 'pp-badge--bad') : d;
                    }
                },
                clipCol('diskripsi')
            ]
        },

        speakup: {
            dataUrl: @json(route('ohs-score-card.peer-pressure.speak-up.data')),
            exportUrl: @json(route('ohs-score-card.peer-pressure.speak-up.export')),
            order: [[0, 'desc']],
            columns: [
                dateCol('tanggal'),
                textCol('waktu'),
                textCol('site'),
                textCol('perusahaan'),
                textCol('sid'),
                nameCol('nama')
            ]
        },

        blindspot: {
            dataUrl: @json(route('ohs-score-card.peer-pressure.blindspot-tbc.data')),
            exportUrl: @json(route('ohs-score-card.peer-pressure.blindspot-tbc.export')),
            order: [[2, 'asc']],
            columns: [
                clipCol('tasklist'),
                clipCol('to_be_concerned_hazard'),
                textCol('gr'),
                textCol('kategori_gr'),
                textCol('no_item_pspp'),
                clipCol('blindspot_terlapor_bc'),
                textCol('sid_pekerja_terlibat'),
                nameCol('nama_pekerja_terlibat'),
                clipCol('catatan')
            ]
        },

        pelaksanaan: {
            dataUrl: @json(route('ohs-score-card.peer-pressure.pelaksanaan.data')),
            exportUrl: @json(route('ohs-score-card.peer-pressure.pelaksanaan.export')),
            order: [[0, 'desc']],
            columns: [
                dateCol('tanggal_edukasi'),
                textCol('site'),
                textCol('perusahaan'),
                textCol('kategori_deviasi'),
                clipCol('lokasi_edukasi'),
                textCol('pemimpin_edukasi'),
                {
                    data: 'durasi_edukasi_menit', className: 'text-center',
                    render: function (d, type) { return type === 'display' ? formatNumber(d) + ' mnt' : d; }
                },
                {
                    data: 'status_pelaksanaan_edukasi',
                    render: function (d, type) {
                        return type === 'display' ? badge(d, d === 'CLOSED' ? 'pp-badge--ok' : 'pp-badge--warn') : d;
                    }
                },
                numCol('jumlah_peserta'),
                numCol('jumlah_pelanggar'),
                numCol('jumlah_peer')
            ]
        }
    };

    var instances = {};

    function build(key) {
        var cfg = TABS[key];
        var el = document.querySelector('#pp-table-' + key);
        if (!cfg || !el) { return null; }

        return new DataTable(el, {
            processing: true,
            serverSide: true,
            searching: true,
            ordering: true,
            pageLength: 25,
            lengthMenu: [25, 50, 100, 200],
            order: cfg.order,
            autoWidth: false,
            layout: {
                topStart: 'pageLength',
                topEnd: 'search',
                bottomStart: 'info',
                bottomEnd: 'paging'
            },
            ajax: {
                url: cfg.dataUrl,
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                dataSrc: function (json) {
                    var count = Number(json.recordsFiltered || 0);
                    document.querySelector('#pp-count-' + key).textContent = formatNumber(count);
                    document.querySelector('#pp-hint-' + key).textContent = 'unduh ' + formatNumber(count) + ' baris';
                    return json.data || [];
                },
                error: function (xhr, error) {
                    document.querySelector('#pp-count-' + key).textContent = '!';
                    document.querySelector('#pp-hint-' + key).textContent = 'gagal memuat';
                    if (typeof console !== 'undefined' && console.error) {
                        console.error('Peer Pressure [' + key + ']: gagal memuat data', error, xhr && xhr.status);
                    }
                }
            },
            columns: cfg.columns,
            language: LANG
        });
    }

    // Tabel dibangun saat tab-nya pertama kali dibuka, bukan sekaligus di awal:
    // empat permintaan AJAX serentak saat halaman dimuat itu mubazir, apalagi
    // tab BeRecord memanggil database berbeda yang bisa lambat atau mati.
    function ensure(key) {
        if (!instances[key]) {
            instances[key] = build(key);
        }
        return instances[key];
    }

    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(function (btn) {
        btn.addEventListener('shown.bs.tab', function () {
            var table = ensure(btn.dataset.tabKey);
            if (table) {
                // Lebar kolom dihitung ulang: saat dibangun, pane-nya masih
                // tersembunyi sehingga DataTables tidak bisa mengukurnya.
                table.columns.adjust();
            }
        });
    });

    // Tab pertama sudah tampil sejak awal, jadi langsung dibangun.
    var firstTab = document.querySelector('[data-bs-toggle="tab"]');
    if (firstTab) {
        ensure(firstTab.dataset.tabKey);
    }

    document.querySelectorAll('.pp-export').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var key = btn.dataset.tabKey;
            var cfg = TABS[key];
            if (!cfg) { return; }

            var params = new URLSearchParams();
            params.set('format', btn.dataset.format);

            var table = instances[key];
            if (table) {
                var search = table.search();
                if (search) { params.set('search', search); }
            }

            window.location.href = cfg.exportUrl + '?' + params.toString();
        });
    });
})();
</script>
@endsection

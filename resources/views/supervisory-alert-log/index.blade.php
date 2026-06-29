@extends('layouts.master')

@section('title', 'Alert Log')

@section('css')
<link rel="stylesheet" href="{{ URL::asset('build/plugins/datatable/css/dataTables.bootstrap5.min.css') }}">
<link rel="stylesheet" href="{{ URL::asset('build/plugins/select2/css/select2.min.css') }}">
<link rel="stylesheet" href="{{ URL::asset('build/plugins/select2-bootstrap-5-theme/select2-bootstrap-5-theme.min.css') }}">
<style>
.alert-log-page .filter-card { border-radius: 1rem; }
.alert-log-page .main-card { border-radius: 1rem; overflow: hidden; }
.alert-log-page .nav-tabs .nav-link { font-weight: 500; color: #495057; }
.alert-log-page .nav-tabs .nav-link.active { color: #0d6efd; border-bottom: 2px solid #0d6efd; }
.alert-log-page .tab-content { padding-top: 1.5rem; }
.alert-log-page .dataTables_wrapper .row:first-child { margin-bottom: 0.75rem; }
.alert-log-page table.dataTable { width: 100% !important; border-collapse: separate; border-spacing: 0; }
.alert-log-page table.dataTable thead th {
    border-bottom: 2px solid #e9ecef;
    white-space: nowrap;
    padding: 0.75rem 1rem;
    font-size: 0.875rem;
    font-weight: 600;
    color: #495057;
    background: #f8f9fa;
}
.alert-log-page table.dataTable tbody td {
    padding: 0.85rem 1rem;
    font-size: 0.9rem;
    line-height: 1.4;
    vertical-align: middle;
}
.alert-log-page table.dataTable tbody tr:hover { background-color: #f8f9fa; }
.alert-log-page .empty-state { padding: 3rem 1rem; text-align: center; color: #6c757d; }
.alert-log-page .badge-high { background-color: #dc3545; }
.alert-log-page .badge-medium { background-color: #ffc107; color: #212529; }
.alert-log-page .badge-low { background-color: #198754; }
</style>
@endsection

@section('content')
<div class="alert-log-page">
    <x-page-title title="Alert Log" pagetitle="Alert Log" />

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    <div class="card main-card">
        <div class="card-header bg-white py-3">
            <ul class="nav nav-tabs card-header-tabs" id="alertLogTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="supervisory-tab" data-bs-toggle="tab" data-bs-target="#supervisory" type="button" role="tab" aria-selected="true">
                        <i class="material-icons-outlined align-middle me-1" style="font-size:18px;">assignment</i>
                        Alert Supervisory
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="mobility-tab" data-bs-toggle="tab" data-bs-target="#mobility" type="button" role="tab" aria-selected="false">
                        <i class="material-icons-outlined align-middle me-1" style="font-size:18px;">directions_car</i>
                        Alert Mobility
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="critical-area-tab" data-bs-toggle="tab" data-bs-target="#critical-area" type="button" role="tab" aria-selected="false">
                        <i class="material-icons-outlined align-middle me-1" style="font-size:18px;">warning</i>
                        Alert Critical Area
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="probability-tab" data-bs-toggle="tab" data-bs-target="#probability" type="button" role="tab" aria-selected="false">
                        <i class="material-icons-outlined align-middle me-1" style="font-size:18px;">insights</i>
                        Alert Probability Area
                    </button>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content" id="alertLogTabContent">
                {{-- Tab 1: Alert Supervisory --}}
                <div class="tab-pane fade show active" id="supervisory" role="tabpanel">
                    <div class="card filter-card mb-4 border">
                        <div class="card-body">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Tanggal</label>
                                    <input type="date" id="filterTanggal" class="form-control">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Risk Level</label>
                                    <select id="filterRiskLevel" class="form-select">
                                        <option value="">Semua</option>
                                        <option value="HIGH">HIGH (Merah)</option>
                                        <option value="MEDIUM">MEDIUM (Kuning)</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <button type="button" id="btnApplyFilter" class="btn btn-primary w-100">
                                        <i class="material-icons-outlined align-middle me-1" style="font-size:18px;">filter_alt</i> Filter
                                    </button>
                                </div>
                                <div class="col-md-3">
                                    <button type="button" id="btnResetFilter" class="btn btn-outline-secondary w-100">Reset</button>
                                </div>
                            </div>
                            <div class="row g-3 mt-1">
                                <div class="col-md-3">
                                    <button type="button" class="btn btn-success w-100" id="btnExportSupervisory">
                                        <i class="material-icons-outlined align-middle me-1" style="font-size:18px;">download</i> Download Excel
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table id="supervisoryTable" class="table table-hover table-striped" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Nama Lokasi</th>
                                    <th>Risk Level</th>
                                    <th>SAP Report</th>
                                    <th>CCTV Online</th>
                                    <th>Diperbarui</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

                {{-- Tab 2: Alert Mobility --}}
                <div class="tab-pane fade" id="mobility" role="tabpanel">
                    <div id="mobilityTableWrap" class="table-responsive d-none">
                        <table id="mobilityTable" class="table table-hover table-striped" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Kode / Unit</th>
                                    <th>Lokasi</th>
                                    <th>Status</th>
                                    <th>Waktu</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="mobilityTableBody"></tbody>
                        </table>
                    </div>
                    <div id="mobilityEmpty" class="empty-state d-none">
                        <i class="material-icons-outlined" style="font-size: 48px;">directions_car</i>
                        <p class="mt-2 mb-0">Belum ada data alert Mobility. Data dapat diintegrasikan dari sistem Unit & Orang.</p>
                    </div>
                    <div id="mobilityLoading" class="empty-state">
                        <div class="spinner-border spinner-border-sm text-primary"></div>
                        <p class="mt-2 mb-0">Memuat data...</p>
                    </div>
                </div>

                {{-- Tab 3: Alert Critical Area --}}
                <div class="tab-pane fade" id="critical-area" role="tabpanel">
                    <div id="criticalAreaTableWrap" class="table-responsive d-none">
                        <table id="criticalAreaTable" class="table table-hover table-striped" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Kode IKK</th>
                                    <th>Jenis IJK</th>
                                    <th>Nama Pekerjaan</th>
                                    <th>Site</th>
                                    <th>Status Matriks</th>
                                    <th>Status Pekerjaan</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="criticalAreaTableBody"></tbody>
                        </table>
                    </div>
                    <div id="criticalAreaEmpty" class="empty-state d-none">
                        <i class="material-icons-outlined" style="font-size: 48px;">check_circle</i>
                        <p class="mt-2 mb-0">Tidak ada alert Critical Area (semua IKK sudah ada IPK/OKK).</p>
                    </div>
                    <div id="criticalAreaLoading" class="empty-state">
                        <div class="spinner-border spinner-border-sm text-primary"></div>
                        <p class="mt-2 mb-0">Memuat data IKK...</p>
                    </div>
                </div>

                {{-- Tab 4: Alert Probability Area --}}
                <div class="tab-pane fade" id="probability" role="tabpanel">
                    <div id="probabilityTableWrap" class="table-responsive d-none">
                        <table id="probabilityTable" class="table table-hover table-striped" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Nama PJA</th>
                                    <th>Site / Lokasi</th>
                                    <th>Tipe / Kategori</th>
                                    <th>Layer</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="probabilityTableBody"></tbody>
                        </table>
                    </div>
                    <div id="probabilityEmpty" class="empty-state d-none">
                        <i class="material-icons-outlined" style="font-size: 48px;">insights</i>
                        <p class="mt-2 mb-0">Tidak ada data PJA (Probability).</p>
                    </div>
                    <div id="probabilityLoading" class="empty-state">
                        <div class="spinner-border spinner-border-sm text-primary"></div>
                        <p class="mt-2 mb-0">Memuat data PJA...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal Hasil Matriks TARP --}}
<div class="modal fade" id="matrixResultModal" tabindex="-1" aria-labelledby="matrixResultModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="matrixResultModalLabel"><i class="material-icons-outlined me-2">assessment</i> Hasil Matriks TARP</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="matrixResultModalBody">
                <div class="text-center py-5 text-muted">
                    <div class="spinner-border" role="status"></div>
                    <p class="mt-2 mb-0">Memuat hasil matriks...</p>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal Intervensi Area Kerja --}}
<div class="modal fade" id="intervensiAreaKerjaModal" tabindex="-1" aria-labelledby="intervensiAreaKerjaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold" id="intervensiAreaKerjaModalLabel">
                    <span class="material-icons-outlined me-2">send</span>
                    Form Intervensi Area Kerja
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="intervensiAreaKerjaForm">
                    <input type="hidden" id="intervensiControlRoomAreaKerja" name="control_room" value="">
                    <input type="hidden" id="intervensiAreaKerja" name="area_kerja" value="">
                    <input type="hidden" id="intervensiLokasi" name="lokasi" value="">
                    
                    <div class="mb-3">
                        <label for="intervensiLokasiDisplay" class="form-label fw-semibold">Lokasi</label>
                        <input type="text" class="form-control" id="intervensiLokasiDisplay" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="intervensiCCTVAreaKerja" class="form-label fw-semibold">CCTV <span class="text-danger">*</span></label>
                        <select class="form-select" id="intervensiCCTVAreaKerja" name="cctv_ids[]" multiple required>
                            <option value="">Pilih CCTV...</option>
                        </select>
                        <div class="form-text">Pilih satu atau lebih CCTV yang bermasalah (bisa pilih lebih dari 1)</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="intervensiPICAreaKerja" class="form-label fw-semibold">PIC (Pengawas) <span class="text-danger">*</span></label>
                        <select class="form-select" id="intervensiPICAreaKerja" name="pic" required>
                            <option value="">Pilih PIC...</option>
                        </select>
                        <div class="form-text">Pilih PIC (Pengawas) dari daftar pengguna</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="intervensiIssueAreaKerja" class="form-label fw-semibold">Issue <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="intervensiIssueAreaKerja" name="issue" rows="5" placeholder="Masukkan issue atau masalah yang ditemukan..." required></textarea>
                        <div class="form-text">Jelaskan issue atau masalah yang memerlukan intervensi</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-top">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="submitIntervensiAreaKerjaBtn">
                    <span class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">send</span>
                    Kirim Intervensi
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Modal Intervensi DOPM (untuk Critical Area IKK) --}}
<div class="modal fade" id="intervensiDopmModal" tabindex="-1" aria-labelledby="intervensiDopmModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4 shadow-lg border border-light">
            <div class="modal-header rounded-top-4 py-3 bg-warning bg-opacity-10">
                <div class="d-flex align-items-center flex-grow-1">
                    <span class="material-icons-outlined me-2 fs-4 text-warning">campaign</span>
                    <div>
                        <h5 class="modal-title fw-bold mb-0 text-dark" id="intervensiDopmModalLabel">
                            <span id="intervensiDopmTitle">Intervensi DOPM</span>
                        </h5>
                        <small class="text-muted" id="intervensiDopmSubtitle">Kode IKK: —</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-white">
                <div id="intervensiPjoWrap" class="intervensi-section mb-4 d-none">
                    <h6 class="text-info border-bottom pb-2 mb-3"><i class="material-icons-outlined align-middle me-1" style="font-size:20px;">person</i> PJO — Intervensi by WA</h6>
                    <div class="card border-info mb-3">
                        <div class="card-header bg-info bg-opacity-10 py-2">
                            <span class="material-icons-outlined align-middle me-1 text-info">contact_phone</span>
                            <strong>PJO Work Permit: <span id="intervensiPjoNameDisplay" class="text-dark">—</span></strong>
                        </div>
                        <div class="card-body py-3">
                            <p class="small text-muted mb-2">Kirim intervensi via WhatsApp ke nomor PJO yang terdaftar.</p>
                            <div id="intervensiPjoUsers" class="d-flex flex-wrap gap-2"></div>
                            <div id="intervensiPjoEmpty" class="text-muted small d-none">Tidak ada user terdaftar dengan nama tersebut.</div>
                            <div id="intervensiPjoLoading" class="text-muted small d-none"><span class="spinner-border spinner-border-sm me-1" role="status"></span>Memuat nomor PJO...</div>
                        </div>
                    </div>
                </div>
                <div class="intervensi-section mb-4">
                    <h6 class="text-primary border-bottom pb-2 mb-3"><i class="material-icons-outlined align-middle me-1" style="font-size:20px;">checklist</i> IPK-IKK <span class="badge bg-primary ms-1" id="intervensiBadgeIpk">0</span></h6>
                    <div id="intervensiLayer1Wrap" class="card border-warning mb-3">
                        <div class="card-header bg-warning bg-opacity-10 py-2">
                            <span class="material-icons-outlined align-middle me-1 text-warning">notifications_active</span>
                            <strong>Layer 1 — Pengingat Isi IPK (INSPEKSI PRA KERJA)</strong>
                        </div>
                        <div class="card-body py-3">
                            <p class="small mb-2"><strong>Nama Layer:</strong> <span id="intervensiLayer1NameDisplay" class="text-dark">—</span></p>
                            <p class="small text-muted mb-2">Klik tombol di bawah untuk langsung kirim intervensi via WhatsApp.</p>
                            <div id="intervensiLayer1Users" class="d-flex flex-wrap gap-2"></div>
                            <div id="intervensiLayer1Empty" class="text-muted small d-none">Tidak ada user terdaftar untuk Layer 1 ini.</div>
                            <div id="intervensiLayer1NoName" class="text-muted small d-none">Kolom <strong>SID Layer 1</strong> atau <strong>Nama Layer 1</strong> untuk DOPM ini belum diisi.</div>
                            <div id="intervensiLayer1Loading" class="text-muted small d-none"><span class="spinner-border spinner-border-sm me-1" role="status"></span>Memuat daftar PIC Layer 1...</div>
                        </div>
                    </div>
                    <div id="intervensiIpkLoading" class="text-center py-3 d-none"><div class="spinner-border text-primary spinner-border-sm" role="status"></div><p class="text-muted mb-0 mt-2 small">Memuat data IPK-IKK...</p></div>
                    <div id="intervensiIpkEmpty" class="text-center py-3 d-none"><span class="material-icons-outlined text-muted" style="font-size: 32px;">inbox</span><p class="text-muted mt-2 mb-0 small">Tidak ada data IPK-IKK.</p></div>
                    <div id="intervensiIpkTableWrap" class="d-none">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover table-striped align-middle mb-0 table-bordered" id="intervensiTableIpk">
                                <thead class="table-light"><tr><th>Waktu</th><th>Nama Pengawas</th><th>Kode SID</th><th>Kode IKK</th><th>Perusahaan</th><th>Site</th><th>Durasi</th><th>CCTV</th><th>Kategori IJK</th><th>Status</th></tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="intervensi-section mb-4">
                    <h6 class="text-success border-bottom pb-2 mb-3"><i class="material-icons-outlined align-middle me-1" style="font-size:20px;">folder_open</i> OKK <span class="badge bg-success ms-1" id="intervensiBadgeOkk">0</span></h6>
                    <div id="intervensiOkkLayer1Wrap" class="card border-success mb-3">
                        <div class="card-header bg-success bg-opacity-10 py-2">
                            <span class="material-icons-outlined align-middle me-1 text-success">notifications_active</span>
                            <strong>Layer 1 — Intervensi OKK (OBSERVASI KEGIATAN KERJA)</strong>
                        </div>
                        <div class="card-body py-3">
                            <p class="small mb-2"><strong>Nama Layer:</strong> <span id="intervensiOkkLayer1NameDisplay" class="text-dark">—</span></p>
                            <p class="small text-muted mb-2">Klik tombol di bawah untuk langsung kirim intervensi via WhatsApp.</p>
                            <div id="intervensiOkkLayer1Users" class="d-flex flex-wrap gap-2"></div>
                            <div id="intervensiOkkLayer1Empty" class="text-muted small d-none">Tidak ada user terdaftar untuk Layer 1 ini.</div>
                            <div id="intervensiOkkLayer1NoName" class="text-muted small d-none">Kolom <strong>SID Layer 1</strong> atau <strong>Nama Layer 1</strong> untuk DOPM ini belum diisi.</div>
                            <div id="intervensiOkkLayer1Loading" class="text-muted small d-none"><span class="spinner-border spinner-border-sm me-1" role="status"></span>Memuat daftar PIC Layer 1...</div>
                        </div>
                    </div>
                    <div id="intervensiOkkLoading" class="text-center py-3 d-none"><div class="spinner-border text-success spinner-border-sm" role="status"></div><p class="text-muted mb-0 mt-2 small">Memuat data OKK...</p></div>
                    <div id="intervensiOkkEmpty" class="text-center py-3 d-none"><span class="material-icons-outlined text-muted" style="font-size: 32px;">inbox</span><p class="text-muted mt-2 mb-0 small">Tidak ada data OKK.</p></div>
                    <div id="intervensiOkkTableWrap" class="d-none">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover table-striped align-middle mb-0 table-bordered" id="intervensiTableOkk">
                                <thead class="table-light"><tr><th>Waktu</th><th>Nama Pengawas</th><th>Kode SID</th><th>Kode IKK</th><th>Perusahaan</th><th>Site</th><th>Jenis IJK</th><th>Layer</th></tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="intervensi-section">
                    <h6 class="text-warning text-dark border-bottom pb-2 mb-3"><i class="material-icons-outlined align-middle me-1" style="font-size:20px;">visibility</i> OAK <span class="badge bg-warning text-dark ms-1" id="intervensiBadgeOak">0</span></h6>
                    <div id="intervensiOakLayersWrap" class="card border-warning mb-3">
                        <div class="card-header bg-warning bg-opacity-10 py-2">
                            <span class="material-icons-outlined align-middle me-1 text-warning">notifications_active</span>
                            <strong>Intervensi OAK — Layer 2, 3, 4</strong>
                        </div>
                        <div class="card-body py-3">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="border rounded p-2 bg-light">
                                        <p class="small mb-1 fw-semibold">Layer 2</p>
                                        <p class="small mb-1 text-muted"><strong>Nama:</strong> <span id="intervensiOakLayer2Name" class="text-dark">—</span></p>
                                        <div id="intervensiOakLayer2Users" class="d-flex flex-wrap gap-1"></div>
                                        <div id="intervensiOakLayer2Empty" class="text-muted small d-none">Tidak ada user.</div>
                                        <div id="intervensiOakLayer2Loading" class="text-muted small d-none"><span class="spinner-border spinner-border-sm me-1"></span>Memuat...</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="border rounded p-2 bg-light">
                                        <p class="small mb-1 fw-semibold">Layer 3</p>
                                        <p class="small mb-1 text-muted"><strong>Nama:</strong> <span id="intervensiOakLayer3Name" class="text-dark">—</span></p>
                                        <div id="intervensiOakLayer3Users" class="d-flex flex-wrap gap-1"></div>
                                        <div id="intervensiOakLayer3Empty" class="text-muted small d-none">Tidak ada user.</div>
                                        <div id="intervensiOakLayer3Loading" class="text-muted small d-none"><span class="spinner-border spinner-border-sm me-1"></span>Memuat...</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="border rounded p-2 bg-light">
                                        <p class="small mb-1 fw-semibold">Layer 4</p>
                                        <p class="small mb-1 text-muted"><strong>Nama:</strong> <span id="intervensiOakLayer4Name" class="text-dark">—</span></p>
                                        <div id="intervensiOakLayer4Users" class="d-flex flex-wrap gap-1"></div>
                                        <div id="intervensiOakLayer4Empty" class="text-muted small d-none">Tidak ada user.</div>
                                        <div id="intervensiOakLayer4Loading" class="text-muted small d-none"><span class="spinner-border spinner-border-sm me-1"></span>Memuat...</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="intervensiOakLoading" class="text-center py-3 d-none"><div class="spinner-border text-warning spinner-border-sm" role="status"></div><p class="text-muted mb-0 mt-2 small">Memuat data OAK...</p></div>
                    <div id="intervensiOakEmpty" class="text-center py-3 d-none"><span class="material-icons-outlined text-muted" style="font-size: 32px;">inbox</span><p class="text-muted mt-2 mb-0 small">Tidak ada data OAK.</p></div>
                    <div id="intervensiOakTableWrap" class="d-none">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover table-striped align-middle mb-0 table-bordered" id="intervensiTableOak">
                                <thead class="table-light"><tr><th>Activity</th><th>Sub Activity</th><th>Submit Date</th><th>Submit By</th><th>SID Pelapor</th><th>Lokasi</th><th>Detail Lokasi</th><th>Conclusion</th><th>Site</th></tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ URL::asset('build/plugins/datatable/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ URL::asset('build/plugins/datatable/js/dataTables.bootstrap5.min.js') }}"></script>
<script src="{{ URL::asset('build/plugins/select2/js/select2.min.js') }}"></script>
<script src="{{ URL::asset('build/plugins/sweetalert2/sweetalert2.min.js') }}"></script>
<script>
$(document).ready(function() {
    // Helper function for escaping HTML
    var escapeHtml = function(s) {
        if (s == null || s === '') return '';
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    };

    var table = $('#supervisoryTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('supervisory-alert-log.data') }}",
            type: 'GET',
            data: function(d) {
                d.tanggal = $('#filterTanggal').val() || '';
                d.risk_level = $('#filterRiskLevel').val() || '';
            }
        },
        columns: [
            { data: 'tanggal', name: 'tanggal', orderable: true },
            { data: 'nama_lokasi', name: 'nama_lokasi', orderable: true },
            { data: 'risk_level', name: 'risk_level', orderable: true },
            { data: 'has_sap_report', name: 'has_sap_report', orderable: true },
            { data: 'has_online_cctv', name: 'has_online_cctv', orderable: true },
            { data: 'updated_at', name: 'updated_at', orderable: true },
            { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-end' }
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        language: {
            processing: '<div class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">Memproses...</span></div> Memproses...',
            lengthMenu: "Tampilkan _MENU_ data",
            zeroRecords: "Tidak ada data yang cocok",
            info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
            infoEmpty: "Menampilkan 0 sampai 0 dari 0 data",
            infoFiltered: "(disaring dari _MAX_ total data)",
            search: "Cari:",
            paginate: {
                first: "&laquo;",
                last: "&raquo;",
                next: "&rsaquo;",
                previous: "&lsaquo;"
            }
        },
        drawCallback: function() {
            $('.dataTables_wrapper .dataTables_info').addClass('text-muted small');
        }
    });

    $('#btnApplyFilter').on('click', function() {
        table.ajax.reload();
    });

    $('#btnResetFilter').on('click', function() {
        $('#filterTanggal').val('');
        $('#filterRiskLevel').val('');
        table.ajax.reload();
    });

    $('#filterTanggal, #filterRiskLevel').on('change', function() {
        table.ajax.reload();
    });

    $('#btnExportSupervisory').on('click', function() {
        const params = new URLSearchParams();
        const tanggal = $('#filterTanggal').val();
        const riskLevel = $('#filterRiskLevel').val();
        if (tanggal) params.set('tanggal', tanggal);
        if (riskLevel) params.set('risk_level', riskLevel);
        const baseUrl = @json(route('supervisory-alert-log.export'));
        window.location.href = params.toString() ? baseUrl + '?' + params.toString() : baseUrl;
    });

    // Tab Mobility: load data saat tab ditampilkan
    $('button[data-bs-target="#mobility"]').on('shown.bs.tab', function() {
        if ($('#mobilityTableBody').data('loaded')) return;
        $('#mobilityLoading').removeClass('d-none');
        $('#mobilityTableWrap').addClass('d-none');
        $('#mobilityEmpty').addClass('d-none');
        $.get("{{ route('supervisory-alert-log.data-mobility') }}").done(function(res) {
            $('#mobilityLoading').addClass('d-none');
            $('#mobilityTableBody').data('loaded', true);
            var data = res.data || [];
            if (data.length === 0) {
                $('#mobilityEmpty').removeClass('d-none');
            } else {
                var rows = data.map(function(r) {
                    var lok = (r.lokasi || r.unit || r.kode || '').toString();
                    return '<tr><td>' + (r.kode || r.unit || '-') + '</td><td>' + (r.lokasi || '-') + '</td><td>' + (r.status || '-') + '</td><td>' + (r.waktu || '-') + '</td><td class="text-end"><button type="button" class="btn btn-sm btn-outline-success btn-intervensi" data-lokasi="' + escapeHtml(lok) + '" title="Intervensi"><i class="material-icons-outlined me-1" style="font-size:16px;vertical-align:middle;">campaign</i> Intervensi</button></td></tr>';
                }).join('');
                $('#mobilityTableBody').html(rows);
                $('#mobilityTableWrap').removeClass('d-none');
            }
        }).fail(function() {
            $('#mobilityLoading').addClass('d-none');
            $('#mobilityEmpty').removeClass('d-none').find('p').text('Gagal memuat data Mobility.');
        });
    });

    // Tab Critical Area: load IKK dari full-maps API
    $('button[data-bs-target="#critical-area"]').on('shown.bs.tab', function() {
        if ($('#criticalAreaTableBody').data('loaded')) return;
        $('#criticalAreaLoading').removeClass('d-none');
        $('#criticalAreaTableWrap').addClass('d-none');
        $('#criticalAreaEmpty').addClass('d-none');
        $.get("{{ route('full-maps.api.ikk-for-controlroom-sidebar') }}").done(function(res) {
            $('#criticalAreaLoading').addClass('d-none');
            $('#criticalAreaTableBody').data('loaded', true);
            var data = (res.success && res.data) ? res.data : [];
            if (data.length === 0) {
                $('#criticalAreaEmpty').removeClass('d-none');
            } else {
                var badge = function(s) {
                    if (s === 'Merah') return '<span class="badge bg-danger">Merah</span>';
                    if (s === 'Kuning') return '<span class="badge bg-warning text-dark">Kuning</span>';
                    return '<span class="badge bg-secondary">' + escapeHtml(s || '-') + '</span>';
                };
                var rows = data.map(function(r) {
                    var dopmData = JSON.stringify({
                        kode_ikk: r.code || '',
                        tanggal_dop: r.tanggal_dop || '{{ now()->format("Y-m-d") }}',
                        nama_pekerjaan: r.nama_pekerjaan || '',
                        jenis_ijin_kerja_khusus: r.jenis_ijin_kerja_khusus || '',
                        site: r.site || '',
                        location_name: r.location_name || r.lokasi || '',
                        location_detail_name: r.location_detail_name || '',
                        nama_layer_1: r.nama_layer_1 || '',
                        nama_layer_2: r.nama_layer_2 || '',
                        nama_layer_3: r.nama_layer_3 || '',
                        nama_layer_4: r.nama_layer_4 || '',
                        sid_layer_1: r.sid_layer_1 || '',
                        sid_layer_2: r.sid_layer_2 || '',
                        sid_layer_3: r.sid_layer_3 || '',
                        sid_layer_4: r.sid_layer_4 || '',
                        ra_pjo_name: r.ra_pjo_name || ''
                    });
                    var btn = '<button type="button" class="btn btn-sm btn-outline-warning btn-intervensi-dopm" data-dopm=\'' + escapeHtml(dopmData) + '\' title="Intervensi DOPM"><i class="material-icons-outlined me-1" style="font-size:16px;vertical-align:middle;">campaign</i> Intervensi</button>';
                    return '<tr><td>' + escapeHtml(r.code || '-') + '</td><td>' + escapeHtml(r.jenis_ijin_kerja_khusus || '-') + '</td><td>' + escapeHtml(r.nama_pekerjaan || '-') + '</td><td>' + escapeHtml(r.site || '-') + '</td><td>' + badge(r.status_matriks) + '</td><td>' + escapeHtml(r.status_pekerjaan || '-') + '</td><td class="text-end">' + btn + '</td></tr>';
                }).join('');
                $('#criticalAreaTableBody').html(rows);
                $('#criticalAreaTableWrap').removeClass('d-none');
            }
        }).fail(function() {
            $('#criticalAreaLoading').addClass('d-none');
            $('#criticalAreaEmpty').removeClass('d-none').find('p').text('Gagal memuat data IKK.');
        });
    });

    // Tab Probability: load PJA dari maps API
    $('button[data-bs-target="#probability"]').on('shown.bs.tab', function() {
        if ($('#probabilityTableBody').data('loaded')) return;
        $('#probabilityLoading').removeClass('d-none');
        $('#probabilityTableWrap').addClass('d-none');
        $('#probabilityEmpty').addClass('d-none');
        $.get("{{ route('maps.api.pja-data') }}").done(function(res) {
            $('#probabilityLoading').addClass('d-none');
            $('#probabilityTableBody').data('loaded', true);
            var data = (res.success && res.data) ? res.data : [];
            if (!Array.isArray(data)) data = [];
            if (data.length === 0) {
                $('#probabilityEmpty').removeClass('d-none');
            } else {
                var rows = data.map(function(r) {
                    var nama = r.nama_pja || r.name || ('PJA ' + (r.pja_id || ''));
                    var siteLok = [r.site, r.lokasi, r.detail_lokasi].filter(Boolean).join(' / ');
                    var tipeCat = [r.pja_type_name, r.pja_category_name].filter(Boolean).join(' / ');
                    var lokParam = (r.site || r.lokasi || r.detail_lokasi || siteLok || '').toString();
                    var btn = '<button type="button" class="btn btn-sm btn-outline-success btn-intervensi" data-lokasi="' + escapeHtml(lokParam) + '" title="Intervensi"><i class="material-icons-outlined me-1" style="font-size:16px;vertical-align:middle;">campaign</i> Intervensi</button>';
                    return '<tr><td>' + escapeHtml(nama) + '</td><td>' + escapeHtml(siteLok || '-') + '</td><td>' + escapeHtml(tipeCat || '-') + '</td><td>' + escapeHtml(r.pja_layer || '-') + '</td><td class="text-end">' + btn + '</td></tr>';
                }).join('');
                $('#probabilityTableBody').html(rows);
                $('#probabilityTableWrap').removeClass('d-none');
            }
        }).fail(function() {
            $('#probabilityLoading').addClass('d-none');
            $('#probabilityEmpty').removeClass('d-none').find('p').text('Gagal memuat data PJA.');
        });
    });

    $(document).on('click', '.btn-view-matrix', function() {
        var id = $(this).data('id');
        if (!id) return;
        var url = "{{ url('supervisory-alert-log') }}/" + id + "/detail";
        var $body = $('#matrixResultModalBody');
        $body.html('<div class="text-center py-5 text-muted"><div class="spinner-border"></div><p class="mt-2 mb-0">Memuat hasil matriks...</p></div>');
        var $modal = new bootstrap.Modal(document.getElementById('matrixResultModal'));
        $modal.show();
        $.get(url).done(function(res) {
            if (!res.success || !res.data) {
                $body.html('<div class="alert alert-danger">Data tidak ditemukan.</div>');
                return;
            }
            var d = res.data;
            var riskColor = d.risk_level === 'HIGH' ? '#dc2626' : (d.risk_level === 'MEDIUM' ? '#f59e0b' : '#22c55e');
            var riskWarna = d.risk_level === 'HIGH' ? 'Merah' : (d.risk_level === 'MEDIUM' ? 'Orange' : 'Hijau');
            var sapText = d.has_sap_report ? 'MEMENUHI' : 'TIDAK MEMENUHI';
            var cctvText = d.has_online_cctv ? 'MEMENUHI' : 'TIDAK MEMENUHI';
            var highRiskText = d.is_high_risk_area ? 'YA' : 'TIDAK';
            var sapInHighText = d.has_sap_in_high_risk ? 'MEMENUHI' : 'TIDAK MEMENUHI';
            var cctvCount = (d.cctv_list || []).length;
            var sapCount = (d.sap_list || []).length;

            var html = '<div class="row"><div class="col-md-6">';
            html += '<div class="card mb-3"><div class="card-header"><h6 class="mb-0"><i class="material-icons-outlined">info</i> Informasi Area Kerja</h6></div><div class="card-body small">';
            html += '<p class="mb-1"><strong>Lokasi:</strong> ' + (d.nama_lokasi || '-') + '</p>';
            html += '<p class="mb-1"><strong>ID Lokasi:</strong> ' + (d.id_lokasi || '-') + '</p>';
            html += '<p class="mb-1"><strong>Tanggal:</strong> ' + (d.tanggal || '-') + '</p>';
            html += '</div></div>';

            html += '<div class="card mb-3"><div class="card-header" style="background-color:' + riskColor + '20;"><h6 class="mb-0"><i class="material-icons-outlined">assessment</i> Risk Matrix Summary</h6></div><div class="card-body small">';
            html += '<p class="mb-1"><strong>Risk Level:</strong> <span style="color:' + riskColor + ';font-weight:bold;">' + (d.risk_level || '-') + '</span></p>';
            html += '<p class="mb-1">' + (d.has_sap_report ? '✓' : '✗') + ' Terdapat Laporan SAP: <span style="color:' + (d.has_sap_report ? '#22c55e' : '#dc2626') + '">' + sapText + '</span></p>';
            html += '<p class="mb-1">' + (d.has_online_cctv ? '✓' : '✗') + ' CCTV Kondisi Online: <span style="color:' + (d.has_online_cctv ? '#22c55e' : '#dc2626') + '">' + cctvText + '</span>' + (cctvCount ? ' (' + cctvCount + ' CCTV ditemukan)' : '') + '</p>';
            html += '<p class="mb-1">⚠ Area Highrisk: <span style="color:' + (d.is_high_risk_area ? '#f59e0b' : '#6b7280') + '">' + highRiskText + '</span></p>';
            if (d.is_high_risk_area) {
                html += '<p class="mb-0">' + (d.has_sap_in_high_risk ? '✓' : '✗') + ' Area Highrisk ada Laporan SAP: <span style="color:' + (d.has_sap_in_high_risk ? '#22c55e' : '#dc2626') + '">' + sapInHighText + '</span></p>';
            }
            html += '</div></div></div><div class="col-md-6">';

            html += '<div class="card mb-3"><div class="card-header"><h6 class="mb-0"><i class="material-icons-outlined">rule</i> TARP (Triggered Action Response Plan)</h6></div><div class="card-body small">';
            html += '<p class="mb-1"><strong>Level:</strong> <span style="color:' + riskColor + ';font-weight:bold;">' + (d.risk_level || '-') + '</span></p>';
            html += '<p class="mb-1"><strong>Warna:</strong> ' + riskWarna + '</p>';
            html += '<p class="mb-0"><strong>Kriteria:</strong> ' + (d.risk_level === 'HIGH' ? 'Risiko tinggi, pelanggaran kritikal.' : (d.risk_level === 'MEDIUM' ? 'Potensi moderate, closed loop tidak tuntas.' : 'Kondisi memenuhi.')) + '</p>';
            html += '</div></div>';

            var recs = d.tarp_recommendations || [];
            if (recs.length) {
                html += '<div class="card mb-3"><div class="card-header"><h6 class="mb-0"><i class="material-icons-outlined">list_alt</i> Rekomendasi Tindakan</h6></div><div class="card-body small">';
                html += '<p class="text-muted small mb-2">' + (d.tanggal || '') + '</p>';
                recs.forEach(function(r) {
                    var badge = r.priority === 'HIGH' ? 'danger' : (r.priority === 'MEDIUM' ? 'warning' : 'success');
                    html += '<div class="d-flex gap-2 mb-2"><span class="badge bg-' + badge + '">' + (r.priority || '') + '</span><span>' + (r.action || '') + '</span></div>';
                });
                html += '</div></div>';
            }
            html += '</div></div>';

            html += '<div class="card mb-3"><div class="card-header"><h6 class="mb-0"><i class="material-icons-outlined">videocam</i> CCTV di Area (' + cctvCount + ')</h6></div><div class="card-body small">';
            if (cctvCount === 0) {
                html += '<p class="text-muted mb-0">Tidak ada CCTV ditemukan di area ini.</p>';
            } else {
                (d.cctv_list || []).forEach(function(c) {
                    var status = (c.kondisi || c.status || '').toLowerCase();
                    var online = status === 'baik' || status === 'online' || (c.connected || '').toLowerCase() === 'yes';
                    html += '<div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2"><div><strong>' + (c.nama_cctv || c.no_cctv || 'CCTV') + '</strong><br><small class="text-muted">No: ' + (c.no_cctv || '-') + ' | <span style="color:' + (online ? '#22c55e' : '#dc2626') + '">' + (online ? 'Online' : 'Offline') + '</span></small>' + (c.lokasi ? '<br><small class="text-muted">' + c.lokasi + '</small>' : '') + '</div></div>';
                });
            }
            html += '</div></div>';

            html += '<div class="card mb-0"><div class="card-header"><h6 class="mb-0"><i class="material-icons-outlined">description</i> Laporan SAP Hari Ini (' + sapCount + ')</h6></div><div class="card-body small">';
            if (sapCount === 0) {
                html += '<p class="text-muted mb-0">Tidak ada laporan SAP hari ini di area ini.</p>';
            } else {
                (d.sap_list || []).forEach(function(s) {
                    html += '<div class="border-bottom pb-2 mb-2"><strong>' + (s.jenis_laporan || 'SAP') + '</strong> #' + (s.task_number || '-') + '<br><small class="text-muted">Lokasi: ' + (s.lokasi || s.detail_lokasi || '-') + '</small></div>';
                });
            }
            html += '</div></div>';

            $body.html(html);
        }).fail(function() {
            $body.html('<div class="alert alert-danger">Gagal memuat data.</div>');
        });
    });

    // ============================================
    // Intervensi Area Kerja
    // ============================================
    
    var intervensiModal = null;
    
    function initializePICSelect2() {
        var picSelect = $('#intervensiPICAreaKerja');
        if (picSelect.length === 0) return;
        
        if (picSelect.hasClass('select2-hidden-accessible')) {
            picSelect.select2('destroy');
        }
        
        picSelect.html('<option value="">Pilih PIC...</option>');
        picSelect.prop('disabled', false);
        
        picSelect.select2({
            theme: 'bootstrap-5',
            placeholder: 'Ketik untuk mencari PIC (Pengawas)...',
            allowClear: true,
            width: '100%',
            minimumInputLength: 0,
            ajax: {
                url: "{{ url('cctv-data-control-room/users') }}",
                type: 'GET',
                dataType: 'json',
                delay: 300,
                data: function(params) {
                    return { q: params.term || '', page: params.page || 1 };
                },
                processResults: function(data) {
                    if (data.success && data.data) {
                        var results = data.data.map(function(user) {
                            return {
                                id: user.id,
                                text: user.text || (user.username + ' - ' + user.nama),
                                username: user.username,
                                nama: user.nama
                            };
                        });
                        return { results: results, pagination: { more: false } };
                    }
                    return { results: data.results || [], pagination: { more: data.pagination && data.pagination.more } };
                },
                cache: false
            },
            dropdownParent: $('#intervensiAreaKerjaModal .modal-body'),
            language: {
                noResults: function() { return "Tidak ada hasil ditemukan"; },
                searching: function() { return "Mencari..."; },
                inputTooShort: function() { return "Ketik untuk mencari"; }
            }
        });
    }
    
    function loadCctvListForAreaKerja(lokasi) {
        var cctvSelect = $('#intervensiCCTVAreaKerja');
        if (cctvSelect.length === 0) return;
        
        if (cctvSelect.hasClass('select2-hidden-accessible')) {
            cctvSelect.select2('destroy');
        }
        
        cctvSelect.html('<option value="">Memuat CCTV...</option>');
        cctvSelect.prop('disabled', true);
        
        $.ajax({
            url: "{{ url('full-maps/api/cctv-for-area-kerja') }}",
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                cctvSelect.html('');
                if (data.success && data.data && data.data.length > 0) {
                    data.data.forEach(function(cctv) {
                        var text = cctv.nama_cctv + (cctv.no_cctv ? ' (' + cctv.no_cctv + ')' : '') + (cctv.lokasi_pemasangan ? ' - ' + cctv.lokasi_pemasangan : '');
                        cctvSelect.append('<option value="' + cctv.id + '">' + escapeHtml(text) + '</option>');
                    });
                    cctvSelect.prop('disabled', false);
                    
                    cctvSelect.select2({
                        theme: 'bootstrap-5',
                        placeholder: 'Pilih satu atau lebih CCTV...',
                        allowClear: true,
                        width: '100%',
                        closeOnSelect: false,
                        dropdownParent: $('#intervensiAreaKerjaModal .modal-body')
                    });
                } else {
                    cctvSelect.html('<option value="">Tidak ada CCTV ditemukan</option>');
                    cctvSelect.prop('disabled', false);
                }
            },
            error: function() {
                cctvSelect.html('<option value="">Error memuat CCTV</option>');
                cctvSelect.prop('disabled', false);
            }
        });
    }
    
    function openIntervensiModal(lokasi) {
        var lokasiValue = lokasi || '';
        
        $('#intervensiControlRoomAreaKerja').val(lokasiValue);
        $('#intervensiAreaKerja').val('');
        $('#intervensiLokasi').val(lokasiValue);
        $('#intervensiLokasiDisplay').val(lokasiValue);
        $('#intervensiIssueAreaKerja').val('');
        
        loadCctvListForAreaKerja(lokasiValue);
        
        if (!intervensiModal) {
            intervensiModal = new bootstrap.Modal(document.getElementById('intervensiAreaKerjaModal'));
        }
        
        $('#intervensiAreaKerjaModal').off('shown.bs.modal').on('shown.bs.modal', function() {
            setTimeout(function() {
                initializePICSelect2();
            }, 300);
        });
        
        intervensiModal.show();
    }
    
    // Handle click on intervensi button
    $(document).on('click', '.btn-intervensi', function(e) {
        e.preventDefault();
        var lokasi = $(this).data('lokasi') || '';
        openIntervensiModal(lokasi);
    });
    
    // Handle modal close to destroy Select2
    $('#intervensiAreaKerjaModal').on('hidden.bs.modal', function() {
        setTimeout(function() {
            var picSelect = $('#intervensiPICAreaKerja');
            if (picSelect.hasClass('select2-hidden-accessible')) {
                picSelect.select2('destroy');
            }
            var cctvSelect = $('#intervensiCCTVAreaKerja');
            if (cctvSelect.hasClass('select2-hidden-accessible')) {
                cctvSelect.select2('destroy');
            }
        }, 100);
    });
    
    // Handle submit intervensi form
    $('#submitIntervensiAreaKerjaBtn').on('click', function(e) {
        e.preventDefault();
        
        var controlRoom = $('#intervensiControlRoomAreaKerja').val();
        var lokasi = $('#intervensiLokasi').val();
        var picId = $('#intervensiPICAreaKerja').val();
        var issue = $('#intervensiIssueAreaKerja').val();
        var selectedCctvIds = $('#intervensiCCTVAreaKerja').val() || [];
        
        if (!controlRoom && !lokasi) {
            Swal.fire({ icon: 'warning', title: 'Peringatan!', text: 'Lokasi harus diisi.' });
            return;
        }
        
        if (selectedCctvIds.length === 0) {
            Swal.fire({ icon: 'warning', title: 'Peringatan!', text: 'Silakan pilih minimal 1 CCTV.' });
            return;
        }
        
        if (!picId) {
            Swal.fire({ icon: 'warning', title: 'Peringatan!', text: 'PIC (Pengawas) harus dipilih.' });
            return;
        }
        
        if (!issue || issue.trim() === '') {
            Swal.fire({ icon: 'warning', title: 'Peringatan!', text: 'Issue harus diisi.' });
            return;
        }
        
        var formData = {
            control_room: controlRoom || lokasi,
            cctv_ids: selectedCctvIds,
            pic_id: picId,
            issue: issue
        };
        
        var submitBtn = $(this);
        submitBtn.prop('disabled', true);
        submitBtn.html('<span class="spinner-border spinner-border-sm me-1"></span> Mengirim...');
        
        $.ajax({
            url: "{{ url('cctv-data-control-room/intervensi') }}",
            type: 'POST',
            dataType: 'json',
            contentType: 'application/json',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            data: JSON.stringify(formData),
            success: function(data) {
                if (data.success) {
                    var whatsappUrl = data.data?.whatsapp_url;
                    
                    if (whatsappUrl) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: data.message || 'Intervensi berhasil dikirim!',
                            showConfirmButton: true,
                            showCancelButton: true,
                            confirmButtonText: 'Buka WhatsApp',
                            cancelButtonText: 'Tutup',
                            confirmButtonColor: '#25D366'
                        }).then(function(result) {
                            if (intervensiModal) {
                                intervensiModal.hide();
                            }
                            $('#intervensiAreaKerjaForm')[0].reset();
                            
                            if (result.isConfirmed) {
                                window.open(whatsappUrl, '_blank');
                            }
                        });
                    } else {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: data.message || 'Intervensi berhasil dikirim!'
                        }).then(function() {
                            if (intervensiModal) {
                                intervensiModal.hide();
                            }
                            $('#intervensiAreaKerjaForm')[0].reset();
                        });
                    }
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal!',
                        text: data.message || 'Gagal mengirim intervensi.'
                    });
                }
            },
            error: function(xhr) {
                var errorMsg = 'Terjadi kesalahan saat mengirim intervensi.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                Swal.fire({ icon: 'error', title: 'Error!', text: errorMsg });
            },
            complete: function() {
                submitBtn.prop('disabled', false);
                submitBtn.html('<span class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">send</span> Kirim Intervensi');
            }
        });
    });

    // ============================================
    // Intervensi DOPM (untuk Critical Area IKK)
    // ============================================
    
    var modalApiUrl = "{{ route('dopmikk.api.ikk-modal-data') }}";
    var layer1UsersApiUrl = "{{ route('dopmikk.api.layer1-users') }}";
    var layers234UsersApiUrl = "{{ route('dopmikk.api.layers234-users') }}";
    var intervensiStoreUrl = "{{ route('dopmikk.api.alert-log-intervensi') }}";
    var updatePicUrl = "{{ route('dopmikk.api.update-intervensi-pic') }}";
    var ipkFormLink = 'https://beikk.beraucoal.co.id/monitoring-ipk';
    var intervensiDopmModalEl = document.getElementById('intervensiDopmModal');
    var intervensiDopmModal = intervensiDopmModalEl ? new bootstrap.Modal(intervensiDopmModalEl) : null;
    var currentIntervensiData = null;
    
    function tr(cells) {
        var row = document.createElement('tr');
        cells.forEach(function(c) {
            var td = document.createElement('td');
            td.textContent = c == null || c === undefined ? '—' : String(c);
            row.appendChild(td);
        });
        return row;
    }
    function safeStr(val, maxLen) {
        if (val == null || val === undefined) return '—';
        var s = String(val).trim();
        if (!s) return '—';
        if (maxLen && s.length > maxLen) s = s.substring(0, maxLen);
        return s;
    }
    function formatTs(ts) {
        if (!ts) return '—';
        var s = String(ts).trim();
        if (!s) return '—';
        var m = s.match(/^(\d{4})-(\d{2})-(\d{2})[T\s](\d{2}):(\d{2})/);
        if (m) return m[3] + '/' + m[2] + '/' + m[1] + ' ' + m[4] + ':' + m[5];
        return s;
    }
    function normalizeWaNumber(selular) {
        if (!selular || typeof selular !== 'string') return '';
        var s = selular.replace(/\s+/g, '').replace(/-/g, '');
        if (/^0\d+/.test(s)) return '62' + s.substring(1);
        if (!/^62/.test(s) && /^\d+/.test(s)) return '62' + s;
        return s;
    }
    
    function savePicAndOpenWa(waUrl, picUser) {
        if (!currentIntervensiData || !currentIntervensiData.kode_ikk) {
            window.open(waUrl, '_blank');
            return;
        }
        var formData = new FormData();
        formData.append('tanggal', currentIntervensiData.tanggal);
        formData.append('kode_ikk', currentIntervensiData.kode_ikk);
        formData.append('alert_level', currentIntervensiData.alert_level || 1);
        formData.append('pic_user_id', picUser.id || '');
        formData.append('pic_name', picUser.nama || picUser.username || '');
        formData.append('pic_email', picUser.email || '');
        fetch(updatePicUrl, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: formData
        }).finally(function() {
            window.open(waUrl, '_blank');
        });
    }
    
    // Handle click on intervensi DOPM button
    $(document).on('click', '.btn-intervensi-dopm', function(e) {
        e.preventDefault();
        if (!intervensiDopmModal) return;
        
        var dataStr = $(this).attr('data-dopm') || '{}';
        var data = {};
        try { data = JSON.parse(dataStr); } catch(e) { data = {}; }
        
        currentIntervensiData = { tanggal: data.tanggal_dop || '', kode_ikk: data.kode_ikk || '', alert_level: 1 };
        
        var namaLayer1 = (data.nama_layer_1 || '').trim();
        var sidLayer1 = (data.sid_layer_1 || '').trim();
        var hasLayer1 = sidLayer1 !== '' || namaLayer1 !== '';
        
        $('#intervensiDopmTitle').text((data.kode_ikk || 'Intervensi') + ' — ' + (data.nama_pekerjaan || 'DOPM').substring(0, 50));
        $('#intervensiDopmSubtitle').text('Kode IKK: ' + (data.kode_ikk || '—'));
        $('#intervensiBadgeIpk').text('0');
        $('#intervensiBadgeOkk').text('0');
        $('#intervensiBadgeOak').text('0');
        
        var raPjoName = (data.ra_pjo_name || '').trim();
        var hasKodeIkk = (data.kode_ikk || '').trim() !== '';
        
        if (raPjoName === '' && !hasKodeIkk) {
            $('#intervensiPjoWrap').addClass('d-none');
        } else {
            $('#intervensiPjoWrap').removeClass('d-none');
            $('#intervensiPjoNameDisplay').text(raPjoName || 'Memuat...');
            $('#intervensiPjoUsers').html('');
            $('#intervensiPjoEmpty').addClass('d-none');
            $('#intervensiPjoLoading').removeClass('d-none');
        }
        
        $('#intervensiIpkLoading').removeClass('d-none');
        $('#intervensiIpkEmpty').addClass('d-none');
        $('#intervensiIpkTableWrap').addClass('d-none');
        $('#intervensiOkkLoading').removeClass('d-none');
        $('#intervensiOkkEmpty').addClass('d-none');
        $('#intervensiOkkTableWrap').addClass('d-none');
        $('#intervensiOakLoading').removeClass('d-none');
        $('#intervensiOakEmpty').addClass('d-none');
        $('#intervensiOakTableWrap').addClass('d-none');
        
        [2, 3, 4].forEach(function(n) {
            $('#intervensiOakLayer' + n + 'Users').html('');
            $('#intervensiOakLayer' + n + 'Empty').addClass('d-none');
            $('#intervensiOakLayer' + n + 'Loading').removeClass('d-none');
            $('#intervensiOakLayer' + n + 'Name').text('—');
        });
        
        $('#intervensiLayer1Wrap').removeClass('d-none');
        $('#intervensiLayer1Users').html('');
        $('#intervensiLayer1NameDisplay').text(namaLayer1 || '—');
        $('#intervensiLayer1Empty').addClass('d-none');
        $('#intervensiLayer1NoName').addClass('d-none');
        $('#intervensiLayer1Loading').addClass('d-none');
        
        $('#intervensiOkkLayer1Wrap').removeClass('d-none');
        $('#intervensiOkkLayer1Users').html('');
        $('#intervensiOkkLayer1NameDisplay').text(namaLayer1 || '—');
        $('#intervensiOkkLayer1Empty').addClass('d-none');
        $('#intervensiOkkLayer1NoName').addClass('d-none');
        $('#intervensiOkkLayer1Loading').addClass('d-none');
        
        if (!hasLayer1) {
            $('#intervensiLayer1NoName').removeClass('d-none');
            $('#intervensiOkkLayer1NoName').removeClass('d-none');
        } else {
            $('#intervensiLayer1Loading').removeClass('d-none');
            $('#intervensiOkkLayer1Loading').removeClass('d-none');
        }
        
        // Store intervensi first
        var formData = new FormData();
        formData.append('tanggal', data.tanggal_dop || '');
        formData.append('kode_ikk', data.kode_ikk || '');
        formData.append('alert_level', 1);
        fetch(intervensiStoreUrl, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: formData
        }).catch(function() {});
        
        intervensiDopmModal.show();
        
        var params = new URLSearchParams({
            kode_ikk: data.kode_ikk || '',
            jenis_ijin_kerja_khusus: data.jenis_ijin_kerja_khusus || '',
            sid_layer_2: data.sid_layer_2 || '',
            sid_layer_3: data.sid_layer_3 || '',
            sid_layer_4: data.sid_layer_4 || '',
            nama_layer_2: data.nama_layer_2 || '',
            nama_layer_3: data.nama_layer_3 || '',
            nama_layer_4: data.nama_layer_4 || '',
            tanggal_dop: data.tanggal_dop || '',
            location_name: data.location_name || '',
            location_detail_name: data.location_detail_name || ''
        });
        
        // Fetch IPK, OKK, OAK data
        fetch(modalApiUrl + '?' + params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (!res || !res.success) throw new Error('Request failed');
                var ipkAll = res.ipk_ikk || [];
                var okkAll = res.okk || [];
                var oak = res.oak || [];
                
                function isLayer1Row(row) {
                    var raw = (row.employee_type !== undefined && row.employee_type !== null && row.employee_type !== '') ? row.employee_type : (row.layer_pengawas || '');
                    var lv = raw.toString().trim().toLowerCase();
                    return lv === 'layer 1' || lv === 'layer1' || lv === '1';
                }
                var ipk = ipkAll.filter(function(r) { if (r.employee_type === undefined && r.layer_pengawas === undefined) return true; return isLayer1Row(r); });
                var okk = okkAll.filter(function(r) { if (r.employee_type === undefined && r.layer_pengawas === undefined) return true; return isLayer1Row(r); });
                
                $('#intervensiBadgeIpk').text(ipk.length);
                $('#intervensiBadgeOkk').text(okk.length);
                $('#intervensiBadgeOak').text(oak.length);
                $('#intervensiIpkLoading').addClass('d-none');
                $('#intervensiOkkLoading').addClass('d-none');
                $('#intervensiOakLoading').addClass('d-none');
                
                if (ipk.length === 0) { $('#intervensiIpkEmpty').removeClass('d-none'); } else {
                    $('#intervensiIpkTableWrap').removeClass('d-none');
                    var tbody = document.querySelector('#intervensiTableIpk tbody');
                    if (tbody) { tbody.innerHTML = ''; ipk.forEach(function(r) { tbody.appendChild(tr([formatTs(r.ts), safeStr(r.nama_pengawas), safeStr(r.kode_sid), safeStr(r.kode_ikk), safeStr(r.nama_perusahaan, 40), safeStr(r.site), safeStr(r.durasi_jam), safeStr(r.cctv_terekam), safeStr(r.kategori_ijk, 35), safeStr(r.status_pekerjaan)])); }); }
                }
                if (okk.length === 0) { $('#intervensiOkkEmpty').removeClass('d-none'); } else {
                    $('#intervensiOkkTableWrap').removeClass('d-none');
                    var tbody = document.querySelector('#intervensiTableOkk tbody');
                    if (tbody) { tbody.innerHTML = ''; okk.forEach(function(r) { tbody.appendChild(tr([formatTs(r.ts), safeStr(r.nama_pengawas), safeStr(r.kode_sid), safeStr(r.kode_ikk), safeStr(r.nama_perusahaan, 40), safeStr(r.site), safeStr(r.jenis_ijk, 35), safeStr(r.layer_pengawas)])); }); }
                }
                if (oak.length === 0) { $('#intervensiOakEmpty').removeClass('d-none'); } else {
                    $('#intervensiOakTableWrap').removeClass('d-none');
                    var tbody = document.querySelector('#intervensiTableOak tbody');
                    if (tbody) { tbody.innerHTML = ''; oak.forEach(function(r) { tbody.appendChild(tr([safeStr(r.activity), safeStr(r.sub_activity), safeStr(r.submit_date), safeStr(r.submit_by), safeStr(r.kode_sid_pelapor), safeStr(r.location), safeStr(r.detail_location), safeStr(r.conclusion, 50), safeStr(r.site)])); }); }
                }
            })
            .catch(function() {
                $('#intervensiIpkLoading').addClass('d-none');
                $('#intervensiOkkLoading').addClass('d-none');
                $('#intervensiOakLoading').addClass('d-none');
                $('#intervensiIpkEmpty').removeClass('d-none');
                $('#intervensiOkkEmpty').removeClass('d-none');
                $('#intervensiOakEmpty').removeClass('d-none');
            });
        
        // Fetch Layer 1 users
        if (hasLayer1) {
            var qs = new URLSearchParams();
            if (sidLayer1) qs.set('sid_layer_1', sidLayer1);
            if (namaLayer1) qs.set('nama_layer_1', namaLayer1);
            fetch(layer1UsersApiUrl + '?' + qs.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    $('#intervensiLayer1Loading').addClass('d-none');
                    $('#intervensiOkkLayer1Loading').addClass('d-none');
                    var usersRaw = (res && res.success && res.users) ? res.users : [];
                    var seen = {}; var users = [];
                    usersRaw.forEach(function(u) {
                        var key = u.id ? ('id_' + u.id) : ('wa_' + normalizeWaNumber(u.selular));
                        if (key && !seen[key]) { seen[key] = true; users.push(u); }
                    });
                    var displayName = (res && res.nama_layer_1) ? res.nama_layer_1 : namaLayer1;
                    $('#intervensiLayer1NameDisplay').text(displayName || '—');
                    $('#intervensiOkkLayer1NameDisplay').text(displayName || '—');
                    
                    var ipkMsg = (displayName || 'PIC') + ', anda harus mengisi INSPEKSI PRA KERJA (IPK) untuk pekerjaan berikut:\n\nIKK: ' + (data.kode_ikk || '—') + (data.nama_pekerjaan ? ' - ' + data.nama_pekerjaan : '') + '\nHari: ' + (data.tanggal_dop || '—') + '\nLokasi: ' + (data.location_name || '—') + '\nDetail Lokasi: ' + (data.location_detail_name || '—') + '\n\n' + ipkFormLink;
                    var okkMsg = (displayName || 'PIC') + ', mohon perhatian untuk OBSERVASI KEGIATAN KERJA (OKK).\n\nIKK: ' + (data.kode_ikk || '—') + (data.nama_pekerjaan ? ' - ' + data.nama_pekerjaan : '') + '\nHari: ' + (data.tanggal_dop || '—') + '\nLokasi: ' + (data.location_name || '—');
                    
                    if (users.length === 0) {
                        $('#intervensiLayer1Empty').removeClass('d-none');
                        $('#intervensiOkkLayer1Empty').removeClass('d-none');
                        return;
                    }
                    
                    users.forEach(function(u) {
                        var num = normalizeWaNumber(u.selular); var label = u.nama || u.username || 'User';
                        if (!num) return;
                        var btn = $('<button type="button" class="btn btn-sm btn-success"></button>');
                        btn.html('<i class="material-icons-outlined me-1" style="font-size:16px;">send</i> WA (IPK) → ' + label);
                        btn.on('click', function() { savePicAndOpenWa('https://wa.me/' + num + '?text=' + encodeURIComponent(ipkMsg), u); });
                        $('#intervensiLayer1Users').append(btn);
                    });
                    users.forEach(function(u) {
                        var num = normalizeWaNumber(u.selular); var label = u.nama || u.username || 'User';
                        if (!num) return;
                        var btn = $('<button type="button" class="btn btn-sm btn-success"></button>');
                        btn.html('<i class="material-icons-outlined me-1" style="font-size:16px;">send</i> WA (OKK) → ' + label);
                        btn.on('click', function() { savePicAndOpenWa('https://wa.me/' + num + '?text=' + encodeURIComponent(okkMsg), u); });
                        $('#intervensiOkkLayer1Users').append(btn);
                    });
                })
                .catch(function() {
                    $('#intervensiLayer1Loading').addClass('d-none');
                    $('#intervensiLayer1Empty').removeClass('d-none');
                    $('#intervensiOkkLayer1Loading').addClass('d-none');
                    $('#intervensiOkkLayer1Empty').removeClass('d-none');
                });
        }
        
        // Fetch Layer 2, 3, 4 users
        var qs234 = new URLSearchParams();
        qs234.set('sid_layer_2', data.sid_layer_2 || ''); qs234.set('sid_layer_3', data.sid_layer_3 || ''); qs234.set('sid_layer_4', data.sid_layer_4 || '');
        qs234.set('nama_layer_2', data.nama_layer_2 || ''); qs234.set('nama_layer_3', data.nama_layer_3 || ''); qs234.set('nama_layer_4', data.nama_layer_4 || '');
        fetch(layers234UsersApiUrl + '?' + qs234.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                [2, 3, 4].forEach(function(n) {
                    var key = 'layer_' + n;
                    var layerData = res && res[key] ? res[key] : { users: [], nama_layer: '' };
                    var usersRaw = layerData.users || [];
                    var seen = {}; var users = [];
                    usersRaw.forEach(function(u) {
                        var k = u.id ? ('id_' + u.id) : ('wa_' + normalizeWaNumber(u.selular));
                        if (k && !seen[k]) { seen[k] = true; users.push(u); }
                    });
                    var displayName = layerData.nama_layer || '—';
                    $('#intervensiOakLayer' + n + 'Loading').addClass('d-none');
                    $('#intervensiOakLayer' + n + 'Name').text(displayName);
                    
                    var oakMsg = (displayName !== '—' ? displayName : 'PIC') + ', mohon perhatian untuk OAK (Observasi Aktivitas Kerja) sesuai IKK ini.';
                    users.forEach(function(u) {
                        var num = normalizeWaNumber(u.selular); if (!num) return;
                        var label = u.nama || u.username || 'User';
                        var btn = $('<button type="button" class="btn btn-sm btn-warning text-dark"></button>');
                        btn.html('<i class="material-icons-outlined me-1" style="font-size:14px;">send</i> WA');
                        btn.attr('title', label);
                        btn.on('click', function() { savePicAndOpenWa('https://wa.me/' + num + '?text=' + encodeURIComponent(oakMsg), u); });
                        $('#intervensiOakLayer' + n + 'Users').append(btn);
                    });
                    if (users.length === 0) $('#intervensiOakLayer' + n + 'Empty').removeClass('d-none');
                });
            })
            .catch(function() {
                [2, 3, 4].forEach(function(n) {
                    $('#intervensiOakLayer' + n + 'Loading').addClass('d-none');
                    $('#intervensiOakLayer' + n + 'Empty').removeClass('d-none');
                });
            });
        
        // Fetch PJO
        if (raPjoName !== '') {
            var pjoQs = new URLSearchParams({ nama_layer_1: raPjoName });
            fetch(layer1UsersApiUrl + '?' + pjoQs.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    $('#intervensiPjoLoading').addClass('d-none');
                    var users = (res && res.success && res.users) ? res.users : [];
                    var intervensiMsg = 'Assalamu\'alaikum. Intervensi DOPM/IKK: mohon perhatian untuk kelengkapan IPK-IKK, OKK, dan OAK. Terima kasih.';
                    users.forEach(function(u) {
                        var num = normalizeWaNumber(u.selular || ''); if (!num) return;
                        var nama = (u.nama || u.username || 'PJO').trim();
                        var btn = $('<button type="button" class="btn btn-sm btn-outline-success"></button>');
                        btn.html('<i class="material-icons-outlined me-1" style="font-size:16px;">send</i> WA ke ' + (nama.length > 25 ? nama.substring(0, 22) + '...' : nama));
                        btn.on('click', function() { savePicAndOpenWa('https://wa.me/' + num + '?text=' + encodeURIComponent(intervensiMsg), u); });
                        $('#intervensiPjoUsers').append(btn);
                    });
                    if (users.length === 0) $('#intervensiPjoEmpty').removeClass('d-none');
                })
                .catch(function() { $('#intervensiPjoLoading').addClass('d-none'); $('#intervensiPjoEmpty').removeClass('d-none'); });
        } else if (hasKodeIkk) {
            var apiParams = new URLSearchParams({ kode_ikk: data.kode_ikk || '', jenis_ijin_kerja_khusus: data.jenis_ijin_kerja_khusus || '', location_name: data.location_name || '', location_detail_name: data.location_detail_name || '', tanggal_dop: data.tanggal_dop || '' });
            fetch(modalApiUrl + '?' + apiParams.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    var name = (res && res.ra_pjo_name) ? String(res.ra_pjo_name).trim() : '';
                    $('#intervensiPjoNameDisplay').text(name || '—');
                    $('#intervensiPjoLoading').addClass('d-none');
                    if (name !== '') {
                        var pjoQs2 = new URLSearchParams({ nama_layer_1: name });
                        fetch(layer1UsersApiUrl + '?' + pjoQs2.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                            .then(function(r2) { return r2.json(); })
                            .then(function(res2) {
                                var users = (res2 && res2.success && res2.users) ? res2.users : [];
                                var intervensiMsg = 'Assalamu\'alaikum. Intervensi DOPM/IKK: mohon perhatian untuk kelengkapan IPK-IKK, OKK, dan OAK. Terima kasih.';
                                users.forEach(function(u) {
                                    var num = normalizeWaNumber(u.selular || ''); if (!num) return;
                                    var nama = (u.nama || u.username || 'PJO').trim();
                                    var btn = $('<button type="button" class="btn btn-sm btn-outline-success"></button>');
                                    btn.html('<i class="material-icons-outlined me-1" style="font-size:16px;">send</i> WA ke ' + (nama.length > 25 ? nama.substring(0, 22) + '...' : nama));
                                    btn.on('click', function() { savePicAndOpenWa('https://wa.me/' + num + '?text=' + encodeURIComponent(intervensiMsg), u); });
                                    $('#intervensiPjoUsers').append(btn);
                                });
                                if (users.length === 0) $('#intervensiPjoEmpty').removeClass('d-none');
                            });
                    } else {
                        $('#intervensiPjoEmpty').text('Tidak ada data PJO untuk IKK ini.').removeClass('d-none');
                    }
                })
                .catch(function() { $('#intervensiPjoLoading').addClass('d-none'); $('#intervensiPjoEmpty').text('Gagal memuat data PJO.').removeClass('d-none'); });
        }
    });
});
</script>
@endsection

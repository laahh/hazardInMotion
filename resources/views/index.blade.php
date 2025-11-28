@extends('layouts.master')

@section('title', 'Matoxi')

@section('css')
<link href="{{ URL::asset('build/plugins/datatable/css/dataTables.bootstrap5.min.css') }}" rel="stylesheet" />
@endsection

@section('content')
    <x-page-title title="Dashboard" pagetitle="Matoxi" />

        <div class="row">
          <div class="col-12 col-xl-4 d-flex">
             <div class="card rounded-4 w-100">
               <div class="card-body">
                 <div class="d-flex align-items-center gap-3 mb-2">
                    <div class="">
                      <h2 class="mb-0">{{ number_format($totalYtdInsiden ?? 0) }}</h2>
                    </div>
                    <div class="">
                      <p class="dash-lable d-flex align-items-center gap-1 rounded mb-0 {{ ($ytdInsidenChange ?? 0) >= 0 ? 'bg-success text-success' : 'bg-danger text-danger' }} bg-opacity-10">
                        <span class="material-icons-outlined fs-6">{{ ($ytdInsidenChange ?? 0) >= 0 ? 'arrow_upward' : 'arrow_downward' }}</span>{{ abs($ytdInsidenChange ?? 0) }}%
                      </p>
                    </div>
                  </div>
                  <p class="mb-0">Total YTD Insiden</p>
                   <div id="chart1"></div>
               </div>
             </div>
          </div>
          <div class="col-12 col-xl-8 d-flex">
            <div class="card rounded-4 w-100">
              <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                  <h5 class="mb-0 fw-bold">Statistik CCTV</h5>
                  <span class="badge bg-primary">{{ number_format($coveragePercentage ?? 0) }}% Coverage</span>
                </div>
                <div class="d-flex align-items-center justify-content-around flex-wrap gap-4 p-4">
                  <button type="button" class="btn p-0 border-0 bg-transparent d-flex flex-column align-items-center justify-content-center gap-2" data-bs-toggle="modal" data-bs-target="#totalCctvModal" title="Lihat detail Total CCTV">
                    <span class="mb-2 wh-48 bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center">
                      <i class="material-icons-outlined">videocam</i>
                    </span>
                    <h3 class="mb-0">{{ number_format($totalCctv ?? 0) }}</h3>
                    <p class="mb-0">Total CCTV</p>
                  </button>
                  <div class="vr"></div>
                  <button type="button" class="btn p-0 border-0 bg-transparent d-flex flex-column align-items-center justify-content-center gap-2" data-bs-toggle="modal" data-bs-target="#cctvOnModal" title="Lihat detail CCTV aktif (On)">
                    <span class="mb-2 wh-48 bg-success bg-opacity-10 text-success rounded-circle d-flex align-items-center justify-content-center">
                      <i class="material-icons-outlined">power</i>
                    </span>
                    <h3 class="mb-0">{{ number_format($cctvOn ?? 0) }}</h3>
                    <p class="mb-0">CCTV On</p>
                    <small class="text-muted">{{ $totalCctv > 0 ? round(($cctvOn / $totalCctv) * 100, 1) : 0 }}%</small>
                  </button>
                  <div class="vr"></div>
                  <button type="button" class="btn p-0 border-0 bg-transparent d-flex flex-column align-items-center justify-content-center gap-2" data-bs-toggle="modal" data-bs-target="#cctvOffModal" title="Lihat detail CCTV nonaktif (Off)">
                    <span class="mb-2 wh-48 bg-danger bg-opacity-10 text-danger rounded-circle d-flex align-items-center justify-content-center">
                      <i class="material-icons-outlined">power_off</i>
                    </span>
                    <h3 class="mb-0">{{ number_format($cctvOff ?? 0) }}</h3>
                    <p class="mb-0">CCTV Off</p>
                    <small class="text-muted">{{ $totalCctv > 0 ? round(($cctvOff / $totalCctv) * 100, 1) : 0 }}%</small>
                  </button>
                  <div class="vr"></div>
                  <button type="button" class="btn p-0 border-0 bg-transparent d-flex flex-column align-items-center justify-content-center gap-2" data-bs-toggle="modal" data-bs-target="#criticalCctvModal" title="Lihat detail area kritis">
                    <span class="mb-2 wh-48 bg-warning bg-opacity-10 text-warning rounded-circle d-flex align-items-center justify-content-center">
                      <i class="material-icons-outlined">warning</i>
                    </span>
                    <h3 class="mb-0">{{ number_format($criticalAreas ?? 0) }}</h3>
                    <p class="mb-0">Area Kritis</p>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div><!--end row-->
        
        {{-- Statistik CCTV Detail --}}
        <div class="row">
          <div class="col-12 col-xl-6 d-flex">
            <div class="card rounded-4 w-100">
              <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                  <div>
                    <h5 class="mb-0 fw-bold">Coverage Area CCTV</h5>
                    <p class="mb-0 text-muted small">Persentase coverage berdasarkan lokasi</p>
                  </div>
                </div>
                @if(!empty($coverageByLocation) && $coverageByLocation->count() > 0)
                  <div class="d-flex flex-column gap-3">
                    @foreach($coverageByLocation->take(5) as $coverage)
                    <div>
                      <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="fw-semibold">{{ $coverage['location'] ?? 'Lokasi Tidak Diketahui' }}</span>
                        <span class="badge bg-primary">{{ $coverage['percentage'] }}%</span>
                      </div>
                      <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $coverage['percentage'] }}%" aria-valuenow="{{ $coverage['percentage'] }}" aria-valuemin="0" aria-valuemax="100"></div>
                      </div>
                      <small class="text-muted">{{ $coverage['count'] }} CCTV</small>
                    </div>
                    @endforeach
                  </div>
                @else
                  <p class="text-muted text-center py-4">Tidak ada data coverage area</p>
                @endif
              </div>
            </div>
          </div>
          
          <div class="col-12 col-xl-6 d-flex">
            <div class="card rounded-4 w-100">
              <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                  <div>
                    <h5 class="mb-0 fw-bold">Distribusi CCTV per Site</h5>
                    <p class="mb-0 text-muted small">Jumlah CCTV berdasarkan site</p>
                  </div>
                </div>
                @if(!empty($distributionBySite) && $distributionBySite->count() > 0)
                  <div class="d-flex flex-column gap-3">
                    @foreach($distributionBySite->take(5) as $site)
                    <div class="d-flex align-items-center gap-3">
                      <div class="wh-48 bg-info bg-opacity-10 text-info rounded-circle d-flex align-items-center justify-content-center">
                        <i class="material-icons-outlined">business</i>
                      </div>
                      <div class="flex-grow-1">
                        <h6 class="mb-0 fw-semibold">{{ $site['site'] ?? 'Site Tidak Diketahui' }}</h6>
                        <p class="mb-0 text-muted small">{{ $site['count'] }} CCTV ({{ $site['percentage'] }}%)</p>
                      </div>
                      <div class="text-end">
                        <h5 class="mb-0 text-primary">{{ $site['count'] }}</h5>
                      </div>
                    </div>
                    @endforeach
                  </div>
                @else
                  <p class="text-muted text-center py-4">Tidak ada data distribusi site</p>
                @endif
              </div>
            </div>
          </div>
        </div><!--end row-->
        
        {{-- Analisis & Insight CCTV --}}
        <div class="row">
          <div class="col-12 col-xl-4 d-flex">
            <div class="card rounded-4 w-100">
              <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                  <div>
                    <h5 class="mb-0 fw-bold">Status CCTV</h5>
                    <p class="mb-0 text-muted small">Breakdown status operasional</p>
                  </div>
                </div>
                @if(!empty($statusBreakdown) && $statusBreakdown->count() > 0)
                  <div class="d-flex flex-column gap-3">
                    @foreach($statusBreakdown as $status)
                    <div class="d-flex align-items-center justify-content-between">
                      <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-{{ $status['status'] == 'Live View' ? 'success' : 'secondary' }}">{{ $status['status'] }}</span>
                      </div>
                      <div class="text-end">
                        <h6 class="mb-0">{{ $status['count'] }}</h6>
                        <small class="text-muted">{{ $totalCctv > 0 ? round(($status['count'] / $totalCctv) * 100, 1) : 0 }}%</small>
                      </div>
                    </div>
                    @endforeach
                  </div>
                @else
                  <p class="text-muted text-center py-4">Tidak ada data status</p>
                @endif
              </div>
            </div>
          </div>
          
          <div class="col-12 col-xl-4 d-flex">
            <div class="card rounded-4 w-100">
              <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                  <div>
                    <h5 class="mb-0 fw-bold">Kondisi CCTV</h5>
                    <p class="mb-0 text-muted small">Breakdown kondisi perangkat</p>
                  </div>
                </div>
                @if(!empty($kondisiBreakdown) && $kondisiBreakdown->count() > 0)
                  <div class="d-flex flex-column gap-3">
                    @foreach($kondisiBreakdown as $kondisi)
                    <div class="d-flex align-items-center justify-content-between">
                      <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-{{ $kondisi['kondisi'] == 'Baik' ? 'success' : ($kondisi['kondisi'] == 'Rusak' ? 'danger' : 'warning') }}">{{ $kondisi['kondisi'] }}</span>
                      </div>
                      <div class="text-end">
                        <h6 class="mb-0">{{ $kondisi['count'] }}</h6>
                        <small class="text-muted">{{ $totalCctv > 0 ? round(($kondisi['count'] / $totalCctv) * 100, 1) : 0 }}%</small>
                      </div>
                    </div>
                    @endforeach
                  </div>
                @else
                  <p class="text-muted text-center py-4">Tidak ada data kondisi</p>
                @endif
              </div>
            </div>
          </div>
          
          <div class="col-12 col-xl-4 d-flex">
            <div class="card rounded-4 w-100">
              <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                  <div>
                    <h5 class="mb-0 fw-bold">Insight & Analisis</h5>
                    <p class="mb-0 text-muted small">Analisis data CCTV</p>
                  </div>
                </div>
                <div class="d-flex flex-column gap-3">
                  <div class="border rounded-3 p-3 bg-light">
                    <div class="d-flex align-items-center gap-2 mb-2">
                      <i class="material-icons-outlined text-primary">insights</i>
                      <h6 class="mb-0">Coverage Rate</h6>
                    </div>
                    <p class="mb-0 small">Sistem CCTV memiliki coverage rate sebesar <strong>{{ $coveragePercentage ?? 0 }}%</strong> dari total perangkat.</p>
                  </div>
                  
                  @if(!empty($topCoverageArea))
                  <div class="border rounded-3 p-3 bg-light">
                    <div class="d-flex align-items-center gap-2 mb-2">
                      <i class="material-icons-outlined text-success">location_on</i>
                      <h6 class="mb-0">Top Coverage Area</h6>
                    </div>
                    <p class="mb-0 small"><strong>{{ $topCoverageArea['location'] }}</strong> memiliki coverage tertinggi dengan <strong>{{ $topCoverageArea['count'] }} CCTV</strong> ({{ $topCoverageArea['percentage'] }}%).</p>
                  </div>
                  @endif
                  
                  @if(!empty($topSite))
                  <div class="border rounded-3 p-3 bg-light">
                    <div class="d-flex align-items-center gap-2 mb-2">
                      <i class="material-icons-outlined text-info">business</i>
                      <h6 class="mb-0">Top Site</h6>
                    </div>
                    <p class="mb-0 small">Site <strong>{{ $topSite['site'] }}</strong> memiliki CCTV terbanyak dengan <strong>{{ $topSite['count'] }} unit</strong> ({{ $topSite['percentage'] }}%).</p>
                  </div>
                  @endif
                  
                  <div class="border rounded-3 p-3 bg-light">
                    <div class="d-flex align-items-center gap-2 mb-2">
                      <i class="material-icons-outlined text-warning">security</i>
                      <h6 class="mb-0">Area Kritis</h6>
                    </div>
                    <p class="mb-0 small">Terdapat <strong>{{ $criticalAreas ?? 0 }} CCTV</strong> yang mengcover area kritis untuk monitoring keamanan.</p>
                  </div>
                  
                  <div class="border rounded-3 p-3 bg-light">
                    <div class="d-flex align-items-center gap-2 mb-2">
                      <i class="material-icons-outlined text-danger">link</i>
                      <h6 class="mb-0">Akses Remote</h6>
                    </div>
                    <p class="mb-0 small"><strong>{{ $cctvWithAccess ?? 0 }} CCTV</strong> memiliki link akses untuk monitoring remote ({{ $totalCctv > 0 ? round(($cctvWithAccess / $totalCctv) * 100, 1) : 0 }}%).</p>
                  </div>
                  
                  @if($cctvWithAutoAlert > 0)
                  <div class="border rounded-3 p-3 bg-light">
                    <div class="d-flex align-items-center gap-2 mb-2">
                      <i class="material-icons-outlined text-success">notifications_active</i>
                      <h6 class="mb-0">Auto Alert</h6>
                    </div>
                    <p class="mb-0 small"><strong>{{ $cctvWithAutoAlert }} CCTV</strong> dilengkapi dengan fitur auto alert untuk deteksi otomatis.</p>
                  </div>
                  @endif
                </div>
              </div>
            </div>
          </div>
        </div><!--end row-->
        
        {{-- Modal Detail Statistik CCTV --}}
        <div class="modal fade" id="totalCctvModal" tabindex="-1" aria-labelledby="totalCctvModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content rounded-4">
              <div class="modal-header">
                <div>
                  <h5 class="modal-title fw-bold" id="totalCctvModalLabel">Detail Total CCTV</h5>
                  <p class="mb-0 text-muted small">Distribusi per perusahaan & sampel perangkat</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <div class="row g-4">
                  <div class="col-12 col-lg-5">
                    <div class="border rounded-4 p-3 h-100">
                      <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="mb-0 fw-bold">Overview Perusahaan</h6>
                        <span class="badge bg-primary bg-opacity-10 text-primary">{{ $companyOverview->count() }} perusahaan</span>
                      </div>
                      @if($companyOverview->count() > 0)
                        <div class="table-responsive">
                          <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                              <tr>
                                <th>Perusahaan</th>
                                <th class="text-end">Total</th>
                                <th class="text-end">Aktif</th>
                                <th class="text-end">Off</th>
                              </tr>
                            </thead>
                            <tbody>
                              @foreach($companyOverview as $company)
                              @php
                                $companyName = trim($company['company'] ?? 'Tidak Diketahui');
                                $companyKey = $company['company_key'] ?? strtolower(preg_replace('/\s+/', '', $companyName));
                              @endphp
                              <tr class="company-row-trigger" role="button" tabindex="0" data-company="{{ $companyName }}" data-company-key="{{ $companyKey }}">
                                <td>
                                  <span class="fw-semibold d-block">{{ $companyName }}</span>
                                  <small class="text-muted">{{ $company['percentage'] }}% dari total</small>
                                </td>
                                <td class="text-end fw-bold">{{ number_format($company['total']) }}</td>
                                <td class="text-end text-success">{{ number_format($company['active']) }}</td>
                                <td class="text-end text-danger">{{ number_format($company['inactive']) }}</td>
                              </tr>
                              @endforeach
                            </tbody>
                          </table>
                        </div>
                      @else
                        <p class="text-muted text-center py-4 mb-0">Tidak ada data perusahaan.</p>
                      @endif
                    </div>
                  </div>
                  <div class="col-12 col-lg-7">
                    <div class="border rounded-4 p-3 h-100 d-flex flex-column">
                      <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                        <div>
                          <h6 class="mb-0 fw-bold">Data CCTV</h6>
                          <small class="text-muted" id="companyCctvCompanyLabel">Pilih perusahaan untuk melihat rincian</small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                          <span class="badge bg-secondary bg-opacity-10 text-secondary" id="companyCctvCount">0 CCTV</span>
                          <button type="button" class="btn btn-sm btn-outline-secondary" id="resetCompanyFilter">
                            <i class="material-icons-outlined" style="font-size:16px;">refresh</i> Reset
                          </button>
                        </div>
                      </div>
                      
                      {{-- Statistik Card --}}
                      <div class="row g-2 mb-3" id="companyStatsCards">
                        <div class="col-4">
                          <div class="card border-0 bg-success bg-opacity-10 rounded-3">
                            <div class="card-body p-2 text-center">
                              <h5 class="mb-0 fw-bold text-success" id="companyStatsAktif">0</h5>
                              <small class="text-muted">Aktif</small>
                            </div>
                          </div>
                        </div>
                        <div class="col-4">
                          <div class="card border-0 bg-danger bg-opacity-10 rounded-3">
                            <div class="card-body p-2 text-center">
                              <h5 class="mb-0 fw-bold text-danger" id="companyStatsNonAktif">0</h5>
                              <small class="text-muted">Non Aktif</small>
                            </div>
                          </div>
                        </div>
                        <div class="col-4">
                          <div class="card border-0 bg-warning bg-opacity-10 rounded-3">
                            <div class="card-body p-2 text-center">
                              <h5 class="mb-0 fw-bold text-warning" id="companyStatsAreaKritis">0</h5>
                              <small class="text-muted">Area Kritis</small>
                            </div>
                          </div>
                        </div>
                      </div>
                      
                      <div class="table-responsive flex-grow-1" style="max-height: 400px; overflow-x: auto; overflow-y: auto;">
                        <table class="table table-striped table-hover align-middle" id="companyCctvTable" style="width: 100%; min-width: 1200px;">
                          <thead class="table-light sticky-top">
                            <tr>
                              <th style="min-width: 50px;">No</th>
                              <th style="min-width: 100px;">Site</th>
                              <th style="min-width: 150px;">Perusahaan</th>
                              <th style="min-width: 120px;">No CCTV</th>
                              <th style="min-width: 150px;">Nama</th>
                              <th style="min-width: 100px;">Status</th>
                              <th style="min-width: 100px;">Kondisi</th>
                              <th style="min-width: 150px;">Coverage Lokasi</th>
                              <th style="min-width: 150px;">Detail Lokasi</th>
                              <th style="min-width: 150px;">Kategori Area</th>
                              <th style="min-width: 150px;">Lokasi Pemasangan</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr>
                              <td colspan="11" class="text-center text-muted">Klik perusahaan di tabel sebelah kiri untuk menampilkan daftar CCTV.</td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="modal fade" id="cctvOnModal" tabindex="-1" aria-labelledby="cctvOnModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content rounded-4">
              <div class="modal-header">
                <div>
                  <h5 class="modal-title fw-bold" id="cctvOnModalLabel">Detail CCTV On</h5>
                  <p class="mb-0 text-muted small">Daftar perangkat dengan status Live View atau kondisi Baik</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                @if($activeCctvRecords->count() > 0)
                <div class="table-responsive">
                  <table class="table table-striped table-hover align-middle">
                    <thead class="table-light">
                      <tr>
                        <th>Site</th>
                        <th>Perusahaan</th>
                        <th>No CCTV</th>
                        <th>Nama</th>
                        <th>Status</th>
                        <th>Kondisi</th>
                        <th>Coverage</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach($activeCctvRecords as $item)
                      <tr>
                        <td>{{ $item->site ?? '-' }}</td>
                        <td>{{ $item->perusahaan ?? '-' }}</td>
                        <td class="fw-semibold text-success">{{ $item->no_cctv ?? '-' }}</td>
                        <td>{{ $item->nama_cctv ?? '-' }}</td>
                        <td><span class="badge bg-success">{{ $item->status ?? 'Live View' }}</span></td>
                        <td><span class="badge bg-success">{{ $item->kondisi ?? 'Baik' }}</span></td>
                        <td>{{ $item->coverage_lokasi ?? '-' }}</td>
                      </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
                @else
                  <p class="text-muted text-center py-4 mb-0">Belum ada data CCTV yang aktif.</p>
                @endif
              </div>
            </div>
          </div>
        </div>

        <div class="modal fade" id="cctvOffModal" tabindex="-1" aria-labelledby="cctvOffModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content rounded-4">
              <div class="modal-header">
                <div>
                  <h5 class="modal-title fw-bold" id="cctvOffModalLabel">Detail CCTV Off</h5>
                  <p class="mb-0 text-muted small">Daftar perangkat yang tidak memenuhi kriteria aktif</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                @if($offlineCctvRecords->count() > 0)
                <div class="table-responsive">
                  <table class="table table-striped table-hover align-middle">
                    <thead class="table-light">
                      <tr>
                        <th>Site</th>
                        <th>Perusahaan</th>
                        <th>No CCTV</th>
                        <th>Nama</th>
                        <th>Status</th>
                        <th>Kondisi</th>
                        <th>Coverage</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach($offlineCctvRecords as $item)
                      <tr>
                        <td>{{ $item->site ?? '-' }}</td>
                        <td>{{ $item->perusahaan ?? '-' }}</td>
                        <td class="fw-semibold text-danger">{{ $item->no_cctv ?? '-' }}</td>
                        <td>{{ $item->nama_cctv ?? '-' }}</td>
                        <td>
                          <span class="badge bg-{{ ($item->status === 'Live View') ? 'success' : 'secondary' }}">{{ $item->status ?? 'Tidak Aktif' }}</span>
                        </td>
                        <td>
                          <span class="badge bg-{{ ($item->kondisi === 'Baik') ? 'success' : 'warning' }}">{{ $item->kondisi ?? 'Perlu Perhatian' }}</span>
                        </td>
                        <td>{{ $item->coverage_lokasi ?? '-' }}</td>
                      </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
                @else
                  <p class="text-muted text-center py-4 mb-0">Semua CCTV berada pada kondisi aktif.</p>
                @endif
              </div>
            </div>
          </div>
        </div>

        <div class="modal fade" id="criticalCctvModal" tabindex="-1" aria-labelledby="criticalCctvModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content rounded-4">
              <div class="modal-header">
                <div>
                  <h5 class="modal-title fw-bold" id="criticalCctvModalLabel">Detail Area Kritis</h5>
                  <p class="mb-0 text-muted small">CCTV yang memantau area kritis / high risk</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                @if($criticalCctvRecords->count() > 0)
                <div class="table-responsive">
                  <table class="table table-striped table-hover align-middle">
                    <thead class="table-light">
                      <tr>
                        <th>Site</th>
                        <th>Perusahaan</th>
                        <th>No CCTV</th>
                        <th>Nama</th>
                        <th>Coverage</th>
                        <th>Kategori Area</th>
                        <th>Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach($criticalCctvRecords as $item)
                      <tr>
                        <td>{{ $item->site ?? '-' }}</td>
                        <td>{{ $item->perusahaan ?? '-' }}</td>
                        <td class="fw-semibold text-warning">{{ $item->no_cctv ?? '-' }}</td>
                        <td>{{ $item->nama_cctv ?? '-' }}</td>
                        <td>{{ $item->coverage_lokasi ?? '-' }}</td>
                        <td>{{ $item->kategori_area_tercapture ?? 'Area Kritis' }}</td>
                        <td>
                          <span class="badge bg-{{ ($item->status === 'Live View') ? 'success' : 'secondary' }}">{{ $item->status ?? 'N/A' }}</span>
                        </td>
                      </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
                @else
                  <p class="text-muted text-center py-4 mb-0">Belum ada CCTV yang ditandai sebagai area kritis.</p>
                @endif
              </div>
            </div>
          </div>
        </div>
        
        {{-- @if(!empty($coverageSummary['data']))
        <div class="row">
          <div class="col-12">
            <div class="card rounded-4">
              <div class="card-header bg-transparent border-0 pb-0">
                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 my-3">
                  <div>
                    <h5 class="mb-1 fw-bold">Profil Coverage CCTV & Laporan Hazard</h5>
                    <p class="mb-0 text-muted small">Data CCTV dari MySQL & laporan hazard dari PostgreSQL</p>
                  </div>
                  <form method="GET" action="{{ url('/') }}" class="w-100 w-lg-auto">
                    <div class="input-group input-group-sm">
                      <input type="text"
                             class="form-control"
                             placeholder="Cari site, nomor CCTV, perusahaan, atau lokasi..."
                             name="coverage_search"
                             value="{{ $coverageSearch ?? '' }}">
                      <input type="hidden" name="coverage_page" value="1">
                      <button class="btn btn-primary" type="submit">
                        <i class="material-icons-outlined" style="font-size: 18px;">search</i>
                      </button>
                      @if(!empty($coverageSearch))
                      <a class="btn btn-outline-secondary" href="{{ url('/') }}">
                        <i class="material-icons-outlined" style="font-size: 18px;">refresh</i>
                      </a>
                      @endif
                    </div>
                  </form>
                </div>
              </div>
              <div class="card-body">
                <div class="table-responsive">
                  <table class="table table-striped table-hover align-middle mb-0">
                    <thead class="table-light text-uppercase small text-muted">
                      <tr>
                        <th class="text-nowrap">
                          <div class="d-flex align-items-center gap-2">
                            <span class="material-icons-outlined text-primary">format_list_numbered</span>
                            <div>
                              <div class="fw-semibold text-dark">No.</div>
                              <div class="text-muted small">Urutan</div>
                            </div>
                          </div>
                        </th>
                        <th class="text-nowrap">
                          <div class="d-flex align-items-center gap-2">
                            <span class="material-icons-outlined text-primary">confirmation_number</span>
                            <div>
                              <div class="fw-semibold text-dark">Nomor CCTV</div>
                              <div class="text-muted small">ID perangkat</div>
                            </div>
                          </div>
                        </th>
                        <th class="text-nowrap">
                          <div class="d-flex align-items-center gap-2">
                            <span class="material-icons-outlined text-primary">corporate_fare</span>
                            <div>
                              <div class="fw-semibold text-dark">Perusahaan</div>
                              <div class="text-muted small">Pemilik/penanggung jawab</div>
                            </div>
                          </div>
                        </th>
                        <th class="text-nowrap">
                          <div class="d-flex align-items-center gap-2">
                            <span class="material-icons-outlined text-primary">videocam</span>
                            <div>
                              <div class="fw-semibold text-dark">Nama CCTV</div>
                              <div class="text-muted small">Alias lokasi</div>
                            </div>
                          </div>
                        </th>
                        <th class="text-nowrap">
                          <div class="d-flex align-items-center gap-2">
                            <span class="material-icons-outlined text-primary">map</span>
                            <div>
                              <div class="fw-semibold text-dark">Lokasi Coverage</div>
                              <div class="text-muted small">Area pemantauan</div>
                            </div>
                          </div>
                        </th>
                        <th class="text-nowrap">
                          <div class="d-flex align-items-center gap-2">
                            <span class="material-icons-outlined text-primary">location_on</span>
                            <div>
                              <div class="fw-semibold text-dark">Detail Lokasi</div>
                              <div class="text-muted small">Titikan coverage</div>
                            </div>
                          </div>
                        </th>
                        <th class="text-nowrap">
                          <div class="d-flex align-items-center gap-2">
                            <span class="material-icons-outlined text-primary">warning</span>
                            <div>
                              <div class="fw-semibold text-dark">Laporan Hazard</div>
                              <div class="text-muted small">Data Beats</div>
                            </div>
                          </div>
                        </th>
                      </tr>
                    </thead>
                    <tbody>
                      @php
                        $meta = $coverageSummary['meta'] ?? ['current_page' => 1, 'per_page' => 10, 'total_items' => 0, 'total_pages' => 0];
                        $coverageData = $coverageSummary['data'] ?? [];
                        $rowNumber = ($meta['current_page'] - 1) * $meta['per_page'] + 1;
                      @endphp
                      @forelse($coverageData as $siteName => $rows)
                        <tr class="table-secondary text-uppercase">
                          <td colspan="7" class="fw-semibold">
                            {{ $siteName ?? 'Site Tidak Diketahui' }}
                            <span class="badge bg-dark text-white ms-2">{{ count($rows) }} CCTV</span>
                          </td>
                        </tr>
                        @foreach($rows as $row)
                        <tr>
                          <td>{{ $rowNumber++ }}</td>
                          <td class="fw-semibold text-primary">{{ $row['cctv_number'] }}</td>
                          <td>{{ $row['company'] }}</td>
                          <td>{{ $row['cctv_name'] }}</td>
                          <td>{{ $row['coverage_location'] }}</td>
                          <td>{{ $row['coverage_detail'] }}</td>
                          <td>
                            @if(!empty($row['hazards']))
                              <div class="d-flex flex-column gap-2">
                                @foreach($row['hazards'] as $hazard)
                                  @php
                                    $severityLabel = strtolower($hazard['severity'] ?? '');
                                    $severityClass = 'bg-secondary';
                                    if (str_contains($severityLabel, 'tinggi') || $severityLabel === 'critical') {
                                        $severityClass = 'bg-danger';
                                    } elseif (str_contains($severityLabel, 'sedang') || $severityLabel === 'medium') {
                                        $severityClass = 'bg-warning';
                                    } elseif (str_contains($severityLabel, 'rendah') || $severityLabel === 'low') {
                                        $severityClass = 'bg-info';
                                    }
                                  @endphp
                                  <div class="border rounded-3 p-2">
                                    <div class="d-flex align-items-center gap-2">
                                      <span class="badge {{ $severityClass }} text-white text-uppercase small">{{ $hazard['severity'] }}</span>
                                      <span class="fw-semibold">{{ $hazard['type'] ?? 'Hazard' }}</span>
                                    </div>
                                    <p class="mb-1 text-muted small">{{ $hazard['description'] }}</p>
                                    <div class="text-muted small d-flex align-items-center gap-1">
                                      <i class="material-icons-outlined fs-6">schedule</i>
                                      {{ $hazard['detected_at'] }}
                                    </div>
                                  </div>
                                @endforeach
                                @if($row['hazard_count'] > count($row['hazards']))
                                  <span class="text-muted small">+{{ $row['hazard_count'] - count($row['hazards']) }} laporan lainnya</span>
                                @endif
                              </div>
                            @else
                              <span class="badge bg-success text-white">Belum ada laporan hazard</span>
                            @endif
                          </td>
                        </tr>
                        @endforeach
                      @empty
                        <tr>
                          <td colspan="7" class="text-center text-muted py-4">
                            Tidak ada data coverage CCTV ditemukan.
                          </td>
                        </tr>
                      @endforelse
                    </tbody>
                  </table>
                </div>
                @if(($coverageSummary['meta']['total_pages'] ?? 0) > 1)
                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between mt-3 gap-2">
                  <span class="text-muted small">
                    Menampilkan
                    {{ min($meta['per_page'], $meta['total_items'] - ($meta['current_page'] - 1) * $meta['per_page']) }}
                    dari {{ $meta['total_items'] }} CCTV
                  </span>
                  <nav>
                    <ul class="pagination pagination-sm mb-0">
                      @php
                        $currentPage = $meta['current_page'];
                        $totalPages = $meta['total_pages'];
                      @endphp
                      <li class="page-item {{ $currentPage === 1 ? 'disabled' : '' }}">
                        <a class="page-link" href="{{ request()->fullUrlWithQuery(['coverage_page' => max($currentPage - 1, 1)]) }}">
                          &laquo;
                        </a>
                      </li>
                      @for($page = 1; $page <= $totalPages; $page++)
                        <li class="page-item {{ $page === $currentPage ? 'active' : '' }}">
                          <a class="page-link" href="{{ request()->fullUrlWithQuery(['coverage_page' => $page]) }}">{{ $page }}</a>
                        </li>
                      @endfor
                      <li class="page-item {{ $currentPage === $totalPages ? 'disabled' : '' }}">
                        <a class="page-link" href="{{ request()->fullUrlWithQuery(['coverage_page' => min($currentPage + 1, $totalPages)]) }}">
                          &raquo;
                        </a>
                      </li>
                    </ul>
                  </nav>
                </div>
                @endif
              </div>
            </div>
          </div>
        </div>
        @endif --}}
        
        <div class="row">
          <div class="col-12 col-xl-5 col-xxl-4 d-flex">
            <div class="card rounded-4 w-100 shadow-none bg-transparent border-0">
               <div class="card-body p-0">
                 <div class="row g-4">
                    <div class="col-12 col-xl-6 d-flex">
                      <div class="card mb-0 rounded-4 w-100">
                       <div class="card-body">
                         <div class="d-flex align-items-start justify-content-between mb-3">
                           <div class="">
                             <h4 class="mb-0">{{ number_format($totalYtdInsiden ?? 0) }}</h4>
                             <p class="mb-0">Total YTD Insiden</p>
                           </div>
                           <div class="dropdown">
                             <a href="javascript:;" class="dropdown-toggle-nocaret options dropdown-toggle"
                               data-bs-toggle="dropdown">
                               <span class="material-icons-outlined fs-5">more_vert</span>
                             </a>
                             <ul class="dropdown-menu">
                               <li><a class="dropdown-item" href="javascript:;">Action</a></li>
                               <li><a class="dropdown-item" href="javascript:;">Another action</a></li>
                               <li><a class="dropdown-item" href="javascript:;">Something else here</a></li>
                             </ul>
                           </div>
                         </div>
                         <div class="chart-container2">
                           <div id="chart3"></div>
                         </div>
                         <div class="text-center">
                          <p class="mb-0"><span class="text-{{ ($ytdInsidenChange ?? 0) >= 0 ? 'success' : 'danger' }} me-1">{{ abs($ytdInsidenChange ?? 0) }}%</span> from last month</p>
                        </div>
                       </div>
                      </div>
                   </div>
                   <div class="col-12 col-xl-6 d-flex">
                    <div class="card mb-0 rounded-4 w-100">
                     <div class="card-body">
                       <div class="d-flex align-items-start justify-content-between mb-1">
                         <div class="">
                           <h4 class="mb-0">{{ number_format($activeHazards ?? 0) }}</h4>
                           <p class="mb-0">Active Hazards</p>
                         </div>
                         <div class="dropdown">
                           <a href="javascript:;" class="dropdown-toggle-nocaret options dropdown-toggle"
                             data-bs-toggle="dropdown">
                             <span class="material-icons-outlined fs-5">more_vert</span>
                           </a>
                           <ul class="dropdown-menu">
                             <li><a class="dropdown-item" href="javascript:;">Action</a></li>
                             <li><a class="dropdown-item" href="javascript:;">Another action</a></li>
                             <li><a class="dropdown-item" href="javascript:;">Something else here</a></li>
                           </ul>
                         </div>
                       </div>
                       <div class="chart-container2">
                         <div id="chart2"></div>
                       </div>
                       <div class="text-center">
                         <p class="mb-0">{{ number_format($hazardIncrease ?? 0) }} hazards increased from last month</p>
                       </div>
                     </div>
                    </div>
                 </div>
                   <div class="col-12 col-xl-12">
                    <div class="card rounded-4 mb-0">
                      <div class="card-body">
                        <div class="d-flex align-items-center gap-3 mb-2">
                           <div class="">
                             <h2 class="mb-0">{{ number_format($resolvedHazards ?? 0) }}</h2>
                           </div>
                           <div class="">
                             <p class="dash-lable d-flex align-items-center gap-1 rounded mb-0 bg-success text-success bg-opacity-10"><span class="material-icons-outlined fs-6">arrow_upward</span>{{ abs($resolvedHazardsChange ?? 0) }}%</p>
                           </div>
                         </div>
                         <p class="mb-0">Resolved Hazards This Year</p>
                          <div class="mt-4">
                            @php
                              $totalHazards = $totalHazards ?? 0;
                              $resolvedCount = $resolvedHazards ?? 0;
                              $resolvedPercentage = $totalHazards > 0 ? round(($resolvedCount / $totalHazards) * 100) : 0;
                              $remaining = max(0, $totalHazards - $resolvedCount);
                            @endphp
                            <p class="mb-2 d-flex align-items-center justify-content-between">{{ $remaining }} left to Goal<span class="">{{ $resolvedPercentage }}%</span></p>
                            <div class="progress w-100" style="height: 7px;">
                              <div class="progress-bar bg-primary" style="width: {{ $resolvedPercentage }}%"></div>
                            </div>
                          </div>
                          
                      </div>
                    </div>
                  </div>

                 </div><!--end row-->
               </div>
            </div>  
          </div> 
          <div class="col-12 col-xl-7 col-xxl-8 d-flex">
            <div class="card w-100 rounded-4">
               <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                  <div class="">
                    <h5 class="mb-0 fw-bold">Hazards & Detections</h5>
                  </div>
                  <div class="dropdown">
                    <a href="javascript:;" class="dropdown-toggle-nocaret options dropdown-toggle"
                      data-bs-toggle="dropdown">
                      <span class="material-icons-outlined fs-5">more_vert</span>
                    </a>
                    <ul class="dropdown-menu">
                      <li><a class="dropdown-item" href="javascript:;">Action</a></li>
                      <li><a class="dropdown-item" href="javascript:;">Another action</a></li>
                      <li><a class="dropdown-item" href="javascript:;">Something else here</a></li>
                    </ul>
                  </div>
                 </div>
                  <div id="chart4"></div>
                  <div class="d-flex flex-column flex-lg-row align-items-start justify-content-around border p-3 rounded-4 mt-3 gap-3">
                    <div class="d-flex align-items-center gap-4">
                      <div class="">
                        <p class="mb-0 data-attributes">
                          <span
                            data-peity='{ "fill": ["#0d6efd", "rgb(0 0 0 / 10%)"], "innerRadius": 32, "radius": 40 }'>5/7</span>
                        </p>
                      </div>
                      <div class="">
                        <p class="mb-1 fs-6 fw-bold">Monthly</p>
                        <h2 class="mb-0">{{ number_format($monthlyHazards ?? 0) }}</h2>
                        <p class="mb-0"><span class="text-{{ ($monthlyChange ?? 0) >= 0 ? 'success' : 'danger' }} me-2 fw-medium">{{ abs($monthlyChange ?? 0) }}%</span><span>{{ number_format($monthlyCount ?? 0) }} hazards</span></p>
                      </div>
                    </div>
                    <div class="vr"></div>
                    <div class="d-flex align-items-center gap-4">
                      <div class="">
                        <p class="mb-0 data-attributes">
                          <span
                            data-peity='{ "fill": ["#6f42c1", "rgb(0 0 0 / 10%)"], "innerRadius": 32, "radius": 40 }'>5/7</span>
                        </p>
                      </div>
                      <div class="">
                        <p class="mb-1 fs-6 fw-bold">Yearly</p>
                        <h2 class="mb-0">{{ number_format($yearlyHazards ?? 0) }}</h2>
                        <p class="mb-0"><span class="text-{{ ($yearlyChange ?? 0) >= 0 ? 'success' : 'danger' }} me-2 fw-medium">{{ abs($yearlyChange ?? 0) }}%</span><span>{{ number_format($yearlyCount ?? 0) }} hazards</span></p>
                      </div>
                    </div>
                  </div>
               </div>
            </div>  
          </div> 
        </div><!--end row-->
        
        {{-- Distribusi Perusahaan --}}
        @if(!empty($distributionByCompany) && $distributionByCompany->count() > 0)
        <div class="row">
          <div class="col-12 d-flex">
            <div class="card rounded-4 w-100">
              <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                  <div>
                    <h5 class="mb-0 fw-bold">Distribusi CCTV per Perusahaan</h5>
                    <p class="mb-0 text-muted small">Jumlah CCTV berdasarkan perusahaan</p>
                  </div>
                </div>
                <div class="row g-3">
                  @foreach($distributionByCompany as $company)
                  <div class="col-12 col-md-6 col-lg-4 col-xl-3">
                    <div class="border rounded-3 p-3 h-100">
                      <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="material-icons-outlined text-primary">corporate_fare</i>
                        <h6 class="mb-0 fw-semibold text-truncate" title="{{ $company['company'] }}">{{ $company['company'] ?? 'Tidak Diketahui' }}</h6>
                      </div>
                      <div class="d-flex align-items-end justify-content-between">
                        <div>
                          <h3 class="mb-0 text-primary">{{ $company['count'] }}</h3>
                          <small class="text-muted">CCTV</small>
                        </div>
                        <span class="badge bg-primary bg-opacity-10 text-primary">{{ $company['percentage'] }}%</span>
                      </div>
                    </div>
                  </div>
                  @endforeach
                </div>
              </div>
            </div>
          </div>
        </div><!--end row-->
        @endif

        <div class="row">
           <div class="col-12 col-xl-4 d-flex">
            <div class="card w-100 rounded-4">
               <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                  <div class="">
                    <h5 class="mb-0 fw-bold">Ongoing Projects</h5>
                  </div>
                  <div class="dropdown">
                    <a href="javascript:;" class="dropdown-toggle-nocaret options dropdown-toggle"
                      data-bs-toggle="dropdown">
                      <span class="material-icons-outlined fs-5">more_vert</span>
                    </a>
                    <ul class="dropdown-menu">
                      <li><a class="dropdown-item" href="javascript:;">Action</a></li>
                      <li><a class="dropdown-item" href="javascript:;">Another action</a></li>
                      <li><a class="dropdown-item" href="javascript:;">Something else here</a></li>
                    </ul>
                  </div>
                 </div>
                  <div class="d-flex flex-column gap-4">
                     <div class="d-flex align-items-center gap-4">
                       <div class="d-flex align-items-center gap-3 flex-grow-1 flex-shrink-0">
                        <div class="wh-48 d-flex align-items-center justify-content-center rounded-3 border">
                          <img src="{{ URL::asset('build/images/projects/angular.png') }}" width="30" alt="">
                        </div>
                          <div class="">
                            <h6 class="mb-0 fw-bold">Angular 12</h6>
                            <p class="mb-0">Admin Template</p>
                          </div>
                       </div>
                       <div class="progress w-25" style="height: 5px;">
                          <div class="progress-bar bg-danger" style="width: 95%"></div>
                       </div>
                       <div class="">
                        <p class="mb-0 fs-6">95%</p>
                       </div>
                     </div>
                     <div class="d-flex align-items-center gap-4">
                      <div class="d-flex align-items-center gap-3 flex-grow-1 flex-shrink-0">
                       <div class="wh-48 d-flex align-items-center justify-content-center rounded-3 border">
                         <img src="{{ URL::asset('build/images/projects/react.png') }}" width="30" alt="">
                       </div>
                         <div class="">
                           <h6 class="mb-0 fw-bold">React Js</h6>
                           <p class="mb-0">eCommerce Admin</p>
                         </div>
                      </div>
                      <div class="progress w-25" style="height: 5px;">
                         <div class="progress-bar bg-info" style="width: 90%"></div>
                      </div>
                      <div class="">
                       <p class="mb-0 fs-6">90%</p>
                      </div>
                    </div>
                    <div class="d-flex align-items-center gap-4">
                      <div class="d-flex align-items-center gap-3 flex-grow-1 flex-shrink-0">
                       <div class="wh-48 d-flex align-items-center justify-content-center rounded-3 border">
                         <img src="{{ URL::asset('build/images/projects/vue.png') }}" width="30" alt="">
                       </div>
                         <div class="">
                           <h6 class="mb-0 fw-bold">Vue Js</h6>
                           <p class="mb-0">Dashboard Template</p>
                         </div>
                      </div>
                      <div class="progress w-25" style="height: 5px;">
                         <div class="progress-bar bg-success" style="width: 85%"></div>
                      </div>
                      <div class="">
                       <p class="mb-0 fs-6">85%</p>
                      </div>
                    </div>
                    <div class="d-flex align-items-center gap-4">
                      <div class="d-flex align-items-center gap-3 flex-grow-1 flex-shrink-0">
                       <div class="wh-48 d-flex align-items-center justify-content-center rounded-3 border">
                         <img src="{{ URL::asset('build/images/projects/bootstrap.png') }}" width="30" alt="">
                       </div>
                         <div class="">
                           <h6 class="mb-0 fw-bold">Bootstrap 5</h6>
                           <p class="mb-0">Corporate Website</p>
                         </div>
                      </div>
                      <div class="progress w-25" style="height: 5px;">
                         <div class="progress-bar bg-voilet" style="width: 75%"></div>
                      </div>
                      <div class="">
                       <p class="mb-0 fs-6">75%</p>
                      </div>
                    </div>
                    <div class="d-flex align-items-center gap-4">
                      <div class="d-flex align-items-center gap-3 flex-grow-1 flex-shrink-0">
                       <div class="wh-48 d-flex align-items-center justify-content-center rounded-3 border">
                         <img src="{{ URL::asset('build/images/projects/magento.png') }}" width="30" alt="">
                       </div>
                         <div class="">
                           <h6 class="mb-0 fw-bold">Magento</h6>
                           <p class="mb-0">Shoping Portal</p>
                         </div>
                      </div>
                      <div class="progress w-25" style="height: 5px;">
                         <div class="progress-bar bg-orange" style="width: 65%"></div>
                      </div>
                      <div class="">
                       <p class="mb-0 fs-6">65%</p>
                      </div>
                    </div>
                    <div class="d-flex align-items-center gap-4">
                      <div class="d-flex align-items-center gap-3 flex-grow-1 flex-shrink-0">
                       <div class="wh-48 d-flex align-items-center justify-content-center rounded-3 border">
                         <img src="{{ URL::asset('build/images/projects/django.png') }}" width="30" alt="">
                       </div>
                         <div class="">
                           <h6 class="mb-0 fw-bold">Django</h6>
                           <p class="mb-0">Backend Admin</p>
                         </div>
                      </div>
                      <div class="progress w-25" style="height: 5px;">
                         <div class="progress-bar bg-cyne" style="width: 55%"></div>
                      </div>
                      <div class="">
                       <p class="mb-0 fs-6">55%</p>
                      </div>
                    </div>
                    <div class="d-flex align-items-center gap-4">
                      <div class="d-flex align-items-center gap-3 flex-grow-1 flex-shrink-0">
                       <div class="wh-48 d-flex align-items-center justify-content-center rounded-3 border">
                         <img src="{{ URL::asset('build/images/projects/python.png') }}" width="30" alt="">
                       </div>
                         <div class="">
                           <h6 class="mb-0 fw-bold">Python</h6>
                           <p class="mb-0">User Panel</p>
                         </div>
                      </div>
                      <div class="progress w-25" style="height: 5px;">
                         <div class="progress-bar" style="width: 45%"></div>
                      </div>
                      <div class="">
                       <p class="mb-0 fs-6">45%</p>
                      </div>
                    </div>
                  </div>
               </div>
             </div>
           </div>

           <div class="col-12 col-xl-4 d-flex">
            <div class="card w-100 rounded-4">
              <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                  <div class="">
                    <h5 class="mb-0 fw-bold">Campaign</h5>
                  </div>
                  <div class="dropdown">
                    <a href="javascript:;" class="dropdown-toggle-nocaret options dropdown-toggle"
                      data-bs-toggle="dropdown">
                      <span class="material-icons-outlined fs-5">more_vert</span>
                    </a>
                    <ul class="dropdown-menu">
                      <li><a class="dropdown-item" href="javascript:;">Action</a></li>
                      <li><a class="dropdown-item" href="javascript:;">Another action</a></li>
                      <li><a class="dropdown-item" href="javascript:;">Something else here</a></li>
                    </ul>
                  </div>
                 </div>
                <div class="d-flex flex-column justify-content-between gap-4">
                  <div class="d-flex align-items-center gap-4">
                    <div class="d-flex align-items-center gap-3 flex-grow-1">
                      <img src="{{ URL::asset('build/images/apps/17.png') }}" width="32" alt="">
                      <p class="mb-0">Facebook</p>
                    </div>
                    <div class="">
                      <p class="mb-0 fs-6">55%</p>
                    </div>
                    <div class="">
                      <p class="mb-0 data-attributes">
                        <span
                          data-peity='{ "fill": ["#0d6efd", "rgb(0 0 0 / 10%)"], "innerRadius": 14, "radius": 18 }'>5/7</span>
                      </p>
                    </div>
                  </div>
                  <div class="d-flex align-items-center gap-4">
                    <div class="d-flex align-items-center gap-3 flex-grow-1">
                      <img src="{{ URL::asset('build/images/apps/18.png') }}" width="32" alt="">
                      <p class="mb-0">LinkedIn</p>
                    </div>
                    <div class="">
                      <p class="mb-0 fs-6">67%</p>
                    </div>
                    <div class="">
                      <p class="mb-0 data-attributes">
                        <span
                          data-peity='{ "fill": ["#fc185a", "rgb(0 0 0 / 10%)"], "innerRadius": 14, "radius": 18 }'>5/7</span>
                      </p>
                    </div>
                  </div>
                  <div class="d-flex align-items-center gap-4">
                    <div class="d-flex align-items-center gap-3 flex-grow-1">
                      <img src="{{ URL::asset('build/images/apps/19.png') }}" width="32" alt="">
                      <p class="mb-0">Instagram</p>
                    </div>
                    <div class="">
                      <p class="mb-0 fs-6">78%</p>
                    </div>
                    <div class="">
                      <p class="mb-0 data-attributes">
                        <span
                          data-peity='{ "fill": ["#02c27a", "rgb(0 0 0 / 10%)"], "innerRadius": 14, "radius": 18 }'>5/7</span>
                      </p>
                    </div>
                  </div>
                  <div class="d-flex align-items-center gap-4">
                    <div class="d-flex align-items-center gap-3 flex-grow-1">
                      <img src="{{ URL::asset('build/images/apps/20.png') }}" width="32" alt="">
                      <p class="mb-0">Snapchat</p>
                    </div>
                    <div class="">
                      <p class="mb-0 fs-6">46%</p>
                    </div>
                    <div class="">
                      <p class="mb-0 data-attributes">
                        <span
                          data-peity='{ "fill": ["#fd7e14", "rgb(0 0 0 / 10%)"], "innerRadius": 14, "radius": 18 }'>5/7</span>
                      </p>
                    </div>
                  </div>
                  <div class="d-flex align-items-center gap-4">
                    <div class="d-flex align-items-center gap-3 flex-grow-1">
                      <img src="{{ URL::asset('build/images/apps/05.png') }}" width="32" alt="">
                      <p class="mb-0">Google</p>
                    </div>
                    <div class="">
                      <p class="mb-0 fs-6">38%</p>
                    </div>
                    <div class="">
                      <p class="mb-0 data-attributes">
                        <span
                          data-peity='{ "fill": ["#0dcaf0", "rgb(0 0 0 / 10%)"], "innerRadius": 14, "radius": 18 }'>5/7</span>
                      </p>
                    </div>
                  </div>
                  <div class="d-flex align-items-center gap-4">
                    <div class="d-flex align-items-center gap-3 flex-grow-1">
                      <img src="{{ URL::asset('build/images/apps/08.png') }}" width="32" alt="">
                      <p class="mb-0">Altaba</p>
                    </div>
                    <div class="">
                      <p class="mb-0 fs-6">15%</p>
                    </div>
                    <div class="">
                      <p class="mb-0 data-attributes">
                        <span
                          data-peity='{ "fill": ["#6f42c1", "rgb(0 0 0 / 10%)"], "innerRadius": 14, "radius": 18 }'>5/7</span>
                      </p>
                    </div>
                  </div>
                  <div class="d-flex align-items-center gap-4">
                    <div class="d-flex align-items-center gap-3 flex-grow-1">
                      <img src="{{ URL::asset('build/images/apps/07.png') }}" width="32" alt="">
                      <p class="mb-0">Spotify</p>
                    </div>
                    <div class="">
                      <p class="mb-0 fs-6">12%</p>
                    </div>
                    <div class="">
                      <p class="mb-0 data-attributes">
                        <span
                          data-peity='{ "fill": ["#ff00b3", "rgb(0 0 0 / 10%)"], "innerRadius": 14, "radius": 18 }'>5/7</span>
                      </p>
                    </div>
                  </div>
                  <div class="d-flex align-items-center gap-4">
                    <div class="d-flex align-items-center gap-3 flex-grow-1">
                      <img src="{{ URL::asset('build/images/apps/12.png') }}" width="32" alt="">
                      <p class="mb-0">Photoes</p>
                    </div>
                    <div class="">
                      <p class="mb-0 fs-6">24%</p>
                    </div>
                    <div class="">
                      <p class="mb-0 data-attributes">
                        <span
                          data-peity='{ "fill": ["#22e3aa", "rgb(0 0 0 / 10%)"], "innerRadius": 14, "radius": 18 }'>5/7</span>
                      </p>
                    </div>
                  </div>
                  
                </div>
              </div>
            </div>  
          </div>

           <div class="col-12 col-xl-4 d-flex">
            <div class="card rounded-4 w-100">
              <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                  <div class="">
                    <h5 class="mb-0 fw-bold">Recent Transactions</h5>
                  </div>
                  <div class="dropdown">
                    <a href="javascript:;" class="dropdown-toggle-nocaret options dropdown-toggle"
                      data-bs-toggle="dropdown">
                      <span class="material-icons-outlined fs-5">more_vert</span>
                    </a>
                    <ul class="dropdown-menu">
                      <li><a class="dropdown-item" href="javascript:;">Action</a></li>
                      <li><a class="dropdown-item" href="javascript:;">Another action</a></li>
                      <li><a class="dropdown-item" href="javascript:;">Something else here</a></li>
                    </ul>
                  </div>
                 </div>
                <div class="payments-list">
                  <div class="d-flex flex-column gap-4">
                    <div class="d-flex align-items-center gap-3">
                      <div class="wh-48 d-flex align-items-center justify-content-center bg-danger rounded-circle">
                        <span class="material-icons-outlined text-white">shopping_cart</span>
                      </div>
                      <div class="flex-grow-1">
                        <h6 class="mb-0 fw-bold">Online Purchase</h6>
                        <p class="mb-0">03/10/2022</p>
                      </div>
                      <div class="d-flex align-items-center">
                        <h6 class="mb-0 fw-bold">$97,896</h6>
                      </div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                      <div class="wh-48 d-flex align-items-center justify-content-center rounded-circle bg-primary">
                        <span class="material-icons-outlined text-white">monetization_on</span>
                      </div>
                      <div class="flex-grow-1">
                        <h6 class="mb-0">Bank Transfer</h6>
                        <p class="mb-0">03/10/2022</p>
                      </div>
                      <div class="d-flex align-items-center gap-1">
                        <h6 class="mb-0 fw-bold">$86,469</h6>
                      </div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                      <div class="wh-48 d-flex align-items-center justify-content-center rounded-circle bg-success">
                        <span class="material-icons-outlined text-white">credit_card</span>
                      </div>
                      <div class="flex-grow-1">
                        <h6 class="mb-0">Credit Card</h6>
                        <p class="mb-0">03/10/2022</p>
                      </div>
                      <div class="d-flex align-items-center gap-1">
                        <h6 class="mb-0 fw-bold">$45,259</h6>
                      </div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                      <div class="wh-48 d-flex align-items-center justify-content-center rounded-circle bg-purple">
                        <span class="material-icons-outlined text-white">account_balance</span>
                      </div>
                      <div class="flex-grow-1">
                        <h6 class="mb-0">Laptop Payment</h6>
                        <p class="mb-0">03/10/2022</p>
                      </div>
                      <div class="d-flex align-items-center gap-1">
                        <h6 class="mb-0 fw-bold">$35,249</h6>
                      </div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                      <div class="wh-48 d-flex align-items-center justify-content-center rounded-circle bg-orange">
                        <span class="material-icons-outlined text-white">savings</span>
                      </div>
                      <div class="flex-grow-1">
                        <h6 class="mb-0">Template Payment</h6>
                        <p class="mb-0">03/10/2022</p>
                      </div>
                      <div class="d-flex align-items-center gap-1">
                        <h6 class="mb-0 fw-bold">$68,478</h6>
                      </div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                      <div class="wh-48 d-flex align-items-center justify-content-center rounded-circle bg-info">
                        <span class="material-icons-outlined text-white">paid</span>
                      </div>
                      <div class="flex-grow-1">
                        <h6 class="mb-0">iPhone Purchase</h6>
                        <p class="mb-0">03/10/2022</p>
                      </div>
                      <div class="d-flex align-items-center gap-1">
                        <h6 class="mb-0 fw-bold">$55,128</h6>
                      </div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                      <div class="wh-48 d-flex align-items-center justify-content-center rounded-circle bg-pink">
                        <span class="material-icons-outlined text-white">card_giftcard</span>
                      </div>
                      <div class="flex-grow-1">
                        <h6 class="mb-0">Account Credit</h6>
                        <p class="mb-0">03/10/2022</p>
                      </div>
                      <div class="d-flex align-items-center gap-1">
                        <h6 class="mb-0 fw-bold">$24,568</h6>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
         </div>

         <div class="col-12 col-xl-12 d-flex">
          <div class="card w-100 rounded-4">
            <div class="card-body">
              <div class="d-flex align-items-start justify-content-between mb-3">
                <div class="">
                  <h5 class="mb-0 fw-bold">Popular Products</h5>
                </div>
                <div class="dropdown">
                  <a href="javascript:;" class="dropdown-toggle-nocaret options dropdown-toggle"
                    data-bs-toggle="dropdown">
                    <span class="material-icons-outlined fs-5">more_vert</span>
                  </a>
                  <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="javascript:;">Action</a></li>
                    <li><a class="dropdown-item" href="javascript:;">Another action</a></li>
                    <li><a class="dropdown-item" href="javascript:;">Something else here</a></li>
                  </ul>
                </div>
               </div>
              <div class="d-flex flex-column gap-4">
                <div class="d-flex align-items-center gap-3">
                  <img src="{{ URL::asset('build/images/orders/01.png') }}" width="78" class="rounded-3" alt="">
                  <div class="flex-grow-1">
                    <h6 class="mb-0 fw-bold">Apple Hand Watch</h6>
                    <p class="mb-0">Sale: 258</p>
                  </div>
                  <div class="">
                    <h6 class="mb-0">$199</h6>
                  </div>
                </div>
                <div class="d-flex align-items-center gap-3">
                  <img src="{{ URL::asset('build/images/orders/08.png') }}" width="78" class="rounded-3" alt="">
                  <div class="flex-grow-1">
                    <h6 class="mb-0 fw-bold">Mobile Phone Set</h6>
                    <p class="mb-0">Sale: 169</p>
                  </div>
                  <div class="">
                    <h6 class="mb-0">$159</h6>
                  </div>
                </div>
                <div class="d-flex align-items-center gap-3">
                  <img src="{{ URL::asset('build/images/orders/03.png') }}" width="78" class="rounded-3" alt="">
                  <div class="flex-grow-1">
                    <h6 class="mb-0 fw-bold">Fancy Chair</h6>
                    <p class="mb-0">Sale: 268</p>
                  </div>
                  <div class="">
                    <h6 class="mb-0">$678</h6>
                  </div>
                </div>
                <div class="d-flex align-items-center gap-3">
                  <img src="{{ URL::asset('build/images/orders/04.png') }}" width="78" class="rounded-3" alt="">
                  <div class="flex-grow-1">
                    <h6 class="mb-0 fw-bold">Blue Shoes Pair</h6>
                    <p class="mb-0">Sale: 859</p>
                  </div>
                  <div class="">
                    <h6 class="mb-0">$279</h6>
                  </div>
                </div>
                <div class="d-flex align-items-center gap-3">
                  <img src="{{ URL::asset('build/images/orders/05.png') }}" width="78" class="rounded-3" alt="">
                  <div class="flex-grow-1">
                    <h6 class="mb-0 fw-bold">Blue Yoga Mat</h6>
                    <p class="mb-0">Sale: 328</p>
                  </div>
                  <div class="">
                    <h6 class="mb-0">$389</h6>
                  </div>
                </div>
                <div class="d-flex align-items-center gap-3">
                  <img src="{{ URL::asset('build/images/orders/06.png') }}" width="75" class="rounded-3" alt="">
                  <div class="flex-grow-1">
                    <h6 class="mb-0 fw-bold">White water Bottle</h6>
                    <p class="mb-0">Sale: 992</p>
                  </div>
                  <div class="">
                    <h6 class="mb-0">$584</h6>
                  </div>
                </div>
                <div class="d-flex align-items-center gap-3">
                  <img src="{{ URL::asset('build/images/orders/07.png') }}" width="78" class="rounded-3" alt="">
                  <div class="flex-grow-1">
                    <h6 class="mb-0 fw-bold">Laptop Full HD</h6>
                    <p class="mb-0">Sale: 489</p>
                  </div>
                  <div class="">
                    <h6 class="mb-0">$398</h6>
                  </div>
                </div>
                
              </div>
            </div>
          </div>
        </div>
@endsection 
@section('scripts')

  <script src="{{ URL::asset('build/plugins/apexchart/apexcharts.min.js') }}"></script>
  <script src="{{ URL::asset('build/js/index.js') }}"></script>
  <script src="{{ URL::asset('build/plugins/peity/jquery.peity.min.js') }}"></script>
  <script src="{{ URL::asset('build/plugins/datatable/js/jquery.dataTables.min.js') }}"></script>
  <script src="{{ URL::asset('build/plugins/datatable/js/dataTables.bootstrap5.min.js') }}"></script>
  <script>
    $(".data-attributes span").peity("donut")
  </script>
  <script>

    // DataTable untuk modal Total CCTV
    let companyCctvTable = null;
    let currentSelectedCompany = '__all__';
    const companyCctvCompanyLabel = document.getElementById('companyCctvCompanyLabel');
    const resetCompanyFilter = document.getElementById('resetCompanyFilter');
    const companyCctvCount = document.getElementById('companyCctvCount');
    const companyRowTriggers = document.querySelectorAll('.company-row-trigger');

    // Inisialisasi DataTable saat modal dibuka
    const totalCctvModal = document.getElementById('totalCctvModal');
    if (totalCctvModal) {
      totalCctvModal.addEventListener('shown.bs.modal', function () {
        if (!companyCctvTable) {
          companyCctvTable = $('#companyCctvTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
              url: "{{ route('company-cctv-data') }}",
              type: "GET",
              data: function (d) {
                d.company = currentSelectedCompany;
              }
            },
            columns: [
              { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false, width: '50px' },
              { data: 'site', name: 'site', width: '100px' },
              { data: 'perusahaan', name: 'perusahaan', width: '150px' },
              { data: 'no_cctv', name: 'no_cctv', className: 'fw-semibold text-primary', width: '120px' },
              { data: 'nama_cctv', name: 'nama_cctv', width: '150px' },
              { data: 'status', name: 'status', orderable: false, searchable: false, width: '100px' },
              { data: 'kondisi', name: 'kondisi', orderable: false, searchable: false, width: '100px' },
              { data: 'coverage_lokasi', name: 'coverage_lokasi', width: '150px' },
              { data: 'coverage_detail_lokasi', name: 'coverage_detail_lokasi', width: '150px' },
              { data: 'kategori_area_tercapture', name: 'kategori_area_tercapture', width: '150px' },
              { data: 'lokasi_pemasangan', name: 'lokasi_pemasangan', width: '150px' }
            ],
            order: [[3, 'asc']], // Order by No CCTV
            pageLength: 25,
            scrollX: true,
            scrollY: '400px',
            scrollCollapse: true,
            autoWidth: false,
            language: {
              processing: "Memproses data...",
              search: "Cari:",
              lengthMenu: "Tampilkan _MENU_ data per halaman",
              info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
              infoEmpty: "Menampilkan 0 sampai 0 dari 0 data",
              infoFiltered: "(disaring dari _MAX_ total data)",
              paginate: {
                first: "Pertama",
                last: "Terakhir",
                next: "Selanjutnya",
                previous: "Sebelumnya"
              },
              emptyTable: "Klik perusahaan di tabel sebelah kiri untuk menampilkan daftar CCTV.",
              zeroRecords: "Tidak ada data yang cocok dengan pencarian"
            },
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
            drawCallback: function(settings) {
              const api = this.api();
              const recordsTotal = api.page.info().recordsTotal;
              companyCctvCount.textContent = recordsTotal + ' CCTV';
            }
          });
        }
      });

      // Function untuk update statistik
      function updateCompanyStats(company) {
        fetch(`{{ route('company-stats') }}?company=${encodeURIComponent(company)}`)
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              document.getElementById('companyStatsAktif').textContent = data.aktif || 0;
              document.getElementById('companyStatsNonAktif').textContent = data.nonAktif || 0;
              document.getElementById('companyStatsAreaKritis').textContent = data.areaKritis || 0;
            }
          })
          .catch(error => {
            console.error('Error fetching company stats:', error);
          });
      }

      // Handler untuk klik perusahaan
      companyRowTriggers.forEach(row => {
        row.addEventListener('click', function() {
          const companyName = this.dataset.company;
          currentSelectedCompany = companyName;
          
          // Update label
          companyCctvCompanyLabel.textContent = companyName;
          
          // Highlight row
          companyRowTriggers.forEach(r => r.classList.remove('table-active'));
          this.classList.add('table-active');
          
          // Update statistik
          updateCompanyStats(companyName);
          
          // Reload DataTable dengan filter perusahaan
          if (companyCctvTable) {
            companyCctvTable.ajax.reload();
          }
        });

        row.addEventListener('keydown', function(event) {
          if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            this.click();
          }
        });
      });

      // Handler untuk reset filter
      if (resetCompanyFilter) {
        resetCompanyFilter.addEventListener('click', function() {
          currentSelectedCompany = '__all__';
          companyCctvCompanyLabel.textContent = 'Semua Perusahaan';
          
          // Remove highlight
          companyRowTriggers.forEach(r => r.classList.remove('table-active'));
          
          // Reset statistik
          updateCompanyStats('__all__');
          
          // Reload DataTable
          if (companyCctvTable) {
            companyCctvTable.ajax.reload();
          }
        });
      }

      // Reset saat modal ditutup
      totalCctvModal.addEventListener('hidden.bs.modal', function () {
        currentSelectedCompany = '__all__';
        companyCctvCompanyLabel.textContent = 'Pilih perusahaan untuk melihat rincian';
        companyCctvCount.textContent = '0 CCTV';
        companyRowTriggers.forEach(r => r.classList.remove('table-active'));
        
        // Reset statistik
        document.getElementById('companyStatsAktif').textContent = '0';
        document.getElementById('companyStatsNonAktif').textContent = '0';
        document.getElementById('companyStatsAreaKritis').textContent = '0';
        
        if (companyCctvTable) {
          companyCctvTable.clear().draw();
        }
      });
    }
  </script>
@endsection 
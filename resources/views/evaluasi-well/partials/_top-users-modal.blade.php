{{-- Modal "Lihat Semua" pada kartu Top User Aktif --}}
<div class="modal fade" id="topUsersModal" tabindex="-1" aria-labelledby="topUsersModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content radius-8 border-0 shadow-lg">
      <div class="modal-header border-bottom py-16 px-24">
        <div class="min-w-0 pe-12">
          <h5 class="modal-title fw-bold text-lg mb-4" id="topUsersModalLabel">Top User Aktif</h5>
          <p class="text-sm text-secondary-light mb-0">
            Ranking total aktivitas (makan + olahraga + sosial) tahun berjalan.
          </p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>

      <div class="modal-body p-24 position-relative">
        <div id="top-users-loading" class="position-absolute top-0 start-0 w-100 h-100 d-none align-items-center justify-content-center bg-base" style="z-index: 5; opacity: 0.92;">
          <div class="text-center">
            <div class="spinner-border text-primary-600" role="status" aria-hidden="true"></div>
            <p class="text-sm text-secondary-light mt-12 mb-0">Memuat leaderboard…</p>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table bordered-table mb-0 align-middle" id="topUsersTable" style="width:100%">
            <thead>
              <tr>
                <th scope="col" style="width:4%">#</th>
                <th scope="col">Karyawan</th>
                <th scope="col">Site</th>
                <th scope="col">Perusahaan</th>
                <th scope="col" class="text-end">Makan</th>
                <th scope="col" class="text-end">Olahraga</th>
                <th scope="col" class="text-end">Sosial</th>
                <th scope="col" class="text-end">Total</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
        <p id="top-users-empty" class="text-secondary-light text-sm mb-0 d-none text-center py-40">Belum ada data aktivitas user.</p>
      </div>

      <div class="modal-footer border-top py-16 px-24">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

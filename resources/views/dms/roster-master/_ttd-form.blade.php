{{--
  Panel Setujui (wajib tanda tangan) / Tolak: Approval Roster & Approval Pengajuan.
  Butuh _ttd-css dan _ttd-js.

  $aksiSetuju, $aksiTolak  URL POST
  $objek                   kata benda di judul & tombol ("roster", "pengajuan")
  $subTolak                keterangan panel Tolak
  $contohSetuju, $contohTolak  placeholder catatan
--}}
      <div class="row g-4">
        {{-- Setujui: wajib tanda tangan (digambar atau upload). --}}
        <div class="col-xl-8">
          <form action="{{ $aksiSetuju }}" method="POST" enctype="multipart/form-data" id="rmFormSetuju" class="rm-panel h-100">
            @csrf
            <input type="hidden" name="ttd_mode" value="gambar" data-ttd-mode>
            <input type="hidden" name="ttd_data" data-ttd-data>

            <div class="rm-panel__kepala">
              <span class="rm-panel__ikon bg-success-focus text-success-main"><iconify-icon icon="solar:check-circle-bold"></iconify-icon></span>
              <div class="flex-grow-1">
                <div class="rm-panel__judul">Setujui {{ $objek }}</div>
                <div class="rm-panel__sub">Tanda tangan approver wajib diisi</div>
              </div>
              <div class="rm-seg" role="tablist" aria-label="Cara tanda tangan">
                <button type="button" class="rm-seg__opsi is-aktif" data-ttd-tab="gambar" aria-pressed="true">
                  <iconify-icon icon="solar:pen-bold"></iconify-icon><span>Gambar</span>
                </button>
                <button type="button" class="rm-seg__opsi" data-ttd-tab="upload" aria-pressed="false">
                  <iconify-icon icon="solar:upload-bold"></iconify-icon><span>Upload gambar</span>
                </button>
              </div>
            </div>

            <div data-ttd-panel="gambar">
              <div class="rm-ttd-kanvas">
                <canvas data-ttd-canvas aria-label="Area tanda tangan"></canvas>
                <span class="rm-ttd-garis" aria-hidden="true"></span>
                <span class="rm-ttd-petunjuk" data-ttd-petunjuk>Tanda tangan di sini (mouse, jari, atau stylus)</span>
              </div>
              <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-8">
                <span class="text-xs text-secondary-light d-inline-flex align-items-center gap-1">
                  <iconify-icon icon="solar:user-linear"></iconify-icon>{{ auth()->user()?->name }} &middot; {{ now()->format('d/m/Y') }}
                </span>
                <div class="d-flex gap-3">
                  <button type="button" class="rm-aksi-teks" data-ttd-undo>
                    <iconify-icon icon="solar:undo-left-round-linear"></iconify-icon>Batalkan goresan
                  </button>
                  <button type="button" class="rm-aksi-teks text-danger-600" data-ttd-hapus>
                    <iconify-icon icon="solar:eraser-linear"></iconify-icon>Hapus
                  </button>
                </div>
              </div>
            </div>

            <div data-ttd-panel="upload" hidden>
              <input type="file" name="ttd_file" accept="image/png,image/jpeg" class="form-control" data-ttd-file>
              <span class="d-block text-xs text-secondary-light mt-4">PNG / JPG, maks 2 MB — sebaiknya latar putih atau transparan.</span>
              <img alt="Pratinjau tanda tangan" class="rm-ttd-pratinjau mt-8" data-ttd-pratinjau hidden>
            </div>

            <label class="rm-label mt-16" for="rmCatatanSetuju">Catatan persetujuan <span class="fw-normal text-secondary-light">(opsional)</span></label>
            <textarea id="rmCatatanSetuju" name="catatan_review" rows="3" class="form-control rm-textarea" maxlength="1000"
                      placeholder="{{ $contohSetuju }}">{{ old('catatan_review') }}</textarea>

            <span class="d-block text-sm text-danger-600 mt-8" data-ttd-galat hidden></span>
            <div class="rm-panel__kaki">
              <button type="submit" class="btn btn-success-600 radius-8 d-inline-flex align-items-center justify-content-center gap-2 px-20">
                <iconify-icon icon="solar:check-circle-bold"></iconify-icon>Setujui &amp; tanda tangani
              </button>
            </div>
          </form>
        </div>

        {{-- Tolak: cukup alasan. --}}
        <div class="col-xl-4">
          <form action="{{ $aksiTolak }}" method="POST" class="rm-panel h-100">
            @csrf
            <div class="rm-panel__kepala">
              <span class="rm-panel__ikon bg-danger-focus text-danger-main"><iconify-icon icon="solar:close-circle-bold"></iconify-icon></span>
              <div>
                <div class="rm-panel__judul">Tolak {{ $objek }}</div>
                <div class="rm-panel__sub">{{ $subTolak }}</div>
              </div>
            </div>

            <label class="rm-label" for="rmAlasanTolak">Alasan penolakan <span class="text-danger-600">*</span></label>
            <textarea id="rmAlasanTolak" name="catatan_review" rows="6" class="form-control rm-textarea" maxlength="1000"
                      placeholder="{{ $contohTolak }}" required></textarea>

            <div class="rm-panel__kaki">
              <button type="submit" class="btn btn-outline-danger radius-8 w-100 d-inline-flex align-items-center justify-content-center gap-2"
                      onclick="return confirm(@js('Tolak '.$objek.' ini?'));">
                <iconify-icon icon="solar:close-circle-bold"></iconify-icon>Tolak {{ $objek }}
              </button>
            </div>
          </form>
        </div>
      </div>

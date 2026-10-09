<div class="modal fade" id="ocr-sap-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h6 class="modal-title" id="ocr-sap-title">Detail SAP</h6>
                    <p class="ocr-card-kicker mb-0" id="ocr-sap-meta"></p>
            </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="ocr-seg ocr-detail-tabs" role="tablist" aria-label="Jenis detail personil">
                    <button type="button" role="tab" id="ocr-detail-tab-sap" class="is-active" aria-selected="true" aria-controls="ocr-sap-pane-sap" data-detail-pane="sap">SAP</button>
                    <button type="button" role="tab" id="ocr-detail-tab-tbc" aria-selected="false" aria-controls="ocr-sap-pane-tbc" data-detail-pane="tbc">TBC</button>
                </div>
                <div id="ocr-sap-pane-sap" role="tabpanel" aria-labelledby="ocr-detail-tab-sap">
                    <div class="ocr-sap-filters" id="ocr-sap-filters" hidden>
                        <button type="button" class="is-active" data-sap-filter="all">Semua</button>
                        <button type="button" data-sap-filter="hazard">Hazard</button>
                        <button type="button" data-sap-filter="inspeksi">Inspeksi</button>
                        <button type="button" data-sap-filter="observasi">Observasi</button>
                        <button type="button" data-sap-filter="oak">OAK</button>
                    </div>
                </div>
                <div id="ocr-sap-pane-tbc" role="tabpanel" aria-labelledby="ocr-detail-tab-tbc" hidden>
                    <p class="ocr-card-kicker ocr-tbc-kicker" id="ocr-tbc-kicker">Hazard &amp; Inspeksi orang ini pada jendela jaga. Sudah TBC = tasklist ada di Google Sheet.</p>
                    <div class="ocr-sap-filters" id="ocr-tbc-filters" hidden>
                        <button type="button" class="is-active" data-tbc-filter="all">Semua</button>
                        <button type="button" data-tbc-filter="sudah">Sudah TBC</button>
                        <button type="button" data-tbc-filter="belum">Belum TBC</button>
                    </div>
                </div>
                <p class="ocr-sap-status-msg" id="ocr-sap-status">Memuat laporan…</p>
                <div class="ocr-sap-grid" id="ocr-sap-grid"></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="ocr-highlight-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h6 class="modal-title" id="ocr-highlight-title">Detail Highlight Temuan</h6>
                    <p class="ocr-card-kicker mb-0" id="ocr-highlight-meta"></p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="ocr-table ocr-highlight-table">
                        <thead>
                            <tr>
                                <th>Kode Tasklist</th>
                                <th>Tanggal temuan</th>
                                <th>Deskripsi temuan</th>
                                <th>Perusahaan &amp; PIC</th>
                                <th>Status temuan</th>
                            </tr>
                        </thead>
                        <tbody id="ocr-highlight-body"></tbody>
                    </table>
                </div>
                <p class="ocr-sap-status-msg" id="ocr-highlight-empty" hidden>Tidak ada temuan untuk kategori ini.</p>
            </div>
        </div>
    </div>
</div>

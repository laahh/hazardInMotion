/*
 * Modal kartu "Pelanggaran Regulasi" & "Tidak Sesuai Mapping Roster"
 * (resources/views/dms/partials/_roster-reg-terkini.blade.php).
 *
 *   1. Daftar per kode REG/MAP — dimuat sekali per kode lewat JSON, disaring di klien.
 *   2. Klik baris → timeline harian karyawan itu langsung dari RFID: strip
 *      kode shift per hari (bulan/kuartal & minggu ISO di atasnya, titik
 *      merah/kuning untuk hari ber-flag), lalu detail tap per hari — gate
 *      dan jam check-in/check-out beserta durasinya.
 */
(function () {
    'use strict';

    var cacheDaftar = {};
    var cacheTimeline = {};

    var BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    var LABEL_KODE = { P: 'Shift Pagi', M: 'Shift Malam', o: 'Off', c: 'Cuti', x: 'Belum ada scan hari ini' };

    function esc(v) {
        return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function ambilJson(url) {
        return fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function (res) { return res.json(); })
            .then(function (json) {
                if (!json.up) { throw new Error(json.pesan || 'RFID tidak terjangkau.'); }
                return json;
            });
    }

    // ── Daftar per kode ─────────────────────────────────────────────────

    function renderDaftar(modal, baris) {
        var pakaiKet = modal.dataset.roKeterangan === '1';
        modal.querySelector('[data-ro-tbody]').innerHTML = baris.map(function (r, i) {
            return '<tr class="ro-temuan-row" data-ro-sid="' + esc(r.sid) + '" title="Lihat timeline RFID ' + esc(r.nama) + '">'
                + '<td>' + (i + 1) + '</td>'
                + '<td>' + esc(r.sid) + '</td>'
                + '<td class="fw-medium">' + esc(r.nama) + '</td>'
                + '<td>' + esc(r.jabatan) + '</td>'
                + '<td>' + esc(r.pt) + '</td>'
                + '<td>' + esc(r.site) + '</td>'
                + '<td class="text-end fw-semibold">' + (r.terpotong ? '≥' : '') + esc(r.hari) + '</td>'
                + '<td>' + esc(r.mulai) + '</td>'
                + '<td>' + esc(r.selesai) + '</td>'
                + (pakaiKet ? '<td>' + esc(r.keterangan) + '</td>' : '')
                + '<td>' + esc(r.scan_terakhir) + '</td>'
                + '</tr>';
        }).join('');
        saring(modal);
    }

    function pesan(modal, teks) {
        var p = modal.querySelector('[data-ro-pesan]');
        p.textContent = teks || '';
        p.classList.toggle('d-none', !teks);
    }

    function saring(modal) {
        var q = modal.querySelector('[data-ro-cari]').value.trim().toLowerCase();
        var rows = modal.querySelectorAll('[data-ro-tbody] tr');
        var tampil = 0;
        rows.forEach(function (tr) {
            var cocok = q === '' || tr.textContent.toLowerCase().indexOf(q) !== -1;
            tr.classList.toggle('d-none', !cocok);
            if (cocok) { tampil++; }
        });
        pesan(modal, rows.length === 0 ? 'Tidak ada karyawan pada kode ini per hari ini.'
            : (tampil === 0 ? 'Tidak ada yang cocok.' : ''));
    }

    function bukaTab(modal, kode) {
        var btn = modal.querySelector('[data-ro-tab-btn="' + kode + '"]');
        if (!btn) { return; }

        modal.querySelectorAll('[data-ro-tab-btn]').forEach(function (b) {
            b.classList.toggle('active', b === btn);
        });
        modal.querySelector('[data-ro-definisi-teks]').textContent = btn.dataset.roDefinisi;
        modal.querySelector('[data-ro-th-hari]').textContent = btn.dataset.roKolomHari;
        modal.querySelector('[data-ro-unduh-btn]').href = btn.dataset.roUnduh;
        modal.querySelector('[data-ro-tbody]').innerHTML = '';

        if (cacheDaftar[kode]) {
            renderDaftar(modal, cacheDaftar[kode]);
            return;
        }

        pesan(modal, 'Memuat data RFID…');
        ambilJson(btn.dataset.roUrl)
            .then(function (json) {
                cacheDaftar[kode] = json.baris;
                // Abaikan respons bila pengguna sudah pindah tab sebelum data tiba.
                if (btn.classList.contains('active')) { renderDaftar(modal, json.baris); }
            })
            .catch(function (err) { pesan(modal, 'Gagal memuat: ' + err.message); });
    }

    function tampilkan(modal, detail) {
        modal.querySelector('[data-ro-view-daftar]').classList.toggle('d-none', detail);
        modal.querySelector('[data-ro-view-detail]').classList.toggle('d-none', !detail);
        modal.querySelector('.modal-body').scrollTop = 0;
    }

    // ── Timeline RFID satu karyawan ────────────────────────────────────

    function mingguIso(tgl) {
        var d = new Date(Date.UTC(tgl.getUTCFullYear(), tgl.getUTCMonth(), tgl.getUTCDate()));
        var hari = d.getUTCDay() || 7;
        d.setUTCDate(d.getUTCDate() + 4 - hari);
        var awalTahun = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
        return Math.ceil(((d - awalTahun) / 86400000 + 1) / 7);
    }

    function stripHtml(hari) {
        var n = hari.length;
        var bulan = '';
        var minggu = '';
        var bar = '';

        hari.forEach(function (h, i) {
            var tgl = new Date(h.iso + 'T00:00:00Z');
            var kiri = (i / n * 100).toFixed(3) + '%';
            var bln = tgl.getUTCMonth();

            if (i === 0 || tgl.getUTCDate() === 1) {
                var q = bln % 3 === 0 ? ' · Q' + (bln / 3 + 1) : '';
                bulan += '<span class="ro-strip__mark" style="left:' + kiri + '">' + BULAN[bln] + q + '</span>';
            }
            if (tgl.getUTCDay() === 1) {
                minggu += '<span class="ro-strip__week" style="left:' + kiri + '">W' + mingguIso(tgl) + '</span>';
            }

            var flag = h.merah ? ' is-merah' : (h.kuning ? ' is-kuning' : '');
            var judul = h.label + ' — ' + (LABEL_KODE[h.kode] || h.kode)
                + (h.merah ? ' · ' + h.merah : (h.kuning ? ' · ' + h.kuning : ''));
            bar += '<button type="button" class="ro-strip__bar sh-' + esc(h.kode) + flag + '"'
                + ' data-ro-hari="' + i + '" title="' + esc(judul) + '" aria-label="' + esc(judul) + '"></button>';
        });

        return '<div class="ro-strip">'
            + '<div class="ro-strip__labels">' + bulan + '</div>'
            + '<div class="ro-strip__labels ro-strip__labels--week">' + minggu + '</div>'
            + '<div class="ro-strip__bars">' + bar + '</div>'
            + '</div>';
    }

    function jamMasuk(h) {
        return h.masuk ? '<b>' + esc(h.masuk.jam) + '</b>' : '<span class="text-secondary-light">—</span>';
    }

    function jamKeluar(h) {
        if (!h.keluar) {
            return h.masuk ? '<span class="text-warning-main">tidak ada check-out</span>' : '<span class="text-secondary-light">—</span>';
        }
        var tglLain = h.keluar.tanggal && h.label.indexOf(h.keluar.tanggal) === -1;
        return '<b>' + esc(h.keluar.jam) + '</b>' + (tglLain ? ' <span class="text-secondary-light text-xs">(' + esc(h.keluar.tanggal) + ')</span>' : '');
    }

    function catatan(h) {
        if (h.merah) { return '<span class="text-danger-main">' + esc(h.merah) + '</span>'; }
        if (h.kuning) { return '<span class="text-warning-main">' + esc(h.kuning) + '</span>'; }
        return '';
    }

    function kartuHari(h) {
        var tap = h.tap.length === 0
            ? '<p class="text-secondary-light text-sm mb-0">Tidak ada tap RFID pada tanggal ini.</p>'
            : '<table class="table sm-table mb-0"><thead><tr><th>Jam</th><th>Jenis</th><th>Gate</th><th>Status</th></tr></thead><tbody>'
                + h.tap.map(function (t) {
                    return '<tr><td>' + esc(t.jam) + '</td><td>' + esc(t.jenis) + '</td><td>' + esc(t.gate) + '</td>'
                        + '<td>' + (t.lolos ? '<span class="text-success-600">PASSED</span>' : '<span class="text-danger-main">tidak lolos</span>') + '</td></tr>';
                }).join('')
                + '</tbody></table>';

        return '<div class="ro-hari-detail border radius-8 p-16">'
            + '<div class="d-flex flex-wrap justify-content-between gap-2 mb-12">'
            + '<div><div class="fw-bold">' + esc(h.label) + '</div>'
            + '<span class="ro-strip__key sh-' + esc(h.kode) + '"></span> <span class="text-sm">' + esc(LABEL_KODE[h.kode] || h.kode) + '</span></div>'
            + '<div class="text-sm">' + catatan(h) + '</div></div>'
            + '<div class="row g-3 mb-12">'
            + '<div class="col-sm-4"><div class="text-xs text-secondary-light">Check-in</div><div>' + jamMasuk(h) + '</div>'
            + '<div class="text-sm text-secondary-light">' + esc(h.masuk ? h.masuk.gate : '') + '</div></div>'
            + '<div class="col-sm-4"><div class="text-xs text-secondary-light">Check-out</div><div>' + jamKeluar(h) + '</div>'
            + '<div class="text-sm text-secondary-light">' + esc(h.keluar ? h.keluar.gate : '') + '</div></div>'
            + '<div class="col-sm-4"><div class="text-xs text-secondary-light">Durasi</div><div class="fw-bold">' + esc(h.durasi || '—') + '</div></div>'
            + '</div>'
            + '<div class="text-xs text-secondary-light mb-4">Semua tap pada tanggal ini</div>' + tap
            + '</div>';
    }

    function tabelHari(hari) {
        // Terbaru di atas; hanya hari yang punya tap atau flag supaya tabel tidak 180 baris kosong.
        var baris = [];
        for (var i = hari.length - 1; i >= 0; i--) {
            var h = hari[i];
            if (h.tap.length === 0 && !h.merah && !h.kuning) { continue; }
            baris.push('<tr class="ro-temuan-row" data-ro-hari-row="' + i + '">'
                + '<td class="text-nowrap">' + esc(h.label) + '</td>'
                + '<td><span class="ro-strip__key sh-' + esc(h.kode) + '"></span> ' + esc(LABEL_KODE[h.kode] || h.kode) + '</td>'
                + '<td>' + jamMasuk(h) + '</td><td>' + esc(h.masuk ? h.masuk.gate : '') + '</td>'
                + '<td>' + jamKeluar(h) + '</td><td>' + esc(h.keluar ? h.keluar.gate : '') + '</td>'
                + '<td class="text-nowrap">' + esc(h.durasi || '—') + '</td>'
                + '<td class="text-sm">' + catatan(h) + '</td>'
                + '</tr>');
        }

        return '<div class="table-responsive ro-hari-scroll"><table class="table bordered-table sm-table mb-0">'
            + '<thead><tr><th>Tanggal</th><th>Kode</th><th>Check-in</th><th>Gate masuk</th>'
            + '<th>Check-out</th><th>Gate keluar</th><th>Durasi</th><th>Catatan</th></tr></thead>'
            + '<tbody>' + baris.join('') + '</tbody></table></div>';
    }

    function pilihHari(wadah, data, i) {
        wadah.querySelectorAll('.ro-strip__bar.is-pilih, [data-ro-hari-row].is-pilih').forEach(function (el) {
            el.classList.remove('is-pilih');
        });
        var bar = wadah.querySelector('[data-ro-hari="' + i + '"]');
        var row = wadah.querySelector('[data-ro-hari-row="' + i + '"]');
        if (bar) { bar.classList.add('is-pilih'); }
        if (row) {
            row.classList.add('is-pilih');
            row.scrollIntoView({ block: 'nearest' });
        }
        wadah.querySelector('[data-ro-hari-detail]').innerHTML = kartuHari(data.hari[i]);
    }

    function renderTimeline(wadah, data) {
        var lencana = data.temuan.map(function (t) {
            var reg = t.indexOf('REG') === 0;
            return '<span class="badge ' + (reg ? 'bg-danger-focus text-danger-main' : 'bg-warning-focus text-warning-main') + ' me-1">' + esc(t) + '</span>';
        }).join('');

        wadah.innerHTML = '<button type="button" class="btn btn-sm btn-light radius-8 mb-16 d-inline-flex align-items-center gap-1" data-ro-kembali>'
            + '<iconify-icon icon="solar:arrow-left-outline"></iconify-icon> Kembali ke daftar</button>'
            + '<div class="d-flex flex-wrap justify-content-between gap-2 mb-16">'
            + '<div><h6 class="mb-1 fw-bold">' + esc(data.nama) + '</h6>'
            + '<span class="text-sm text-secondary-light">' + esc(data.sid) + ' · ' + esc(data.jabatan) + ' · ' + esc(data.pt)
            + ' · ' + esc(data.site) + ' · ' + esc(data.kategori) + ' · blok roster PT ' + esc(data.blok) + ' hari</span></div>'
            + '<div>' + (lencana || '<span class="text-success-600 text-sm fw-medium">Tidak ada temuan per hari ini</span>') + '</div>'
            + '</div>'
            + stripHtml(data.hari)
            + '<div class="ro-sc-legend my-12">'
            + '<span><i class="sh-P ro-strip__key"></i>Pagi</span><span><i class="sh-M ro-strip__key"></i>Malam</span>'
            + '<span><i class="sh-o ro-strip__key"></i>Off</span><span><i class="sh-c ro-strip__key"></i>Cuti (off &gt; 5 hari)</span>'
            + '<span><i class="sh-x ro-strip__key"></i>Belum scan hari ini</span>'
            + '<span><i class="ro-strip__dot is-merah"></i>Pelanggaran regulasi</span>'
            + '<span><i class="ro-strip__dot is-kuning"></i>Tidak sesuai mapping</span>'
            + '</div>'
            + '<p class="text-xs text-secondary-light mb-12">Klik batang hari atau baris tabel untuk melihat detail tap RFID.</p>'
            + '<div data-ro-hari-detail class="mb-16"></div>'
            + tabelHari(data.hari);

        // Default: hari terakhir yang punya tap.
        var awal = data.hari.length - 1;
        while (awal > 0 && data.hari[awal].tap.length === 0) { awal--; }
        pilihHari(wadah, data, awal);

        wadah.addEventListener('click', function (e) {
            var el = e.target.closest('[data-ro-hari], [data-ro-hari-row]');
            if (!el) { return; }
            pilihHari(wadah, data, parseInt(el.dataset.roHari || el.dataset.roHariRow, 10));
        });
    }

    function bukaTimeline(modal, sid) {
        var wadah = modal.querySelector('[data-ro-view-detail]');
        // Ganti node supaya listener klik timeline sebelumnya ikut terbuang.
        var baru = wadah.cloneNode(false);
        wadah.parentNode.replaceChild(baru, wadah);
        baru.innerHTML = '<p class="text-secondary-light text-sm my-24 text-center">Memuat tap RFID ' + esc(sid) + '…</p>';
        tampilkan(modal, true);

        var muat = cacheTimeline[sid]
            ? Promise.resolve(cacheTimeline[sid])
            : ambilJson(modal.dataset.roTimelineUrl.replace('__SID__', encodeURIComponent(sid)));

        muat.then(function (data) {
            cacheTimeline[sid] = data;
            renderTimeline(baru, data);
        }).catch(function (err) {
            baru.innerHTML = '<button type="button" class="btn btn-sm btn-light radius-8 mb-16" data-ro-kembali>← Kembali ke daftar</button>'
                + '<p class="text-danger-main text-sm">Gagal memuat timeline: ' + esc(err.message) + '</p>';
        });
    }

    // ── Pemasangan ─────────────────────────────────────────────────────

    document.querySelectorAll('[id^="roModalTemuan-"]').forEach(function (modal) {
        modal.addEventListener('show.bs.modal', function (e) {
            var pemicu = e.relatedTarget;
            modal.querySelector('[data-ro-cari]').value = '';
            tampilkan(modal, false);
            bukaTab(modal, pemicu && pemicu.dataset.roTab
                ? pemicu.dataset.roTab
                : modal.querySelector('[data-ro-tab-btn]').dataset.roTabBtn);
        });

        modal.querySelectorAll('[data-ro-tab-btn]').forEach(function (b) {
            b.addEventListener('click', function () {
                modal.querySelector('[data-ro-cari]').value = '';
                bukaTab(modal, b.dataset.roTabBtn);
            });
        });

        modal.querySelector('[data-ro-cari]').addEventListener('input', function () {
            saring(modal);
        });

        modal.addEventListener('click', function (e) {
            var row = e.target.closest('[data-ro-sid]');
            if (row) {
                bukaTimeline(modal, row.dataset.roSid);
                return;
            }
            if (e.target.closest('[data-ro-kembali]')) {
                tampilkan(modal, false);
            }
        });
    });
})();

@extends('control-room.layouts.bare')

@section('page-title', 'Edit Roster per SID')
@section('app-title', 'Master Roster')

@push('styles')
@include('dms.roster-master._grid-css')
<style>
  .fs-hari { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: .5rem; }
  .fs-tgl { border: 1.5px solid var(--ocr-line); border-radius: 10px; background: #fff; padding: .45rem .25rem; font: inherit; cursor: pointer; text-align: center; min-width: 0; color: var(--ocr-ink); }
  .fs-tgl small { display: block; font-size: .68rem; color: var(--ocr-muted); line-height: 1.25; }
  .fs-tgl .rm-sel { margin-top: .3rem; min-width: 0; height: 24px; line-height: 24px; font-size: 11px; }
  .fs-tgl:hover:not(:disabled) { border-color: #94a3b8; }
  .fs-tgl:focus-visible, .fs-kode:focus-visible { outline: 2px solid var(--ocr-brand); outline-offset: 1px; }
  .fs-tgl.is-kini small:first-child { color: var(--ocr-brand); font-weight: 700; }
  .fs-tgl.is-kini { background: var(--ocr-brand-soft); }
  .fs-tgl.is-pilih { border-color: var(--ocr-ink); }
  .fs-tgl.is-ubah { border-color: var(--ocr-brand); box-shadow: 0 0 0 1px var(--ocr-brand); }
  .fs-tgl:disabled { background: #f8fafc; cursor: not-allowed; opacity: .6; }
  .fs-tgl small.fs-diajukan { margin-top: .25rem; color: #b45309; font-weight: 600; }
  .fs-pilih { margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--ocr-line); }
  .fs-pilih__judul { font-weight: 700; font-size: .9rem; }
  .fs-grup { font-size: .7rem; font-weight: 600; color: var(--ocr-muted); text-transform: uppercase; letter-spacing: .04em; margin: .75rem 0 .35rem; }
  .fs-kodes { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: .35rem; }
  .fs-kode { border: 1.5px solid transparent; border-radius: 8px; height: 36px; font: inherit; font-size: .75rem; font-weight: 700; cursor: pointer; padding: 0; }
  .fs-kode.is-kini { border-color: var(--ocr-ink); }
  .fs-kode--lebar { grid-column: span 3; }
  .fs-kegiatan { margin-top: .75rem; padding: .75rem; border: 1px solid var(--ocr-line); border-radius: 10px; display: grid; gap: .5rem; }
  .fs-kegiatan label { font-size: .8rem; font-weight: 600; }
  .fs-ringkas { margin: 0; padding: 0; list-style: none; font-size: .86rem; }
  .fs-ringkas li { display: flex; justify-content: space-between; gap: .75rem; padding: .35rem 0; border-bottom: 1px solid #f1f5f9; }
  .fs-ringkas li:last-child { border-bottom: 0; }
  [hidden] { display: none !important; }
  @media (max-width: 420px) { .fs-hari { grid-template-columns: repeat(3, minmax(0, 1fr)); } .fs-kodes { grid-template-columns: repeat(5, minmax(0, 1fr)); } }
</style>
@endpush

@section('content')
<div class="ocr-wrap">
  <div class="ocr-hero">
    <div class="ocr-hero-top"></div>
    <div class="ocr-hero-body">
      <span class="ocr-badge"><span class="material-symbols-outlined" style="font-size:15px">lock_open</span>Master Roster</span>
      <h1>Edit Roster per SID</h1>
      <p class="ocr-lead">Isi SID, lalu ajukan kode roster baru untuk tanggal seminggu sebelum sampai seminggu sesudah hari ini. Jadwal berubah setelah pengajuan disetujui.</p>
      <span class="ocr-pill"><span class="material-symbols-outlined" style="font-size:16px">calendar_today</span>{{ $hariIni->locale('id')->translatedFormat('l, d F Y') }}</span>
    </div>
  </div>

  <div class="ocr-alert ocr-alert-ok" id="fsSukses" role="status" hidden></div>
  <div class="ocr-alert ocr-alert-error" id="fsGalat" role="alert" hidden></div>

  <section class="ocr-card">
    <h2 class="ocr-card-title"><span class="ocr-num">1</span> Cari SID</h2>
    <form id="fsCari" class="ocr-field" style="margin-bottom:0" novalidate>
      <label for="fsSid">Nomor SID <span class="ocr-req">*</span></label>
      <div class="ocr-row">
        <input class="ocr-input ocr-input-mono" type="text" id="fsSid" placeholder="Contoh: C5BXK" required maxlength="50" autocomplete="off" autocapitalize="characters" spellcheck="false">
        <button type="submit" class="ocr-btn ocr-btn-secondary" id="fsCariBtn" style="white-space:nowrap"><span class="material-symbols-outlined">search</span>Cari</button>
      </div>
    </form>
    <div class="ocr-preview" id="fsProfil">
      <div class="ocr-preview-grid">
        <div><span>Nama</span><strong id="fsNama"></strong></div>
        <div><span>Perusahaan</span><strong id="fsPt"></strong></div>
        <div><span>Jabatan</span><strong id="fsJabatan"></strong></div>
        <div><span>Site</span><strong id="fsSite"></strong></div>
      </div>
    </div>
  </section>

  <section class="ocr-card" id="fsJadwal" hidden>
    <h2 class="ocr-card-title"><span class="ocr-num">2</span> Pilih tanggal &amp; kode</h2>
    <p class="ocr-hint" style="margin-top:-.5rem">Pastikan nama di atas benar. Tanggal abu-abu berada di luar periode roster karyawan ini, jadi perlu upload roster dulu. Tanggal bertanda "Diajukan" masih menunggu approval, jadi belum bisa diajukan lagi. Garis putus oranye = roster masih menunggu approval.</p>
    <div class="fs-hari" id="fsHari"></div>
    <div class="fs-pilih" id="fsPilih" hidden>
      <div class="fs-pilih__judul" id="fsPilihJudul" aria-live="polite"></div>
      <div id="fsKode"></div>
    </div>
  </section>

  <section class="ocr-card" id="fsKirim" hidden>
    <h2 class="ocr-card-title"><span class="ocr-num">3</span> Periksa &amp; ajukan</h2>
    <ul class="fs-ringkas" id="fsRingkas"></ul>
    <div class="ocr-field" style="margin:1rem 0 0">
      <label for="fsCatatan">Keterangan <span style="font-weight:400;color:var(--ocr-muted)">(opsional)</span></label>
      <textarea class="ocr-input" id="fsCatatan" rows="2" maxlength="1000" placeholder="Mis. izin keperluan keluarga"></textarea>
    </div>
    <button type="button" class="ocr-btn ocr-btn-primary ocr-btn-block" id="fsSimpan" style="margin-top:1rem"><span class="material-symbols-outlined">send</span><span id="fsSimpanTeks">Kirim pengajuan</span></button>
    <button type="button" class="ocr-btn ocr-btn-secondary ocr-btn-block" id="fsBatal" style="margin-top:.65rem">Batalkan perubahan</button>
  </section>
</div>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    var URL_CARI = @json(route('dms.master-roster.edit-form.cari', absolute: false));
    var URL_SIMPAN = @json(route('dms.master-roster.edit-form.simpan', absolute: false));
    var HARI_INI = @json($hariIni->toDateString());
    var NAMA_KODE = @json($kode);
    var KODE = Object.keys(NAMA_KODE);
    var KODE_KEGIATAN = @json($kodeKegiatan);
    var DAFTAR_KEGIATAN = @json($kegiatan);
    var LAINNYA = 'Lainnya';
    var GRUP = [
        ['Day', KODE.filter(function (k) { return /^D\d/.test(k); })],
        ['Night', KODE.filter(function (k) { return /^N\d/.test(k); })],
        ['Lainnya', KODE.filter(function (k) { return !/^[DN]\d/.test(k); })],
    ];

    function el(id) { return document.getElementById(id); }
    var sidInput = el('fsSid');
    var cariBtn = el('fsCariBtn');
    var simpanBtn = el('fsSimpan');

    var data = null;     // jadwal dari server
    var ubah = {};       // tanggal => kode baru
    var kegiatan = {};   // tanggal => nama kegiatan (kode KG)
    var pilih = null;    // tanggal yang sedang dipilih

    function kelasKode(k) { return k === '' ? 'rm-kosong' : (KODE.indexOf(k) !== -1 ? 'rm-' + k : 'rm-lain'); }
    function tanggal(t, opsi) { return new Date(t + 'T00:00:00').toLocaleDateString('id-ID', opsi); }
    function kodeKini(h) { return ubah[h.tanggal] !== undefined ? ubah[h.tanggal] : h.kode; }

    function tampil(kotak, pesan) {
        kotak.hidden = !pesan;
        kotak.textContent = pesan || '';
    }

    function tampilGalat(daftar) {
        tampil(el('fsSukses'), '');
        var kotak = el('fsGalat');
        kotak.hidden = false;
        kotak.textContent = '';
        var ul = document.createElement('ul');
        daftar.forEach(function (g) {
            var li = document.createElement('li');
            li.textContent = g;
            ul.appendChild(li);
        });
        kotak.appendChild(ul);
        kotak.scrollIntoView({ block: 'center', behavior: 'smooth' });
    }

    function galatDari(d, status) {
        return d.galat || (d.errors ? Object.values(d.errors).flat() : [status === 429 ? 'Terlalu banyak percobaan. Tunggu satu menit lalu coba lagi.' : (d.message || 'Terjadi kesalahan.')]);
    }

    function kirim(url, opsi) {
        return fetch(url, Object.assign({ headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }, opsi || {}))
            .then(function (r) {
                return r.json().catch(function () { return {}; }).then(function (d) {
                    if (r.ok && d.ok) { return d; }
                    return Promise.reject(galatDari(d, r.status));
                });
            })
            .catch(function (g) {
                return Promise.reject(Array.isArray(g) ? g : ['Gagal terhubung ke server. Coba lagi.']);
            });
    }

    function adaUbahan() { return Object.keys(ubah).length > 0; }

    function gambarHari() {
        var wadah = el('fsHari');
        wadah.textContent = '';
        data.hari.forEach(function (h) {
            var kode = kodeKini(h);
            var diajukan = data.diajukan[h.tanggal];
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'fs-tgl' + (h.tanggal === HARI_INI ? ' is-kini' : '') + (ubah[h.tanggal] !== undefined ? ' is-ubah' : '') + (pilih === h.tanggal ? ' is-pilih' : '');
            b.disabled = !h.boleh || diajukan !== undefined;
            b.title = diajukan !== undefined ? 'Menunggu approval: diajukan menjadi ' + diajukan : (h.boleh ? (kegiatan[h.tanggal] || '') : 'Di luar periode roster karyawan ini');
            b.setAttribute('aria-pressed', pilih === h.tanggal ? 'true' : 'false');

            var atas = document.createElement('small');
            atas.textContent = h.tanggal === HARI_INI ? 'Hari ini' : tanggal(h.tanggal, { weekday: 'short' });
            var bawah = document.createElement('small');
            bawah.textContent = tanggal(h.tanggal, { day: '2-digit', month: 'short' });
            var sel = document.createElement('div');
            sel.className = 'rm-sel ' + kelasKode(kode) + (h.menunggu && ubah[h.tanggal] === undefined ? ' rm-menunggu' : '');
            sel.textContent = kode || '–';
            b.append(atas, bawah, sel);
            if (diajukan !== undefined) {
                var tanda = document.createElement('small');
                tanda.className = 'fs-diajukan';
                tanda.textContent = 'Diajukan: ' + diajukan;
                b.appendChild(tanda);
            }
            b.addEventListener('click', function () { bukaKode(h.tanggal); });
            wadah.appendChild(b);
        });
    }

    function bukaKode(tgl) {
        pilih = tgl;
        var h = data.hari.find(function (x) { return x.tanggal === tgl; });
        el('fsPilih').hidden = false;
        el('fsPilihJudul').textContent = tanggal(tgl, { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' });
        var wadah = el('fsKode');
        wadah.textContent = '';
        GRUP.forEach(function (g) {
            var judul = document.createElement('div');
            judul.className = 'fs-grup';
            judul.textContent = g[0];
            var isi = document.createElement('div');
            isi.className = 'fs-kodes';
            g[1].forEach(function (k) {
                var b = document.createElement('button');
                b.type = 'button';
                b.className = 'fs-kode ' + kelasKode(k) + (kodeKini(h) === k ? ' is-kini' : '');
                b.title = NAMA_KODE[k];
                if (k === KODE_KEGIATAN) {
                    b.classList.add('fs-kode--lebar');
                    b.textContent = NAMA_KODE[k];
                    b.setAttribute('aria-expanded', 'false');
                    b.addEventListener('click', function () {
                        panel.hidden = !panel.hidden;
                        b.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
                    });
                } else {
                    b.textContent = k;
                    b.addEventListener('click', function () { pilihKode(h, k); });
                }
                isi.appendChild(b);
            });
            wadah.append(judul, isi);
        });
        var panel = panelKegiatan(h);
        wadah.appendChild(panel);
        gambarHari();
    }

    function panelKegiatan(h) {
        var panel = document.createElement('div');
        panel.className = 'fs-kegiatan';
        panel.hidden = true;

        var label = document.createElement('label');
        label.htmlFor = 'fsKegiatanPilih';
        label.textContent = 'Jenis kegiatan (tanggal ini tetap dihitung on-site)';

        var pilihan = document.createElement('select');
        pilihan.className = 'ocr-input';
        pilihan.id = 'fsKegiatanPilih';
        pilihan.add(new Option('Pilih kegiatan…', ''));
        DAFTAR_KEGIATAN.concat(LAINNYA).forEach(function (nama) { pilihan.add(new Option(nama, nama)); });

        var ketik = document.createElement('input');
        ketik.className = 'ocr-input';
        ketik.type = 'text';
        ketik.maxLength = 100;
        ketik.placeholder = 'Tulis nama kegiatan';
        ketik.setAttribute('aria-label', 'Nama kegiatan');
        ketik.hidden = true;

        var pakai = document.createElement('button');
        pakai.type = 'button';
        pakai.className = 'ocr-btn ocr-btn-secondary';
        pakai.textContent = 'Pakai untuk tanggal ini';

        function namaKegiatan() {
            return pilihan.value === LAINNYA ? ketik.value.trim() : pilihan.value;
        }
        function segarkan() {
            ketik.hidden = pilihan.value !== LAINNYA;
            pakai.disabled = namaKegiatan() === '';
        }
        pilihan.addEventListener('change', function () {
            segarkan();
            if (!ketik.hidden) { ketik.focus(); }
        });
        ketik.addEventListener('input', segarkan);
        pakai.addEventListener('click', function () { pilihKode(h, KODE_KEGIATAN, namaKegiatan()); });
        segarkan();

        panel.append(label, pilihan, ketik, pakai);
        return panel;
    }

    function pilihKode(h, k, nama) {
        delete kegiatan[h.tanggal];
        if (k === h.kode) {
            delete ubah[h.tanggal];
        } else {
            ubah[h.tanggal] = k;
            if (nama) { kegiatan[h.tanggal] = nama; }
        }
        pilih = null;
        el('fsPilih').hidden = true;
        tampil(el('fsSukses'), '');
        tampil(el('fsGalat'), '');
        gambarHari();
        gambarRingkas();
    }

    function gambarRingkas() {
        var ul = el('fsRingkas');
        ul.textContent = '';
        data.hari.forEach(function (h) {
            if (ubah[h.tanggal] === undefined) { return; }
            var li = document.createElement('li');
            var kiri = document.createElement('span');
            kiri.textContent = tanggal(h.tanggal, { weekday: 'short', day: '2-digit', month: 'short' });
            var kanan = document.createElement('strong');
            kanan.textContent = (h.kode || 'Kosong') + ' → ' + ubah[h.tanggal] + (kegiatan[h.tanggal] ? ' (' + kegiatan[h.tanggal] + ')' : '');
            li.append(kiri, kanan);
            ul.appendChild(li);
        });
        var n = Object.keys(ubah).length;
        el('fsSimpanTeks').textContent = 'Kirim pengajuan (' + n + ' tanggal)';
        el('fsKirim').hidden = n === 0;
    }

    function pasang(d) {
        data = d;
        ubah = {};
        kegiatan = {};
        pilih = null;
        el('fsCatatan').value = '';
        sidInput.value = d.sid;
        el('fsNama').textContent = d.nama || '—';
        el('fsPt').textContent = d.perusahaan || '—';
        el('fsJabatan').textContent = d.jabatan || '—';
        el('fsSite').textContent = d.site || '—';
        el('fsProfil').classList.add('is-visible');
        el('fsJadwal').hidden = false;
        el('fsPilih').hidden = true;
        gambarHari();
        gambarRingkas();
    }

    function kosongkan() {
        data = null;
        ubah = {};
        kegiatan = {};
        pilih = null;
        el('fsProfil').classList.remove('is-visible');
        el('fsJadwal').hidden = true;
        el('fsKirim').hidden = true;
    }

    el('fsCari').addEventListener('submit', function (e) {
        e.preventDefault();
        var sid = sidInput.value.trim().toUpperCase();
        tampil(el('fsSukses'), '');
        tampil(el('fsGalat'), '');
        if (!sid) { tampilGalat(['SID wajib diisi.']); return; }
        if (adaUbahan() && !window.confirm('Perubahan yang belum disimpan akan hilang. Lanjut mencari?')) { return; }
        cariBtn.disabled = true;
        kirim(URL_CARI + '?' + new URLSearchParams({ sid: sid }).toString())
            .then(function (r) { pasang(r.data); })
            .catch(function (g) { kosongkan(); tampilGalat(g); })
            .finally(function () { cariBtn.disabled = false; });
    });

    el('fsBatal').addEventListener('click', function () {
        pasang(data);
        tampil(el('fsGalat'), '');
    });

    simpanBtn.addEventListener('click', function () {
        var sel = Object.keys(ubah).map(function (t) { return { tanggal: t, kode: ubah[t], kegiatan: kegiatan[t] || null }; });
        simpanBtn.disabled = true;
        tampil(el('fsGalat'), '');
        kirim(URL_SIMPAN, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ sid: data.sid, sel: sel, catatan: el('fsCatatan').value.trim() }),
        })
            .then(function (r) {
                pasang(r.data);
                tampil(el('fsSukses'), r.pesan);
                el('fsSukses').scrollIntoView({ block: 'center', behavior: 'smooth' });
            })
            .catch(tampilGalat)
            .finally(function () { simpanBtn.disabled = false; });
    });

    window.addEventListener('beforeunload', function (e) {
        if (adaUbahan()) { e.preventDefault(); e.returnValue = ''; }
    });
})();
</script>
@endpush

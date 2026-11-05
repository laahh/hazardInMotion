{{-- Perilaku panel _ttd-form. $objek: kata benda di konfirmasi ("roster", "pengajuan"). --}}
{{-- signature_pad (szimek) — tanda tangan digambar di canvas, disimpan PNG. --}}
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
<script>
(function () {
    'use strict';

    var form = document.getElementById('rmFormSetuju');
    var KONFIRMASI = @json('Setujui '.$objek.' ini dengan tanda tangan tersebut?');
    if (!form) { return; }

    var canvas = form.querySelector('[data-ttd-canvas]');
    var mode = form.querySelector('[data-ttd-mode]');
    var data = form.querySelector('[data-ttd-data]');
    var file = form.querySelector('[data-ttd-file]');
    var pratinjau = form.querySelector('[data-ttd-pratinjau]');
    var petunjuk = form.querySelector('[data-ttd-petunjuk]');
    var galat = form.querySelector('[data-ttd-galat]');

    function tampilGalat(teks) {
        galat.textContent = teks;
        galat.hidden = !teks;
    }

    if (typeof SignaturePad === 'undefined') {
        tampilGalat('Library tanda tangan gagal dimuat (cek koneksi internet). Gunakan tab "Upload gambar".');
        return;
    }

    var pad = new SignaturePad(canvas, { backgroundColor: 'rgba(255,255,255,0)', penColor: '#0F172A', minWidth: 0.8, maxWidth: 2.6 });
    pad.addEventListener('beginStroke', function () { petunjuk.hidden = true; tampilGalat(''); });

    // Canvas mengikuti lebar kotak & kerapatan layar; goresan dipertahankan saat diubah ukurannya.
    function sesuaikan() {
        var rasio = Math.max(window.devicePixelRatio || 1, 1);
        var goresan = pad.toData();
        canvas.width = canvas.offsetWidth * rasio;
        canvas.height = canvas.offsetHeight * rasio;
        canvas.getContext('2d').scale(rasio, rasio);
        pad.clear();
        pad.fromData(goresan);
    }
    window.addEventListener('resize', sesuaikan);
    sesuaikan();

    form.querySelector('[data-ttd-hapus]').addEventListener('click', function () { pad.clear(); petunjuk.hidden = false; });
    form.querySelector('[data-ttd-undo]').addEventListener('click', function () {
        var goresan = pad.toData();
        goresan.pop();
        pad.fromData(goresan);
        petunjuk.hidden = goresan.length > 0;
    });

    form.querySelectorAll('[data-ttd-tab]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var pilih = btn.dataset.ttdTab;
            mode.value = pilih;
            form.querySelectorAll('[data-ttd-tab]').forEach(function (b) {
                var aktif = b === btn;
                b.classList.toggle('is-aktif', aktif);
                b.setAttribute('aria-pressed', aktif ? 'true' : 'false');
            });
            form.querySelectorAll('[data-ttd-panel]').forEach(function (p) { p.hidden = p.dataset.ttdPanel !== pilih; });
            if (pilih === 'gambar') { sesuaikan(); }
            tampilGalat('');
        });
    });

    file.addEventListener('change', function () {
        var f = file.files && file.files[0];
        if (!f) { pratinjau.hidden = true; return; }
        if (!/^image\/(png|jpeg)$/.test(f.type)) { tampilGalat('File harus PNG atau JPG.'); file.value = ''; return; }
        if (f.size > 2 * 1024 * 1024) { tampilGalat('File tanda tangan maksimal 2 MB.'); file.value = ''; return; }
        tampilGalat('');
        pratinjau.src = URL.createObjectURL(f);
        pratinjau.hidden = false;
    });

    // Tanda tangan gambar dikirim sebagai FILE PNG, bukan teks base64 di
    // ttd_data: WAF server memblokir (403 polos) body berisi data URL panjang.
    // ttd_data hanya cadangan untuk browser tanpa DataTransfer.
    function kirimSebagaiFile(blob) {
        try {
            var dt = new DataTransfer();
            dt.items.add(new File([blob], 'ttd.png', { type: 'image/png' }));
            file.files = dt.files;
            data.value = '';
        } catch (err) {
            data.value = pad.toDataURL('image/png');
            file.value = '';
        }
        form.submit();
    }

    form.addEventListener('submit', function (e) {
        if (mode.value === 'gambar') {
            e.preventDefault();
            if (pad.isEmpty()) { tampilGalat('Tanda tangan belum digambar.'); return; }
            if (!window.confirm(KONFIRMASI)) { return; }
            canvas.toBlob(function (blob) {
                if (!blob) { tampilGalat('Gagal membaca tanda tangan, coba gambar ulang.'); return; }
                kirimSebagaiFile(blob);
            }, 'image/png');
            return;
        }
        if (!file.files || !file.files.length) { e.preventDefault(); tampilGalat('Pilih file gambar tanda tangan.'); return; }
        data.value = '';
        if (!window.confirm(KONFIRMASI)) { e.preventDefault(); }
    });
})();
</script>

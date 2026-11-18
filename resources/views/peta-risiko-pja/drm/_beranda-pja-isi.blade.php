{{--
  Markup Beranda PJA (bersama): halaman /peta-risiko-pja/pja/{id} dan tab "Beranda PJA" di halaman indeks.
  Semua elemen ada di dalam #br-wadah; skrip _beranda-pja-script hanya mencari elemen di dalam wadah ini.
  $denganSub: tampilkan baris keterangan (#dr-sub) di dalam wadah (tab indeks; di halaman PJA ada di judul).
--}}
<div id="br-wadah">
@if (! empty($denganSub))
  <p class="dr-mute" id="dr-sub" style="margin:0 0 14px"></p>
@endif
  <div id="br-isi" class="br-root"><div class="pr-card"><div class="pr-empty">Memuat Beranda PJA…</div></div></div>

  {{-- Lampiran hanya tampil di sub-tab "Lainnya" (diatur skrip Beranda). --}}
  <div id="br-lampiran" class="br-lampiran" hidden>
  <details class="pr-card" style="padding:0">
    <summary class="pr-card-head" style="cursor:pointer"><div><b>Lampiran: pengawasan personil PJA & coverage SAP</b><small>Pemenuhan SAP harian (14 hari), mingguan (BeARC), dan laporan per detail lokasi</small></div></summary>
    <div id="dr-personil"><div class="pr-empty">Memuat…</div></div>
  </details>

  <details class="pr-card" style="padding:0">
    <summary class="pr-card-head" style="cursor:pointer"><div><b>Lampiran: temuan Hazard/Inspeksi yang masih open</b><small id="dr-temuan-sub">Di detail lokasi PJA</small></div></summary>
    <div class="dr-scroll"><table class="pr-tbl dr-tbl" id="dr-temuan"></table></div>
  </details>
  </div>

  <div class="pr-scrim" id="dr-scrim"></div>
  <aside class="pr-panel" id="dr-panel" aria-hidden="true" aria-labelledby="dr-panel-judul">
    <div class="pr-panel-head"><p class="pr-kicker" id="dr-panel-kicker"></p><h2 id="dr-panel-judul"></h2>
      <button type="button" class="pr-x" id="dr-tutup" aria-label="Tutup">✕</button></div>
    <div class="pr-panel-body" id="dr-panel-isi"></div>
  </aside>
</div>

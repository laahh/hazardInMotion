{{-- Definisi status + helper tampilan bersama (halaman daftar & detail). --}}
<script>
  window.PR = {
    STATUS: {
      gap: { label: 'Ada gap', desc: 'Dilaporkan di SAP, tapi masih open atau berulang' },
      tindak: { label: 'Tanpa kontrol HIRA', desc: 'Belum dilaporkan dan pengendalian HIRA belum terlihat' },
      fyi: { label: 'Cukup dipantau', desc: 'Belum dilaporkan, pengendalian HIRA sudah ada' },
      ok: { label: 'Terkontrol', desc: 'Dilaporkan dan sudah terverifikasi' },
    },
    URUT: ['gap', 'tindak', 'fyi', 'ok'],
    fmt: (n) => Number(n || 0).toLocaleString('id-ID'),
    esc: (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])),
    kpis(tot) {
      return this.URUT.map((k) => `<div class="pr-kpi s-${k}"><b>${this.fmt(tot[k])}</b><strong>${this.STATUS[k].label}</strong><span>${this.STATUS[k].desc}</span></div>`).join('');
    },
    comp(n) {
      const t = this.URUT.reduce((a, k) => a + (n[k] || 0), 0) || 1;
      const judul = this.URUT.map((k) => `${this.STATUS[k].label}: ${n[k] || 0}`).join(' · ');
      return `<div class="pr-comp" title="${judul}" role="img" aria-label="${judul}">${this.URUT.map((k) => n[k] ? `<i class="s-${k}" style="width:${(100 * n[k] / t).toFixed(2)}%"></i>` : '').join('')}</div>`;
    },
    tag(k, extra = '') {
      return `<span class="pr-tag s-${k}">${this.STATUS[k].label}${extra}</span>`;
    },
  };
</script>

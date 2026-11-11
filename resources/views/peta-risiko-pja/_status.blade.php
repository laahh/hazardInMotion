{{-- Definisi status bersama untuk JS (warna = kelas Wowdash). --}}
<script>
  window.PR_STATUS = {
    gap: { label: 'Ada gap', desc: 'Dilaporkan, tapi masih open atau berulang', tag: 'Tindak lanjut', icon: 'ri-error-warning-line', soft: 'bg-warning-focus', text: 'text-warning-main', bar: 'bg-warning-main' },
    tindak: { label: 'Tanpa kontrol HIRA', desc: 'Belum dilaporkan dan pengendalian HIRA belum terlihat', tag: 'Kendalikan', icon: 'ri-shield-cross-line', soft: 'bg-danger-focus', text: 'text-danger-main', bar: 'bg-danger-main' },
    fyi: { label: 'Belum dilaporkan', desc: 'Pengendalian HIRA sudah ada, tidak perlu dicari-cari', tag: 'Info', icon: 'ri-information-line', soft: 'bg-neutral-200', text: 'text-secondary-light', bar: 'bg-neutral-400' },
    ok: { label: 'Terkontrol', desc: 'Dilaporkan dan sudah terverifikasi', tag: 'Aman', icon: 'ri-checkbox-circle-line', soft: 'bg-success-focus', text: 'text-success-main', bar: 'bg-success-main' },
  };
  window.PR_UTIL = {
    fmt: (n) => Number(n || 0).toLocaleString('id-ID'),
    esc: (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])),
    kartu(k, n) {
      const s = window.PR_STATUS[k];
      return `<div class="col-xxl-3 col-sm-6">
        <div class="card h-100 radius-8 border-0">
          <div class="card-body p-20 d-flex align-items-start gap-16">
            <span class="pr-ic ${s.soft} ${s.text} flex-shrink-0 d-flex justify-content-center align-items-center radius-8"><i class="${s.icon}"></i></span>
            <div class="flex-grow-1">
              <div class="d-flex align-items-center justify-content-between gap-2">
                <span class="fw-medium text-secondary-light text-md">${s.label}</span>
                <span class="${s.soft} ${s.text} px-8 py-2 rounded-pill fw-medium text-xs">${s.tag}</span>
              </div>
              <h6 class="fw-semibold text-primary-light mb-1 mt-4">${this.fmt(n)}</h6>
              <p class="text-sm text-secondary-light mb-0">${s.desc}</p>
            </div>
          </div>
        </div>
      </div>`;
    },
    stack(n) {
      const t = (n.gap + n.tindak + n.fyi + n.ok) || 1;
      return `<div class="progress-stacked pr-stack" title="${Object.keys(window.PR_STATUS).map((k) => window.PR_STATUS[k].label + ': ' + n[k]).join(' · ')}">${Object.keys(window.PR_STATUS).map((k) => n[k] ? `<div class="progress" style="width:${(100 * n[k] / t).toFixed(2)}%"><div class="progress-bar ${window.PR_STATUS[k].bar}"></div></div>` : '').join('')}</div>`;
    },
    badge(k, extra = '') {
      const s = window.PR_STATUS[k];
      return `<span class="${s.soft} ${s.text} px-12 py-4 rounded-pill fw-medium text-sm text-nowrap">${s.label}${extra}</span>`;
    },
  };
</script>

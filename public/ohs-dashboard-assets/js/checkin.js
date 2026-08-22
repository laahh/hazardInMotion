const API_BASE = window.OHS_API_BASE || '/ohs-dashboard/api';

async function api(path, options = {}) {
    const res = await fetch(API_BASE + path, {
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        ...options,
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.error || 'Permintaan gagal');
    return data;
}

const root = document.querySelector('.ohs-checkin');
const eventId = root?.dataset.eventId || new URLSearchParams(location.search).get('eventId') || '';
const message = document.getElementById('checkin-message');
const results = document.getElementById('checkin-results');

function show(text, ok) {
    message.style.color = ok ? '#166534' : '#b91c1c';
    message.textContent = text;
}

async function loadEvent() {
    if (!eventId) {
        show('Event tidak ditemukan atau QR sudah tidak berlaku.', false);
        return;
    }
    try {
        const data = await api('/events/checkin-info?eventId=' + encodeURIComponent(eventId));
        document.getElementById('checkin-event-name').textContent = data.event.EventName;
        document.getElementById('checkin-meta').textContent =
            `${data.event.EventDate} • ${data.event.Where} • ${data.attendanceCount} sudah absen`;
    } catch (e) {
        show(e.message, false);
    }
}

let timer;
document.getElementById('checkin-q')?.addEventListener('input', () => {
    clearTimeout(timer);
    timer = setTimeout(async () => {
        const q = document.getElementById('checkin-q').value.trim();
        if (q.length < 2) { results.innerHTML = ''; return; }
        const rows = await api('/employees/search?q=' + encodeURIComponent(q));
        const items = Array.isArray(rows) ? rows : [];
        results.innerHTML = items.map((r) => `<li data-id="${r.EmpId}"><strong>${r.EmpName}</strong><div class="ohs-muted">${r.EmpId} · ${r.Team || ''}</div></li>`).join('') || '<li>Tidak ada hasil</li>';
    }, 250);
});

results?.addEventListener('click', async (e) => {
    const li = e.target.closest('li[data-id]');
    if (!li) return;
    const ok = confirm('Absen sebagai ' + li.textContent + '?');
    if (!ok) return;
    try {
        const r = await api('/events/checkin', {
            method: 'POST',
            body: JSON.stringify({ EventId: eventId, EmpId: li.dataset.id }),
        });
        if (r.alreadyCheckedIn) {
            show(r.empName + ' sudah absen pada ' + r.checkInAt, true);
        } else {
            show('Berhasil: ' + r.empName + ' check-in ' + r.checkInAt, true);
        }
        loadEvent();
    } catch (err) {
        show(err.message, false);
    }
});

loadEvent();

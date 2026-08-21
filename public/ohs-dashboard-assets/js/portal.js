const API_BASE = window.OHS_API_BASE || '/ohs-dashboard/api';

async function api(path, options = {}) {
    const res = await fetch(API_BASE + path, {
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', ...(options.headers || {}) },
        ...options,
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.error || 'Permintaan gagal');
    return data;
}

function el(html) {
    const t = document.createElement('template');
    t.innerHTML = html.trim();
    return t.content.firstElementChild;
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function badge(status) {
    const cls = String(status || '').replace(/\s+/g, '-');
    return `<span class="badge ${cls}">${escapeHtml(status)}</span>`;
}

function fillSelect(select, values, allLabel) {
    if (!select) return;
    const current = select.value || allLabel;
    select.innerHTML = `<option>${escapeHtml(allLabel)}</option>` + values.map((v) => `<option>${escapeHtml(v)}</option>`).join('');
    select.value = values.includes(current) || current === allLabel ? current : allLabel;
}

function debounce(fn, ms) {
    let t;
    return (...args) => {
        clearTimeout(t);
        t = setTimeout(() => fn(...args), ms);
    };
}

function openModal(title, bodyHtml, onMount) {
    closeModal();
    const node = el(`<div class="ohs-modal-backdrop"><div class="ohs-modal"><div style="display:flex;justify-content:space-between;gap:12px"><h3>${escapeHtml(title)}</h3><button type="button" data-close>Tutup</button></div><div class="ohs-modal-body">${bodyHtml}</div></div></div>`);
    node.addEventListener('click', (e) => {
        if (e.target === node || e.target.dataset.close !== undefined) closeModal();
    });
    document.getElementById('ohs-modal-root').appendChild(node);
    if (onMount) onMount(node);
}

function closeModal() {
    document.getElementById('ohs-modal-root').innerHTML = '';
}

function employeePicker(root, hiddenName) {
    const input = root.querySelector('input[type="search"]');
    const list = root.querySelector('ul');
    const hidden = root.querySelector(`input[name="${hiddenName}"]`);
    const run = debounce(async () => {
        const q = input.value.trim();
        if (q.length < 2) { list.innerHTML = ''; return; }
        const rows = await api('/employees/search?q=' + encodeURIComponent(q));
        list.innerHTML = rows.map((r) => `<li data-id="${escapeHtml(r.EmpId)}" data-name="${escapeHtml(r.EmpName)}">${escapeHtml(r.EmpName)} • ${escapeHtml(r.EmpId)} • ${escapeHtml(r.Team)}</li>`).join('') || '<li>Tidak ada hasil</li>';
    }, 250);
    input.addEventListener('input', run);
    list.addEventListener('click', (e) => {
        const li = e.target.closest('li[data-id]');
        if (!li) return;
        hidden.value = li.dataset.id;
        input.value = li.dataset.name + ' (' + li.dataset.id + ')';
        list.innerHTML = '';
    });
}

let INIT = null;
async function loadInit() {
    if (!INIT) INIT = await api('/init');
    return INIT;
}

function initOverview() {
    const page = document.querySelector('[data-ohs-page="overview"]');
    if (!page) return;
    const team = document.getElementById('filter-team');
    const site = document.getElementById('filter-site');
    const year = document.getElementById('filter-year');

    async function refresh() {
        const data = await api('/dashboard/overview', {
            method: 'POST',
            body: JSON.stringify({ team: team.value, site: site.value, year: Number(year.value) }),
        });
        const k = data.kpis;
        document.getElementById('overview-kpis').innerHTML = [
            ['Event This Week', k.eventThisWeek],
            ['Upcoming Event', k.upcomingEvent],
            ['Leave This Week', k.leaveThisWeek],
            ['Upcoming Leave', k.upcomingLeave],
            ['Project Active', k.projectActive],
            ['Issue Active', k.issueActive],
            ['Effective %', data.workforceEffectiveness.effectiveWorkingPercent],
        ].map(([l, v]) => `<div class="ohs-kpi"><span>${l}</span><b>${v}</b></div>`).join('');

        const renderGroup = (title, items, fields) => {
            const body = (items || []).map((it) => `<tr>${fields.map((f) => `<td>${escapeHtml(it[f] ?? '')}</td>`).join('')}</tr>`).join('') || '<tr><td colspan="4">Tidak ada data</td></tr>';
            return `<details open><summary class="collapse-h">${title} (${(items || []).length})</summary><table class="ohs-table">${body}</table></details>`;
        };
        document.getElementById('overview-events').innerHTML =
            '<h3>Event Status</h3>' +
            renderGroup('This Week', data.eventStatus.thisWeek, ['EventName', 'EventDate', 'PICName', 'Where']) +
            renderGroup('Next Week', data.eventStatus.nextWeek, ['EventName', 'EventDate', 'PICName', 'Where']) +
            renderGroup('Next 2 Week', data.eventStatus.nextTwoWeek, ['EventName', 'EventDate', 'PICName', 'Where']) +
            renderGroup('More Than 2 Weeks Ahead', data.eventStatus.moreThanTwoWeeks, ['EventName', 'EventDate', 'PICName', 'Where']);

        document.getElementById('overview-leave').innerHTML =
            '<h3>Leave Status</h3>' +
            renderGroup('Leave This Week', data.leaveStatus.thisWeek, ['EmpName', 'LeaveType', 'StartDate', 'EndDate']) +
            renderGroup('Upcoming Leave', data.leaveStatus.upcoming, ['EmpName', 'LeaveType', 'StartDate', 'EndDate']);

        const w = data.workforceEffectiveness;
        document.getElementById('overview-effectiveness').innerHTML = `<h3>Workforce Effectiveness</h3><p>${w.employeeCount} karyawan • ${w.effectiveWorkingPercent}% hari kerja efektif</p>`;
        document.getElementById('overview-leaderboard').innerHTML = `<h3>Leaderboard Working Days</h3>
            <table class="ohs-table"><thead><tr><th>Nama</th><th>Team</th><th>Leave YTD</th><th>Effective %</th></tr></thead>
            <tbody>${data.leaderboard.map((r) => `<tr data-emp="${escapeHtml(r.EmpId)}"><td>${escapeHtml(r.EmpName)}</td><td>${escapeHtml(r.Team)}</td><td>${r.LeaveYTD}</td><td>${r.EffectiveWorkingPercent}</td></tr>`).join('')}</tbody></table>`;
        document.getElementById('overview-leaderboard').querySelectorAll('tr[data-emp]').forEach((tr) => {
            tr.addEventListener('click', async () => {
                const hist = await api(`/leave/history?empId=${encodeURIComponent(tr.dataset.emp)}&year=${year.value}`);
                openModal('Riwayat Cuti ' + (hist.employee.EmpName || ''), `<p>Leave YTD: ${hist.leaveDaysYTD} hari • Effective: ${hist.effectiveWorkingPercent}%</p>
                    <table class="ohs-table"><tr><th>Tipe</th><th>Start</th><th>End</th><th>Status</th></tr>
                    ${hist.records.map((r) => `<tr><td>${escapeHtml(r.LeaveType)}</td><td>${r.StartDate}</td><td>${r.EndDate}</td><td>${r.Status}</td></tr>`).join('')}</table>`);
            });
        });

        const pageSize = 10;
        let pageNo = 1;
        const renderTrackers = () => {
            const all = data.trackerHighlights || [];
            const slice = all.slice((pageNo - 1) * pageSize, pageNo * pageSize);
            document.getElementById('overview-trackers').innerHTML = `<h3>Tracker Highlights</h3>
                <table class="ohs-table"><thead><tr><th>Type</th><th>Nama</th><th>Status</th><th>Due</th><th>%</th></tr></thead>
                <tbody>${slice.map((t) => `<tr><td>${escapeHtml(t.TrackerType)}</td><td>${escapeHtml(t.ProjectIssueName)}</td><td>${badge(t.EffectiveStatus)}</td><td>${t.DueDate}</td><td>${t.CurrentPercentComplete}</td></tr>`).join('')}</tbody></table>
                <div class="ohs-pager"><button type="button" id="trk-prev">Prev</button><span>Hal ${pageNo}</span><button type="button" id="trk-next">Next</button></div>`;
            document.getElementById('trk-prev').onclick = () => { if (pageNo > 1) { pageNo--; renderTrackers(); } };
            document.getElementById('trk-next').onclick = () => { if (pageNo * pageSize < all.length) { pageNo++; renderTrackers(); } };
        };
        renderTrackers();
    }

    loadInit().then((init) => {
        fillSelect(team, init.teams, 'All Teams');
        fillSelect(site, init.sites, 'All Sites');
        year.innerHTML = init.years.map((y) => `<option ${y === init.currentYear ? 'selected' : ''}>${y}</option>`).join('');
        refresh().catch((e) => alert(e.message));
    });
    document.getElementById('btn-refresh').addEventListener('click', () => refresh().catch((e) => alert(e.message)));
}

function initLeave() {
    const page = document.querySelector('[data-ohs-page="leave"]');
    if (!page) return;
    const state = { viewMode: 'WEEK', anchorISO: '' };
    const team = document.getElementById('cal-team');
    const site = document.getElementById('cal-site');

    async function refresh() {
        const data = await api('/calendar/range', {
            method: 'POST',
            body: JSON.stringify({
                viewMode: state.viewMode,
                anchorISO: state.anchorISO,
                team: team.value,
                site: site.value,
                search: document.getElementById('cal-search').value,
            }),
        });
        state.anchorISO = data.rangeStart;
        document.getElementById('calendar-meta').textContent =
            `${data.rangeStart} s/d ${data.rangeEnd} • Event ${data.counts.events} • Project ${data.counts.projects} • Issue ${data.counts.issues} • Acting ${data.counts.actingTransfers}`;
        const colCount = data.cols.length;
        const head = `<div class="cal-row" style="grid-template-columns: 220px repeat(${colCount}, 1fr)"><div class="cal-name">Karyawan</div>${data.cols.map((c) => `<div class="cal-name">${escapeHtml(c.label)}</div>`).join('')}</div>`;
        const rows = data.rows.map((row) => {
            const bars = data.cols.map((col) => {
                const hits = (row.items || []).filter((it) => it.start <= col.end && it.end >= col.start);
                return `<div>${hits.map((it) => `<div class="cal-chip ${it.category.replace(' ', '-')} ${it.acting ? 'acting' : ''}" title="${escapeHtml(it.title)}">${escapeHtml(it.title)}</div>`).join('')}</div>`;
            }).join('');
            return `<div class="cal-row" style="grid-template-columns: 220px repeat(${colCount}, 1fr)">
                <div class="cal-name"><strong>${escapeHtml(row.employee.EmpName)}</strong><div class="ohs-muted">${escapeHtml(row.employee.Position)} • ${escapeHtml(row.employee.SiteDedicated)}</div><div class="ohs-muted">${escapeHtml(row.chip)}</div></div>${bars}</div>`;
        }).join('');
        document.getElementById('calendar-grid').innerHTML = head + rows;
    }

    loadInit().then((init) => {
        fillSelect(team, init.teams, 'All Teams');
        fillSelect(site, init.sites, 'All Sites');
        state.anchorISO = init.todayISO;
        refresh().catch((e) => alert(e.message));
    });
    page.querySelectorAll('[data-view]').forEach((btn) => btn.addEventListener('click', () => {
        page.querySelectorAll('[data-view]').forEach((b) => b.classList.remove('is-active'));
        btn.classList.add('is-active');
        state.viewMode = btn.dataset.view;
        refresh().catch((e) => alert(e.message));
    }));
    const shift = (days) => {
        const d = new Date(state.anchorISO);
        d.setDate(d.getDate() + days);
        state.anchorISO = d.toISOString().slice(0, 10);
        refresh().catch((e) => alert(e.message));
    };
    document.getElementById('cal-prev').onclick = () => shift(state.viewMode === 'YEAR' ? -365 : state.viewMode === 'MONTH' ? -30 : -7);
    document.getElementById('cal-next').onclick = () => shift(state.viewMode === 'YEAR' ? 365 : state.viewMode === 'MONTH' ? 30 : 7);
    document.getElementById('cal-today').onclick = () => { state.anchorISO = INIT.todayISO; refresh().catch((e) => alert(e.message)); };
    document.getElementById('cal-search').addEventListener('input', debounce(() => refresh().catch((e) => alert(e.message)), 300));
    team.onchange = site.onchange = () => refresh().catch((e) => alert(e.message));

    document.getElementById('btn-create-leave').onclick = () => {
        openModal('Create Leave Request', `
            <form id="leave-form" class="ohs-form">
                <div class="emp-box"><label>Employee<input type="search"><input type="hidden" name="EmpId"><ul class="ohs-search-list"></ul></label></div>
                <label>Leave Type<select name="LeaveType"></select></label>
                <label>Start Date<input type="date" name="StartDate" required></label>
                <label>End Date<input type="date" name="EndDate" required></label>
                <label>Time From<input type="time" name="TimeFrom"></label>
                <label>Time To<input type="time" name="TimeTo"></label>
                <div class="emp-box"><label>Backup / Acting PIC<input type="search"><input type="hidden" name="BackupEmpId"><ul class="ohs-search-list"></ul></label></div>
                <label>Note<textarea name="Note"></textarea></label>
                <p id="leave-overlap" class="ohs-muted"></p>
                <button class="btn-primary" type="submit">Simpan</button>
            </form>`, (modal) => {
            const form = modal.querySelector('#leave-form');
            form.LeaveType.innerHTML = (INIT.leaveTypes || []).map((t) => `<option>${escapeHtml(t.LeaveType)}</option>`).join('');
            employeePicker(form.querySelectorAll('.emp-box')[0], 'EmpId');
            employeePicker(form.querySelectorAll('.emp-box')[1], 'BackupEmpId');
            const check = debounce(async () => {
                const r = await api('/leave/check-overlap', { method: 'POST', body: JSON.stringify({ EmpId: form.EmpId.value, BackupEmpId: form.BackupEmpId.value, StartDate: form.StartDate.value, EndDate: form.EndDate.value }) });
                form.parentElement.querySelector('#leave-overlap').textContent = r.message || '';
            }, 300);
            ['EmpId', 'BackupEmpId', 'StartDate', 'EndDate'].forEach(() => {
                form.StartDate.addEventListener('change', check);
                form.EndDate.addEventListener('change', check);
            });
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                try {
                    const r = await api('/leave/create', { method: 'POST', body: JSON.stringify(Object.fromEntries(new FormData(form))) });
                    alert('Tersimpan ' + r.requestId);
                    closeModal();
                    refresh();
                } catch (err) { alert(err.message); }
            });
        });
    };
}

function initEvents() {
    const page = document.querySelector('[data-ohs-page="events"]');
    if (!page) return;
    const team = document.getElementById('ev-team');
    const site = document.getElementById('ev-site');

    async function refresh() {
        const data = await api('/events/maker-data', { method: 'POST', body: JSON.stringify({ team: team.value, site: site.value }) });
        const c = data.counts;
        document.getElementById('event-badges').innerHTML = Object.entries(c).map(([k, v]) => `<span>${escapeHtml(k)}: ${v}</span>`).join('');
        const table = document.getElementById('event-table');
        table.querySelector('thead').innerHTML = '<tr><th>Status</th><th>Event</th><th>Date</th><th>PIC</th><th>Where</th><th>Kesiapan</th><th>Last Update</th><th></th></tr>';
        table.querySelector('tbody').innerHTML = data.events.map((ev) => `<tr>
            <td>${escapeHtml(ev.ScheduleStatus)}</td>
            <td>${escapeHtml(ev.EventName)}</td>
            <td>${escapeHtml(ev.EventDate)}</td>
            <td>${escapeHtml(ev.PICName)}<div class="ohs-muted">${escapeHtml(ev.PICTeam)} • ${escapeHtml(ev.PICSiteDedicated)}</div></td>
            <td>${escapeHtml(ev.Where)}</td>
            <td>${escapeHtml(ev.ReadinessUpdate)}</td>
            <td>${escapeHtml(ev.ReadinessUpdatedAt)}</td>
            <td>
                <button data-act="edit" data-id="${escapeHtml(ev.EventId)}">Edit</button>
                <button data-act="ready" data-id="${escapeHtml(ev.EventId)}">Kesiapan</button>
                <button data-act="qr" data-id="${escapeHtml(ev.EventId)}">QR</button>
                <button data-act="att" data-id="${escapeHtml(ev.EventId)}">Hadir</button>
                <button data-act="min" data-id="${escapeHtml(ev.EventId)}">Notulensi</button>
            </td>
        </tr>`).join('');
        table.querySelectorAll('button[data-act]').forEach((btn) => btn.addEventListener('click', () => onEventAction(btn.dataset.act, btn.dataset.id, data.events.find((e) => e.EventId === btn.dataset.id))));
    }

    function eventForm(ev) {
        openModal(ev ? 'Edit Event' : 'Create Event', `
            <form id="event-form" class="ohs-form">
                <label>Nama<input name="EventName" required></label>
                <label>Deskripsi<textarea name="Description" required></textarea></label>
                <label>Where<input name="Where" required></label>
                <div class="emp-box"><label>PIC<input type="search"><input type="hidden" name="PICEmpId"><ul class="ohs-search-list"></ul></label></div>
                <label>Tanggal<input type="date" name="EventDate" required></label>
                <button class="btn-primary">Simpan</button>
            </form>`, (modal) => {
            const form = modal.querySelector('#event-form');
            employeePicker(form.querySelector('.emp-box'), 'PICEmpId');
            if (ev) {
                form.EventName.value = ev.EventName;
                form.Description.value = ev.Description;
                form.Where.value = ev.Where;
                form.PICEmpId.value = ev.PICEmpId;
                form.querySelector('input[type="search"]').value = ev.PICName;
                form.EventDate.value = ev.EventDate;
            }
            form.onsubmit = async (e) => {
                e.preventDefault();
                const payload = Object.fromEntries(new FormData(form));
                if (ev) payload.EventId = ev.EventId;
                try {
                    await api(ev ? '/events/update' : '/events/create', { method: 'POST', body: JSON.stringify(payload) });
                    closeModal();
                    refresh();
                } catch (err) { alert(err.message); }
            };
        });
    }

    async function onEventAction(act, id, ev) {
        if (act === 'edit') return eventForm(ev);
        if (act === 'ready') {
            openModal('Update Kesiapan', `<form id="ready-form" class="ohs-form"><textarea name="ReadinessUpdate" required></textarea><button class="btn-primary">Simpan</button></form>`, (modal) => {
                modal.querySelector('form').onsubmit = async (e) => {
                    e.preventDefault();
                    try {
                        await api('/events/readiness', { method: 'POST', body: JSON.stringify({ EventId: id, ReadinessUpdate: e.target.ReadinessUpdate.value }) });
                        closeModal(); refresh();
                    } catch (err) { alert(err.message); }
                };
            });
        }
        if (act === 'qr') {
            const url = `${location.origin}/ohs-dashboard/checkin?eventId=${encodeURIComponent(id)}`;
            openModal('QR Absensi', `<p><a href="${url}" target="_blank">${url}</a></p><p><button type="button" id="copy-qr">Copy link</button></p><img alt="QR" src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${encodeURIComponent(url)}">`, (modal) => {
                modal.querySelector('#copy-qr').onclick = () => navigator.clipboard.writeText(url);
            });
        }
        if (act === 'att') {
            const data = await api('/events/attendance?eventId=' + encodeURIComponent(id));
            openModal('Daftar Hadir', `<p>${data.attendanceCount} hadir</p><table class="ohs-table">${data.attendance.map((a) => `<tr><td>${escapeHtml(a.EmpName)}</td><td>${escapeHtml(a.CheckInAt)}</td></tr>`).join('')}</table>`);
        }
        if (act === 'min') {
            const data = await api('/events/minutes?eventId=' + encodeURIComponent(id));
            openModal('Notulensi', `
                <form id="min-form" class="ohs-form">
                    <label>Summary<textarea name="Summary">${escapeHtml(data.summary || '')}</textarea></label>
                    <div class="emp-box"><label>Updated By<input type="search"><input type="hidden" name="UpdatedByEmpId"><ul class="ohs-search-list"></ul></label></div>
                    <button class="btn-primary">Simpan notulensi</button>
                </form>
                <h4>Action Items</h4>
                <div id="ai-list">${(data.actionItems || []).map((i) => `<p>${escapeHtml(i.Task)} — ${escapeHtml(i.Status)} <button data-ai="${escapeHtml(i.ActionItemId)}" data-st="${i.Status === 'Open' ? 'Done' : 'Open'}">Toggle</button></p>`).join('')}</div>
                <form id="ai-form" class="ohs-form">
                    <input name="Task" placeholder="Task" required>
                    <div class="emp-box"><label>PIC<input type="search"><input type="hidden" name="PICEmpId"><ul class="ohs-search-list"></ul></label></div>
                    <input type="date" name="DueDate">
                    <button class="btn-primary">Tambah</button>
                </form>`, (modal) => {
                employeePicker(modal.querySelectorAll('.emp-box')[0], 'UpdatedByEmpId');
                employeePicker(modal.querySelectorAll('.emp-box')[1], 'PICEmpId');
                modal.querySelector('#min-form').onsubmit = async (e) => {
                    e.preventDefault();
                    await api('/events/minutes', { method: 'POST', body: JSON.stringify({ EventId: id, ...Object.fromEntries(new FormData(e.target)) }) });
                    closeModal(); onEventAction('min', id, ev);
                };
                modal.querySelector('#ai-form').onsubmit = async (e) => {
                    e.preventDefault();
                    await api('/events/action-items/add', { method: 'POST', body: JSON.stringify({ EventId: id, ...Object.fromEntries(new FormData(e.target)) }) });
                    closeModal(); onEventAction('min', id, ev);
                };
                modal.querySelectorAll('[data-ai]').forEach((b) => b.onclick = async () => {
                    await api('/events/action-items/status', { method: 'POST', body: JSON.stringify({ EventId: id, ActionItemId: b.dataset.ai, Status: b.dataset.st }) });
                    closeModal(); onEventAction('min', id, ev);
                });
            });
        }
    }

    loadInit().then((init) => {
        fillSelect(team, init.teams, 'All Teams');
        fillSelect(site, init.sites, 'All Sites');
        refresh().catch((e) => alert(e.message));
    });
    team.onchange = site.onchange = () => refresh().catch((e) => alert(e.message));
    document.getElementById('btn-create-event').onclick = () => eventForm(null);
}

function initTracker() {
    const page = document.querySelector('[data-ohs-page="tracker"]');
    if (!page) return;
    let cache = [];
    let pageNo = 1;
    const pageSize = 10;

    async function refresh() {
        const data = await api('/tracker/data', {
            method: 'POST',
            body: JSON.stringify({
                type: document.getElementById('tr-type').value,
                status: document.getElementById('tr-status').value,
                department: document.getElementById('tr-dept').value,
                site: document.getElementById('tr-site').value,
                search: document.getElementById('tr-search').value,
            }),
        });
        cache = data.trackers;
        const c = data.counts;
        document.getElementById('tracker-counts').innerHTML = `<span>Total ${c.total}</span><span>On Going ${c.onGoing}</span><span>Overdue ${c.overdue}</span><span>Closed ${c.closed}</span>`;
        render();
    }

    function render() {
        const slice = cache.slice((pageNo - 1) * pageSize, pageNo * pageSize);
        const table = document.getElementById('tracker-table');
        table.querySelector('thead').innerHTML = '<tr><th>Type</th><th>Nama</th><th>Leader</th><th>Site</th><th>Due</th><th>%</th><th>Status</th><th></th></tr>';
        table.querySelector('tbody').innerHTML = slice.map((t) => `<tr>
            <td>${escapeHtml(t.TrackerType)}</td>
            <td>${escapeHtml(t.ProjectIssueName)}</td>
            <td>${escapeHtml(t.ProjectLeaderName)}</td>
            <td>${escapeHtml(t.Site)}</td>
            <td>${escapeHtml(t.DueDate)}</td>
            <td>${t.CurrentPercentComplete}</td>
            <td>${badge(t.EffectiveStatus)}</td>
            <td>
                <button data-act="edit" data-id="${t.TrackerId}">Edit</button>
                <button data-act="prog" data-id="${t.TrackerId}">Progress</button>
                <button data-act="log" data-id="${t.TrackerId}">Log</button>
            </td>
        </tr>`).join('');
        table.querySelectorAll('button[data-act]').forEach((b) => b.onclick = () => onTracker(b.dataset.act, cache.find((t) => t.TrackerId === b.dataset.id)));
        document.getElementById('tracker-pager').innerHTML = `<button type="button" id="pprev">Prev</button><span>Hal ${pageNo}</span><button type="button" id="pnext">Next</button>`;
        document.getElementById('pprev').onclick = () => { if (pageNo > 1) { pageNo--; render(); } };
        document.getElementById('pnext').onclick = () => { if (pageNo * pageSize < cache.length) { pageNo++; render(); } };
    }

    function trackerForm(existing) {
        openModal(existing ? 'Edit Details' : 'Create Tracker', `
            <form id="tr-form" class="ohs-form">
                <label>Type<select name="TrackerType"><option>Project</option><option>Issue</option></select></label>
                <label>Nama<input name="ProjectIssueName" required></label>
                <label>Department<input name="Department" required></label>
                <div class="emp-box"><label>Leader<input type="search"><input type="hidden" name="ProjectLeaderEmpId"><ul class="ohs-search-list"></ul></label></div>
                <label>Site<input name="Site" required></label>
                <label>Description<textarea name="DescriptionProject" required></textarea></label>
                <label>Background<textarea name="BackgroundProject" required></textarea></label>
                <label>Impact<textarea name="ImpactProject" required></textarea></label>
                <label>Start<input type="date" name="StartDate" required></label>
                <label>Due<input type="date" name="DueDate" required></label>
                <label>Success Indicator<textarea name="SuccessIndicator" required></textarea></label>
                <div id="subtask-box"></div>
                <button type="button" id="add-st">+ Sub Task</button>
                <button class="btn-primary">Simpan</button>
            </form>`, (modal) => {
            const form = modal.querySelector('#tr-form');
            employeePicker(form.querySelector('.emp-box'), 'ProjectLeaderEmpId');
            const box = modal.querySelector('#subtask-box');
            const addRow = (st) => {
                const row = el(`<div class="ohs-card st-row">
                    <input type="hidden" name="SubTaskId" value="${escapeHtml(st?.SubTaskId || '')}">
                    <label>Sub Task<input name="SubTaskName" value="${escapeHtml(st?.SubTaskName || '')}"></label>
                    <div class="emp-box"><label>PIC<input type="search" value="${escapeHtml(st?.PICName || '')}"><input type="hidden" name="PICEmpId" value="${escapeHtml(st?.PICEmpId || '')}"><ul class="ohs-search-list"></ul></label></div>
                    <label>Site<input name="Site" value="${escapeHtml(st?.Site || '')}"></label>
                    <label>Deskripsi<textarea name="DescriptionSubTask">${escapeHtml(st?.DescriptionSubTask || '')}</textarea></label>
                    <label>Start<input type="date" name="StartDate" value="${escapeHtml(st?.StartDate || '')}"></label>
                    <label>Due<input type="date" name="DueDate" value="${escapeHtml(st?.DueDate || '')}"></label>
                    <label>Success<textarea name="SuccessIndicator">${escapeHtml(st?.SuccessIndicator || '')}</textarea></label>
                    ${st ? '' : '<label>Initial Weekly<textarea name="InitialProgressReportWeekly"></textarea></label><label>Initial Keterangan<textarea name="InitialRemarks"></textarea></label>'}
                </div>`);
                box.appendChild(row);
                employeePicker(row.querySelector('.emp-box'), 'PICEmpId');
            };
            modal.querySelector('#add-st').onclick = () => addRow(null);
            if (existing) {
                form.TrackerType.value = existing.TrackerType;
                form.ProjectIssueName.value = existing.ProjectIssueName;
                form.Department.value = existing.Department;
                form.ProjectLeaderEmpId.value = existing.ProjectLeaderEmpId;
                form.querySelector('input[type="search"]').value = existing.ProjectLeaderName;
                form.Site.value = existing.Site;
                form.DescriptionProject.value = existing.DescriptionProject;
                form.BackgroundProject.value = existing.BackgroundProject;
                form.ImpactProject.value = existing.ImpactProject;
                form.StartDate.value = existing.StartDate;
                form.DueDate.value = existing.DueDate;
                form.SuccessIndicator.value = existing.SuccessIndicator;
                (existing.SubTasks || []).forEach(addRow);
            }
            form.onsubmit = async (e) => {
                e.preventDefault();
                const payload = Object.fromEntries(new FormData(form));
                payload.SubTasks = [...box.querySelectorAll('.st-row')].map((row) => {
                    const o = {};
                    row.querySelectorAll('input,textarea').forEach((i) => { if (i.name) o[i.name] = i.value; });
                    return o;
                });
                try {
                    if (existing) {
                        payload.TrackerId = existing.TrackerId;
                        await api('/tracker/update-details', { method: 'POST', body: JSON.stringify(payload) });
                    } else {
                        await api('/tracker/create', { method: 'POST', body: JSON.stringify(payload) });
                    }
                    closeModal(); refresh();
                } catch (err) { alert(err.message); }
            };
        });
    }

    async function onTracker(act, t) {
        if (!t) return;
        if (act === 'edit') return trackerForm(t);
        if (act === 'log') {
            const data = await api('/tracker/log?trackerId=' + encodeURIComponent(t.TrackerId));
            openModal('Update Log', `<table class="ohs-table">${data.logs.map((l) => `<tr><td>${escapeHtml(l.Timestamp)}</td><td>${l.PercentComplete}</td><td>${escapeHtml(l.ProgressReportWeekly)}</td><td>${escapeHtml(l.UpdatedByName)}</td></tr>`).join('')}</table>`);
            return;
        }
        if (t.HasSubTasks) {
            const options = (t.SubTasks || []).map((s) => `<option value="${escapeHtml(s.SubTaskId)}">${escapeHtml(s.SubTaskName)}</option>`).join('');
            openModal('Update Progress Sub Task', `<form id="up" class="ohs-form">
                <label>Sub Task<select name="SubTaskId">${options}</select></label>
                <label>%<input name="PercentComplete" required></label>
                <label>Weekly<textarea name="ProgressReportWeekly" required></textarea></label>
                <label>Keterangan<textarea name="Remarks" required></textarea></label>
                <div class="emp-box"><label>Updated By<input type="search"><input type="hidden" name="UpdatedByEmpId"><ul class="ohs-search-list"></ul></label></div>
                <button class="btn-primary">Simpan</button></form>`, (modal) => {
                employeePicker(modal.querySelector('.emp-box'), 'UpdatedByEmpId');
                modal.querySelector('#up').onsubmit = async (e) => {
                    e.preventDefault();
                    try {
                        await api('/tracker/update-subtask', { method: 'POST', body: JSON.stringify(Object.fromEntries(new FormData(e.target))) });
                        closeModal(); refresh();
                    } catch (err) { alert(err.message); }
                };
            });
        } else {
            openModal('Update Progress', `<form id="up" class="ohs-form">
                <label>%<input name="PercentComplete" required></label>
                <label>Weekly<textarea name="ProgressReportWeekly" required></textarea></label>
                <label>Keterangan<textarea name="Remarks" required></textarea></label>
                <div class="emp-box"><label>Updated By<input type="search"><input type="hidden" name="UpdatedByEmpId"><ul class="ohs-search-list"></ul></label></div>
                <button class="btn-primary">Simpan</button></form>`, (modal) => {
                employeePicker(modal.querySelector('.emp-box'), 'UpdatedByEmpId');
                modal.querySelector('#up').onsubmit = async (e) => {
                    e.preventDefault();
                    try {
                        await api('/tracker/update', { method: 'POST', body: JSON.stringify({ TrackerId: t.TrackerId, ...Object.fromEntries(new FormData(e.target)) }) });
                        closeModal(); refresh();
                    } catch (err) { alert(err.message); }
                };
            });
        }
    }

    loadInit().then((init) => {
        fillSelect(document.getElementById('tr-dept'), init.teams, 'All Departments');
        fillSelect(document.getElementById('tr-site'), init.sites, 'All Sites');
        refresh().catch((e) => alert(e.message));
    });
    document.getElementById('tr-refresh').onclick = () => { pageNo = 1; refresh().catch((e) => alert(e.message)); };
    document.getElementById('btn-create-tracker').onclick = () => trackerForm(null);
}

function initAdmin() {
    const page = document.querySelector('[data-ohs-page="admin"]');
    if (!page) return;
    const form = document.getElementById('admin-form');
    const hour = form.SendHour;
    hour.innerHTML = Array.from({ length: 24 }, (_, i) => `<option value="${i}">${String(i).padStart(2, '0')}</option>`).join('');

    async function load() {
        const [init, s] = await Promise.all([loadInit(), api('/admin/email-settings')]);
        fillSelect(document.getElementById('admin-team'), init.teams, 'All Teams');
        fillSelect(document.getElementById('admin-site'), init.sites, 'All Sites');
        form.Enabled.checked = !!s.Enabled;
        form.querySelectorAll('[name="days"]').forEach((cb) => { cb.checked = String(s.ScheduleDays || '').split(',').includes(cb.value); });
        form.SendHour.value = String(s.SendHour ?? 7);
        form.SendMinute.value = String(s.SendMinute ?? 0);
        form.Recipients.value = s.Recipients || '';
        form.Cc.value = s.Cc || '';
        form.Bcc.value = s.Bcc || '';
        form.PortalUrl.value = s.PortalUrl || '';
        form.OverviewTeam.value = s.OverviewTeam || 'All Teams';
        form.OverviewSite.value = s.OverviewSite || 'All Sites';
        form.SubjectPrefix.value = s.SubjectPrefix || '[OHS Portal]';
        form.IncludeLeaveSummary.checked = !!s.IncludeLeaveSummary;
        form.IncludeTrackerSummary.checked = !!s.IncludeTrackerSummary;
        form.IncludeLeaderboard.checked = !!s.IncludeLeaderboard;
        document.getElementById('admin-status').innerHTML = `
            <div class="ohs-kpi"><span>Scheduler</span><b>${s.Enabled ? 'ON' : 'OFF'}</b></div>
            <div class="ohs-kpi"><span>Hari & jam</span><b>${escapeHtml(s.ScheduleDays)} ${String(s.SendHour).padStart(2,'0')}:${String(s.SendMinute).padStart(2,'0')}</b></div>
            <div class="ohs-kpi"><span>Last run</span><b>${escapeHtml(s.LastRunStatus)}</b></div>
            <div class="ohs-kpi"><span>Mail quota</span><b>-</b></div>`;
        document.getElementById('admin-note').textContent = s.CronNote || '';
    }

    form.onsubmit = async (e) => {
        e.preventDefault();
        const days = [...form.querySelectorAll('[name="days"]:checked')].map((c) => c.value).join(',');
        try {
            await api('/admin/email-settings', {
                method: 'POST',
                body: JSON.stringify({
                    Enabled: form.Enabled.checked,
                    ScheduleDays: days,
                    SendHour: Number(form.SendHour.value),
                    SendMinute: Number(form.SendMinute.value),
                    Recipients: form.Recipients.value,
                    Cc: form.Cc.value,
                    Bcc: form.Bcc.value,
                    PortalUrl: form.PortalUrl.value,
                    OverviewTeam: form.OverviewTeam.value,
                    OverviewSite: form.OverviewSite.value,
                    SubjectPrefix: form.SubjectPrefix.value,
                    IncludeLeaveSummary: form.IncludeLeaveSummary.checked,
                    IncludeTrackerSummary: form.IncludeTrackerSummary.checked,
                    IncludeLeaderboard: form.IncludeLeaderboard.checked,
                }),
            });
            alert('Settings tersimpan');
            load();
        } catch (err) { alert(err.message); }
    };

    const run = (path, label) => async () => {
        if (!confirm(label + '?')) return;
        try {
            const r = await api(path, { method: 'POST', body: '{}' });
            alert(r.message || JSON.stringify(r));
            load();
        } catch (err) { alert(err.message); }
    };
    document.getElementById('admin-refresh').onclick = () => load().catch((e) => alert(e.message));
    document.getElementById('admin-send').onclick = run('/admin/email-send', 'Kirim digest sekarang');
    document.getElementById('admin-test').onclick = run('/admin/email-test', 'Kirim test email');
    document.getElementById('admin-overdue').onclick = run('/admin/overdue-reminder-send', 'Kirim overdue reminder');
    document.getElementById('admin-hse').onclick = run('/admin/hse-sync-now', 'Sync HSE akan menimpa seluruh ohs_employees');
    load().catch((e) => alert(e.message));
}

document.addEventListener('DOMContentLoaded', () => {
    initOverview();
    initLeave();
    initEvents();
    initTracker();
    initAdmin();
});

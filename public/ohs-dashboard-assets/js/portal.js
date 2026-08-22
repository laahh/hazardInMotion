const API_BASE = window.OHS_API_BASE || '/ohs-dashboard/api';
const FETCH_TIMEOUT_MS = 20000;

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

let loadingCount = 0;

function loadingOverlay() {
    let node = document.getElementById('ohs-loading');
    if (node) return node;
    node = document.createElement('div');
    node.id = 'ohs-loading';
    node.hidden = true;
    node.innerHTML = '<div class="ohs-loading-card"><span class="ohs-spinner" aria-hidden="true"></span><p>Memuat data…</p></div>';
    document.body.appendChild(node);
    return node;
}

function showLoading() {
    loadingCount++;
    if (loadingCount === 1) {
        const node = loadingOverlay();
        node.hidden = false;
        document.body.classList.add('is-ohs-loading');
    }
}

function hideLoading() {
    loadingCount = Math.max(0, loadingCount - 1);
    if (loadingCount === 0) {
        const node = document.getElementById('ohs-loading');
        if (node) node.hidden = true;
        document.body.classList.remove('is-ohs-loading');
    }
}

function toast(message, tone = 'error') {
    if (!message) return;
    let root = document.getElementById('ohs-toast-root');
    if (!root) {
        root = document.createElement('div');
        root.id = 'ohs-toast-root';
        document.body.appendChild(root);
    }
    const item = document.createElement('div');
    item.className = 'ohs-toast tone-' + tone;
    item.textContent = message;
    root.appendChild(item);
    setTimeout(() => item.remove(), 5200);
}

function silencedAbort() {
    const err = new Error('aborted');
    err.name = 'AbortError';
    err.silenced = true;
    return err;
}

function attachTimeout(parentSignal, timeoutMs) {
    const controller = new AbortController();
    if (parentSignal) {
        if (parentSignal.aborted) controller.abort();
        else parentSignal.addEventListener('abort', () => controller.abort(), { once: true });
    }
    const timer = setTimeout(() => controller.abort(), timeoutMs);
    return {
        signal: controller.signal,
        timedOut: () => parentSignal?.aborted !== true && controller.signal.aborted,
        clear: () => clearTimeout(timer),
    };
}

async function api(path, options = {}) {
    const silent = !!options.silent;
    const timeoutMs = options.timeout ?? FETCH_TIMEOUT_MS;
    const parentSignal = options.signal;
    delete options.silent;
    delete options.timeout;
    delete options.signal;

    const gate = attachTimeout(parentSignal, timeoutMs);
    if (!silent) showLoading();
    try {
        const res = await fetch(API_BASE + path, {
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
                ...(options.headers || {}),
            },
            ...options,
            signal: gate.signal,
        });
        const data = await res.json().catch(() => ({}));
        if (res.status === 401) {
            location.href = '/login';
            throw new Error('Silakan login.');
        }
        if (!res.ok) throw new Error(data.error || data.message || 'Permintaan gagal');
        return data;
    } catch (e) {
        if (e?.name === 'AbortError') {
            if (parentSignal?.aborted) throw silencedAbort();
            throw new Error('Server lambat merespons. Sempitkan filter Team/Site, lalu coba lagi.');
        }
        throw e;
    } finally {
        gate.clear();
        if (!silent) hideLoading();
    }
}

function runSafe(promise) {
    return Promise.resolve(promise).catch((e) => {
        if (e?.silenced || e?.name === 'AbortError') return;
        toast(e.message || 'Permintaan gagal');
    });
}

function withAbort(holder) {
    if (holder.ctrl) holder.ctrl.abort();
    holder.ctrl = new AbortController();
    return holder.ctrl.signal;
}

function addDaysISO(iso, days) {
    const parts = String(iso || '').split('-').map(Number);
    if (parts.length < 3 || parts.some((n) => Number.isNaN(n))) return iso;
    const dt = new Date(parts[0], parts[1] - 1, parts[2]);
    dt.setDate(dt.getDate() + days);
    const mm = String(dt.getMonth() + 1).padStart(2, '0');
    const dd = String(dt.getDate()).padStart(2, '0');
    return dt.getFullYear() + '-' + mm + '-' + dd;
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

function kpiCard(label, value, tone = '', hint = '') {
    return `<div class="ohs-kpi ${tone ? 'tone-' + tone : ''}"><span>${escapeHtml(label)}</span><b>${escapeHtml(value)}</b>${hint ? `<small>${escapeHtml(hint)}</small>` : ''}</div>`;
}

function progressBar(percent) {
    const pct = Math.max(0, Math.min(100, Number(percent) || 0));
    return `<div class="ohs-progress"><i><span style="width:${pct}%"></span></i><em>${pct}%</em></div>`;
}

function personCell(name, meta) {
    return `<div class="ohs-person"><strong>${escapeHtml(name || '')}</strong><span>${escapeHtml(meta || '')}</span></div>`;
}

function emptyCell(cols, text = 'Belum ada data') {
    return `<tr><td colspan="${cols}"><div class="ohs-empty">${escapeHtml(text)}</div></td></tr>`;
}

function statChip(label, value) {
    return `<span class="ohs-stat">${escapeHtml(label)} <b>${escapeHtml(value)}</b></span>`;
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

function formObject(form, skipSelector = '.st-row') {
    const data = {};
    form.querySelectorAll('input,select,textarea').forEach((input) => {
        if (!input.name || (skipSelector && input.closest(skipSelector))) return;
        if (input.type === 'checkbox') {
            data[input.name] = input.checked;
            return;
        }
        data[input.name] = input.value;
    });
    return data;
}

function openModal(title, bodyHtml, onMount) {
    closeModal();
    const node = el(`<div class="ohs-modal-backdrop"><div class="ohs-modal"><div class="ohs-modal-head"><h3>${escapeHtml(title)}</h3><button type="button" class="ohs-modal-close" data-close aria-label="Tutup">✕</button></div><div class="ohs-modal-body">${bodyHtml}</div></div></div>`);
    node.addEventListener('click', (e) => {
        if (e.target === node || e.target.dataset.close !== undefined) closeModal();
    });
    document.getElementById('ohs-modal-root').appendChild(node);
    if (onMount) onMount(node);
}

function closeModal() {
    document.getElementById('ohs-modal-root').innerHTML = '';
}

function employeePicker(root, hiddenName, onSelect) {
    const input = root.querySelector('input[type="search"]');
    const list = root.querySelector('ul');
    const hidden = root.querySelector(`input[name="${hiddenName}"]`);
    const run = debounce(async () => {
        const q = input.value.trim();
        if (q.length < 2) { list.innerHTML = ''; return; }
        const rows = await api('/employees/search?q=' + encodeURIComponent(q), { silent: true });
        const items = Array.isArray(rows) ? rows : [];
        list.innerHTML = items.map((r) => `<li data-id="${escapeHtml(r.EmpId)}" data-name="${escapeHtml(r.EmpName)}">${escapeHtml(r.EmpName)} • ${escapeHtml(r.EmpId)} • ${escapeHtml(r.Team)}</li>`).join('') || '<li>Tidak ada hasil</li>';
    }, 250);
    input.addEventListener('input', run);
    list.addEventListener('click', (e) => {
        const li = e.target.closest('li[data-id]');
        if (!li) return;
        hidden.value = li.dataset.id;
        input.value = li.dataset.name + ' (' + li.dataset.id + ')';
        list.innerHTML = '';
        if (onSelect) onSelect(hidden.value, li.dataset.name);
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
    const req = {};

    async function refresh() {
        const data = await api('/dashboard/overview', {
            method: 'POST',
            signal: withAbort(req),
            body: JSON.stringify({ team: team.value, site: site.value, year: Number(year.value) }),
        });
        const k = data.kpis;
        document.getElementById('overview-kpis').innerHTML = [
            kpiCard('Event This Week', k.eventThisWeek, 'blue'),
            kpiCard('Upcoming Event', k.upcomingEvent, 'blue'),
            kpiCard('Leave This Week', k.leaveThisWeek, 'purple'),
            kpiCard('Upcoming Leave', k.upcomingLeave, 'purple'),
            kpiCard('Project Active', k.projectActive),
            kpiCard('Issue Active', k.issueActive, 'amber'),
            kpiCard('Effective %', data.workforceEffectiveness.effectiveWorkingPercent, 'gold'),
        ].join('');

        const renderGroup = (title, items, fields) => {
            const body = (items || []).map((it) => `<tr class="is-clickable" data-kind="${escapeHtml(fields.kind)}" data-id="${escapeHtml(it[fields.id] || '')}">${fields.cols.map((f) => `<td>${escapeHtml(it[f] ?? '')}</td>`).join('')}</tr>`).join('') || emptyCell(4, 'Tidak ada data pada periode ini');
            return `<details open><summary class="collapse-h">${title} (${(items || []).length})</summary><table class="ohs-table">${body}</table></details>`;
        };
        document.getElementById('overview-events').innerHTML =
            '<h3>Event Status</h3>' +
            renderGroup('This Week', data.eventStatus.thisWeek, { cols: ['EventName', 'EventDate', 'PICName', 'Where'], id: 'EventId', kind: 'event' }) +
            renderGroup('Next Week', data.eventStatus.nextWeek, { cols: ['EventName', 'EventDate', 'PICName', 'Where'], id: 'EventId', kind: 'event' }) +
            renderGroup('Next 2 Week', data.eventStatus.nextTwoWeek, { cols: ['EventName', 'EventDate', 'PICName', 'Where'], id: 'EventId', kind: 'event' }) +
            renderGroup('More Than 2 Weeks Ahead', data.eventStatus.moreThanTwoWeeks, { cols: ['EventName', 'EventDate', 'PICName', 'Where'], id: 'EventId', kind: 'event' });

        document.getElementById('overview-leave').innerHTML =
            '<h3>Leave Status</h3>' +
            renderGroup('Leave This Week', data.leaveStatus.thisWeek, { cols: ['EmpName', 'LeaveType', 'StartDate', 'EndDate'], id: 'RequestId', kind: 'leave' }) +
            renderGroup('Upcoming Leave', data.leaveStatus.upcoming, { cols: ['EmpName', 'LeaveType', 'StartDate', 'EndDate'], id: 'RequestId', kind: 'leave' });

        page.querySelectorAll('#overview-events tr[data-id], #overview-leave tr[data-id]').forEach((tr) => {
            if (!tr.dataset.id) return;
            tr.addEventListener('click', () => {
                if (tr.dataset.kind === 'event') location.href = '/ohs-dashboard/events';
                if (tr.dataset.kind === 'leave') location.href = '/ohs-dashboard/leave';
            });
        });

        const w = data.workforceEffectiveness;
        document.getElementById('overview-effectiveness').innerHTML = `<h3>Workforce Effectiveness</h3>
            <p class="ohs-muted">${w.employeeCount} karyawan · ${w.leavePersonDays} hari cuti YTD · ${w.effectivePersonDays} hari efektif</p>
            <div class="effectiveness-bar"><span style="width:${Number(w.effectiveWorkingPercent) || 0}%"></span></div>
            <p style="margin:10px 0 0;font-weight:800;font-size:22px">${w.effectiveWorkingPercent}%</p>`;
        document.getElementById('overview-leaderboard').innerHTML = `<h3>Leaderboard Working Days</h3>
            <table class="ohs-table"><thead><tr><th>Nama</th><th>Team</th><th>Leave YTD</th><th>Effective %</th></tr></thead>
            <tbody>${data.leaderboard.length ? data.leaderboard.map((r) => `<tr class="is-clickable" data-emp="${escapeHtml(r.EmpId)}"><td>${personCell(r.EmpName, r.Position)}</td><td>${escapeHtml(r.Team)}</td><td>${r.LeaveYTD}</td><td>${progressBar(r.EffectiveWorkingPercent)}</td></tr>`).join('') : emptyCell(4, 'Roster masih kosong')}</tbody></table>`;
        document.getElementById('overview-leaderboard').querySelectorAll('tr[data-emp]').forEach((tr) => {
            tr.addEventListener('click', () => runSafe((async () => {
                const hist = await api(`/leave/history?empId=${encodeURIComponent(tr.dataset.emp)}&year=${year.value}`);
                openModal('Riwayat Cuti ' + (hist.employee.EmpName || ''), `<p>Leave YTD: ${hist.leaveDaysYTD} hari • Effective: ${hist.effectiveWorkingPercent}%</p>
                    <table class="ohs-table"><tr><th>Tipe</th><th>Start</th><th>End</th><th>Status</th></tr>
                    ${hist.records.map((r) => `<tr><td>${escapeHtml(r.LeaveType)}</td><td>${r.StartDate}</td><td>${r.EndDate}</td><td>${r.Status}</td></tr>`).join('')}</table>`);
            })()));
        });

        const pageSize = 10;
        let pageNo = 1;
        const renderTrackers = () => {
            const all = data.trackerHighlights || [];
            const slice = all.slice((pageNo - 1) * pageSize, pageNo * pageSize);
            document.getElementById('overview-trackers').innerHTML = `<h3>Tracker Highlights</h3>
                <table class="ohs-table"><thead><tr><th>Type</th><th>Nama</th><th>Status</th><th>Due</th><th>%</th></tr></thead>
                <tbody>${slice.length ? slice.map((t) => `<tr class="is-clickable" data-id="${escapeHtml(t.TrackerId)}"><td>${escapeHtml(t.TrackerType)}</td><td>${escapeHtml(t.ProjectIssueName)}</td><td>${badge(t.EffectiveStatus)}</td><td>${t.DueDate}</td><td>${progressBar(t.CurrentPercentComplete)}</td></tr>`).join('') : emptyCell(5, 'Belum ada tracker')}</tbody></table>
                <div class="ohs-pager"><button type="button" class="btn-ghost" id="trk-prev">Prev</button><span>Hal ${pageNo}</span><button type="button" class="btn-ghost" id="trk-next">Next</button></div>`;
            document.getElementById('overview-trackers').querySelectorAll('tr[data-id]').forEach((tr) => {
                tr.addEventListener('click', () => { location.href = '/ohs-dashboard/tracker'; });
            });
            document.getElementById('trk-prev').onclick = () => { if (pageNo > 1) { pageNo--; renderTrackers(); } };
            document.getElementById('trk-next').onclick = () => { if (pageNo * pageSize < all.length) { pageNo++; renderTrackers(); } };
        };
        renderTrackers();
    }

    loadInit().then((init) => {
        fillSelect(team, init.teams, 'All Teams');
        fillSelect(site, init.sites, 'All Sites');
        year.innerHTML = init.years.map((y) => `<option ${y === init.currentYear ? 'selected' : ''}>${y}</option>`).join('');
        return refresh();
    }).catch((e) => toast(e.message));
    document.getElementById('btn-refresh').addEventListener('click', () => runSafe(refresh()));
    team.onchange = site.onchange = year.onchange = () => runSafe(refresh());
}

function initLeave() {
    const page = document.querySelector('[data-ohs-page="leave"]');
    if (!page) return;
    const state = { viewMode: 'WEEK', anchorISO: '' };
    const team = document.getElementById('cal-team');
    const site = document.getElementById('cal-site');
    const yearSel = document.getElementById('cal-year');
    const calReq = {};
    const listReq = {};

    async function refreshCalendar() {
        const data = await api('/calendar/range', {
            method: 'POST',
            signal: withAbort(calReq),
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
            `${data.rangeStart} s/d ${data.rangeEnd} · Event ${data.counts.events} · Project ${data.counts.projects} · Issue ${data.counts.issues} · Acting ${data.counts.actingTransfers}`;
        const colCount = data.cols.length;
        const today = INIT?.todayISO || '';
        const head = `<div class="cal-row" style="grid-template-columns: 220px repeat(${colCount}, minmax(88px, 1fr))"><div class="cal-name">Karyawan</div>${data.cols.map((c) => `<div class="cal-name ${today && c.start <= today && c.end >= today ? 'is-today' : ''}">${escapeHtml(c.label)}</div>`).join('')}</div>`;
        const rows = data.rows.length ? data.rows.map((row) => {
            const bars = data.cols.map((col) => {
                const hits = (row.items || []).filter((it) => it.start <= col.end && it.end >= col.start);
                return `<div>${hits.map((it) => {
                    const d = it.data || {};
                    const id = d.RequestId || d.EventId || d.TrackerId || d.SubTaskId || '';
                    return `<div class="cal-chip ${it.category.replace(' ', '-')} ${it.acting ? 'acting' : ''}" data-cat="${escapeHtml(it.category)}" data-id="${escapeHtml(id)}" title="${escapeHtml(it.title)}">${escapeHtml(it.title)}</div>`;
                }).join('')}</div>`;
            }).join('');
            return `<div class="cal-row" style="grid-template-columns: 220px repeat(${colCount}, minmax(88px, 1fr))">
                <div class="cal-name">${personCell(row.employee.EmpName, (row.employee.Position || '') + ' · ' + (row.employee.SiteDedicated || ''))}<div class="ohs-muted">${escapeHtml(row.chip)}</div></div>${bars}</div>`;
        }).join('') : `<div class="ohs-empty">Tidak ada assignment pada rentang ini. Ubah filter atau buat leave request.</div>`;
        document.getElementById('calendar-grid').innerHTML = head + rows;
        document.getElementById('calendar-grid').querySelectorAll('.cal-chip[data-id]').forEach((chip) => {
            chip.addEventListener('click', () => runSafe(onCalendarChip(chip.dataset.cat, chip.dataset.id, chip.title)));
        });
    }

    async function refreshList() {
        const data = await api('/leave/list', {
            method: 'POST',
            signal: withAbort(listReq),
            body: JSON.stringify({
                team: team.value,
                site: site.value,
                search: document.getElementById('cal-search').value,
                year: Number(yearSel.value || INIT.currentYear),
            }),
        });
        const c = data.counts;
        document.getElementById('leave-counts').innerHTML =
            `${statChip('Total', c.total)}${statChip('On Leave', c.onLeave)}${statChip('Upcoming', c.upcoming)}${statChip('Completed', c.completed)}`;
        const table = document.getElementById('leave-table');
        table.querySelector('thead').innerHTML = '<tr><th>Status</th><th>Karyawan</th><th>Tipe</th><th>Start</th><th>End</th><th>Backup</th><th>Hari</th><th></th></tr>';
        table.querySelector('tbody').innerHTML = (data.requests || []).length ? data.requests.map((r) => `<tr>
            <td>${badge(r.Status)}</td>
            <td>${personCell(r.EmpName, (r.EmpId || '') + ' · ' + (r.Team || ''))}</td>
            <td>${escapeHtml(r.LeaveType)}</td>
            <td>${escapeHtml(r.StartDate)}</td>
            <td>${escapeHtml(r.EndDate)}</td>
            <td>${escapeHtml(r.BackupEmpName)}</td>
            <td>${r.LeaveDays}</td>
            <td>
                <div class="ohs-row-actions">
                    <button type="button" class="btn-ghost" data-act="edit" data-id="${escapeHtml(r.RequestId)}">Edit</button>
                    <button type="button" class="btn-danger" data-act="del" data-id="${escapeHtml(r.RequestId)}">Hapus</button>
                </div>
            </td>
        </tr>`).join('') : emptyCell(8, 'Belum ada leave request');
        table.querySelectorAll('button[data-act]').forEach((btn) => btn.addEventListener('click', () => runSafe((async () => {
            if (btn.dataset.act === 'edit') {
                const row = await api('/leave/show?requestId=' + encodeURIComponent(btn.dataset.id));
                leaveForm(row);
                return;
            }
            if (!confirm('Hapus leave request ini?')) return;
            await api('/leave/delete', { method: 'POST', body: JSON.stringify({ RequestId: btn.dataset.id }) });
            refreshAll();
        })())));
    }

    async function refreshAll() {
        await Promise.all([refreshCalendar(), refreshList()]);
    }

    async function onCalendarChip(cat, id, title) {
        if (!id) return;
        if (cat === 'LEAVE') {
            const row = await api('/leave/show?requestId=' + encodeURIComponent(id));
            leaveForm(row);
            return;
        }
        openModal(title || cat, `<p>Kelola item ini di menu ${cat.startsWith('EVENT') ? 'Event Maker' : 'Project & Issue Tracker'}.</p>
            <p class="ohs-muted">ID: ${escapeHtml(id)}</p>`);
    }

    function leaveForm(existing) {
        openModal(existing ? 'Edit Leave Request' : 'Create Leave Request', `
            <form id="leave-form" class="ohs-form">
                <input type="hidden" name="RequestId" value="${escapeHtml(existing?.RequestId || '')}">
                <div class="emp-box full"><label>Employee<input type="search" value="${escapeHtml(existing ? (existing.EmpName + ' (' + existing.EmpId + ')') : '')}" placeholder="Cari nama / NPK"><input type="hidden" name="EmpId" value="${escapeHtml(existing?.EmpId || '')}"><ul class="ohs-search-list"></ul></label></div>
                <div class="ohs-form-grid">
                    <label>Leave Type<select name="LeaveType"></select></label>
                    <label>Start Date<input type="date" name="StartDate" required value="${escapeHtml(existing?.StartDate || '')}"></label>
                    <label>End Date<input type="date" name="EndDate" required value="${escapeHtml(existing?.EndDate || '')}"></label>
                    <label>Time From<input type="time" name="TimeFrom" value="${escapeHtml(existing?.TimeFrom || '')}"></label>
                    <label>Time To<input type="time" name="TimeTo" value="${escapeHtml(existing?.TimeTo || '')}"></label>
                </div>
                <div class="emp-box full"><label>Backup / Acting PIC<input type="search" value="${escapeHtml(existing ? (existing.BackupEmpName + ' (' + existing.BackupEmpId + ')') : '')}" placeholder="Cari backup PIC"><input type="hidden" name="BackupEmpId" value="${escapeHtml(existing?.BackupEmpId || '')}"><ul class="ohs-search-list"></ul></label></div>
                <label class="full">Note<textarea name="Note">${escapeHtml(existing?.Note || '')}</textarea></label>
                <p id="leave-overlap" class="ohs-muted"></p>
                <div class="ohs-actions">
                    <button class="btn-primary" type="submit">Simpan</button>
                    ${existing ? '<button type="button" class="btn-danger" id="leave-del">Hapus</button>' : ''}
                </div>
            </form>`, (modal) => {
            const form = modal.querySelector('#leave-form');
            form.LeaveType.innerHTML = (INIT.leaveTypes || []).map((t) => `<option ${existing && t.LeaveType === existing.LeaveType ? 'selected' : ''}>${escapeHtml(t.LeaveType)}</option>`).join('');
            const check = debounce(async () => {
                const r = await api('/leave/check-overlap', {
                    method: 'POST',
                    silent: true,
                    body: JSON.stringify({
                        EmpId: form.EmpId.value,
                        BackupEmpId: form.BackupEmpId.value,
                        StartDate: form.StartDate.value,
                        EndDate: form.EndDate.value,
                        ExcludeRequestId: form.RequestId.value,
                    }),
                });
                form.parentElement.querySelector('#leave-overlap').textContent = r.message || '';
            }, 300);
            employeePicker(form.querySelectorAll('.emp-box')[0], 'EmpId', check);
            employeePicker(form.querySelectorAll('.emp-box')[1], 'BackupEmpId', check);
            form.StartDate.addEventListener('change', check);
            form.EndDate.addEventListener('change', check);
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                if (form.dataset.busy === '1') return;
                form.dataset.busy = '1';
                const btn = form.querySelector('[type="submit"]');
                if (btn) btn.disabled = true;
                try {
                    const payload = formObject(form, null);
                    await api(existing ? '/leave/update' : '/leave/create', { method: 'POST', body: JSON.stringify(payload) });
                    closeModal();
                    refreshAll();
                } catch (err) { toast(err.message); }
                finally {
                    form.dataset.busy = '0';
                    if (btn) btn.disabled = false;
                }
            });
            modal.querySelector('#leave-del')?.addEventListener('click', async () => {
                if (!confirm('Hapus leave request ini?')) return;
                try {
                    await api('/leave/delete', { method: 'POST', body: JSON.stringify({ RequestId: existing.RequestId }) });
                    closeModal();
                    refreshAll();
                } catch (err) { toast(err.message); }
            });
        });
    }

    loadInit().then((init) => {
        fillSelect(team, init.teams, 'All Teams');
        fillSelect(site, init.sites, 'All Sites');
        yearSel.innerHTML = init.years.map((y) => `<option ${y === init.currentYear ? 'selected' : ''}>${y}</option>`).join('');
        state.anchorISO = init.todayISO;
        return refreshAll();
    }).catch((e) => toast(e.message));
    page.querySelectorAll('[data-view]').forEach((btn) => btn.addEventListener('click', () => {
        page.querySelectorAll('[data-view]').forEach((b) => b.classList.remove('is-active'));
        btn.classList.add('is-active');
        state.viewMode = btn.dataset.view;
        runSafe(refreshCalendar());
    }));
    const shift = (days) => {
        state.anchorISO = addDaysISO(state.anchorISO, days);
        runSafe(refreshCalendar());
    };
    document.getElementById('cal-prev').onclick = () => shift(state.viewMode === 'YEAR' ? -365 : state.viewMode === 'MONTH' ? -30 : -7);
    document.getElementById('cal-next').onclick = () => shift(state.viewMode === 'YEAR' ? 365 : state.viewMode === 'MONTH' ? 30 : 7);
    document.getElementById('cal-today').onclick = () => { state.anchorISO = INIT.todayISO; runSafe(refreshCalendar()); };
    document.getElementById('cal-search').addEventListener('input', debounce(() => runSafe(refreshAll()), 300));
    team.onchange = site.onchange = yearSel.onchange = () => runSafe(refreshAll());
    document.getElementById('btn-create-leave').onclick = () => leaveForm(null);
}

function initEvents() {
    const page = document.querySelector('[data-ohs-page="events"]');
    if (!page) return;
    const team = document.getElementById('ev-team');
    const site = document.getElementById('ev-site');

    const evReq = {};

    async function refresh() {
        const data = await api('/events/maker-data', {
            method: 'POST',
            signal: withAbort(evReq),
            body: JSON.stringify({ team: team.value, site: site.value }),
        });
        const c = data.counts;
        document.getElementById('event-badges').innerHTML = Object.entries(c).map(([k, v]) => statChip(k, v)).join('');
        const table = document.getElementById('event-table');
        table.querySelector('thead').innerHTML = '<tr><th>Status</th><th>Event</th><th>Date</th><th>PIC</th><th>Where</th><th>Kesiapan</th><th>Last Update</th><th></th></tr>';
        table.querySelector('tbody').innerHTML = data.events.length ? data.events.map((ev) => `<tr>
            <td>${badge(ev.ScheduleStatus)}</td>
            <td><div class="ohs-person"><strong>${escapeHtml(ev.EventName)}</strong><span>${escapeHtml(ev.Description || '').slice(0, 80)}</span></div></td>
            <td>${escapeHtml(ev.EventDate)}</td>
            <td>${personCell(ev.PICName, (ev.PICTeam || '') + ' · ' + (ev.PICSiteDedicated || ''))}</td>
            <td>${escapeHtml(ev.Where)}</td>
            <td>${escapeHtml(ev.ReadinessUpdate || '-')}</td>
            <td>${escapeHtml(ev.ReadinessUpdatedAt || '-')}</td>
            <td>
                <div class="ohs-row-actions">
                    <button class="btn-ghost" data-act="edit" data-id="${escapeHtml(ev.EventId)}">Edit</button>
                    <button class="btn-ghost" data-act="ready" data-id="${escapeHtml(ev.EventId)}">Kesiapan</button>
                    <button class="btn-ghost" data-act="qr" data-id="${escapeHtml(ev.EventId)}">QR</button>
                    <button class="btn-ghost" data-act="att" data-id="${escapeHtml(ev.EventId)}">Hadir</button>
                    <button class="btn-ghost" data-act="min" data-id="${escapeHtml(ev.EventId)}">Notulensi</button>
                    <button class="btn-danger" data-act="del" data-id="${escapeHtml(ev.EventId)}">Hapus</button>
                </div>
            </td>
        </tr>`).join('') : emptyCell(8, 'Belum ada event');
        table.querySelectorAll('button[data-act]').forEach((btn) => btn.addEventListener('click', () => runSafe(onEventAction(btn.dataset.act, btn.dataset.id, data.events.find((e) => e.EventId === btn.dataset.id)))));
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
                const payload = formObject(form, null);
                if (ev) payload.EventId = ev.EventId;
                try {
                    await api(ev ? '/events/update' : '/events/create', { method: 'POST', body: JSON.stringify(payload) });
                    closeModal();
                    refresh();
                } catch (err) { toast(err.message); }
            };
        });
    }

    async function onEventAction(act, id, ev) {
        if (act === 'edit') return eventForm(ev);
        if (act === 'del') {
            if (!confirm('Hapus event beserta absensi, notulensi, dan action item?')) return;
            try {
                await api('/events/delete', { method: 'POST', body: JSON.stringify({ EventId: id }) });
                refresh();
            } catch (err) { toast(err.message); }
            return;
        }
        if (act === 'ready') {
            openModal('Update Kesiapan', `<form id="ready-form" class="ohs-form"><textarea name="ReadinessUpdate" required>${escapeHtml(ev?.ReadinessUpdate || '')}</textarea><button class="btn-primary">Simpan</button></form>`, (modal) => {
                modal.querySelector('form').onsubmit = async (e) => {
                    e.preventDefault();
                    try {
                        await api('/events/readiness', { method: 'POST', body: JSON.stringify({ EventId: id, ReadinessUpdate: e.target.ReadinessUpdate.value }) });
                        closeModal(); refresh();
                    } catch (err) { toast(err.message); }
                };
            });
        }
        if (act === 'qr') {
            const url = `${location.origin}/ohs-dashboard/checkin?eventId=${encodeURIComponent(id)}`;
            openModal('QR Absensi', `<div class="ohs-qr"><p><a href="${url}" target="_blank">${url}</a></p><p><button type="button" class="btn-primary" id="copy-qr">Copy link</button></p><img alt="QR" src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${encodeURIComponent(url)}"></div>`, (modal) => {
                modal.querySelector('#copy-qr').onclick = () => navigator.clipboard.writeText(url);
            });
        }
        if (act === 'att') {
            const data = await api('/events/attendance?eventId=' + encodeURIComponent(id));
            openModal('Daftar Hadir', `<p class="ohs-muted">${data.attendanceCount} peserta sudah check-in</p><table class="ohs-table"><thead><tr><th>Nama</th><th>Check-in</th></tr></thead><tbody>${data.attendance.length ? data.attendance.map((a) => `<tr><td>${escapeHtml(a.EmpName)}</td><td>${escapeHtml(a.CheckInAt)}</td></tr>`).join('') : emptyCell(2, 'Belum ada absensi')}</tbody></table>`);
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
                <div id="ai-list">${(data.actionItems || []).map((i) => `<div class="ohs-ai-item"><div><strong>${escapeHtml(i.Task)}</strong><div class="ohs-muted">${escapeHtml(i.PICName || 'Tanpa PIC')} · ${escapeHtml(i.DueDate || '-')}</div></div>${badge(i.Status)} <button class="btn-ghost" data-ai="${escapeHtml(i.ActionItemId)}" data-st="${i.Status === 'Open' ? 'Done' : 'Open'}">Toggle</button></div>`).join('') || '<p class="ohs-muted">Belum ada action item</p>'}</div>
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
                    await api('/events/minutes', { method: 'POST', body: JSON.stringify({ EventId: id, ...formObject(e.target, null) }) });
                    closeModal(); onEventAction('min', id, ev);
                };
                modal.querySelector('#ai-form').onsubmit = async (e) => {
                    e.preventDefault();
                    await api('/events/action-items/add', { method: 'POST', body: JSON.stringify({ EventId: id, ...formObject(e.target, null) }) });
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
        return refresh();
    }).catch((e) => toast(e.message));
    team.onchange = site.onchange = () => runSafe(refresh());
    document.getElementById('btn-create-event').onclick = () => eventForm(null);
}

function initTracker() {
    const page = document.querySelector('[data-ohs-page="tracker"]');
    if (!page) return;
    let cache = [];
    let pageNo = 1;
    const pageSize = 10;

    const trReq = {};

    async function refresh() {
        const data = await api('/tracker/data', {
            method: 'POST',
            signal: withAbort(trReq),
            body: JSON.stringify({
                type: document.getElementById('tr-type').value,
                status: document.getElementById('tr-status').value,
                department: document.getElementById('tr-dept').value,
                site: document.getElementById('tr-site').value,
                search: document.getElementById('tr-search').value,
            }),
        });
        cache = data.trackers;
        pageNo = 1;
        const c = data.counts;
        document.getElementById('tracker-counts').innerHTML = `${statChip('Total', c.total)}${statChip('On Going', c.onGoing)}${statChip('Overdue', c.overdue)}${statChip('Closed', c.closed)}`;
        render();
    }

    function render() {
        const slice = cache.slice((pageNo - 1) * pageSize, pageNo * pageSize);
        const table = document.getElementById('tracker-table');
        table.querySelector('thead').innerHTML = '<tr><th>Type</th><th>Nama</th><th>Leader</th><th>Site</th><th>Due</th><th>%</th><th>Status</th><th></th></tr>';
        table.querySelector('tbody').innerHTML = slice.length ? slice.map((t) => `<tr>
            <td>${escapeHtml(t.TrackerType)}</td>
            <td><div class="ohs-person"><strong>${escapeHtml(t.ProjectIssueName)}</strong><span>${escapeHtml(t.Department || '')}</span></div></td>
            <td>${escapeHtml(t.ProjectLeaderName)}</td>
            <td>${escapeHtml(t.Site)}</td>
            <td>${escapeHtml(t.DueDate)}</td>
            <td>${progressBar(t.CurrentPercentComplete)}</td>
            <td>${badge(t.EffectiveStatus)}</td>
            <td>
                <div class="ohs-row-actions">
                    <button class="btn-ghost" data-act="edit" data-id="${t.TrackerId}">Edit</button>
                    <button class="btn-ghost" data-act="prog" data-id="${t.TrackerId}">Progress</button>
                    <button class="btn-ghost" data-act="log" data-id="${t.TrackerId}">Log</button>
                    <button class="btn-danger" data-act="del" data-id="${t.TrackerId}">Hapus</button>
                </div>
            </td>
        </tr>`).join('') : emptyCell(8, 'Belum ada tracker');
        table.querySelectorAll('button[data-act]').forEach((b) => b.onclick = () => runSafe(onTracker(b.dataset.act, cache.find((t) => t.TrackerId === b.dataset.id))));
        document.getElementById('tracker-pager').innerHTML = `<button type="button" class="btn-ghost" id="pprev">Prev</button><span>Hal ${pageNo}</span><button type="button" class="btn-ghost" id="pnext">Next</button>`;
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
                <button type="button" class="btn-ghost" id="add-st">+ Sub Task</button>
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
                const payload = formObject(form);
                payload.SubTasks = [...box.querySelectorAll('.st-row')].map((row) => {
                    const o = {};
                    row.querySelectorAll('input,textarea,select').forEach((i) => { if (i.name) o[i.name] = i.value; });
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
                } catch (err) { toast(err.message); }
            };
        });
    }

    async function onTracker(act, t) {
        if (!t) return;
        try {
            if (act !== 'del') {
                t = await api('/tracker/show?trackerId=' + encodeURIComponent(t.TrackerId));
            }
            if (act === 'edit') return trackerForm(t);
            if (act === 'del') {
                if (!confirm('Hapus tracker beserta sub task dan log progress?')) return;
                await api('/tracker/delete', { method: 'POST', body: JSON.stringify({ TrackerId: t.TrackerId }) });
                refresh();
                return;
            }
        if (act === 'log') {
            const data = await api('/tracker/log?trackerId=' + encodeURIComponent(t.TrackerId));
            const subLinks = (t.SubTasks || []).map((s) => `<button type="button" class="btn-ghost" data-st="${escapeHtml(s.SubTaskId)}">${escapeHtml(s.SubTaskName)}</button>`).join(' ');
            openModal('Update Log', `
                ${subLinks ? `<p>Sub task log: ${subLinks}</p>` : ''}
                <table class="ohs-table">${(data.logs || []).map((l) => `<tr><td>${escapeHtml(l.Timestamp)}</td><td>${l.PercentComplete}</td><td>${escapeHtml(l.ProgressReportWeekly)}</td><td>${escapeHtml(l.UpdatedByName)}</td></tr>`).join('') || '<tr><td colspan="4">Belum ada log parent</td></tr>'}</table>`, (modal) => {
                modal.querySelectorAll('[data-st]').forEach((b) => b.onclick = async () => {
                    const sub = await api('/tracker/subtask-log?subTaskId=' + encodeURIComponent(b.dataset.st));
                    openModal('Log Sub Task', `<table class="ohs-table">${(sub.logs || []).map((l) => `<tr><td>${escapeHtml(l.Timestamp)}</td><td>${l.PercentComplete}</td><td>${escapeHtml(l.ProgressReportWeekly)}</td><td>${escapeHtml(l.UpdatedByName)}</td></tr>`).join('') || '<tr><td colspan="4">Belum ada log</td></tr>'}</table>`);
                });
            });
            return;
        }
        if (t.HasSubTasks) {
            const options = (t.SubTasks || []).map((s) => `<option value="${escapeHtml(s.SubTaskId)}">${escapeHtml(s.SubTaskName)}</option>`).join('');
            openModal('Update Progress Sub Task', `<form id="up" class="ohs-form">
                <label>Sub Task<select name="SubTaskId">${options}</select></label>
                <label>%<input name="PercentComplete" type="number" min="0" max="100" step="0.01" required></label>
                <label>Weekly<textarea name="ProgressReportWeekly" required></textarea></label>
                <label>Keterangan<textarea name="Remarks" required></textarea></label>
                <div class="emp-box"><label>Updated By<input type="search"><input type="hidden" name="UpdatedByEmpId"><ul class="ohs-search-list"></ul></label></div>
                <button class="btn-primary">Simpan</button></form>`, (modal) => {
                employeePicker(modal.querySelector('.emp-box'), 'UpdatedByEmpId');
                modal.querySelector('#up').onsubmit = async (e) => {
                    e.preventDefault();
                    try {
                        await api('/tracker/update-subtask', { method: 'POST', body: JSON.stringify(formObject(e.target, null)) });
                        closeModal(); refresh();
                    } catch (err) { toast(err.message); }
                };
            });
        } else {
            openModal('Update Progress', `<form id="up" class="ohs-form">
                <label>%<input name="PercentComplete" type="number" min="0" max="100" step="0.01" required></label>
                <label>Weekly<textarea name="ProgressReportWeekly" required></textarea></label>
                <label>Keterangan<textarea name="Remarks" required></textarea></label>
                <div class="emp-box"><label>Updated By<input type="search"><input type="hidden" name="UpdatedByEmpId"><ul class="ohs-search-list"></ul></label></div>
                <button class="btn-primary">Simpan</button></form>`, (modal) => {
                employeePicker(modal.querySelector('.emp-box'), 'UpdatedByEmpId');
                modal.querySelector('#up').onsubmit = async (e) => {
                    e.preventDefault();
                    try {
                        await api('/tracker/update', { method: 'POST', body: JSON.stringify({ TrackerId: t.TrackerId, ...formObject(e.target, null) }) });
                        closeModal(); refresh();
                    } catch (err) { toast(err.message); }
                };
            });
        }
        } catch (err) { toast(err.message); }
    }

    loadInit().then((init) => {
        fillSelect(document.getElementById('tr-dept'), init.teams, 'All Departments');
        fillSelect(document.getElementById('tr-site'), init.sites, 'All Sites');
        return refresh();
    }).catch((e) => toast(e.message));
    document.getElementById('tr-refresh').onclick = () => { pageNo = 1; runSafe(refresh()); };
    document.getElementById('tr-type').onchange = document.getElementById('tr-status').onchange =
        document.getElementById('tr-dept').onchange = document.getElementById('tr-site').onchange =
        () => runSafe(refresh());
    document.getElementById('tr-search').addEventListener('input', debounce(() => runSafe(refresh()), 300));
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
        document.getElementById('admin-status').innerHTML = [
            kpiCard('Scheduler', s.Enabled ? 'ON' : 'OFF', s.Enabled ? '' : 'red'),
            kpiCard('Hari & jam', `${s.ScheduleDays || '-'} ${String(s.SendHour).padStart(2, '0')}:${String(s.SendMinute).padStart(2, '0')}`, 'blue'),
            kpiCard('Digest terakhir', s.LastRunAt || '-', 'gold', s.LastRunStatus || ''),
            kpiCard('Overdue reminder', s.OverdueReminderLastRunAt || '-', 'amber', `${s.OverdueReminderLastCount ?? 0} item`),
            kpiCard('HSE sync', s.HseSyncLastRunAt || '-', 'purple', `${s.HseSyncLastCount ?? 0} karyawan`),
            kpiCard('Roster karyawan', init.employeeCount, ''),
        ].join('');
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
            toast('Settings tersimpan', 'ok');
            load();
        } catch (err) { toast(err.message); }
    };

    const run = (path, label) => async () => {
        if (!confirm(label + '?')) return;
        try {
            const r = await api(path, { method: 'POST', body: '{}', timeout: path.includes('hse-sync') ? 120000 : 45000 });
            toast(r.message || 'Selesai', 'ok');
            load();
        } catch (err) { toast(err.message); }
    };
    document.getElementById('admin-refresh').onclick = () => runSafe(load());
    document.getElementById('admin-send').onclick = run('/admin/email-send', 'Kirim digest sekarang');
    document.getElementById('admin-test').onclick = run('/admin/email-test', 'Kirim test email');
    document.getElementById('admin-overdue').onclick = run('/admin/overdue-reminder-send', 'Kirim overdue reminder');
    document.getElementById('admin-hse').onclick = run('/admin/hse-sync-now', 'Sync HSE akan menimpa seluruh ohs_employees');
    load().catch((e) => toast(e.message));
}

document.addEventListener('DOMContentLoaded', () => {
    initOverview();
    initLeave();
    initEvents();
    initTracker();
    initAdmin();
});

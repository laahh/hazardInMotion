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

function createSortableTable(mount, config) {
    const state = { sortKey: config.defaultSort || null, sortDir: 'asc', filters: {}, page: 1 };
    const pageSize = config.pageSize || 10;

    const theadSortRow = config.columns.map((col) => {
        if (col.sortable === false) return `<th>${escapeHtml(col.label)}</th>`;
        return `<th><button type="button" class="ohs-th-sort" data-sort="${col.key}">${escapeHtml(col.label)} <span class="ohs-sort-arrow" data-arrow="${col.key}">↕</span></button></th>`;
    }).join('');
    const theadFilterRow = config.columns.map((col) => {
        if (col.searchable === false) return '<th></th>';
        return `<th><input type="search" class="ohs-th-filter" data-filter="${col.key}" placeholder="Cari…"></th>`;
    }).join('');

    mount.innerHTML = `
        <div class="ohs-table-wrap">
            <table class="ohs-table">
                <thead><tr>${theadSortRow}</tr><tr class="ohs-table-filter-row">${theadFilterRow}</tr></thead>
                <tbody></tbody>
            </table>
        </div>
        <div class="ohs-pager" data-pager></div>`;

    const tbody = mount.querySelector('tbody');
    const pager = mount.querySelector('[data-pager]');

    function filteredSortedRows() {
        let rows = (config.rows || []).slice();
        config.columns.forEach((col) => {
            const q = (state.filters[col.key] || '').trim().toLowerCase();
            if (!q) return;
            rows = rows.filter((row) => String(col.searchValue ? col.searchValue(row) : (row[col.key] ?? '')).toLowerCase().includes(q));
        });
        if (state.sortKey) {
            const col = config.columns.find((c) => c.key === state.sortKey);
            if (col) {
                const dir = state.sortDir === 'desc' ? -1 : 1;
                rows.sort((a, b) => {
                    const va = col.sortValue ? col.sortValue(a) : (a[col.key] ?? '');
                    const vb = col.sortValue ? col.sortValue(b) : (b[col.key] ?? '');
                    if (va < vb) return -1 * dir;
                    if (va > vb) return 1 * dir;
                    return 0;
                });
            }
        }
        return rows;
    }

    function renderBody() {
        const filtered = filteredSortedRows();
        const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize));
        state.page = Math.min(state.page, totalPages);
        const pageRows = filtered.slice((state.page - 1) * pageSize, state.page * pageSize);
        tbody.innerHTML = pageRows.length
            ? pageRows.map((row) => `<tr class="${config.rowClass ? config.rowClass(row) : ''}">${config.columns.map((col) => `<td>${col.render ? col.render(row) : escapeHtml(row[col.key] ?? '')}</td>`).join('')}</tr>${config.detailRow ? config.detailRow(row) : ''}`).join('')
            : emptyCell(config.columns.length, config.emptyText || 'Belum ada data');
        pager.innerHTML = `
            <span class="ohs-muted">${filtered.length} data</span>
            <button type="button" class="btn-ghost" data-page="first" ${state.page <= 1 ? 'disabled' : ''}>«</button>
            <button type="button" class="btn-ghost" data-page="prev" ${state.page <= 1 ? 'disabled' : ''}>‹</button>
            <span>${state.page} / ${totalPages}</span>
            <button type="button" class="btn-ghost" data-page="next" ${state.page >= totalPages ? 'disabled' : ''}>›</button>
            <button type="button" class="btn-ghost" data-page="last" ${state.page >= totalPages ? 'disabled' : ''}>»</button>`;
        pager.querySelectorAll('[data-page]').forEach((btn) => {
            btn.addEventListener('click', () => {
                if (btn.dataset.page === 'first') state.page = 1;
                if (btn.dataset.page === 'prev') state.page = Math.max(1, state.page - 1);
                if (btn.dataset.page === 'next') state.page = state.page + 1;
                if (btn.dataset.page === 'last') state.page = totalPages;
                renderBody();
            });
        });
        mount.querySelectorAll('[data-arrow]').forEach((span) => {
            const active = state.sortKey === span.dataset.arrow;
            span.textContent = active ? (state.sortDir === 'asc' ? '▲' : '▼') : '↕';
        });
        if (config.onRender) config.onRender(pageRows, mount);
    }

    mount.querySelectorAll('[data-sort]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const key = btn.dataset.sort;
            if (state.sortKey === key) state.sortDir = state.sortDir === 'asc' ? 'desc' : 'asc';
            else { state.sortKey = key; state.sortDir = 'asc'; }
            renderBody();
        });
    });
    mount.querySelectorAll('[data-filter]').forEach((input) => {
        input.addEventListener('input', debounce(() => {
            state.filters[input.dataset.filter] = input.value;
            state.page = 1;
            renderBody();
        }, 200));
    });

    renderBody();

    return {
        setRows(rows) { config.rows = rows; state.page = 1; renderBody(); },
        refresh: renderBody,
    };
}

let INIT = null;
async function loadInit() {
    if (!INIT) INIT = await api('/init');
    return INIT;
}

async function openLeaveHistory(empId, year) {
    const hist = await api(`/leave/history?empId=${encodeURIComponent(empId)}&year=${encodeURIComponent(year)}`);
    openModal('Riwayat Cuti ' + (hist.employee.EmpName || ''), `
        <div class="ohs-kpis" style="grid-template-columns:repeat(auto-fit, minmax(130px, 1fr));">
            ${kpiCard('Total Working Days YTD', hist.ytdWorkingDays)}
            ${kpiCard('Leave YTD', hist.leaveDaysYTD, 'red')}
            ${kpiCard('Effective Working Days', hist.effectiveWorkingDays, 'blue')}
            ${kpiCard('Effective Working %', hist.effectiveWorkingPercent + '%', 'blue')}
            ${kpiCard('Total Requests', hist.totalRequests)}
        </div>
        <div class="ohs-table-wrap">
            <table class="ohs-table"><thead><tr><th>Tipe</th><th>Start</th><th>End</th><th>Hari</th><th>Status</th></tr></thead>
            <tbody>${hist.records.length ? hist.records.map((r) => `<tr><td>${escapeHtml(r.LeaveType)}</td><td>${escapeHtml(r.StartDate)}</td><td>${escapeHtml(r.EndDate)}</td><td>${r.LeaveDays}</td><td>${badge(r.Status)}</td></tr>`).join('') : emptyCell(5, 'Belum ada riwayat cuti')}</tbody></table>
        </div>`);
}

function initOverview() {
    const page = document.querySelector('[data-ohs-page="overview"]');
    if (!page) return;
    const team = document.getElementById('filter-team');
    const site = document.getElementById('filter-site');
    const year = document.getElementById('filter-year');
    const req = {};
    let trackerTable = null;
    let leaderboardCache = [];
    const eventGroupData = {};
    const leaveGroupData = {};

    page.querySelectorAll('[data-collapse-toggle]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const target = document.getElementById(btn.dataset.collapseTarget);
            const collapsed = target.classList.toggle('collapsed');
            btn.textContent = collapsed ? '+' : '−';
        });
    });

    function sortItems(items, mode, dateKey, nameKey) {
        const sorted = items.slice();
        if (mode === 'dateDesc') sorted.sort((a, b) => String(b[dateKey] || '').localeCompare(String(a[dateKey] || '')));
        else if (mode === 'nameAsc') sorted.sort((a, b) => String(a[nameKey] || '').localeCompare(String(b[nameKey] || '')));
        else sorted.sort((a, b) => String(a[dateKey] || '').localeCompare(String(b[dateKey] || '')));
        return sorted;
    }

    function renderEventGroup(key) {
        const card = page.querySelector(`[data-event-group="${key}"]`);
        if (!card) return;
        const items = eventGroupData[key] || [];
        card.querySelector('[data-count]').textContent = items.length;
        const mode = card.querySelector('[data-sort-select]').value;
        const sorted = sortItems(items, mode, 'EventDate', 'EventName');
        card.querySelector('[data-item-list]').innerHTML = sorted.length ? sorted.map((it) => `
            <div class="ohs-item">
                <div class="ohs-item-title">${escapeHtml(it.EventName)}</div>
                <div class="ohs-item-meta">${escapeHtml(it.EventDate || '-')} • ${escapeHtml(it.PICName || '-')} • ${escapeHtml(it.Where || '-')}</div>
            </div>`).join('') : '<div class="ohs-empty">Tidak ada data pada periode ini</div>';
    }

    function renderLeaveGroup(key) {
        const card = page.querySelector(`[data-leave-group="${key}"]`);
        if (!card) return;
        const items = leaveGroupData[key] || [];
        card.querySelector('[data-count]').textContent = items.length;
        const mode = card.querySelector('[data-sort-select]').value;
        const sorted = sortItems(items, mode, 'StartDate', 'EmpName');
        card.querySelector('[data-item-list]').innerHTML = sorted.length ? sorted.map((it) => `
            <div class="ohs-item">
                <div class="ohs-item-title">${escapeHtml(it.EmpName)}</div>
                <div class="ohs-item-meta">${escapeHtml(it.LeaveType || '-')} • ${escapeHtml(it.StartDate || '-')} → ${escapeHtml(it.EndDate || '-')}</div>
            </div>`).join('') : '<div class="ohs-empty">Tidak ada data pada periode ini</div>';
    }

    page.querySelectorAll('[data-event-group] [data-sort-select]').forEach((sel) => {
        sel.addEventListener('change', () => renderEventGroup(sel.closest('[data-event-group]').dataset.eventGroup));
    });
    page.querySelectorAll('[data-leave-group] [data-sort-select]').forEach((sel) => {
        sel.addEventListener('change', () => renderLeaveGroup(sel.closest('[data-leave-group]').dataset.leaveGroup));
    });

    function ensureTrackerTable() {
        if (trackerTable) return trackerTable;
        trackerTable = createSortableTable(document.getElementById('overview-tracker-mount'), {
            pageSize: 10,
            rows: [],
            emptyText: 'Belum ada tracker',
            rowClass: (t) => t.EffectiveStatus === 'Closed' ? 'closed' : t.EffectiveStatus === 'Overdue' ? 'overdue' : '',
            columns: [
                {
                    key: 'type', label: 'Type', sortValue: (t) => t.TrackerType, searchValue: (t) => t.TrackerType + ' ' + t.TrackerId,
                    render: (t) => `<span class="ohs-tracker-type">${escapeHtml(t.TrackerType)}</span><div class="ohs-person-sub">${escapeHtml(t.TrackerId)}</div>`,
                },
                { key: 'name', label: 'Project / Issue', sortValue: (t) => t.ProjectIssueName, render: (t) => `<b>${escapeHtml(t.ProjectIssueName)}</b>` },
                {
                    key: 'dept', label: 'Department / Site', sortValue: (t) => t.Department, searchValue: (t) => (t.Department || '') + ' ' + (t.Site || ''),
                    render: (t) => `${escapeHtml(t.Department || '-')}<div class="ohs-person-sub">${escapeHtml(t.Site || '-')}</div>`,
                },
                { key: 'leader', label: 'Project Leader', sortValue: (t) => t.ProjectLeaderName, render: (t) => escapeHtml(t.ProjectLeaderName || t.ProjectLeaderEmpId || '-') },
                {
                    key: 'timeline', label: 'Timeline', sortValue: (t) => t.DueDate || '', searchValue: (t) => (t.StartDate || '') + ' ' + (t.DueDate || ''),
                    render: (t) => `${escapeHtml(t.StartDate || '-')}<br>→ ${escapeHtml(t.DueDate || '-')}`,
                },
                {
                    key: 'subtasks', label: 'Sub Tasks / PIC', sortable: false, searchValue: (t) => (t.SubTasks || []).map((s) => s.SubTaskName + ' ' + (s.PICName || '')).join(' '),
                    render: (t) => {
                        const tasks = t.SubTasks || [];
                        if (!tasks.length) return '<span class="ohs-tracker-no-subtask-label">NO SUB TASK</span>';
                        return `<b>${tasks.length} Sub Task</b><div class="ohs-subtask-summary">${tasks.slice(0, 4).map((s) => `<span class="ohs-subtask-chip">${escapeHtml(s.SubTaskName)} • ${escapeHtml(s.PICName || s.PICEmpId || '-')}</span>`).join('')}</div>`;
                    },
                },
                { key: 'percent', label: '% Complete', sortValue: (t) => Number(t.CurrentPercentComplete || 0), render: (t) => progressBar(t.CurrentPercentComplete) },
                {
                    key: 'status', label: 'Status', sortValue: (t) => t.EffectiveStatus === 'Overdue' ? 0 : t.EffectiveStatus === 'On Going' ? 1 : 2,
                    render: (t) => `<span class="ohs-tracker-status ${trackerStatusBadgeClass(t.EffectiveStatus)}">${escapeHtml(t.EffectiveStatus)}</span>`,
                },
                { key: 'report', label: 'Latest Weekly Report', sortValue: (t) => t.CurrentProgressReportWeekly || '', render: (t) => escapeHtml(t.CurrentProgressReportWeekly || '-') },
                {
                    key: 'action', label: 'Action', sortable: false, searchable: false,
                    render: (t) => `<button type="button" class="btn-ghost" data-view-tracker="${escapeHtml(t.TrackerId)}">Buka</button>`,
                },
            ],
            onRender: (pageRows, mount) => {
                mount.querySelectorAll('[data-view-tracker]').forEach((btn) => btn.addEventListener('click', () => { location.href = '/ohs-dashboard/tracker'; }));
            },
        });
        return trackerTable;
    }

    function renderLeaderboardList() {
        const search = document.getElementById('leaderboard-search').value.trim().toLowerCase();
        const mode = document.getElementById('leaderboard-sort').value;
        let items = leaderboardCache.slice();
        if (search) {
            items = items.filter((it) => [it.EmpId, it.EmpName, it.Position, it.Team, it.SiteDedicated].some((v) => String(v || '').toLowerCase().includes(search)));
        }
        if (mode === 'effectiveDesc') items.sort((a, b) => (b.EffectiveWorkingPercent || 0) - (a.EffectiveWorkingPercent || 0));
        else if (mode === 'effectiveAsc') items.sort((a, b) => (a.EffectiveWorkingPercent || 0) - (b.EffectiveWorkingPercent || 0));
        else if (mode === 'nameAsc') items.sort((a, b) => String(a.EmpName || '').localeCompare(String(b.EmpName || '')));
        else items.sort((a, b) => (b.LeaveYTD || 0) - (a.LeaveYTD || 0));

        const list = document.getElementById('leaderboard-list');
        list.innerHTML = items.length ? items.map((it, idx) => `
            <div class="ohs-leaderboard-sidebar-item">
                <div class="ohs-leaderboard-rank">${idx + 1}</div>
                <div class="ohs-leaderboard-avatar">${escapeHtml(String(it.EmpName || '').trim().split(/\s+/).map((p) => p[0] || '').slice(0, 2).join('').toUpperCase() || '?')}</div>
                <div style="min-width:0;">
                    <button type="button" class="link-button" data-emp="${escapeHtml(it.EmpId)}" style="background:none;border:0;padding:0;font-weight:700;cursor:pointer;color:inherit;text-align:left;">${escapeHtml(it.EmpName)}</button>
                    <div class="ohs-person-sub">${escapeHtml(it.Position || it.EmpId)}</div>
                    <div class="ohs-person-sub">${escapeHtml(it.Team || '-')} • ${escapeHtml(it.SiteDedicated || '-')}</div>
                </div>
                <div class="ohs-leaderboard-days"><b>${it.LeaveYTD || 0}</b>leave days<small>${it.EffectiveWorkingDays || 0} effective days</small></div>
            </div>`).join('') : '<div class="ohs-empty">Tidak ada employee sesuai pencarian.</div>';
        list.querySelectorAll('[data-emp]').forEach((btn) => btn.addEventListener('click', () => runSafe(openLeaveHistory(btn.dataset.emp, year.value))));
    }

    function setLeaderboardOpen(open) {
        document.getElementById('leaderboard-sidebar').classList.toggle('open', open);
        document.getElementById('leaderboard-toggle').classList.toggle('open', open);
        document.getElementById('leaderboard-toggle').textContent = open ? '›' : '‹';
        document.getElementById('leaderboard-sidebar').setAttribute('aria-hidden', open ? 'false' : 'true');
        document.getElementById('leaderboard-backdrop').classList.toggle('hide', !open);
    }

    document.getElementById('leaderboard-search').addEventListener('input', debounce(renderLeaderboardList, 200));
    document.getElementById('leaderboard-sort').addEventListener('change', renderLeaderboardList);
    document.getElementById('leaderboard-toggle').addEventListener('click', () => setLeaderboardOpen(true));
    document.getElementById('leaderboard-close').addEventListener('click', () => setLeaderboardOpen(false));
    document.getElementById('leaderboard-backdrop').addEventListener('click', () => setLeaderboardOpen(false));

    async function refresh() {
        const data = await api('/dashboard/overview', {
            method: 'POST',
            signal: withAbort(req),
            body: JSON.stringify({ team: team.value, site: site.value, year: Number(year.value) }),
        });
        const k = data.kpis;
        document.getElementById('overview-kpis').innerHTML = [
            kpiCard('Event This Week', k.eventThisWeek, 'green', 'Event pada minggu berjalan'),
            kpiCard('Upcoming Event', k.upcomingEvent, 'blue', 'Mulai minggu depan'),
            kpiCard('Leave This Week', k.leaveThisWeek, 'red', 'Leave yang beririsan minggu ini'),
            kpiCard('Upcoming Leave', k.upcomingLeave, 'orange', 'Leave setelah minggu berjalan'),
            kpiCard('Project Active', k.projectActive, 'green', 'Project On Going dan Overdue'),
            kpiCard('Issue Active', k.issueActive, 'red', 'Issue On Going dan Overdue'),
        ].join('');

        document.getElementById('dashboard-period').textContent =
            `Tahun ${data.year} • Minggu acuan: ${data.windows.thisWeekStart} s/d ${data.windows.thisWeekEnd}`;

        eventGroupData.thisWeek = data.eventStatus.thisWeek || [];
        eventGroupData.nextWeek = data.eventStatus.nextWeek || [];
        eventGroupData.nextTwoWeek = data.eventStatus.nextTwoWeek || [];
        eventGroupData.moreThanTwoWeeks = data.eventStatus.moreThanTwoWeeks || [];
        page.querySelector('[data-event-group="thisWeek"] [data-period]').textContent = `${data.windows.thisWeekStart} - ${data.windows.thisWeekEnd}`;
        page.querySelector('[data-event-group="nextWeek"] [data-period]').textContent = `${data.windows.nextWeekStart} - ${data.windows.nextWeekEnd}`;
        page.querySelector('[data-event-group="nextTwoWeek"] [data-period]').textContent = `${data.windows.nextTwoWeekStart} - ${data.windows.nextTwoWeekEnd}`;
        ['thisWeek', 'nextWeek', 'nextTwoWeek', 'moreThanTwoWeeks'].forEach(renderEventGroup);

        leaveGroupData.thisWeek = data.leaveStatus.thisWeek || [];
        leaveGroupData.upcoming = data.leaveStatus.upcoming || [];
        ['thisWeek', 'upcoming'].forEach(renderLeaveGroup);

        leaderboardCache = data.leaderboard || [];
        document.getElementById('leaderboard-note').textContent = `Leave days & effective working days ${data.year} • weekend dan holiday tidak dihitung`;
        renderLeaderboardList();

        const tc = { onGoing: 0, overdue: 0, closed: 0 };
        (data.trackerHighlights || []).forEach((t) => {
            if (t.EffectiveStatus === 'On Going') tc.onGoing++;
            else if (t.EffectiveStatus === 'Overdue') tc.overdue++;
            else if (t.EffectiveStatus === 'Closed') tc.closed++;
        });
        document.getElementById('overview-tracker-counts').innerHTML = `${statChip('On Going', tc.onGoing)}${statChip('Overdue', tc.overdue)}${statChip('Closed', tc.closed)}`;
        ensureTrackerTable().setRows(data.trackerHighlights || []);
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

    function parseISODate(iso) {
        const [y, m, d] = String(iso || '').split('-').map(Number);
        return new Date(y || 1970, (m || 1) - 1, d || 1);
    }

    function formatDateShort(iso) {
        return parseISODate(iso).toLocaleDateString('en-US', { day: '2-digit', month: 'short' });
    }

    function rangeTitle(data) {
        if (state.viewMode === 'YEAR') return String(parseISODate(data.rangeStart).getFullYear());
        if (state.viewMode === 'MONTH') return parseISODate(data.rangeStart).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
        return formatDateShort(data.rangeStart) + ' - ' + formatDateShort(data.rangeEnd);
    }

    function assignLanes(items) {
        const lanes = [];
        return items
            .slice()
            .sort((a, b) => a.startIndex - b.startIndex || a.endIndex - b.endIndex)
            .map((item) => {
                let lane = lanes.findIndex((lastEnd) => lastEnd < item.startIndex);
                if (lane < 0) { lane = lanes.length; lanes.push(item.endIndex); }
                else { lanes[lane] = item.endIndex; }
                return Object.assign({}, item, { lane });
            });
    }

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
        const holidays = data.holidays || {};
        const counts = data.counts || {};

        document.getElementById('calendar-status').textContent =
            `Integrated Calendar • ${counts.events || 0} event • ${counts.projects || 0} project • ${counts.issues || 0} issue assignment • ${counts.leaveEmployees || 0} employee memiliki leave • ${counts.actingTransfers || 0} temporary Event/Project/Issue handover ke Backup PIC. Leave YTD tidak menghitung Sabtu, Minggu, dan hari libur.`;
        document.getElementById('cal-range-title').textContent = rangeTitle(data);

        const cols = data.cols || [];
        const colCount = cols.length;
        const colMin = state.viewMode === 'MONTH' ? 150 : state.viewMode === 'YEAR' ? 105 : 90;
        const template = `260px repeat(${colCount}, minmax(${colMin}px, 1fr))`;

        const headCells = cols.map((col) => {
            if (state.viewMode === 'WEEK') {
                const d = parseISODate(col.key);
                const isWeekend = d.getDay() === 0 || d.getDay() === 6;
                const holidayName = holidays[col.key];
                return `<div class="ohs-cal-head-cell${isWeekend ? ' ohs-weekend' : ''}${holidayName ? ' ohs-holiday' : ''}"><b>${d.toLocaleDateString('en-US', { weekday: 'short' })}</b><div class="hint">${d.getDate()}${holidayName ? ' • ' + escapeHtml(holidayName) : ''}</div></div>`;
            }
            return `<div class="ohs-cal-head-cell"><b>${escapeHtml(col.label)}</b><div class="hint">${escapeHtml(formatDateShort(col.start))} - ${escapeHtml(formatDateShort(col.end))}</div></div>`;
        }).join('');
        const headEl = document.getElementById('calendar-head');
        headEl.style.minWidth = (260 + colCount * colMin) + 'px';
        headEl.innerHTML = `<div class="ohs-cal-head-row" style="grid-template-columns:${template}"><div class="ohs-sticky-left ohs-name-head">Employee / PIC</div>${headCells}</div>`;

        const gridEl = document.getElementById('calendar-grid');
        gridEl.style.minWidth = (260 + colCount * colMin) + 'px';

        if (!data.rows.length) {
            gridEl.innerHTML = '<div class="ohs-empty">Tidak ada Leave, Event, Project, atau Issue untuk filter dan periode ini.</div>';
            return;
        }

        const groups = [];
        const groupIndex = {};
        data.rows.forEach((row) => {
            const key = row.employee.Team || 'OTHER';
            if (!(key in groupIndex)) {
                groupIndex[key] = groups.length;
                groups.push({ label: row.employee.Team || 'Other', rows: [] });
            }
            groups[groupIndex[key]].rows.push(row);
        });

        gridEl.innerHTML = groups.map((group) => {
            const groupRow = `<div class="ohs-team-row" style="grid-template-columns:${template}">
                <div class="ohs-team-cell ohs-sticky-left">${escapeHtml(group.label)} • ${group.rows.length} item</div>
                <div class="ohs-team-cell" style="grid-column:2 / span ${colCount}"></div>
            </div>`;
            const bodyRows = group.rows.map((row) => {
                const mapped = (row.items || []).map((item) => {
                    let startIndex = -1;
                    let endIndex = -1;
                    cols.forEach((col, idx) => {
                        if (item.start <= col.end && item.end >= col.start) {
                            if (startIndex < 0) startIndex = idx;
                            endIndex = idx;
                        }
                    });
                    return startIndex >= 0 ? Object.assign({}, item, { startIndex, endIndex }) : null;
                }).filter(Boolean);
                const placed = assignLanes(mapped);
                const laneCount = placed.reduce((max, it) => Math.max(max, it.lane + 1), 1);
                const events = placed.map((item) => {
                    const cat = String(item.category || 'LEAVE').toLowerCase().split('-')[0];
                    const d = item.data || {};
                    const id = d.RequestId || d.EventId || d.TrackerId || d.SubTaskId || '';
                    const tip = item.detail || (item.title + '\n' + item.start + ' - ' + item.end);
                    return `<div class="ohs-calendar-event ${cat}${item.acting ? ' acting' : ''}" style="grid-column:${item.startIndex + 1} / ${item.endIndex + 2};grid-row:${item.lane + 1}" data-cat="${escapeHtml(item.category)}" data-id="${escapeHtml(id)}" title="${escapeHtml(tip)}">${escapeHtml(item.title)}</div>`;
                }).join('');
                return `<div class="ohs-calendar-row" style="grid-template-columns:${template}">
                    <div class="ohs-calendar-row-label ohs-sticky-left">
                        <div class="ohs-calendar-row-name">${escapeHtml(row.employee.EmpName || '-')}</div>
                        <div class="ohs-calendar-row-meta">${escapeHtml((row.employee.Position || '') + ' · ' + (row.employee.SiteDedicated || ''))}</div>
                        ${row.chip ? `<div class="ohs-calendar-row-chip">${escapeHtml(row.chip)}</div>` : ''}
                    </div>
                    <div class="ohs-lane-wrap" style="grid-column:2 / span ${colCount}">
                        <div class="ohs-lane-grid" style="grid-template-columns:repeat(${colCount}, minmax(${colMin}px,1fr));grid-template-rows:repeat(${laneCount}, 27px)">${events}</div>
                    </div>
                </div>`;
            }).join('');
            return groupRow + bodyRows;
        }).join('');

        gridEl.querySelectorAll('.ohs-calendar-event[data-id]').forEach((chip) => {
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

const EVENT_STATUS_ORDER = [
    ['This Week', 'This-Week'],
    ['Next Week', 'Next-Week'],
    ['Next 2 Week', 'Next-2-Week'],
    ['More Than 2 Weeks Ahead', 'More-Than-2-Weeks-Ahead'],
    ['Previous Event', 'Previous-Event'],
];

function initEvents() {
    const page = document.querySelector('[data-ohs-page="events"]');
    if (!page) return;
    const team = document.getElementById('ev-team');
    const site = document.getElementById('ev-site');
    let table = null;
    let events = [];

    const evReq = {};

    function ensureTable() {
        if (table) return table;
        table = createSortableTable(document.getElementById('event-table-mount'), {
            pageSize: 10,
            rows: [],
            emptyText: 'Belum ada event',
            columns: [
                {
                    key: 'status', label: 'Status', sortValue: (ev) => EVENT_STATUS_ORDER.findIndex(([label]) => label === ev.ScheduleStatus),
                    render: (ev) => badge(ev.ScheduleStatus),
                },
                {
                    key: 'name', label: 'Event', sortValue: (ev) => ev.EventName, searchValue: (ev) => ev.EventName + ' ' + (ev.Description || ''),
                    render: (ev) => `<div class="ohs-person"><strong>${escapeHtml(ev.EventName)}</strong><span>${escapeHtml((ev.Description || '').slice(0, 80))}</span></div>`,
                },
                { key: 'date', label: 'Date', sortValue: (ev) => ev.EventDate || '', render: (ev) => escapeHtml(ev.EventDate || '-') },
                {
                    key: 'pic', label: 'PIC / Team / Site', sortValue: (ev) => ev.PICName || '', searchValue: (ev) => [ev.PICName, ev.PICTeam, ev.PICSiteDedicated].join(' '),
                    render: (ev) => personCell(ev.PICName, (ev.PICTeam || '') + ' · ' + (ev.PICSiteDedicated || '')),
                },
                { key: 'where', label: 'Where', sortValue: (ev) => ev.Where || '', render: (ev) => escapeHtml(ev.Where || '-') },
                { key: 'readiness', label: 'Update Kesiapan', sortValue: (ev) => ev.ReadinessUpdate || '', render: (ev) => escapeHtml(ev.ReadinessUpdate || '-') },
                { key: 'lastupdate', label: 'Last Update', sortValue: (ev) => ev.ReadinessUpdatedAt || '', render: (ev) => escapeHtml(ev.ReadinessUpdatedAt || '-') },
                {
                    key: 'action', label: 'Action', sortable: false, searchable: false,
                    render: (ev) => `<div class="ohs-row-actions">
                        <button class="btn-ghost" data-act="edit" data-id="${escapeHtml(ev.EventId)}">Edit</button>
                        <button class="btn-ghost" data-act="ready" data-id="${escapeHtml(ev.EventId)}">Update Kesiapan</button>
                        <button class="btn-ghost" data-act="qr" data-id="${escapeHtml(ev.EventId)}">QR Absensi</button>
                        <button class="btn-ghost" data-act="min" data-id="${escapeHtml(ev.EventId)}">Notulensi</button>
                        <button class="btn-danger" data-act="del" data-id="${escapeHtml(ev.EventId)}">Hapus</button>
                    </div>`,
                },
            ],
            onRender: (pageRows, mount) => {
                mount.querySelectorAll('button[data-act]').forEach((btn) => btn.addEventListener('click', () => runSafe(onEventAction(btn.dataset.act, btn.dataset.id, events.find((e) => e.EventId === btn.dataset.id)))));
            },
        });
        return table;
    }

    async function refresh() {
        const data = await api('/events/maker-data', {
            method: 'POST',
            signal: withAbort(evReq),
            body: JSON.stringify({ team: team.value, site: site.value }),
        });
        events = data.events || [];
        const c = data.counts || {};
        document.getElementById('event-badges').innerHTML = EVENT_STATUS_ORDER.map(([label, cls]) => `<span class="badge ${cls}">${escapeHtml(label)}: ${c[label] || 0}</span>`).join('');
        ensureTable().setRows(events);
    }

    function eventForm(ev) {
        openModal(ev ? 'Edit Event' : 'Create Event', `
            <form id="event-form" class="ohs-form">
                <label>Nama Event<input name="EventName" required></label>
                <label>Tanggal Event<input type="date" name="EventDate" required></label>
                <label>Where<input name="Where" required></label>
                <div class="emp-box full"><label>PIC<input type="search"><input type="hidden" name="PICEmpId"><ul class="ohs-search-list"></ul></label></div>
                <label class="full">Deskripsi Event<textarea name="Description" required></textarea></label>
                <p class="hint full">Update Kesiapan diisi setelah event tersimpan, lewat tombol "Update Kesiapan" pada baris event.</p>
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

    function openEventQr(id) {
        const url = `${location.origin}/ohs-dashboard/checkin?eventId=${encodeURIComponent(id)}`;
        openModal('QR Absensi', `
            <div class="ohs-qr">
                <img alt="QR" src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${encodeURIComponent(url)}">
                <p><a href="${escapeHtml(url)}" target="_blank" rel="noopener">${escapeHtml(url)}</a></p>
                <button type="button" class="btn-primary" id="copy-qr">Copy link</button>
            </div>
            <h3 style="margin-top:18px;">Daftar Hadir</h3>
            <p class="ohs-muted" id="qr-att-summary">Memuat…</p>
            <div class="ohs-table-wrap"><table class="ohs-table"><thead><tr><th>Nama</th><th>Check-in</th></tr></thead><tbody id="qr-att-body"></tbody></table></div>
            <button type="button" class="btn-ghost" id="qr-att-refresh" style="margin-top:10px;">Refresh Daftar Hadir</button>`, (modal) => {
            modal.querySelector('#copy-qr').onclick = () => navigator.clipboard.writeText(url);
            const loadAttendance = () => runSafe((async () => {
                const data = await api('/events/attendance?eventId=' + encodeURIComponent(id), { silent: true });
                modal.querySelector('#qr-att-summary').textContent = `${data.attendanceCount || 0} peserta sudah check-in`;
                modal.querySelector('#qr-att-body').innerHTML = (data.attendance || []).length
                    ? data.attendance.map((a) => `<tr><td>${escapeHtml(a.EmpName)}</td><td>${escapeHtml(a.CheckInAt)}</td></tr>`).join('')
                    : emptyCell(2, 'Belum ada absensi');
            })());
            modal.querySelector('#qr-att-refresh').onclick = loadAttendance;
            loadAttendance();
        });
    }

    function renderActionItems(modal, id, items) {
        const list = modal.querySelector('#ai-list');
        list.innerHTML = items.length ? items.map((i) => `<div class="ohs-ai-item"><div><strong>${escapeHtml(i.Task)}</strong><div class="ohs-muted">${escapeHtml(i.PICName || 'Tanpa PIC')} · ${escapeHtml(i.DueDate || '-')}</div></div>${badge(i.Status)} <button type="button" class="btn-ghost action-item-status-btn" data-ai="${escapeHtml(i.ActionItemId)}" data-st="${i.Status === 'Open' ? 'Done' : 'Open'}">Toggle</button></div>`).join('') : '<p class="ohs-muted">Belum ada action item</p>';
        list.querySelectorAll('[data-ai]').forEach((b) => b.onclick = () => runSafe((async () => {
            const updated = await api('/events/action-items/status', { method: 'POST', body: JSON.stringify({ EventId: id, ActionItemId: b.dataset.ai, Status: b.dataset.st }) });
            renderActionItems(modal, id, updated.actionItems || []);
        })()));
    }

    function openEventMinutes(id) {
        runSafe((async () => {
            const data = await api('/events/minutes?eventId=' + encodeURIComponent(id));
            openModal('Notulensi', `
                <p class="ohs-muted" id="min-updated">${data.updatedAt ? 'Terakhir diperbarui ' + escapeHtml(data.updatedAt) + ' oleh ' + escapeHtml(data.updatedByName || '-') : 'Belum pernah diperbarui.'}</p>
                <form id="min-form" class="ohs-form">
                    <label>Summary<textarea name="Summary">${escapeHtml(data.summary || '')}</textarea></label>
                    <div class="emp-box"><label>Updated By<input type="search"><input type="hidden" name="UpdatedByEmpId"><ul class="ohs-search-list"></ul></label></div>
                    <button class="btn-primary">Simpan notulensi</button>
                </form>
                <h3 style="margin-top:18px;">Action Items</h3>
                <div id="ai-list"></div>
                <form id="ai-form" class="ohs-form" style="margin-top:10px;">
                    <input name="Task" placeholder="Task" required>
                    <div class="emp-box"><label>PIC<input type="search"><input type="hidden" name="PICEmpId"><ul class="ohs-search-list"></ul></label></div>
                    <input type="date" name="DueDate">
                    <button class="btn-primary">Tambah</button>
                </form>`, (modal) => {
                employeePicker(modal.querySelectorAll('.emp-box')[0], 'UpdatedByEmpId');
                employeePicker(modal.querySelectorAll('.emp-box')[1], 'PICEmpId');
                renderActionItems(modal, id, data.actionItems || []);
                modal.querySelector('#min-form').onsubmit = (e) => {
                    e.preventDefault();
                    runSafe((async () => {
                        try {
                            const updated = await api('/events/minutes', { method: 'POST', body: JSON.stringify({ EventId: id, ...formObject(e.target, null) }) });
                            modal.querySelector('#min-updated').textContent = 'Terakhir diperbarui ' + updated.updatedAt + ' oleh ' + (updated.updatedByName || '-');
                            toast('Notulensi tersimpan.', 'ok');
                        } catch (err) { toast(err.message); }
                    })());
                };
                modal.querySelector('#ai-form').onsubmit = (e) => {
                    e.preventDefault();
                    const form = e.target;
                    runSafe((async () => {
                        try {
                            const updated = await api('/events/action-items/add', { method: 'POST', body: JSON.stringify({ EventId: id, ...formObject(form, null) }) });
                            renderActionItems(modal, id, updated.actionItems || []);
                            form.reset();
                        } catch (err) { toast(err.message); }
                    })());
                };
            });
        })());
    }

    async function onEventAction(act, id, ev) {
        if (act === 'edit') return eventForm(ev);
        if (act === 'del') {
            if (!confirm('Hapus event beserta absensi, notulensi, dan action item?')) return;
            await api('/events/delete', { method: 'POST', body: JSON.stringify({ EventId: id }) });
            refresh();
            return;
        }
        if (act === 'ready') {
            openModal('Update Kesiapan', `
                <div class="ohs-summary-box" style="margin-bottom:12px;">
                    <div class="ohs-summary-label">Kesiapan Saat Ini</div>
                    <div class="ohs-summary-value" style="font-size:13px;white-space:pre-wrap;">${escapeHtml(ev?.ReadinessUpdate || 'Belum ada update kesiapan.')}</div>
                </div>
                <form id="ready-form" class="ohs-form">
                    <label>Update Kesiapan Baru<textarea name="ReadinessUpdate" required></textarea></label>
                    <button class="btn-primary">Simpan</button>
                </form>`, (modal) => {
                modal.querySelector('form').onsubmit = async (e) => {
                    e.preventDefault();
                    try {
                        await api('/events/readiness', { method: 'POST', body: JSON.stringify({ EventId: id, ReadinessUpdate: e.target.ReadinessUpdate.value }) });
                        closeModal(); refresh();
                    } catch (err) { toast(err.message); }
                };
            });
            return;
        }
        if (act === 'qr') return openEventQr(id);
        if (act === 'min') return openEventMinutes(id);
    }

    loadInit().then((init) => {
        fillSelect(team, init.teams, 'All Teams');
        fillSelect(site, init.sites, 'All Sites');
        return refresh();
    }).catch((e) => toast(e.message));
    team.onchange = site.onchange = () => runSafe(refresh());
    document.getElementById('btn-create-event').onclick = () => eventForm(null);
}

function trackerStatusBadgeClass(status) {
    if (status === 'Closed') return 'closed';
    if (status === 'Overdue') return 'overdue';
    return 'on-going';
}

function trackerInlineSummaryBox(label, value) {
    return `<div class="ohs-summary-box"><div class="ohs-summary-label">${escapeHtml(label)}</div><div class="ohs-summary-value" style="font-size:13px;">${escapeHtml(value || '-')}</div></div>`;
}

function trackerInlineUpdateForm(kind, tracker, task) {
    const source = task || tracker;
    const entityId = task ? task.SubTaskId : tracker.TrackerId;
    const selName = task ? (task.PICName || tracker.ProjectLeaderName || '') : (tracker.ProjectLeaderName || '');
    const selId = task ? (task.PICEmpId || tracker.ProjectLeaderEmpId || '') : (tracker.ProjectLeaderEmpId || '');
    const buttonText = task ? 'Save Sub Task Update' : 'Save Overall Update';
    return `
        <div class="ohs-tracker-inline-form" data-inline-kind="${escapeHtml(kind)}" data-entity-id="${escapeHtml(entityId)}">
            <div class="field"><label>% Complete</label><input class="inline-percent" type="number" min="0" max="100" step="1" value="${escapeHtml(source.CurrentPercentComplete || 0)}"></div>
            <div class="field"><label>Progress Report Weekly</label><textarea class="inline-progress" placeholder="Milestone, evidence, kendala, dan next action."></textarea></div>
            <div class="field"><label>Keterangan</label><textarea class="inline-remarks" placeholder="Keputusan, risiko, support, atau catatan penting."></textarea></div>
            <div class="field emp-box"><label>Updated By</label><input type="search" value="${escapeHtml(selName)}"><input type="hidden" name="UpdatedByEmpId" value="${escapeHtml(selId)}"><ul class="ohs-search-list"></ul></div>
            <div class="field"><button type="button" class="btn-primary tracker-inline-save">${escapeHtml(buttonText)}</button></div>
        </div>
        <div class="ohs-tracker-inline-message hide"></div>`;
}

function trackerInlineLogBox(kind, id) {
    return `
        <div class="ohs-tracker-inline-log-box" data-log-box>
            <div class="ohs-tracker-inline-log-head">
                <div><b>Immutable Update Log</b><div class="hint">Histori sebelumnya read-only dan tidak dapat diedit.</div></div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <span class="badge log-count">Belum dimuat</span>
                    <button type="button" class="btn-ghost tracker-log-toggle" data-log-kind="${escapeHtml(kind)}" data-log-id="${escapeHtml(id)}">Show Log</button>
                </div>
            </div>
            <div class="ohs-tracker-inline-log-content hide">
                <div class="ohs-table-wrap">
                    <table class="ohs-table"><thead><tr><th>Timestamp</th><th>% Complete</th><th>Status</th><th>Weekly Report</th><th>Keterangan</th><th>Updated By</th></tr></thead>
                    <tbody class="log-body"><tr><td colspan="6"><div class="ohs-empty">Klik Show Log untuk memuat histori.</div></td></tr></tbody></table>
                </div>
            </div>
        </div>`;
}

function trackerInlineSubTaskHtml(task, tracker) {
    const percent = Number(task.CurrentPercentComplete || 0);
    return `
        <div class="ohs-tracker-inline-task" data-task-id="${escapeHtml(task.SubTaskId)}">
            <button type="button" class="ohs-tracker-inline-task-toggle" data-subtask-toggle="${escapeHtml(task.SubTaskId)}">
                <div><div class="ohs-tracker-inline-task-name">${escapeHtml(task.SubTaskName)}</div><div class="ohs-person-sub">${escapeHtml(task.SubTaskId)}</div></div>
                <div><b>${escapeHtml(task.PICName || task.PICEmpId || '-')}</b><div class="ohs-person-sub">${escapeHtml([task.Department, task.Site].filter(Boolean).join(' • '))}</div></div>
                <div>${escapeHtml(task.StartDate || '-')}<br>→ ${escapeHtml(task.DueDate || '-')}</div>
                <div><div class="ohs-progress-track" style="width:90px;"><div class="ohs-progress-fill" style="width:${Math.max(0, Math.min(100, percent))}%"></div></div><div class="ohs-progress-value">${escapeHtml(percent)}%</div></div>
                <div style="display:flex;align-items:center;justify-content:flex-end;gap:8px;">
                    <span class="ohs-tracker-status ${trackerStatusBadgeClass(task.EffectiveStatus)}">${escapeHtml(task.EffectiveStatus)}</span>
                    <span class="subtask-toggle-symbol" data-symbol>＋</span>
                </div>
            </button>
            <div class="ohs-tracker-inline-task-body hide" data-task-body>
                <div class="ohs-tracker-inline-task-meta">
                    ${trackerInlineSummaryBox('Description', task.DescriptionSubTask)}
                    ${trackerInlineSummaryBox('Success Indicator', task.SuccessIndicator)}
                    ${trackerInlineSummaryBox('Latest Weekly Report', task.CurrentProgressReportWeekly)}
                    ${trackerInlineSummaryBox('Latest Keterangan', task.CurrentRemarks)}
                </div>
                ${trackerInlineUpdateForm('subtask', tracker, task)}
                ${trackerInlineLogBox('subtask', task.SubTaskId)}
            </div>
        </div>`;
}

function trackerInlinePanelHtml(tracker) {
    const tasks = tracker.SubTasks || [];
    const percent = Number(tracker.CurrentPercentComplete || 0);
    const trackingMode = tasks.length ? `${tasks.length} Sub Task` : 'Overall only — tanpa Sub Task';
    const contextHtml = [
        ['Description', tracker.DescriptionProject],
        ['Background', tracker.BackgroundProject],
        ['Impact', tracker.ImpactProject],
        ['Success Indicator', tracker.SuccessIndicator],
    ].map(([label, value]) => `<div class="ohs-tracker-inline-context-item"><b>${escapeHtml(label)}</b>${escapeHtml(value || '-')}</div>`).join('');

    const updateArea = tasks.length
        ? `<div class="ohs-tracker-inline-section">
            <div class="ohs-tracker-inline-section-head">
                <div><b>Sub Task Progress &amp; Update Log</b><div class="hint">Klik setiap Sub Task untuk membuka form update dan immutable log.</div></div>
                <span class="badge">${tasks.length} Sub Task</span>
            </div>
            <div class="ohs-tracker-inline-section-body"><div class="ohs-tracker-inline-task-list">${tasks.map((t) => trackerInlineSubTaskHtml(t, tracker)).join('')}</div></div>
          </div>`
        : `<div class="ohs-tracker-inline-section">
            <div class="ohs-tracker-inline-section-head">
                <div><b>Overall Progress Update</b><div class="hint">Tracker ini tidak memiliki Sub Task. Progress dikelola langsung pada level parent.</div></div>
                <span class="ohs-tracker-no-subtask-label">NO SUB TASK</span>
            </div>
            <div class="ohs-tracker-inline-section-body">${trackerInlineUpdateForm('parent', tracker, null)}${trackerInlineLogBox('parent', tracker.TrackerId)}</div>
          </div>`;

    return `
        <div class="ohs-tracker-inline-panel">
            <div class="ohs-tracker-inline-header">
                <div><div class="ohs-tracker-inline-title">${escapeHtml(tracker.TrackerType + ' — ' + tracker.ProjectIssueName)}</div><div class="hint">${escapeHtml(tracker.TrackerId)} • ${escapeHtml(trackingMode)}</div></div>
                <span class="ohs-tracker-status ${trackerStatusBadgeClass(tracker.EffectiveStatus)}">${escapeHtml(tracker.EffectiveStatus)}</span>
            </div>
            <div class="ohs-tracker-inline-overview">
                ${trackerInlineSummaryBox('Project Leader', tracker.ProjectLeaderName || tracker.ProjectLeaderEmpId)}
                ${trackerInlineSummaryBox('Department / Site', [tracker.Department, tracker.Site].filter(Boolean).join(' • '))}
                ${trackerInlineSummaryBox('Timeline', (tracker.StartDate || '-') + ' → ' + (tracker.DueDate || '-'))}
                ${trackerInlineSummaryBox('Overall Progress', percent + '%')}
            </div>
            <div class="ohs-tracker-inline-context">${contextHtml}</div>
            ${updateArea}
        </div>`;
}

function initTracker() {
    const page = document.querySelector('[data-ohs-page="tracker"]');
    if (!page) return;
    let cache = [];
    let table = null;
    let reopenTrackerId = '';
    let reopenSubTaskId = '';
    const trReq = {};
    const expandedIds = new Set();

    function findTracker(id) {
        return cache.find((t) => t.TrackerId === id);
    }

    function bindInlinePanel(panelRoot, tracker) {
        panelRoot.querySelectorAll('.emp-box').forEach((box) => employeePicker(box, 'UpdatedByEmpId'));

        panelRoot.querySelectorAll('[data-subtask-toggle]').forEach((btn) => {
            const shouldOpen = reopenSubTaskId && btn.dataset.subtaskToggle === reopenSubTaskId;
            btn.addEventListener('click', () => toggleInlineSubTask(btn));
            if (shouldOpen) toggleInlineSubTask(btn, true);
        });

        panelRoot.querySelectorAll('.tracker-inline-save').forEach((btn) => {
            btn.addEventListener('click', () => runSafe(saveInlineUpdate(btn, tracker)));
        });

        panelRoot.querySelectorAll('.tracker-log-toggle').forEach((btn) => {
            btn.addEventListener('click', () => runSafe(toggleInlineLog(btn)));
        });

        reopenSubTaskId = '';
    }

    function toggleInlineSubTask(button, forceOpen) {
        const body = button.closest('.ohs-tracker-inline-task').querySelector('[data-task-body]');
        const symbol = button.querySelector('[data-symbol]');
        const isHidden = body.classList.contains('hide');
        const shouldOpen = forceOpen === true ? true : isHidden;
        body.classList.toggle('hide', !shouldOpen);
        if (symbol) symbol.textContent = shouldOpen ? '−' : '＋';
    }

    async function saveInlineUpdate(button, tracker) {
        const form = button.closest('[data-inline-kind]');
        const kind = form.dataset.inlineKind;
        const entityId = form.dataset.entityId;
        const percent = Number(form.querySelector('.inline-percent').value);
        const weekly = form.querySelector('.inline-progress').value;
        const remarks = form.querySelector('.inline-remarks').value;
        const updatedBy = form.querySelector('input[name="UpdatedByEmpId"]').value;
        const msgBox = form.nextElementSibling;

        const setMsg = (text, tone) => {
            msgBox.textContent = text;
            msgBox.classList.remove('hide', 'success', 'error');
            if (tone) msgBox.classList.add(tone);
        };

        if (!Number.isFinite(percent) || percent < 0 || percent > 100) return setMsg('% Complete harus berupa angka 0 sampai 100.', 'error');
        if (!weekly.trim()) return setMsg('Progress Report Weekly wajib diisi.', 'error');
        if (!remarks.trim()) return setMsg('Keterangan wajib diisi.', 'error');
        if (!updatedBy) return setMsg('Updated By wajib dipilih.', 'error');

        button.disabled = true;
        setMsg('Menyimpan...');
        try {
            if (kind === 'parent') {
                await api('/tracker/update', { method: 'POST', body: JSON.stringify({ TrackerId: entityId, PercentComplete: percent, ProgressReportWeekly: weekly, Remarks: remarks, UpdatedByEmpId: updatedBy }) });
            } else {
                await api('/tracker/update-subtask', { method: 'POST', body: JSON.stringify({ SubTaskId: entityId, PercentComplete: percent, ProgressReportWeekly: weekly, Remarks: remarks, UpdatedByEmpId: updatedBy }) });
            }
            reopenTrackerId = tracker.TrackerId;
            reopenSubTaskId = kind === 'subtask' ? entityId : '';
            expandedIds.add(tracker.TrackerId);
            await refresh();
        } catch (err) {
            button.disabled = false;
            setMsg('Gagal menyimpan update: ' + err.message, 'error');
        }
    }

    async function toggleInlineLog(button) {
        const box = button.closest('[data-log-box]');
        const content = box.querySelector('.ohs-tracker-inline-log-content');
        const isHidden = content.classList.contains('hide');
        content.classList.toggle('hide', !isHidden);
        button.textContent = isHidden ? 'Hide Log' : 'Show Log';
        if (!isHidden || box.dataset.loaded === '1') return;

        const body = box.querySelector('.log-body');
        const count = box.querySelector('.log-count');
        body.innerHTML = '<tr><td colspan="6"><div class="ohs-empty">Loading immutable log…</div></td></tr>';
        count.textContent = 'Loading…';
        try {
            const kind = button.dataset.logKind;
            const id = button.dataset.logId;
            const data = kind === 'parent'
                ? await api('/tracker/log?trackerId=' + encodeURIComponent(id), { silent: true })
                : await api('/tracker/subtask-log?subTaskId=' + encodeURIComponent(id), { silent: true });
            const logs = data.logs || [];
            count.textContent = logs.length + ' update';
            body.innerHTML = logs.length ? logs.map((l) => `<tr>
                <td><b>${escapeHtml(l.Timestamp || '-')}</b><div class="ohs-person-sub">${escapeHtml(l.UpdateId || '')}</div></td>
                <td><b>${escapeHtml(l.PercentComplete)}%</b></td>
                <td><span class="ohs-tracker-status ${trackerStatusBadgeClass(l.Status)}">${escapeHtml(l.Status || '-')}</span></td>
                <td>${escapeHtml(l.ProgressReportWeekly || '-')}</td>
                <td>${escapeHtml(l.Remarks || '-')}</td>
                <td>${escapeHtml(l.UpdatedByName || l.UpdatedByEmpId || '-')}<div class="ohs-person-sub">${escapeHtml([l.UpdatedByPosition, l.UpdatedByTeam, l.UpdatedBySiteDedicated].filter(Boolean).join(' • '))}</div></td>
            </tr>`).join('') : '<tr><td colspan="6"><div class="ohs-empty">Belum ada update log.</div></td></tr>';
            box.dataset.loaded = '1';
        } catch (err) {
            body.innerHTML = `<tr><td colspan="6"><div class="ohs-empty">Gagal mengambil log: ${escapeHtml(err.message)}</div></td></tr>`;
            count.textContent = 'Error';
        }
    }

    function ensureTable() {
        if (table) return table;
        table = createSortableTable(document.getElementById('tracker-table-mount'), {
            pageSize: 10,
            rows: [],
            emptyText: 'Belum ada tracker',
            rowClass: (t) => t.EffectiveStatus === 'Closed' ? 'closed' : t.EffectiveStatus === 'Overdue' ? 'overdue' : '',
            columns: [
                {
                    key: 'type', label: 'Type / ID', sortValue: (t) => t.TrackerType, searchValue: (t) => t.TrackerType + ' ' + t.TrackerId,
                    render: (t) => `<span class="ohs-tracker-type">${escapeHtml(t.TrackerType)}</span><div class="ohs-person-sub">${escapeHtml(t.TrackerId)}</div>`,
                },
                {
                    key: 'name', label: 'Project / Issue', sortValue: (t) => t.ProjectIssueName, searchValue: (t) => t.ProjectIssueName + ' ' + (t.DescriptionProject || ''),
                    render: (t) => `<b>${escapeHtml(t.ProjectIssueName)}</b><div class="ohs-person-sub">${escapeHtml(t.DescriptionProject || '-')}</div>`,
                },
                {
                    key: 'dept', label: 'Department / Site', sortValue: (t) => t.Department, searchValue: (t) => (t.Department || '') + ' ' + (t.Site || ''),
                    render: (t) => `${escapeHtml(t.Department || '-')}<div class="ohs-person-sub">${escapeHtml(t.Site || '-')}</div>`,
                },
                {
                    key: 'leader', label: 'Project Leader', sortValue: (t) => t.ProjectLeaderName, searchValue: (t) => t.ProjectLeaderName || '',
                    render: (t) => `${escapeHtml(t.ProjectLeaderName || t.ProjectLeaderEmpId || '-')}<div class="ohs-person-sub">${escapeHtml([t.ProjectLeaderPosition, t.ProjectLeaderTeam].filter(Boolean).join(' • '))}</div>`,
                },
                {
                    key: 'timeline', label: 'Timeline', sortValue: (t) => t.DueDate || '', searchValue: (t) => (t.StartDate || '') + ' ' + (t.DueDate || ''),
                    render: (t) => `${escapeHtml(t.StartDate || '-')}<br>→ ${escapeHtml(t.DueDate || '-')}`,
                },
                {
                    key: 'subtasks', label: 'Sub Tasks / PIC', sortable: false,
                    searchValue: (t) => (t.SubTasks || []).map((s) => s.SubTaskName + ' ' + (s.PICName || '')).join(' '),
                    render: (t) => {
                        const tasks = t.SubTasks || [];
                        if (!tasks.length) return `<span class="ohs-tracker-no-subtask-label">NO SUB TASK</span><div class="ohs-person-sub" style="margin-top:5px;">Overall progress tracking</div>`;
                        const chips = tasks.slice(0, 4).map((s) => `<span class="ohs-subtask-chip">${escapeHtml(s.SubTaskName)} • ${escapeHtml(s.PICName || s.PICEmpId || '-')} • ${escapeHtml(s.CurrentPercentComplete)}%</span>`).join('');
                        return `<b>${tasks.length} Sub Task</b><div class="ohs-subtask-summary">${chips}</div>${tasks.length > 4 ? `<div class="ohs-person-sub">+${tasks.length - 4} lainnya</div>` : ''}`;
                    },
                },
                {
                    key: 'percent', label: '% Complete', sortValue: (t) => Number(t.CurrentPercentComplete || 0),
                    render: (t) => progressBar(t.CurrentPercentComplete),
                },
                {
                    key: 'status', label: 'Status', sortValue: (t) => t.EffectiveStatus === 'Overdue' ? 0 : t.EffectiveStatus === 'On Going' ? 1 : 2,
                    render: (t) => `<span class="ohs-tracker-status ${trackerStatusBadgeClass(t.EffectiveStatus)}">${escapeHtml(t.EffectiveStatus)}</span>`,
                },
                {
                    key: 'weekly', label: 'Latest Weekly Report', sortValue: (t) => t.CurrentProgressReportWeekly || '',
                    render: (t) => `${escapeHtml(t.CurrentProgressReportWeekly || '-')}<div class="ohs-person-sub">${escapeHtml(t.CurrentRemarks || '')}</div>`,
                },
                {
                    key: 'updated', label: 'Last Updated', sortValue: (t) => t.LastUpdated || '',
                    render: (t) => escapeHtml(t.LastUpdated || '-'),
                },
                {
                    key: 'action', label: 'Action', sortable: false, searchable: false,
                    render: (t) => `<div class="ohs-tracker-row-actions">
                        <button type="button" class="btn-ghost" data-act="edit" data-id="${escapeHtml(t.TrackerId)}">Edit</button>
                        <button type="button" class="btn-ghost" data-act="expand" data-id="${escapeHtml(t.TrackerId)}">${expandedIds.has(t.TrackerId) ? 'Collapse' : 'Expand'}</button>
                        <button type="button" class="btn-danger" data-act="del" data-id="${escapeHtml(t.TrackerId)}">Hapus</button>
                    </div>`,
                },
            ],
            detailRow: (t) => `<tr class="tracker-detail-row${expandedIds.has(t.TrackerId) ? '' : ' hide'}"><td colspan="11">${expandedIds.has(t.TrackerId) ? trackerInlinePanelHtml(t) : ''}</td></tr>`,
            onRender: (pageRows, mount) => {
                mount.querySelectorAll('[data-act="expand"]').forEach((btn) => {
                    btn.addEventListener('click', () => {
                        const id = btn.dataset.id;
                        if (expandedIds.has(id)) expandedIds.delete(id); else expandedIds.add(id);
                        table.refresh();
                    });
                });
                mount.querySelectorAll('[data-act="edit"]').forEach((btn) => {
                    btn.addEventListener('click', () => runSafe((async () => {
                        const t = await api('/tracker/show?trackerId=' + encodeURIComponent(btn.dataset.id));
                        trackerForm(t);
                    })()));
                });
                mount.querySelectorAll('[data-act="del"]').forEach((btn) => {
                    btn.addEventListener('click', () => runSafe((async () => {
                        if (!confirm('Hapus tracker beserta sub task dan log progress?')) return;
                        await api('/tracker/delete', { method: 'POST', body: JSON.stringify({ TrackerId: btn.dataset.id }) });
                        expandedIds.delete(btn.dataset.id);
                        await refresh();
                    })()));
                });
                const detailRows = mount.querySelectorAll('.tracker-detail-row');
                pageRows.forEach((t, idx) => {
                    if (!expandedIds.has(t.TrackerId)) return;
                    const row = detailRows[idx];
                    if (row) bindInlinePanel(row, t);
                });
                if (reopenTrackerId) reopenTrackerId = '';
            },
        });
        return table;
    }

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
        cache = data.trackers || [];
        const c = data.counts || {};
        document.getElementById('tracker-counts').innerHTML = `${statChip('On Going', c.onGoing)}${statChip('Overdue', c.overdue)}${statChip('Closed', c.closed)}`;
        ensureTable().setRows(cache);
    }

    function trackerForm(existing) {
        openModal(existing ? 'Edit Details' : 'Create Tracker', `
            <form id="tr-form" class="ohs-form">
                <fieldset><legend>1. Overall</legend>
                    <div class="ohs-form-grid" style="width:100%;">
                        <label>Type<select name="TrackerType"><option>Project</option><option>Issue</option></select></label>
                        <label>Nama<input name="ProjectIssueName" required></label>
                        <label>Department<input name="Department" required></label>
                        <label>Site<input name="Site" required></label>
                        <div class="emp-box full"><label>Project Leader<input type="search"><input type="hidden" name="ProjectLeaderEmpId"><ul class="ohs-search-list"></ul></label></div>
                        <label>Start<input type="date" name="StartDate" required></label>
                        <label>Due<input type="date" name="DueDate" required></label>
                    </div>
                </fieldset>
                <fieldset><legend>2. Context &amp; Success</legend>
                    <div class="ohs-form-grid" style="width:100%;">
                        <label class="full">Description<textarea name="DescriptionProject" required></textarea></label>
                        <label class="full">Background<textarea name="BackgroundProject" required></textarea></label>
                        <label class="full">Impact<textarea name="ImpactProject" required></textarea></label>
                        <label class="full">Success Indicator<textarea name="SuccessIndicator" required></textarea></label>
                    </div>
                </fieldset>
                <fieldset><legend>3. Initial Progress / Sub Task</legend>
                    <div id="subtask-box"></div>
                    <button type="button" class="btn-ghost" id="add-st">+ Sub Task</button>
                </fieldset>
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

    loadInit().then((init) => {
        fillSelect(document.getElementById('tr-dept'), init.teams, 'All Departments');
        fillSelect(document.getElementById('tr-site'), init.sites, 'All Sites');
        return refresh();
    }).catch((e) => toast(e.message));
    document.getElementById('tr-refresh').onclick = () => runSafe(refresh());
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
            kpiCard('Scheduler', s.Enabled ? 'ON' : 'OFF', s.Enabled ? 'green' : 'red'),
            kpiCard('Hari & Jam Pengiriman', `${s.ScheduleDays || '-'} ${String(s.SendHour).padStart(2, '0')}:${String(s.SendMinute).padStart(2, '0')}`, 'blue'),
            kpiCard('Last Run', s.LastRunAt || '-', 'green', s.LastRunStatus || ''),
            kpiCard('Overdue Reminder Terakhir', s.OverdueReminderLastRunAt || '-', 'orange', `${s.OverdueReminderLastCount ?? 0} item`),
            kpiCard('HSE Sync Terakhir', s.HseSyncLastRunAt || '-', 'blue', `${s.HseSyncLastCount ?? 0} karyawan`),
            kpiCard('Roster Karyawan', init.employeeCount, 'green'),
        ].join('');
        const triggerBadge = document.getElementById('admin-trigger-badge');
        triggerBadge.textContent = 'Trigger: ' + (s.Enabled ? 'ON' : 'OFF');
        triggerBadge.className = 'badge ' + (s.Enabled ? 'green' : 'gray');
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

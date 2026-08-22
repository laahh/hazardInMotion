(function () {
    var API_BASE = window.OHS_API_BASE || '/ohs-dashboard/api';
    var params = new URLSearchParams(window.location.search);
    var eventId = (params.get('eventId') || '').trim();

    var loadingScreen = document.getElementById('loadingScreen');
    var errorScreen = document.getElementById('errorScreen');
    var successScreen = document.getElementById('successScreen');
    var formScreen = document.getElementById('formScreen');

    function showScreen(name) {
        loadingScreen.classList.add('hide');
        errorScreen.classList.add('hide');
        successScreen.classList.add('hide');
        formScreen.classList.add('hide');
        document.getElementById(name).classList.remove('hide');
    }

    function showError(text) {
        document.getElementById('errorText').textContent = text || 'Terjadi kesalahan.';
        showScreen('errorScreen');
    }

    if (!eventId) {
        showError('Link tidak valid: Event ID tidak ditemukan pada URL.');
        return;
    }

    var checkedInIds = [];
    var selectedEmpId = '';
    var submitting = false;
    var searchDebounce = null;

    var searchInput = document.getElementById('search');
    var empListEl = document.getElementById('empList');
    var selectedBox = document.getElementById('selectedBox');
    var selectedAvatarEl = document.getElementById('selectedAvatar');
    var selectedNameEl = document.getElementById('selectedName');
    var submitBtn = document.getElementById('submitBtn');
    var submitBtnLabel = submitBtn.querySelector('.btn-label');
    var formMsg = document.getElementById('formMsg');

    function initials(name) {
        var parts = String(name || '').trim().split(/\s+/);
        var first = parts[0] && parts[0][0] ? parts[0][0] : '';
        var second = parts[1] && parts[1][0] ? parts[1][0] : '';
        return (first + second).toUpperCase() || '?';
    }

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function formatDateLabel(iso) {
        if (!iso) return '';
        var parts = String(iso).split('-');
        if (parts.length !== 3) return iso;
        var months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
        var monthIndex = Number(parts[1]) - 1;
        return Number(parts[2]) + ' ' + (months[monthIndex] || parts[1]) + ' ' + parts[0];
    }

    var lastMatches = [];

    function renderMatches(matches) {
        lastMatches = matches;
        empListEl.innerHTML = '';

        if (!matches.length) {
            empListEl.innerHTML = '<div class="empty-hint">Nama tidak ditemukan.<br>Coba kata kunci lain.</div>';
            return;
        }

        matches.forEach(function (emp) {
            var alreadyIn = checkedInIds.indexOf(emp.EmpId) >= 0;
            var isSelected = emp.EmpId === selectedEmpId;
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'emp-item' + (isSelected ? ' active' : '');
            btn.innerHTML =
                '<span class="emp-avatar">' + escapeHtml(initials(emp.EmpName)) + '</span>' +
                '<span class="emp-info">' +
                    '<span class="name">' + escapeHtml(emp.EmpName) + '</span>' +
                    '<span class="sub">' + escapeHtml([emp.Position, emp.Team, emp.Company].filter(Boolean).join(' • ')) + '</span>' +
                '</span>' +
                (isSelected
                    ? '<span class="check">&#10003;</span>'
                    : alreadyIn ? '<span class="done">Sudah absen</span>' : '');
            btn.addEventListener('click', function () {
                selectEmployee(emp);
            });
            empListEl.appendChild(btn);
        });
    }

    function renderList(filterText) {
        var text = (filterText || '').trim();

        clearTimeout(searchDebounce);

        if (!text) {
            empListEl.innerHTML = '<div class="empty-hint">Ketik minimal 1 huruf untuk mencari nama.</div>';
            return;
        }

        empListEl.innerHTML = '<div class="empty-hint">Mencari...</div>';

        searchDebounce = setTimeout(function () {
            fetch(API_BASE + '/employees/search?q=' + encodeURIComponent(text) + '&limit=30')
                .then(function (res) {
                    return res.json().then(function (data) {
                        if (!res.ok) throw new Error(data.error || 'Gagal mencari nama.');
                        return data;
                    });
                })
                .then(function (matches) {
                    renderMatches(matches || []);
                })
                .catch(function () {
                    empListEl.innerHTML = '<div class="empty-hint">Gagal mencari nama. Coba lagi.</div>';
                });
        }, 250);
    }

    function selectEmployee(emp) {
        selectedEmpId = emp.EmpId;
        selectedAvatarEl.textContent = initials(emp.EmpName);
        selectedNameEl.textContent = emp.EmpName;
        selectedBox.classList.add('show');
        submitBtn.disabled = false;
        renderMatches(lastMatches);
    }

    searchInput.addEventListener('input', function () {
        renderList(searchInput.value);
    });

    function setSubmitting(isSubmitting) {
        submitting = isSubmitting;
        submitBtn.disabled = isSubmitting;
        submitBtn.classList.toggle('loading', isSubmitting);
        submitBtnLabel.textContent = isSubmitting ? 'Memproses...' : 'Absen Sekarang';
    }

    submitBtn.addEventListener('click', function () {
        if (submitting || !selectedEmpId) return;
        setSubmitting(true);
        formMsg.innerHTML = '';

        fetch(API_BASE + '/events/checkin', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ EventId: eventId, EmpId: selectedEmpId }),
        })
            .then(function (res) {
                return res.json().then(function (data) {
                    if (!res.ok) throw new Error(data.error || 'Gagal mengirim absensi.');
                    return data;
                });
            })
            .then(function (data) {
                var icon = document.getElementById('successIcon');
                if (data.alreadyCheckedIn) {
                    icon.className = 'state-icon warn';
                    icon.innerHTML = '&#8505;&#65039;';
                    document.getElementById('successTitle').textContent = 'Anda Sudah Absen';
                    document.getElementById('successText').innerHTML =
                        '<span class="name-highlight">' + escapeHtml(data.empName) + '</span> sudah tercatat hadir sebelumnya pada event ini.';
                } else {
                    icon.className = 'state-icon success';
                    icon.innerHTML = '&#9989;';
                    document.getElementById('successTitle').textContent = 'Absensi Tercatat';
                    document.getElementById('successText').innerHTML =
                        'Terima kasih, <span class="name-highlight">' + escapeHtml(data.empName) + '</span>. Kehadiran Anda berhasil dicatat.';
                }
                showScreen('successScreen');
            })
            .catch(function (err) {
                setSubmitting(false);
                formMsg.innerHTML = '<div class="msg error">' + escapeHtml(err.message) + '</div>';
            });
    });

    fetch(API_BASE + '/events/checkin-info?eventId=' + encodeURIComponent(eventId))
        .then(function (res) {
            return res.json().then(function (data) {
                if (!res.ok) throw new Error(data.error || 'Event tidak ditemukan.');
                return data;
            });
        })
        .then(function (data) {
            checkedInIds = data.checkedInEmpIds || [];

            document.getElementById('eventName').textContent = data.event.EventName;
            document.getElementById('eventMeta').innerHTML =
                [formatDateLabel(data.event.EventDate), data.event.Where]
                    .filter(Boolean)
                    .map(escapeHtml)
                    .join(' <span class="dot">&middot;</span> ');

            showScreen('formScreen');
        })
        .catch(function (err) {
            showError(err.message);
        });
})();

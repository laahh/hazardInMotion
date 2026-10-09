/*
 * Panel insight Control Room: Pareto jam laporan, Volume vs Variasi,
 * modal Detail SAP/TBC, dan modal Highlight Temuan.
 * Dipakai /control-room/dashboard dan /control-room/pengawas.
 */
(function (window, document) {
    'use strict';

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function init(config) {
        var pareto = config.pareto || { s1: [], s2: [] };
        var quality = config.quality || [];
        var highlightData = config.highlight || {};
        var sapDetailUrl = config.sapDetailUrl;
        var sapPhotosUrl = config.sapPhotosUrl;
        var emptyDetailText = config.emptyDetailText || 'Tidak ada laporan SAP pada hari jaga dan H+1.';

        function paretoOptions(series) {
            series = series || [];
            return {
                chart: { type: 'line', height: 280, toolbar: { show: false }, fontFamily: 'inherit', animations: { enabled: false } },
                stroke: { width: [0, 3], curve: 'straight' },
                series: [
                    { name: 'Jumlah Laporan', type: 'column', data: series.map(function (row) { return row.count; }) },
                    { name: 'Kumulatif %', type: 'line', data: series.map(function (row) { return row.cumulative; }) },
                ],
                xaxis: { categories: series.map(function (row) { return row.hour + ':00'; }), axisBorder: { show: false } },
                yaxis: [
                    { title: { text: 'Laporan' } },
                    { opposite: true, min: 0, max: 100, title: { text: '%' } },
                ],
                colors: ['#487FFF', '#FF9F29'],
                grid: { borderColor: 'rgba(209, 213, 219, 0.4)', strokeDashArray: 4 },
                legend: { position: 'top', horizontalAlign: 'right' },
                dataLabels: { enabled: false },
                annotations: {
                    yaxis: [{ y: 80, yAxisIndex: 1, borderColor: '#9CA3AF', strokeDashArray: 6, label: { text: '80%', style: { fontSize: '10px' } } }],
                },
            };
        }

        var paretoChart = new ApexCharts(document.getElementById('chart-pareto'), paretoOptions(pareto.s1));
        paretoChart.render();

        function applyPareto(series) {
            series = series || [];
            paretoChart.updateOptions({
                xaxis: { categories: series.map(function (row) { return row.hour + ':00'; }) },
            }, false, false);
            paretoChart.updateSeries([
                { name: 'Jumlah Laporan', type: 'column', data: series.map(function (row) { return row.count; }) },
                { name: 'Kumulatif %', type: 'line', data: series.map(function (row) { return row.cumulative; }) },
            ]);
        }

        document.querySelectorAll('[data-pareto]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('[data-pareto]').forEach(function (el) { el.classList.remove('is-active'); });
                btn.classList.add('is-active');
                applyPareto(pareto[btn.getAttribute('data-pareto')]);
            });
        });

        var qualityPoints = quality.filter(function (row) {
            return row.variety_score !== null;
        });
        new ApexCharts(document.getElementById('chart-quality-scatter'), {
            chart: { type: 'scatter', height: 280, toolbar: { show: false }, fontFamily: 'inherit' },
            series: [{
                name: 'Personil',
                data: qualityPoints.map(function (row) { return [row.total_findings, row.variety_score]; }),
            }],
            xaxis: { title: { text: 'Volume Temuan' }, tickAmount: 5 },
            yaxis: { title: { text: 'Variasi Score' }, min: 0, max: 1 },
            colors: ['#487FFF'],
            grid: { borderColor: 'rgba(209, 213, 219, 0.4)', strokeDashArray: 4 },
            tooltip: {
                custom: function (opts) {
                    var row = qualityPoints[opts.dataPointIndex];
                    if (!row) {
                        return '';
                    }
                    return '<div class="p-8 text-xs"><strong>' + row.name + '</strong><br>Volume: ' + row.total_findings + '<br>Kategori: ' + row.distinct_categories + '<br>Variasi: ' + row.variety_score + '</div>';
                },
            },
        }).render();

        var sapCards = [];
        var tbcCards = [];
        var tbcLoaded = false;
        var sapFilter = 'all';
        var tbcFilter = 'all';
        var detailPane = 'sap';
        var sapModalEl = document.getElementById('ocr-sap-modal');
        var sapModal = sapModalEl ? new bootstrap.Modal(sapModalEl) : null;
        var sapTitle = document.getElementById('ocr-sap-title');
        var sapMeta = document.getElementById('ocr-sap-meta');
        var sapStatus = document.getElementById('ocr-sap-status');
        var sapGrid = document.getElementById('ocr-sap-grid');
        var sapFilters = document.getElementById('ocr-sap-filters');
        var tbcFilters = document.getElementById('ocr-tbc-filters');
        var sapPane = document.getElementById('ocr-sap-pane-sap');
        var tbcPane = document.getElementById('ocr-sap-pane-tbc');

        function setDetailPane(pane) {
            detailPane = pane;
            if (sapPane) {
                sapPane.hidden = pane !== 'sap';
            }
            if (tbcPane) {
                tbcPane.hidden = pane !== 'tbc';
            }
            document.querySelectorAll('[data-detail-pane]').forEach(function (btn) {
                var active = btn.getAttribute('data-detail-pane') === pane;
                btn.classList.toggle('is-active', active);
                btn.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            renderDetailCards();
        }

        function setSapFilterCounts(counts) {
            document.querySelectorAll('[data-sap-filter]').forEach(function (btn) {
                var key = btn.getAttribute('data-sap-filter');
                var label = {
                    all: 'Semua',
                    hazard: 'Hazard',
                    inspeksi: 'Inspeksi',
                    observasi: 'Observasi',
                    oak: 'OAK'
                }[key] || key;
                var n = counts && counts[key] != null ? counts[key] : 0;
                btn.textContent = label + ' (' + n + ')';
            });
        }

        function setTbcFilterCounts(counts) {
            document.querySelectorAll('[data-tbc-filter]').forEach(function (btn) {
                var key = btn.getAttribute('data-tbc-filter');
                var label = {
                    all: 'Semua',
                    sudah: 'Sudah TBC',
                    belum: 'Belum TBC'
                }[key] || key;
                var n = counts && counts[key] != null ? counts[key] : 0;
                btn.textContent = label + ' (' + n + ')';
            });
        }

        function tbcBadge(card) {
            if (card.tbc === 'sudah') {
                return '<span class="ocr-sap-badge is-tbc-yes">Sudah TBC</span>';
            }
            if (card.tbc === 'belum') {
                return '<span class="ocr-sap-badge is-tbc-no">Belum TBC</span>';
            }
            return '<span class="ocr-sap-badge is-tbc-wait">TBC belum termuat</span>';
        }

        function sapPhotoMarkup(url, label, reportId) {
            if (!url) {
                return '';
            }
            return '<figure class="ocr-sap-photo">'
                + '<figcaption class="ocr-sap-photo-label">' + escapeHtml(label) + '</figcaption>'
                + '<a href="' + escapeHtml(url) + '" target="_blank" rel="noopener">'
                + '<img src="' + escapeHtml(url) + '" alt="' + escapeHtml(label + ' ' + reportId) + '" loading="lazy" onerror="this.closest(\'figure\').hidden=true">'
                + '</a></figure>';
        }

        function sapPhotoBlock(card) {
            if (card.photo_page_id) {
                var kind = card.photo_page_kind || 'photocar';
                return '<div class="ocr-sap-photos" data-photo-page="' + escapeHtml(String(card.photo_page_id)) + '" data-photo-kind="' + escapeHtml(kind) + '" data-report-id="' + escapeHtml(card.id) + '">'
                    + '<p class="ocr-sap-muted">Memuat foto…</p>'
                    + '</div>';
            }
            if (card.photo_url) {
                return sapPhotoMarkup(card.photo_url, 'Foto', card.id);
            }
            return '';
        }

        function hydrateSapPhotos() {
            sapGrid.querySelectorAll('[data-photo-page]').forEach(function (el) {
                var id = el.getAttribute('data-photo-page');
                var kind = el.getAttribute('data-photo-kind') || 'photocar';
                var reportId = el.getAttribute('data-report-id') || id;
                fetch(sapPhotosUrl + '?id=' + encodeURIComponent(id) + '&kind=' + encodeURIComponent(kind), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(function (res) { return res.json().then(function (body) { return { ok: res.ok, body: body }; }); })
                    .then(function (result) {
                        var data = (result.ok && result.body && result.body.data) ? result.body.data : {};
                        var html = sapPhotoMarkup(data.foto_temuan, 'Foto Temuan', reportId)
                            + sapPhotoMarkup(data.foto_penyelesaian, 'Foto Penyelesaian', reportId);
                        el.innerHTML = html;
                    })
                    .catch(function () {
                        el.innerHTML = '';
                    });
            });
        }

        function renderSapCards() {
            var list = sapFilter === 'all' ? sapCards : sapCards.filter(function (card) {
                return card.type === sapFilter;
            });
            renderCardList(list, sapCards.length, 'Tidak ada laporan untuk filter ini.', false);
        }

        function renderTbcCards() {
            var list = tbcFilter === 'all' ? tbcCards : tbcCards.filter(function (card) {
                return card.tbc === tbcFilter;
            });
            var empty = tbcCards.length
                ? 'Tidak ada Hazard/Inspeksi untuk filter TBC ini.'
                : 'Tidak ada Hazard/Inspeksi pada jendela jaga ini.';
            renderCardList(list, tbcCards.length, empty, true);
        }

        function renderDetailCards() {
            if (detailPane === 'tbc') {
                renderTbcCards();
                return;
            }
            renderSapCards();
        }

        function renderCardList(list, sourceCount, emptyText, showTbc) {
            if (!list.length) {
                sapGrid.innerHTML = '';
                sapStatus.hidden = false;
                if (sourceCount || detailPane === 'tbc') {
                    sapStatus.textContent = emptyText;
                }
                return;
            }
            sapStatus.hidden = true;
            sapGrid.innerHTML = list.map(function (card) {
                var photo = sapPhotoBlock(card);
                var geotag = card.geotag
                    ? '<p class="ocr-sap-geotag">GEOTAGGING Jam: ' + escapeHtml(card.geotag) + '</p>'
                    : '';
                var status = (card.status && card.status !== '—')
                    ? '<span class="ocr-sap-badge ' + ((card.status || '').toLowerCase() === 'closed' ? 'is-closed' : 'is-plain') + '">' + escapeHtml(card.status) + '</span>'
                    : '';
                var tbcMark = showTbc ? tbcBadge(card) : '';
                return '<article class="ocr-sap-card" data-type="' + escapeHtml(card.type) + '">'
                    + photo
                    + '<p class="ocr-sap-id">' + escapeHtml(card.id) + '</p>'
                    + geotag
                    + '<p>Submit BEATS: ' + escapeHtml(card.submitted_label || '—') + '</p>'
                    + '<p class="ocr-sap-headline">' + escapeHtml(card.headline) + '</p>'
                    + (card.subcategory ? '<p>' + escapeHtml(card.subcategory) + '</p>' : '')
                    + (card.description ? '<p class="ocr-sap-desc">' + escapeHtml(card.description) + '</p>' : '')
                    + (card.pic ? '<p>PIC: ' + escapeHtml(card.pic) + '</p>' : '')
                    + (card.pic_meta && card.pic_meta !== '—' ? '<p class="ocr-sap-muted">' + escapeHtml(card.pic_meta) + '</p>' : '')
                    + (card.reporter ? '<p>Pelapor: ' + escapeHtml(card.reporter) + '</p>' : '')
                    + (card.reporter_meta && card.reporter_meta !== '—' ? '<p class="ocr-sap-muted">' + escapeHtml(card.reporter_meta) + '</p>' : '')
                    + (card.location ? '<p>Lokasi: ' + escapeHtml(card.location) + '</p>' : '')
                    + (card.location_detail ? '<p>Detail Lok: ' + escapeHtml(card.location_detail) + '</p>' : '')
                    + tbcMark
                    + status
                    + '</article>';
            }).join('');
            hydrateSapPhotos();
        }

        document.querySelectorAll('[data-sap-filter]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                sapFilter = btn.getAttribute('data-sap-filter');
                document.querySelectorAll('[data-sap-filter]').forEach(function (el) { el.classList.remove('is-active'); });
                btn.classList.add('is-active');
                renderSapCards();
            });
        });

        document.querySelectorAll('[data-tbc-filter]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                tbcFilter = btn.getAttribute('data-tbc-filter');
                document.querySelectorAll('[data-tbc-filter]').forEach(function (el) { el.classList.remove('is-active'); });
                btn.classList.add('is-active');
                renderTbcCards();
            });
        });

        document.querySelectorAll('[data-detail-pane]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                setDetailPane(btn.getAttribute('data-detail-pane'));
            });
        });

        (function initHighlightDetail() {
            var modalEl = document.getElementById('ocr-highlight-modal');
            var titleEl = document.getElementById('ocr-highlight-title');
            var metaEl = document.getElementById('ocr-highlight-meta');
            var bodyEl = document.getElementById('ocr-highlight-body');
            var emptyEl = document.getElementById('ocr-highlight-empty');
            if (!modalEl || !bodyEl) {
                return;
            }
            var modal = bootstrap.Modal.getOrCreateInstance(modalEl);

            function highlightItems(kind, name) {
                if (kind === 'golden_rule') {
                    var rules = highlightData.goldenRules || [];
                    for (var i = 0; i < rules.length; i += 1) {
                        if (rules[i].name === name) {
                            return rules[i].items || [];
                        }
                    }
                    return [];
                }
                if (kind === 'blindspot') {
                    return highlightData.blindspotItems || [];
                }
                return highlightData.tbcItems || [];
            }

            function highlightTitle(kind, name) {
                if (kind === 'golden_rule') {
                    return name || 'Golden Rule';
                }
                if (kind === 'blindspot') {
                    return 'Blindspot';
                }
                return 'Ratio TBC';
            }

            document.querySelectorAll('[data-highlight-kind]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var kind = btn.getAttribute('data-highlight-kind');
                    var name = btn.getAttribute('data-highlight-name') || '';
                    var items = highlightItems(kind, name);
                    titleEl.textContent = 'Highlight — ' + highlightTitle(kind, name);
                    metaEl.textContent = items.length + ' temuan';
                    bodyEl.innerHTML = items.map(function (item) {
                        return '<tr>'
                            + '<td>' + escapeHtml(item.tasklist || '—') + '</td>'
                            + '<td>' + escapeHtml(item.found_at || '—') + '</td>'
                            + '<td>' + escapeHtml(item.description || '—') + '</td>'
                            + '<td>' + escapeHtml(item.company_pic || '—') + '</td>'
                            + '<td>' + escapeHtml(item.status || '—') + '</td>'
                            + '</tr>';
                    }).join('');
                    emptyEl.hidden = items.length > 0;
                    var tableWrap = modalEl.querySelector('.table-responsive');
                    if (tableWrap) {
                        tableWrap.hidden = items.length === 0;
                    }
                    modal.show();
                });
            });
        })();

        document.querySelectorAll('.ocr-detail-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var sid = btn.getAttribute('data-sid');
                var date = btn.getAttribute('data-date');
                var shift = btn.getAttribute('data-shift');
                var name = btn.getAttribute('data-name') || sid;
                sapCards = [];
                tbcCards = [];
                tbcLoaded = false;
                sapFilter = 'all';
                tbcFilter = 'all';
                sapGrid.innerHTML = '';
                sapFilters.hidden = true;
                if (tbcFilters) {
                    tbcFilters.hidden = true;
                }
                sapStatus.hidden = false;
                sapStatus.textContent = 'Memuat laporan…';
                sapTitle.textContent = 'Detail — ' + name;
                sapMeta.textContent = shift + ' · ' + date + ' · SID ' + sid;
                setSapFilterCounts({ all: 0, hazard: 0, inspeksi: 0, observasi: 0, oak: 0 });
                setTbcFilterCounts({ all: 0, sudah: 0, belum: 0 });
                document.querySelectorAll('[data-sap-filter]').forEach(function (el) { el.classList.toggle('is-active', el.getAttribute('data-sap-filter') === 'all'); });
                document.querySelectorAll('[data-tbc-filter]').forEach(function (el) { el.classList.toggle('is-active', el.getAttribute('data-tbc-filter') === 'all'); });
                setDetailPane('sap');
                if (sapModal) {
                    sapModal.show();
                }

                var url = sapDetailUrl + '?sid=' + encodeURIComponent(sid) + '&date=' + encodeURIComponent(date) + '&shift=' + encodeURIComponent(shift);
                fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function (res) { return res.json().then(function (body) { return { ok: res.ok, body: body }; }); })
                    .then(function (result) {
                        if (!result.ok) {
                            sapStatus.textContent = 'Gagal memuat detail SAP.';
                            return;
                        }
                        var data = result.body;
                        sapCards = data.cards || [];
                        tbcCards = data.tbc_cards || [];
                        tbcLoaded = !!data.tbc_loaded;
                        sapMeta.textContent = shift + ' · jendela ' + (data.window_start || '') + ' – ' + (data.window_end || '') + ' · SID ' + sid;
                        setSapFilterCounts(data.counts || {});
                        setTbcFilterCounts(data.tbc_counts || {});
                        sapFilters.hidden = false;
                        if (tbcFilters) {
                            tbcFilters.hidden = false;
                        }
                        if (!data.reachable) {
                            sapStatus.textContent = (data.errors && data.errors.length) ? data.errors.join(' ') : 'Sumber SAP (OBDS) tidak terjangkau.';
                            sapGrid.innerHTML = '';
                            return;
                        }
                        if (data.errors && data.errors.length && !sapCards.length) {
                            sapStatus.textContent = data.errors.join(' ');
                            sapGrid.innerHTML = '';
                            return;
                        }
                        if (!sapCards.length) {
                            sapStatus.textContent = emptyDetailText;
                            sapGrid.innerHTML = '';
                            return;
                        }
                        var extra = [];
                        if (data.truncated) extra.push('Menampilkan maksimal 40 laporan per jenis.');
                        if (data.errors && data.errors.length) extra.push(data.errors.join(' '));
                        if (detailPane === 'tbc' && !tbcLoaded) extra.push('Sumber TBC belum termuat.');
                        sapStatus.textContent = extra.join(' ');
                        sapStatus.hidden = extra.length === 0;
                        renderDetailCards();
                    })
                    .catch(function () {
                        sapStatus.textContent = 'Gagal memuat detail SAP.';
                    });
            });
        });
    }

    window.OcrInsights = { init: init, escapeHtml: escapeHtml };
})(window, document);

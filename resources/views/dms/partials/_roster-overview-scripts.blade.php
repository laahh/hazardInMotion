{{--
  Chart untuk halaman Ringkasan Kepatuhan Roster.

  Di-include dari @section('scripts') supaya berjalan SETELAH apexcharts.min.js
  dimuat di bagian bawah layout. Seluruh angkanya dibaca dari atribut data-*
  pada elemen chart — tidak ada permintaan data ke mana pun.
--}}
<script>
(function () {
  if (!window.ApexCharts) return;

  var baca = function (el, nama, bawaan) {
    try { return JSON.parse(el.dataset[nama]); } catch (e) { return bawaan; }
  };

  var bar = document.getElementById('roBarChart');
  if (bar) {
    new ApexCharts(bar, {
      chart: { type: 'bar', height: 264, toolbar: { show: false }, animations: { enabled: false } },
      series: [
        { name: 'Pelanggaran', data: baca(bar, 'pelanggaran', []) },
        { name: 'Wajib cuti', data: baca(bar, 'wajib', []) },
      ],
      colors: ['#487FFF', '#16A34A'],
      plotOptions: { bar: { borderRadius: 4, columnWidth: '32%' } },
      dataLabels: { enabled: false },
      legend: { show: false },
      xaxis: {
        categories: baca(bar, 'kategori', []),
        labels: { style: { fontSize: '11px', colors: '#9ca3af' } },
        axisBorder: { show: false },
        axisTicks: { show: false },
      },
      yaxis: { labels: { style: { fontSize: '11px', colors: '#9ca3af' } } },
      grid: { borderColor: '#eef1f6', strokeDashArray: 4 },
      tooltip: { y: { formatter: function (v) { return v.toLocaleString('id') + ' orang'; } } },
    }).render();
  }

  var donut = document.getElementById('roDonutChart');
  if (donut) {
    new ApexCharts(donut, {
      chart: { type: 'donut', height: 270, sparkline: { enabled: true }, animations: { enabled: false } },
      series: [
        parseInt(donut.dataset.pelanggaran, 10) || 0,
        parseInt(donut.dataset.patuh, 10) || 0,
      ],
      labels: ['Pelanggaran', 'Patuh'],
      colors: ['#EF4444', '#487FFF'],
      stroke: { width: 0 },
      dataLabels: { enabled: false },
      legend: { show: false },
      plotOptions: { pie: { donut: { size: '70%' } } },
      tooltip: { y: { formatter: function (v) { return v.toLocaleString('id') + ' karyawan'; } } },
    }).render();
  }
})();
</script>

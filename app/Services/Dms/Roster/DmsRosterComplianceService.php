<?php

declare(strict_types=1);

namespace App\Services\Dms\Roster;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Penyusun payload dashboard /dms/roster-compliance.
 *
 * Alur: baca pola terkompilasi dari dms_roster_pola (join dms_roster_karyawan,
 * disaring ke base wajib_cek) → jalankan
 * DmsRosterRuleEngine untuk seluruh populasi (hasil ringkas di-cache per
 * periode) → agregasi kartu & per-site → filter, urut, paginasi. Array
 * per-hari (merah/kuning) hanya dihitung ulang untuk baris yang benar-benar
 * tampil di halaman, supaya cache tetap kecil.
 */
final class DmsRosterComplianceService
{
    public function __construct(
        private readonly DmsRosterRuleEngine $engine,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function dashboard(array $filters): array
    {
        $filters = $this->normalisasiFilters($filters);
        $tahun = (int) $filters['tahun'];
        $meta = $this->meta($tahun);

        if ($meta['jumlah'] === 0) {
            return $this->payloadKosong($tahun, $meta, $filters);
        }

        $panjang = $meta['panjang'];
        $periode = $this->resolvePeriode($filters, $tahun, $panjang, $meta['hari_terakhir']);
        $ringkas = $this->evaluasiRingkas($tahun, $periode['r0'], $periode['r1'], $meta['disinkron'], $panjang);
        $baris = $ringkas['baris'];

        $agregat = $this->agregat($baris);
        $perSite = $this->agregatPerSite($baris);
        $perPt = $this->agregatPerPt($baris);

        $tersaring = $this->saring($baris, $filters);
        $tersaring = $this->urutkan($tersaring, (string) ($filters['sort'] ?? 'nama'), (string) ($filters['dir'] ?? 'asc'));

        $perPage = (int) config('dms_roster.per_page', 30);
        $totalHalaman = max(1, (int) ceil(count($tersaring) / $perPage));
        $halaman = min(max(1, (int) ($filters['page'] ?? 1)), $totalHalaman);
        $potongan = array_slice($tersaring, ($halaman - 1) * $perPage, $perPage);

        return [
            'up' => true,
            'tahun' => $tahun,
            'meta' => $meta,
            'periode' => $periode,
            'hari' => $this->hariWindow($tahun, $periode['r0'], $periode['r1']),
            'agregat' => $agregat,
            'ambang' => $this->engine->ambang(),
            'perSite' => $perSite,
            'perPt' => $perPt,
            'seri' => $this->lengkapiSeri($ringkas['seri'], $agregat['total'], $tahun, $periode['r0'], $periode['r1']),
            'topPelanggaran' => $this->topPelanggaran($perPt, $perSite, (string) $filters['pt']),
            'kategoriTersedia' => $this->nilaiUnik($baris, 'kategori'),
            'rosterTersedia' => $this->rosterUnik($baris),
            'siteTersedia' => $this->siteTerurut($this->nilaiUnik($baris, 'site')),
            'statusTersedia' => DmsRosterRuleEngine::statusList(),
            'baris' => $this->lengkapiLabel($potongan, $tahun),
            'paginasi' => [
                'halaman' => $halaman,
                'total_halaman' => $totalHalaman,
                'per_page' => $perPage,
                'total_baris' => count($tersaring),
                'dari' => count($potongan) > 0 ? ($halaman - 1) * $perPage + 1 : 0,
                'sampai' => ($halaman - 1) * $perPage + count($potongan),
            ],
            'filters' => $filters,
        ];
    }

    /**
     * Rincian harian satu karyawan: gate, jam check-in/out, durasi, dan flag
     * rule per hari — dipakai panel detail saat baris tabel diklik.
     *
     * @return array<string, mixed>
     */
    public function detailKaryawan(string $sid, int $tahun): array
    {
        $row = DB::table('dms_roster_pola as p')
            ->join('dms_roster_karyawan as k', 'k.id', '=', 'p.karyawan_id')
            ->where('k.kode_sid', $sid)
            ->where('p.tahun', $tahun)
            ->select(
                'p.karyawan_id', 'p.pola', 'p.hari_terakhir',
                'k.kode_sid', 'k.nama', 'k.jabatan_struktural as jabatan',
                'k.kategori', 'k.perusahaan', 'k.site',
            )
            ->first();

        if ($row === null) {
            return ['found' => false];
        }

        $param = $this->paramPt((string) $row->perusahaan);
        $eval = $this->engine->evaluasi((string) $row->pola, (string) $row->kategori, $param['thr'], $param['map']);
        $awal = $this->awalTahun($tahun);

        // Nama gate di-join dari tabel lookup; tabel fakta hanya simpan id.
        $scan = DB::table('dms_roster_scan_harian as d')
            ->leftJoin('dms_roster_gate as gi', 'gi.id', '=', 'd.gate_masuk_id')
            ->leftJoin('dms_roster_gate as go', 'go.id', '=', 'd.gate_keluar_id')
            ->where('d.karyawan_id', (int) $row->karyawan_id)
            ->whereBetween('d.tanggal', [$awal->toDateString(), (string) $row->hari_terakhir])
            ->orderBy('d.tanggal')
            ->select('d.tanggal', 'd.menit_masuk', 'd.menit_keluar', 'gi.nama as gate_in', 'go.nama as gate_out')
            ->get()
            ->keyBy(fn (object $r): string => CarbonImmutable::parse((string) $r->tanggal)->toDateString());

        $hari = [];
        $n = strlen($eval->pola);
        for ($i = 0; $i < $n; $i++) {
            $iso = $awal->addDays($i)->toDateString();
            $s = $scan->get($iso);

            $hari[] = [
                'iso' => $iso,
                'label' => $this->labelTanggalPanjang($awal->addDays($i)),
                'kode' => $eval->pola[$i],
                'kode_label' => $this->labelKode($eval->pola[$i]),
                'hari_ke' => $eval->dayno[$i],
                'gate_in' => $s->gate_in ?? null,
                'gate_out' => $s->gate_out ?? null,
                'jam_in' => isset($s->menit_masuk) && $s->menit_masuk !== null ? $this->jam((int) $s->menit_masuk) : null,
                'jam_out' => isset($s->menit_keluar) && $s->menit_keluar !== null ? $this->jam((int) $s->menit_keluar) : null,
                'durasi' => isset($s->menit_masuk, $s->menit_keluar) && $s->menit_masuk !== null && $s->menit_keluar !== null
                    ? $this->durasi((int) $s->menit_masuk, (int) $s->menit_keluar)
                    : null,
                'merah' => $eval->red[$i],
                'kuning' => $eval->yel[$i],
            ];
        }

        return [
            'found' => true,
            'sid' => (string) $row->kode_sid,
            'nama' => (string) $row->nama,
            'jabatan' => (string) ($row->jabatan ?? ''),
            'kategori' => (string) $row->kategori,
            'perusahaan' => (string) $row->perusahaan,
            'site' => (string) ($row->site ?? ''),
            'longgar' => $eval->longgar,
            'status' => $eval->status,
            'roster' => $eval->roster,
            'onAll' => $eval->onAll,
            'cutiMin' => $eval->cutiMin,
            'onCur' => $eval->onCur,
            'onNoOff' => $eval->onNoOff,
            'wajibCuti' => $this->engine->wajibCuti($eval),
            'pelanggaran' => $this->ringkasPelanggaran($eval),
            'hari' => $hari,
        ];
    }

    /**
     * Daftar karyawan yang wajib segera dicutikan — dipakai tombol unduh.
     *
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function daftarWajibCuti(array $filters): array
    {
        $filters = $this->normalisasiFilters($filters);
        $tahun = (int) $filters['tahun'];
        $meta = $this->meta($tahun);
        if ($meta['jumlah'] === 0) {
            return [];
        }

        $periode = $this->resolvePeriode($filters, $tahun, $meta['panjang'], $meta['hari_terakhir']);
        $baris = $this->evaluasiRingkas($tahun, $periode['r0'], $periode['r1'], $meta['disinkron'], $meta['panjang'])['baris'];

        $wajib = array_values(array_filter($baris, static fn (array $r): bool => $r['wajib']));
        usort($wajib, static fn (array $a, array $b): int => $b['onCur'] <=> $a['onCur']);

        return $wajib;
    }

    /**
     * Lengkapi filter dengan nilai default. View selalu menerima seluruh key,
     * jadi form filter tidak pernah rontok karena pemanggil mengirim array
     * sebagian (mis. dari command, test, atau tautan lama tanpa parameter).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function normalisasiFilters(array $filters): array
    {
        $default = [
            'tahun' => CarbonImmutable::now()->year,
            'q' => '',
            'pt' => '',
            'site' => '',
            'kategori' => '',
            'status' => '',
            'note' => '',
            'roster' => '',
            'periode' => '',
            'nilai' => '',
            'dari' => '',
            'sampai' => '',
            'sort' => 'nama',
            'dir' => 'asc',
            'page' => 1,
        ];

        return array_merge($default, array_filter($filters, static fn (mixed $v): bool => $v !== null));
    }

    /**
     * @return array{jumlah:int,panjang:int,hari_terakhir:string,disinkron:string,label_periode:string}
     */
    public function meta(int $tahun): array
    {
        // Ditangkap supaya halaman menampilkan state "data belum tersedia"
        // (lengkap dengan perintah sinkronisasinya) alih-alih 500, saat tabel
        // belum dimigrasikan atau sinkronisasi pertama belum dijalankan.
        try {
            $row = DB::table('dms_roster_pola as p')
                ->join('dms_roster_karyawan as k', 'k.id', '=', 'p.karyawan_id')
                ->where('p.tahun', $tahun)
                ->where('k.wajib_cek', true)
                ->selectRaw('COUNT(*) AS jumlah, MAX(p.hari_terakhir) AS hari_terakhir, MAX(p.dikompilasi_pada) AS disinkron')
                ->first();
        } catch (Throwable $e) {
            Log::warning('DmsRoster meta gagal (tabel belum ada / belum sinkron): '.$e->getMessage());
            $row = null;
        }

        $jumlah = (int) ($row->jumlah ?? 0);
        $hariTerakhir = is_string($row->hari_terakhir ?? null) && $row->hari_terakhir !== ''
            ? CarbonImmutable::parse((string) $row->hari_terakhir)
            : $this->awalTahun($tahun);
        $awal = $this->awalTahun($tahun);

        return [
            'jumlah' => $jumlah,
            'panjang' => $jumlah === 0 ? 0 : $this->selisihHari($awal, $hariTerakhir) + 1,
            'hari_terakhir' => $hariTerakhir->toDateString(),
            'disinkron' => (string) ($row->disinkron ?? ''),
            'label_periode' => $this->labelTanggalPanjang($awal).' – '.$this->labelTanggalPanjang($hariTerakhir),
        ];
    }

    /**
     * Evaluasi seluruh populasi, disimpan tanpa array per-hari agar ringan
     * di cache. Kunci cache memuat hari_terakhir/disinkron supaya hasil
     * sinkronisasi baru langsung terlihat tanpa perlu menunggu TTL habis.
     *
     * @return list<array<string, mixed>>
     */
    private function evaluasiRingkas(int $tahun, int $r0, int $r1, string $disinkron, int $panjangData): array
    {
        $key = sprintf('dms_roster:ringkas:v3:%d:%d:%d:%d:%s', $tahun, $r0, $r1, $panjangData, md5($disinkron));
        $ttl = (int) config('dms_roster.cache.evaluasi_ttl', 300);

        /** @var array{baris: list<array<string, mixed>>, seri: array<string, mixed>} */
        return Cache::remember($key, $ttl, function () use ($tahun, $r0, $r1, $panjangData): array {
            $out = [];
            $window = $r1 - $r0 + 1;
            $weekOf = $this->petaMinggu($tahun, $r0, $r1);
            $nWeek = $window > 0 ? $weekOf[$window - 1] + 1 : 0;

            $pelWeek = array_fill(0, max(1, $nWeek), 0);
            $mapWeek = array_fill(0, max(1, $nWeek), 0);
            $wajibWeek = array_fill(0, max(1, $nWeek), 0);
            $harian = array_fill(0, max(1, $window), 0);
            $batasWajib = (int) ($this->engine->ambang()['wajib_cuti'] ?? 71);

            DB::table('dms_roster_pola as p')
                ->join('dms_roster_karyawan as k', 'k.id', '=', 'p.karyawan_id')
                ->where('p.tahun', $tahun)
                ->where('k.wajib_cek', true)
                ->select('k.kode_sid', 'k.nama', 'k.jabatan_struktural as jabatan', 'k.kategori',
                    'k.perusahaan', 'k.kode_pt', 'k.site', 'p.pola')
                ->orderBy('p.id')
                ->chunk(2000, function ($rows) use (
                    &$out, &$pelWeek, &$mapWeek, &$wajibWeek, &$harian,
                    $r0, $r1, $weekOf, $nWeek, $window, $batasWajib, $panjangData
                ): void {
                    foreach ($rows as $row) {
                        $param = $this->paramPt((string) $row->perusahaan);
                        // Pola placeholder bisa lebih panjang dari data yang
                        // benar-benar ada; sisanya harus dibuang supaya tidak
                        // terbaca sebagai blok cuti palsu oleh rule engine.
                        $eval = $this->engine->evaluasi(
                            substr((string) $row->pola, 0, $panjangData),
                            (string) $row->kategori,
                            $param['thr'],
                            $param['map'],
                            $r0,
                            $r1,
                        );

                        $out[] = [
                            'sid' => (string) $row->kode_sid,
                            'nama' => (string) $row->nama,
                            'jabatan' => (string) ($row->jabatan ?? ''),
                            'kategori' => (string) $row->kategori,
                            'perusahaan' => (string) $row->perusahaan,
                            'kode_pt' => (string) $row->kode_pt,
                            'site' => (string) ($row->site ?? ''),
                            'roster' => $eval->roster,
                            'hadir' => $eval->hadir,
                            'pagi' => $eval->pagi,
                            'malam' => $eval->malam,
                            'off' => $eval->off,
                            'cutiCur' => $eval->cutiCur,
                            'onNoOff' => $eval->onNoOff,
                            'onS' => $eval->onS,
                            'onE' => $eval->onE,
                            'onAll' => $eval->onAll,
                            'onAllR' => $eval->onAllR,
                            'onAllS' => $eval->onAllS,
                            'onAllE' => $eval->onAllE,
                            'cutiMin' => $eval->cutiMin,
                            'cutiMinR' => $eval->cutiMinR,
                            'cutiMinS' => $eval->cutiMinS,
                            'cutiMinE' => $eval->cutiMinE,
                            'onCur' => $eval->onCur,
                            'status' => $eval->status,
                            'merah' => $eval->adaMerahDiPeriode,
                            'kuning' => $eval->adaKuningDiPeriode,
                            'everRed' => $eval->everRed,
                            'everYel' => $eval->everYel,
                            'k1' => $eval->redK1,
                            'k2' => $eval->redK2,
                            'k3' => $eval->redK3,
                            'longgar' => $eval->longgar,
                            'wajib' => $this->engine->wajibCuti($eval),
                        ];

                        $this->akumulasiSeri(
                            $eval, $r0, $r1, $window, $weekOf, $nWeek, $batasWajib,
                            $pelWeek, $mapWeek, $wajibWeek, $harian,
                        );
                    }
                });

            return [
                'baris' => $out,
                'seri' => [
                    'pel' => array_values($pelWeek),
                    'map' => array_values($mapWeek),
                    'wajib' => array_values($wajibWeek),
                    'harian' => array_values($harian),
                    'max_harian' => $harian === [] ? 0 : max($harian),
                ],
            ];
        });
    }

    /**
     * Akumulasi seri mingguan & harian untuk sparkline, chart tren, dan
     * heatmap — dikerjakan di lintasan yang sama dengan evaluasi rule supaya
     * tidak perlu memindai ulang pola seluruh populasi.
     *
     * @param  list<int>  $weekOf
     * @param  list<int>  $pelWeek
     * @param  list<int>  $mapWeek
     * @param  list<int>  $wajibWeek
     * @param  list<int>  $harian
     */
    private function akumulasiSeri(
        DmsRosterEvaluation $eval,
        int $r0,
        int $r1,
        int $window,
        array $weekOf,
        int $nWeek,
        int $batasWajib,
        array &$pelWeek,
        array &$mapWeek,
        array &$wajibWeek,
        array &$harian,
    ): void {
        if ($window <= 0 || $nWeek <= 0) {
            return;
        }

        $pola = $eval->pola;
        $n = strlen($pola);
        if ($n === 0) {
            return;
        }

        $kenaPel = array_fill(0, $nWeek, false);
        $kenaMap = array_fill(0, $nWeek, false);

        // On-site berjalan dihitung dari 1 Januari, bukan dari awal periode,
        // supaya status "wajib cuti" di minggu-minggu awal tidak ikut ter-reset.
        $run = 0;
        for ($i = 0; $i <= $r1 && $i < $n; $i++) {
            $run = $pola[$i] === 'c' ? 0 : $run + 1;
            if ($i < $r0) {
                continue;
            }

            $k = $i - $r0;
            $wk = $weekOf[$k];

            if ($eval->red[$i] !== '') {
                $kenaPel[$wk] = true;
                $harian[$k]++;
            } elseif ($eval->yel[$i] !== '') {
                $kenaMap[$wk] = true;
            }

            $akhirMinggu = $k === $window - 1 || $weekOf[$k + 1] !== $wk;
            if ($akhirMinggu && $run > $batasWajib && ! $eval->longgar) {
                $wajibWeek[$wk]++;
            }
        }

        for ($w = 0; $w < $nWeek; $w++) {
            if ($kenaPel[$w]) {
                $pelWeek[$w]++;
            }
            if ($kenaMap[$w]) {
                $mapWeek[$w]++;
            }
        }
    }

    /**
     * Lengkapi seri mentah dengan label minggu, seri total, dan persentase
     * tren — bentuknya langsung siap dipakai ApexCharts di view.
     *
     * @param  array<string, mixed>  $seri
     * @return array<string, mixed>
     */
    private function lengkapiSeri(array $seri, int $total, int $tahun, int $r0, int $r1): array
    {
        $awal = $this->awalTahun($tahun);
        $weekOf = $this->petaMinggu($tahun, $r0, $r1);
        $label = [];
        $sebelum = -1;

        foreach ($weekOf as $k => $w) {
            if ($w !== $sebelum) {
                $label[] = $this->labelTanggalSingkat($awal->addDays($r0 + $k));
                $sebelum = $w;
            }
        }

        /** @var list<int> $pel */
        $pel = $seri['pel'] ?? [];
        $persen = array_map(
            static fn (int $v): float => $total > 0 ? round($v / $total * 100, 1) : 0.0,
            $pel,
        );

        return [
            'minggu' => $label,
            'pel' => $pel,
            'map' => $seri['map'] ?? [],
            'wajib' => $seri['wajib'] ?? [],
            'total' => array_fill(0, count($label), $total),
            'persen' => $persen,
            'harian' => $seri['harian'] ?? [],
            'max_harian' => (int) ($seri['max_harian'] ?? 0),
        ];
    }

    /**
     * Peringkat penyumbang pelanggaran: per kontraktor saat semua PT
     * ditampilkan, per site saat satu PT sudah dipilih.
     *
     * @param  list<array<string, mixed>>  $perPt
     * @param  list<array<string, mixed>>  $perSite
     * @return list<array{label: string, value: int}>
     */
    private function topPelanggaran(array $perPt, array $perSite, string $ptTerpilih): array
    {
        $sumber = $ptTerpilih !== '' ? $perSite : $perPt;

        $out = [];
        foreach ($sumber as $x) {
            $nilai = (int) $x['pelanggaran'];
            if ($nilai > 0) {
                $out[] = [
                    'label' => (string) ($x['kode'] ?? $x['site'] ?? '—'),
                    'value' => $nilai,
                ];
            }
        }

        usort($out, static fn (array $a, array $b): int => $b['value'] <=> $a['value']);

        return array_slice($out, 0, 6);
    }

    /**
     * Indeks kolom minggu per hari dalam window — kolom baru setiap Senin.
     *
     * @return list<int>
     */
    private function petaMinggu(int $tahun, int $r0, int $r1): array
    {
        $awal = $this->awalTahun($tahun);
        $out = [];
        $w = -1;

        for ($i = $r0; $i <= $r1; $i++) {
            $t = $awal->addDays($i);
            if ($i === $r0 || $t->dayOfWeek === CarbonImmutable::MONDAY) {
                $w++;
            }
            $out[] = $w;
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $baris
     * @return array<string, mixed>
     */
    private function agregat(array $baris): array
    {
        $total = count($baris);
        $status = array_fill_keys(DmsRosterRuleEngine::statusList(), 0);
        $npel = 0;
        $k1 = 0;
        $k2 = 0;
        $k3 = 0;
        $nwajib = 0;
        $nmap = 0;

        foreach ($baris as $r) {
            $status[$r['status']] = ($status[$r['status']] ?? 0) + 1;
            if ($r['everRed']) {
                $npel++;
            }
            if ($r['k1']) {
                $k1++;
            }
            if ($r['k2']) {
                $k2++;
            }
            if ($r['k3']) {
                $k3++;
            }
            if ($r['everYel']) {
                $nmap++;
            }
            if ($r['wajib']) {
                $nwajib++;
            }
        }

        return [
            'total' => $total,
            'status' => $status,
            'pelanggaran' => $npel,
            'reg1' => $k1,
            'reg2' => $k2,
            'reg3' => $k3,
            'mapping' => $nmap,
            'wajib_cuti' => $nwajib,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $baris
     * @return list<array<string, mixed>>
     */
    private function agregatPerSite(array $baris): array
    {
        $agg = [];
        foreach ($baris as $r) {
            $site = $r['site'] !== '' ? $r['site'] : '(tanpa site)';
            $agg[$site] ??= ['site' => $site, 'total' => 0, 'pelanggaran' => 0, 'wajib_cuti' => 0];
            $agg[$site]['total']++;
            if ($r['everRed']) {
                $agg[$site]['pelanggaran']++;
            }
            if ($r['wajib']) {
                $agg[$site]['wajib_cuti']++;
            }
        }

        $urut = $this->siteTerurut(array_keys($agg));

        return array_values(array_map(static fn (string $s): array => $agg[$s], $urut));
    }

    /**
     * @param  list<array<string, mixed>>  $baris
     * @return list<array<string, mixed>>
     */
    private function agregatPerPt(array $baris): array
    {
        $agg = [];
        foreach ($baris as $r) {
            $kode = $r['kode_pt'];
            $agg[$kode] ??= [
                'kode' => $kode,
                'perusahaan' => $r['perusahaan'],
                'total' => 0,
                'pelanggaran' => 0,
                'wajib_cuti' => 0,
            ];
            $agg[$kode]['total']++;
            if ($r['everRed']) {
                $agg[$kode]['pelanggaran']++;
            }
            if ($r['wajib']) {
                $agg[$kode]['wajib_cuti']++;
            }
        }

        uasort($agg, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);

        return array_values($agg);
    }

    /**
     * @param  list<array<string, mixed>>  $baris
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    private function saring(array $baris, array $filters): array
    {
        $q = mb_strtolower(trim((string) ($filters['q'] ?? '')));
        $pt = (string) ($filters['pt'] ?? '');
        $site = (string) ($filters['site'] ?? '');
        $kategori = (string) ($filters['kategori'] ?? '');
        $status = (string) ($filters['status'] ?? '');
        $note = (string) ($filters['note'] ?? '');
        $roster = (string) ($filters['roster'] ?? '');

        return array_values(array_filter($baris, static function (array $r) use ($q, $pt, $site, $kategori, $status, $note, $roster): bool {
            if ($pt !== '' && $r['kode_pt'] !== $pt) {
                return false;
            }
            if ($site !== '' && $r['site'] !== $site) {
                return false;
            }
            if ($kategori !== '' && $r['kategori'] !== $kategori) {
                return false;
            }
            if ($status !== '' && $r['status'] !== $status) {
                return false;
            }
            if ($roster !== '' && (string) $r['roster'] !== $roster) {
                return false;
            }
            if ($note === 'pel' && ! $r['merah']) {
                return false;
            }
            if ($note === 'wajib' && ! $r['wajib']) {
                return false;
            }
            if ($note === 'map' && ! $r['kuning']) {
                return false;
            }
            if ($q !== '') {
                $cocok = str_contains(mb_strtolower($r['nama']), $q)
                    || str_contains(mb_strtolower($r['sid']), $q)
                    || str_contains(mb_strtolower($r['perusahaan']), $q)
                    || str_contains(mb_strtolower($r['jabatan']), $q);
                if (! $cocok) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * @param  list<array<string, mixed>>  $baris
     * @return list<array<string, mixed>>
     */
    private function urutkan(array $baris, string $sort, string $dir): array
    {
        $teks = ['sid' => true, 'nama' => true, 'jabatan' => true, 'perusahaan' => true, 'site' => true, 'status' => true, 'kategori' => true];
        $angka = [
            'roster' => true, 'hadir' => true, 'pagi' => true, 'malam' => true, 'off' => true,
            'cutiCur' => true, 'onNoOff' => true, 'onAll' => true, 'cutiMin' => true, 'onCur' => true,
        ];

        if (! isset($teks[$sort]) && ! isset($angka[$sort])) {
            $sort = 'nama';
        }

        $arah = $dir === 'desc' ? -1 : 1;

        usort($baris, static function (array $a, array $b) use ($sort, $arah, $teks): int {
            $r = isset($teks[$sort])
                ? strcasecmp((string) $a[$sort], (string) $b[$sort])
                : ($a[$sort] <=> $b[$sort]);
            $r *= $arah;

            if ($r === 0 && $sort !== 'nama') {
                $r = strcasecmp($a['nama'], $b['nama']);
            }

            return $r !== 0 ? $r : strcasecmp($a['sid'], $b['sid']);
        });

        return $baris;
    }

    /**
     * Tambahkan label rentang tanggal untuk baris yang tampil. Indeksnya sudah
     * ada di hasil evaluasi, jadi tidak perlu menjalankan rule engine lagi —
     * strip timeline harian dibangun panel detail dari endpoint JSON.
     *
     * @param  list<array<string, mixed>>  $potongan
     * @return list<array<string, mixed>>
     */
    private function lengkapiLabel(array $potongan, int $tahun): array
    {
        foreach ($potongan as $i => $r) {
            $potongan[$i]['rentang_onNoOff'] = $this->labelRentang($tahun, (int) $r['onS'], (int) $r['onE']);
            $potongan[$i]['rentang_onAll'] = $this->labelRentang($tahun, (int) $r['onAllS'], (int) $r['onAllE']);
            $potongan[$i]['rentang_cutiMin'] = $this->labelRentang($tahun, (int) $r['cutiMinS'], (int) $r['cutiMinE']);
        }

        return $potongan;
    }

    /**
     * Daftar hari pada window terpilih + penanda batas minggu/bulan/kuartal
     * untuk penggaris di atas strip timeline.
     *
     * @return list<array{iso:string,sep:string,label:string}>
     */
    private function hariWindow(int $tahun, int $r0, int $r1): array
    {
        $awal = $this->awalTahun($tahun);
        $out = [];
        $bulanSebelum = '';

        for ($i = $r0; $i <= $r1; $i++) {
            $t = $awal->addDays($i);
            $ym = $t->format('Y-m');
            $sep = '';

            if ($i > 0) {
                $sebelum = $awal->addDays($i - 1);
                if ($sebelum->format('Y-m') !== $ym) {
                    $sep = ($t->month - 1) % 3 === 0 ? 'q' : 'mo';
                } elseif ($t->dayOfWeek === CarbonImmutable::MONDAY) {
                    $sep = 'wk';
                }
            }

            $label = '';
            if ($i === $r0 || $ym !== $bulanSebelum) {
                $label = $this->namaBulanSingkat($t->month);
                if (($t->month - 1) % 3 === 0 && $i > $r0) {
                    $label .= ' · Q'.(int) (floor(($t->month - 1) / 3) + 1);
                }
            }
            $bulanSebelum = $ym;

            $out[] = ['iso' => $t->toDateString(), 'sep' => $sep, 'label' => $label];
        }

        return $out;
    }

    /**
     * Terjemahkan pilihan periode menjadi indeks [r0, r1] pada pola.
     *
     * @param  array<string, mixed>  $filters
     * @return array{r0:int,r1:int,jenis:string,nilai:string,label:string,minggu:list<int>,bulan:list<int>}
     */
    private function resolvePeriode(array $filters, int $tahun, int $panjang, string $hariTerakhir): array
    {
        $awal = $this->awalTahun($tahun);
        $maxIdx = max(0, $panjang - 1);
        $jenis = (string) ($filters['periode'] ?? '');
        $nilai = (string) ($filters['nilai'] ?? '');
        $r0 = 0;
        $r1 = $maxIdx;

        $mingguPer = $this->nomorMinggu($awal, $panjang);

        if ($jenis === 'kuartal' && $nilai !== '') {
            $q = max(1, min(4, (int) $nilai));
            [$r0, $r1] = $this->rentangBulan($awal, $panjang, range(($q - 1) * 3 + 1, ($q - 1) * 3 + 3));
        } elseif ($jenis === 'bulan' && $nilai !== '') {
            $m = max(1, min(12, (int) $nilai));
            [$r0, $r1] = $this->rentangBulan($awal, $panjang, [$m]);
        } elseif ($jenis === 'minggu' && $nilai !== '') {
            $w = (int) $nilai;
            $idx = array_keys($mingguPer, $w, true);
            if ($idx !== []) {
                $r0 = (int) $idx[0];
                $r1 = (int) $idx[count($idx) - 1];
            }
        } elseif ($jenis === 'custom') {
            $dari = (string) ($filters['dari'] ?? '');
            $sampai = (string) ($filters['sampai'] ?? '');
            if ($dari !== '') {
                $r0 = max(0, min($maxIdx, $this->selisihHari($awal, CarbonImmutable::parse($dari))));
            }
            if ($sampai !== '') {
                $r1 = max(0, min($maxIdx, $this->selisihHari($awal, CarbonImmutable::parse($sampai))));
            }
        }

        if ($r1 < $r0) {
            $r1 = $r0;
        }

        return [
            'r0' => $r0,
            'r1' => $r1,
            'jenis' => $jenis,
            'nilai' => $nilai,
            'dari' => $awal->addDays($r0)->toDateString(),
            'sampai' => $awal->addDays($r1)->toDateString(),
            'label' => $this->labelTanggalPanjang($awal->addDays($r0)).' – '.$this->labelTanggalPanjang($awal->addDays($r1)),
            'minggu' => array_values(array_unique($mingguPer)),
            'bulan' => $this->bulanTersedia($awal, $panjang),
            'hari_terakhir' => $hariTerakhir,
        ];
    }

    /**
     * Nomor minggu per hari: minggu 1 mulai 1 Januari, minggu baru setiap
     * hari Senin — sama seperti referensi (bukan ISO week).
     *
     * @return list<int>
     */
    private function nomorMinggu(CarbonImmutable $awal, int $panjang): array
    {
        $out = [];
        $wk = 0;
        for ($i = 0; $i < $panjang; $i++) {
            $t = $awal->addDays($i);
            if ($i === 0 || $t->dayOfWeek === CarbonImmutable::MONDAY) {
                $wk++;
            }
            $out[] = $wk;
        }

        return $out;
    }

    /**
     * @param  list<int>  $bulan
     * @return array{0:int,1:int}
     */
    private function rentangBulan(CarbonImmutable $awal, int $panjang, array $bulan): array
    {
        $r0 = -1;
        $r1 = -1;
        for ($i = 0; $i < $panjang; $i++) {
            if (in_array($awal->addDays($i)->month, $bulan, true)) {
                if ($r0 < 0) {
                    $r0 = $i;
                }
                $r1 = $i;
            }
        }

        return $r0 < 0 ? [0, max(0, $panjang - 1)] : [$r0, $r1];
    }

    /**
     * @return list<int>
     */
    private function bulanTersedia(CarbonImmutable $awal, int $panjang): array
    {
        $out = [];
        for ($i = 0; $i < $panjang; $i++) {
            $out[$awal->addDays($i)->month] = true;
        }

        return array_map('intval', array_keys($out));
    }

    /**
     * @return array{thr:int,map:string,roster:string,shift:string,shift_detail:string,kode:string}
     */
    private function paramPt(string $perusahaan): array
    {
        /** @var array<string, array<string, mixed>> $daftar */
        $daftar = config('dms_roster.perusahaan', []);
        $p = $daftar[$perusahaan] ?? [];

        return [
            'kode' => (string) ($p['kode'] ?? ''),
            'thr' => (int) ($p['thr'] ?? config('dms_roster.thr_default', 7)),
            'map' => (string) ($p['map'] ?? config('dms_roster.map_default', 'block')),
            'roster' => (string) ($p['roster'] ?? '—'),
            'shift' => (string) ($p['shift'] ?? '—'),
            'shift_detail' => (string) ($p['shift_detail'] ?? '—'),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $baris
     * @return list<string>
     */
    private function nilaiUnik(array $baris, string $key): array
    {
        $set = [];
        foreach ($baris as $r) {
            $v = (string) $r[$key];
            if ($v !== '') {
                $set[$v] = true;
            }
        }
        $out = array_keys($set);
        sort($out);

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $baris
     * @return list<int>
     */
    private function rosterUnik(array $baris): array
    {
        $set = [];
        foreach ($baris as $r) {
            $set[(int) $r['roster']] = true;
        }
        $out = array_map('intval', array_keys($set));
        sort($out);

        return $out;
    }

    /**
     * @param  list<string>  $sites
     * @return list<string>
     */
    private function siteTerurut(array $sites): array
    {
        /** @var list<string> $urutan */
        $urutan = config('dms_roster.site_order', []);

        usort($sites, static function (string $a, string $b) use ($urutan): int {
            $ia = array_search($a, $urutan, true);
            $ib = array_search($b, $urutan, true);
            $ia = $ia === false ? 99 : (int) $ia;
            $ib = $ib === false ? 99 : (int) $ib;

            return $ia <=> $ib ?: strcasecmp($a, $b);
        });

        return array_values($sites);
    }

    /**
     * @return list<string>
     */
    private function ringkasPelanggaran(DmsRosterEvaluation $e): array
    {
        $out = [];
        if ($e->redK1) {
            $out[] = 'REG-1 · kerja beruntun ≥14 hari';
        }
        if ($e->redK2) {
            $out[] = 'REG-2 · on-site melebihi ambang tanpa cuti';
        }
        if ($e->redK3) {
            $out[] = 'REG-3 · blok cuti terlalu pendek';
        }
        if ($e->everYel) {
            $out[] = 'MAP · pola tidak sesuai desain roster';
        }

        return $out;
    }

    private function labelRentang(int $tahun, int $mulai, int $selesai): string
    {
        if ($mulai < 0 || $selesai < 0) {
            return '';
        }

        $awal = $this->awalTahun($tahun);

        return $this->labelTanggalSingkat($awal->addDays($mulai)).'–'.$this->labelTanggalSingkat($awal->addDays($selesai));
    }

    private function labelKode(string $kode): string
    {
        return match ($kode) {
            'P' => 'Shift pagi',
            'M' => 'Shift malam',
            'c' => 'Cuti',
            default => 'Off',
        };
    }

    private function jam(int $menit): string
    {
        return sprintf('%02d:%02d', intdiv($menit, 60), $menit % 60);
    }

    private function durasi(int $masuk, int $keluar): string
    {
        $d = $keluar - $masuk;
        if ($d < 0) {
            $d += 1440;
        }

        return intdiv($d, 60).'j '.($d % 60).'m';
    }

    private function labelTanggalPanjang(CarbonImmutable $t): string
    {
        return $t->day.' '.$this->namaBulanSingkat($t->month).' '.$t->year;
    }

    private function labelTanggalSingkat(CarbonImmutable $t): string
    {
        return $t->day.' '.$this->namaBulanSingkat($t->month);
    }

    private function namaBulanSingkat(int $bulan): string
    {
        return ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'][$bulan - 1] ?? '';
    }

    private function selisihHari(CarbonImmutable $dari, CarbonImmutable $sampai): int
    {
        return (int) floor($dari->startOfDay()->diffInDays($sampai->startOfDay(), false));
    }

    private function awalTahun(int $tahun): CarbonImmutable
    {
        return CarbonImmutable::create($tahun, 1, 1)->startOfDay();
    }

    /**
     * @param  array{jumlah:int,panjang:int,hari_terakhir:string,disinkron:string,label_periode:string}  $meta
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function payloadKosong(int $tahun, array $meta, array $filters): array
    {
        return [
            'up' => false,
            'tahun' => $tahun,
            'meta' => $meta,
            'periode' => [
                'r0' => 0, 'r1' => 0, 'jenis' => '', 'nilai' => '',
                'dari' => $meta['hari_terakhir'], 'sampai' => $meta['hari_terakhir'],
                'label' => '—', 'minggu' => [], 'bulan' => [], 'hari_terakhir' => $meta['hari_terakhir'],
            ],
            'hari' => [],
            'agregat' => [
                'total' => 0,
                'status' => array_fill_keys(DmsRosterRuleEngine::statusList(), 0),
                'pelanggaran' => 0, 'reg1' => 0, 'reg2' => 0, 'reg3' => 0,
                'mapping' => 0, 'wajib_cuti' => 0,
            ],
            'ambang' => $this->engine->ambang(),
            'perSite' => [],
            'perPt' => [],
            'seri' => [
                'minggu' => [], 'pel' => [], 'map' => [], 'wajib' => [],
                'total' => [], 'persen' => [], 'harian' => [], 'max_harian' => 0,
            ],
            'topPelanggaran' => [],
            'kategoriTersedia' => [],
            'rosterTersedia' => [],
            'siteTersedia' => [],
            'statusTersedia' => DmsRosterRuleEngine::statusList(),
            'baris' => [],
            'paginasi' => ['halaman' => 1, 'total_halaman' => 1, 'per_page' => (int) config('dms_roster.per_page', 30), 'total_baris' => 0, 'dari' => 0, 'sampai' => 0],
            'filters' => $filters,
        ];
    }
}

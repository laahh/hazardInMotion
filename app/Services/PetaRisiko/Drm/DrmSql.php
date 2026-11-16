<?php

namespace App\Services\PetaRisiko\Drm;

use Carbon\CarbonImmutable;

/**
 * Query ClickHouse OBDS untuk Peta Risiko PJA (DRM 2.1).
 *
 * Semua query berbagi satu prelude CTE (lihat prelude()): baseline dari master DRM 2.1 (DrmMaster):
 *   hira  : 1.726 baris HIRA v31 (hid = id_baris), kelompok aktivitas (proses = K0..K25)
 *   dl    : detail lokasi master + grup lokasi;  pjadl : PJA per detail lokasi (aaj_lokasi_dan_pja)
 *   sap   : laporan Hazard/Inspeksi/Observasi/OAK dari ch_rfid_sap_cuti_all_site
 *   ma    : label ketidaksesuaian/sub → baris (M09);  mb : teks aktivitas OBS/OAK → baris (M11/M12)
 *   base  : pasangan baseline (detail lokasi, baris) = baris grup lokasi detail lokasi itu (M05)
 */
class DrmSql
{
    public const STATUS_TERKONTROL = 'K';

    public const STATUS_GAP = 'G';

    public const STATUS_TIDAK = 'T';

    public const STATUS_TIDAK_TERPETAKAN = 'X';

    /** Status SAP Hazard/Inspeksi yang dianggap sudah selesai. */
    private const STATUS_SELESAI = ['CLOSED', 'DONE'];

    /** Batasi detail lokasi ke satu site (untuk memecah query berat); null = semua. */
    private ?string $site = null;

    /**
     * @param  array<string, mixed>  $cfg
     * @param  array<string, string>  $lit  literal master dari DrmMaster::literal()
     */
    public function __construct(
        private readonly array $cfg,
        private readonly CarbonImmutable $sampai,
        private readonly array $lit,
    ) {
    }

    public function untukSite(?string $site): self
    {
        $salin = clone $this;
        $salin->site = $site;

        return $salin;
    }

    /** Daftar site yang punya detail lokasi aktif. */
    public function daftarSite(): string
    {
        return '
SELECT site, count() AS n FROM obds.bep_vw_site_lokasi_detil_lokasi
WHERE status_detil_lokasi = \'1\' AND status_lokasi = \'1\' AND status_site = \'1\' AND ifNull(site, \'\') != \'\'
GROUP BY site ORDER BY n DESC';
    }

    public function sampai(): CarbonImmutable
    {
        return $this->sampai;
    }

    /** Baseline bahaya HIRA unik, dengan flag apakah punya pasangan di Master A / B. */
    public function hira(): string
    {
        return $this->prelude().'
SELECT toString(h.hid) AS hid, h.a AS a, h.an AS an, h.sn AS sn, h.bn AS bn, h.p AS p, h.sites AS sites, h.grp AS grp,
       h.k1 AS k1, h.c1 AS c1, h.n1 AS n1, h.k2 AS k2, h.c2 AS c2, h.n2 AS n2,
       substring(h.kt, 1, 700) AS kt, h.faktor AS faktor, h.aspek AS aspek, h.proses AS proses,
       h.hid IN (SELECT hid FROM ma) AS di_a,
       (h.a, h.s) IN (SELECT a, s FROM mb) AS di_b,
       h.hid IN (SELECT hid FROM mtx) AS di_d
FROM hira h';
    }

    /** Detail lokasi aktif + Group Lokasi + PJA BC pemilik lokasinya. */
    public function detailLokasi(): string
    {
        return $this->prelude().'
SELECT toString(d.dl) AS dl, d.site AS site, d.id_lok AS id_lok, d.lokasi AS lokasi, d.detlok AS detlok, d.grp AS grp,
       arrayMap(x -> toString(x), groupUniqArrayIf(pl.pja_id, pl.pja_id != 0)) AS pja
FROM dl d
LEFT JOIN pjadl pl ON pl.dl = d.dl
GROUP BY d.dl, d.site, d.id_lok, d.lokasi, d.detlok, d.grp';
    }

    /** Master PJA (BC + Mitra) aktif. */
    public function pja(): string
    {
        return '
SELECT toString(p.id) AS id, p.name AS nama, toString(ifNull(p.id_parent, 0)) AS parent,
       ifNull(t.name, \'\') AS tipe, toString(p.id_site) AS id_site
FROM (SELECT id, any(name) AS name, any(id_parent) AS id_parent, any(id_pja_type) AS id_pja_type,
             any(id_site) AS id_site, max(is_active) AS is_active
      FROM obds.m_pja GROUP BY id) p
LEFT JOIN (SELECT id, any(name) AS name FROM obds.m_pja_type GROUP BY id) t ON t.id = p.id_pja_type
WHERE p.is_active = 1';
    }

    /**
     * Per (detail lokasi, aktivitas): komposisi status bahaya pada tanggal akhir + strip harian.
     * Strip: satu karakter per hari (lama = strip_hari), lihat kodeStrip().
     */
    public function aktivitasDetailLokasi(): string
    {
        $strip = (int) $this->cfg['strip_hari'];

        return strtr($this->prelude().$this->statusCte().',
hari AS (
  SELECT dl, a, d,
         max(open_now) AS m_open, max(1) AS m_temuan, 0 AS m_ok, 0 AS m_stop
  FROM evh_a WHERE d >= t_strip GROUP BY dl, a, d
  UNION ALL
  SELECT dl, a, d, 0, 0, max(ok), max(stop) FROM evo_a WHERE d >= t_strip GROUP BY dl, a, d
  UNION ALL
  -- Bukti checklist OAK / teks OBS = terkontrol (butir tidak sesuai bukan gap, spec DRM 2.1).
  SELECT e.dl, h.a, e.d, 0, 0, 1, 0 FROM evd e JOIN hira h ON h.hid = e.hid
  WHERE e.d >= t_strip AND (e.dl, e.hid) IN (SELECT dl, hid FROM basek) GROUP BY e.dl, h.a, e.d
),
hari2 AS (
  SELECT dl, a, d,
         multiIf(max(m_stop) = 1 OR max(m_open) = 1, \'G\', max(m_temuan) = 1, \'C\', max(m_ok) = 1, \'K\', \'-\') AS kode
  FROM hari GROUP BY dl, a, d
),
strip AS (
  SELECT dl, a,
         arrayStringConcat(arrayMap(i -> if(mp[t_strip + i] = \'\', \'-\', mp[t_strip + i]), range('.$strip.'))) AS strip
  FROM (SELECT dl, a, CAST((groupArray(d), groupArray(kode)), \'Map(Date, String)\') AS mp FROM hari2 GROUP BY dl, a)
)
SELECT toString(st.dl) AS dl, any(hb.an) AS aktivitas, st.a AS a,
       count() AS bahaya,
       countIf(st.status = \'K\') AS k, countIf(st.status = \'G\') AS g,
       countIf(st.status = \'T\') AS t, countIf(st.status = \'X\') AS x,
       countIf(st.open_n > 0) AS gap_open, countIf(st.rep >= {ambang}) AS gap_ulang, countIf(st.stop > 0) AS gap_stop,
       countIf(st.n1 = \'Significant\') AS sig, countIf(st.n1 = \'High\') AS high,
       toString(max(st.last)) AS terakhir,
       anyIf(sp.strip, sp.strip != \'\') AS strip,
       max(st.via_bukti) AS via_bukti,
       topK(1)(st.proses)[1] AS proses
FROM st
LEFT JOIN (SELECT a, any(an) AS an FROM hira GROUP BY a) hb ON hb.a = st.a
LEFT JOIN strip sp ON sp.dl = st.dl AND sp.a = st.a
GROUP BY st.dl, st.a', ['{ambang}' => (string) (int) $this->cfg['ambang_berulang']]);
    }

    /**
     * Status per bahaya-risiko UNIK dalam satu cakupan (SITE / GRUP / PROSES / PJA), per minggu
     * (tanggal akhir mundur 7 hari × tren_minggu). Status unik = terburuk antar detail lokasi:
     * G > K > T > X. Hasil diagregasi ke sel matriks agar baris tetap sedikit.
     *
     * Hemat memori: status mingguan hanya dihitung untuk pasangan yang punya bukti SAP (sb).
     * Pasangan tanpa bukti statusnya tetap: X bila bahaya tak terpetakan, selain itu T.
     */
    public function matriksCakupan(): string
    {
        return $this->prelude().$this->statusMingguanCte().$this->insidenCte().',
pjalok AS (SELECT dl, groupUniqArray(pja_id) AS pjas FROM pjadl GROUP BY dl),
anggota AS (
  SELECT b.dl AS dl, b.hid AS hid,
         arrayConcat([(\'SITE\', d.site), (\'SITE\', \'ALL\'),
                      (\'GRUP\', concat(d.grp, \'|\', d.site)), (\'GRUP\', concat(d.grp, \'|ALL\')),
                      (\'PROSES\', concat(h.proses, \'|\', d.site)), (\'PROSES\', concat(h.proses, \'|ALL\'))],
                     arrayMap(x -> (\'PJA\', toString(x)), pl.pjas)) AS ks
  FROM basek b
  JOIN dl d ON d.dl = b.dl
  JOIN hira h ON h.hid = b.hid
  LEFT JOIN pjalok pl ON pl.dl = d.dl
),
ak AS (
  SELECT dl, hid, kk.1 AS jenis, kk.2 AS kunci FROM anggota ARRAY JOIN ks AS kk
),
ev0 AS (
  SELECT a.jenis AS jenis, a.kunci AS kunci, a.hid AS hid, s.minggu AS minggu, max(s.r) AS rmax
  FROM ak a JOIN sb s ON s.dl = a.dl AND s.hid = a.hid
  GROUP BY jenis, kunci, hid, minggu
),
ev AS (
  SELECT jenis, kunci, hid, CAST((groupArray(minggu), groupArray(rmax)), \'Map(Date, UInt8)\') AS mp
  FROM ev0 GROUP BY jenis, kunci, hid
),
uk AS (SELECT DISTINCT jenis, kunci, hid FROM ak),
ukw AS (SELECT jenis, kunci, hid, w AS minggu FROM uk ARRAY JOIN ws AS w),
u AS (
  SELECT x.jenis AS jenis, x.kunci AS kunci, x.hid AS hid, x.minggu AS minggu, ev.mp[x.minggu] AS r
  FROM ukw AS x LEFT JOIN ev ON ev.jenis = x.jenis AND ev.kunci = x.kunci AND ev.hid = x.hid
),
inck AS (
  -- (cakupan, bahaya) yang terkait insiden 12 bulan terakhir di salah satu detail lokasi cakupan itu.
  SELECT DISTINCT a.jenis AS jenis, a.kunci AS kunci, a.hid AS hid
  FROM cocok c JOIN incx x ON x.no = c.no JOIN ak a ON a.dl = toInt64(x.dl) AND a.hid = c.hid
  WHERE x.tgl > t_end - 365
),
us AS (
  SELECT jenis, kunci, hid, minggu,
         multiIf(r = 4, \'G\', r = 3, \'K\', hid IN (SELECT hid FROM petakan), \'T\', \'X\') AS status,
         (jenis, kunci, hid) IN (SELECT jenis, kunci, hid FROM inck) AS ada_insiden
  FROM u
)
SELECT us.jenis AS jenis, us.kunci AS kunci, toString(us.minggu) AS minggu, us.status AS status,
       h.k1 AS k1, h.c1 AS c1, h.n1 AS n1,
       if(us.status = \'K\', h.k2, h.k1) AS kf, if(us.status = \'K\', h.c2, h.c1) AS cf, if(us.status = \'K\', h.n2, h.n1) AS nf,
       -- Laten = terkait insiden TAPI status gabungan di cakupan ini tidak teridentifikasi / tidak terpetakan.
       toUInt8(us.ada_insiden AND us.status IN (\'T\', \'X\')) AS laten,
       count() AS n
FROM us JOIN hira h ON h.hid = us.hid
GROUP BY us.jenis, us.kunci, us.minggu, us.status, h.k1, h.c1, h.n1, kf, cf, nf, laten';
    }

    /**
     * Semua pasangan baseline (detail lokasi, bahaya) + status pada tanggal akhir, ringkas per detail lokasi
     * (string "hid:status" dipisah koma) — supaya halaman PJA hanya memakai bahaya di area PJA itu.
     * Dijalankan per site.
     */
    public function pasanganBaseline(): string
    {
        return $this->prelude().$this->statusCte().'
SELECT toString(st.dl) AS dl, arrayStringConcat(groupArray(concat(toString(st.hid), \':\', st.status)), \',\') AS pasangan
FROM st GROUP BY st.dl';
    }

    /** Untuk Excel mapping: per bahaya, jumlah detail lokasi baseline per status + sinyal SAP (dijalankan per site). */
    public function statusPerBahaya(): string
    {
        return strtr($this->prelude().$this->statusCte().'
SELECT toString(st.hid) AS hid, count() AS dl_n,
       countIf(st.status = \'G\') AS g, countIf(st.status = \'K\') AS k,
       countIf(st.status = \'T\') AS t, countIf(st.status = \'X\') AS x,
       countIf(st.open_n > 0) AS dl_open, countIf(st.rep >= {ambang}) AS dl_ulang, countIf(st.stop > 0) AS dl_stop,
       sum(st.open_n) AS temuan_open, sum(st.rep) AS temuan_jendela,
       toString(max(st.last)) AS terakhir
FROM st GROUP BY st.hid', ['{ambang}' => (string) (int) $this->cfg['ambang_berulang']]);
    }

    /** Untuk Excel mapping: label SAP (Master A) dan teks aktivitas Observasi/OAK (Master B) per bahaya. */
    public function mappingBahaya(): string
    {
        return $this->prelude().',
mapa AS (SELECT hid, count() AS n, arraySlice(groupArray(concat(k, \' › \', sk)), 1, 25) AS contoh FROM ma GROUP BY hid),
mapb AS (
  SELECT h.hid AS hid, count() AS n, arraySlice(groupUniqArray(m.tx), 1, 10) AS contoh
  FROM mb m JOIN hira h ON h.a = m.a AND h.s = m.s GROUP BY hid
),
mapd AS (
  SELECT m.hid AS hid, count() AS n, arraySlice(groupUniqArray(substring(concat(o.name, \' › \', d.name), 1, 160)), 1, 10) AS contoh
  FROM md m
  JOIN obds.m_oak_detail_objek AS d FINAL ON toInt64(d.id) = m.did
  JOIN obds.m_oak_objek_pengamatan AS o FINAL ON o.id = d.id_objek_pengamatan_observe
  GROUP BY m.hid
)
SELECT toString(h.hid) AS hid, ifNull(a.n, 0) AS n_a, a.contoh AS label_a, ifNull(b.n, 0) AS n_b, b.contoh AS teks_b,
       ifNull(dd.n, 0) AS n_d, dd.contoh AS butir_d
FROM hira h LEFT JOIN mapa a ON a.hid = h.hid LEFT JOIN mapb b ON b.hid = h.hid LEFT JOIN mapd dd ON dd.hid = h.hid';
    }

    // ---------------------------------------------------------- Beranda PJA

    /**
     * Beranda PJA — bukti tingkat KELOMPOK aktivitas per (detail lokasi, kelompok), dijalankan per site.
     * Kelompok terdeteksi oleh: Observasi/OAK yang aktivitasnya terpetakan ke kelompok itu, atau label
     * Hazard/Inspeksi spesifik (cocok ke ≤ N kelompok di detail lokasi itu).
     * w / h: daftar jarang "indeks:bit" per minggu (0 = minggu berjalan) / per hari (0 = hari pertama strip).
     * Bit: 1 = terdeteksi, 16 = OAK Stop Pekerjaan. last = laporan terakhir (rentang data).
     * Baris kel = '' berisi laporan SAP terakhir apa pun di detail lokasi itu.
     */
    public function berandaKelompok(): string
    {
        return $this->prelude().$this->berandaDasarCte().',
bk AS (SELECT DISTINCT b.dl AS dl, h.proses AS kel FROM basek b JOIN hira h ON h.hid = b.hid),
-- Deteksi keluarga (spec dlEntB): kelompok aktivitas (M02) dari teks aktivitas OBS / "aktivitas / sub"
-- OAK yang dilaporkan di detail lokasi itu (tanpa "tidak ada aktivitas"); OAK Stop ikut ditandai.
kev AS (
  SELECT s.dl AS dl, k.kel AS kel, s.d AS d, toUInt8(s.stop) AS stop
  FROM sap s JOIN okx k ON k.tx = s.tx
  WHERE s.j IN (\'OBSERVASI\', \'OBSERVASI AREA KRITIS\') AND NOT s.na
    AND (s.dl, k.kel) IN (SELECT dl, kel FROM bk)
),
kw AS (
  SELECT dl, kel, intDiv(dateDiff(\'day\', toStartOfWeek(d, 0), wk0), 7) AS wi, groupBitOr(toUInt8(1 + 16 * stop)) AS bit
  FROM kev WHERE d >= wk_awal GROUP BY dl, kel, wi
),
kh AS (
  SELECT dl, kel, dateDiff(\'day\', h0, d) AS di, groupBitOr(toUInt8(1 + 16 * stop)) AS bit
  FROM kev WHERE d >= h0 GROUP BY dl, kel, di
),
kl AS (SELECT dl, kel, max(d) AS last FROM kev GROUP BY dl, kel),
kwa AS (SELECT dl, kel, arrayStringConcat(groupArray(concat(toString(wi), \':\', toString(bit))), \',\') AS w FROM kw GROUP BY dl, kel),
kha AS (SELECT dl, kel, arrayStringConcat(groupArray(concat(toString(di), \':\', toString(bit))), \',\') AS h FROM kh GROUP BY dl, kel)
SELECT toString(l.dl) AS dl, l.kel AS kel, ifNull(a.w, \'\') AS w, ifNull(b.h, \'\') AS h, toString(l.last) AS last
FROM kl l LEFT JOIN kwa a ON a.dl = l.dl AND a.kel = l.kel LEFT JOIN kha b ON b.dl = l.dl AND b.kel = l.kel
UNION ALL
SELECT toString(dl), \'\', \'\', \'\', toString(max(d)) FROM sap GROUP BY dl';
    }

    /**
     * Beranda PJA — bukti SPESIFIK per (detail lokasi, bahaya baseline), dijalankan per site:
     * label Hazard/Inspeksi yang cocok ke bahaya dan butir checklist OAK.
     * Bit: 2 = teridentifikasi spesifik, 4 = belum closed (status saat ini bukan CLOSED/DONE; REJECT terbuka),
     * 8 = berulang (≥ ambang laporan berlabel sama di detail lokasi itu dalam minggu yang sama),
     * 32 = butir checklist tidak sesuai (informasi, bukan gap),
     * 64 (harian) = insiden pada hari itu yang root cause-nya terpetakan ke bahaya ini.
     */
    public function berandaSpesifik(): string
    {
        $ulang = (int) $this->cfg['beranda']['ambang_berulang'];

        return $this->prelude().$this->berandaDasarCte().$this->rootCauseCte().',
lw AS (SELECT dl, k, sk, toStartOfWeek(d, 0) AS ws, sum(n) AS nl FROM sh GROUP BY dl, k, sk, ws),
sxw AS (
  SELECT e.dl AS dl, e.hid AS hid, e.d AS d, max(toUInt8(e.cd IS NULL)) AS op, max(toUInt8(l.nl >= '.$ulang.')) AS ul
  FROM (SELECT dl, k, sk, hid, d, cd, toStartOfWeek(d, 0) AS ws FROM evh) e
  JOIN lw l ON l.dl = e.dl AND l.k = e.k AND l.sk = e.sk AND l.ws = e.ws
  GROUP BY e.dl, e.hid, e.d
),
dx AS (SELECT dl, hid, d, ts FROM evd WHERE (dl, hid) IN (SELECT dl, hid FROM basek)),
ix AS (
  SELECT DISTINCT r.dl AS dl, m.hid AS hid, r.tgl AS d FROM rci r JOIN mar m ON m.k = r.k AND m.sk = r.sk
  WHERE r.tgl >= h0 AND r.tgl <= t_end AND (r.dl, m.hid) IN (SELECT dl, hid FROM basek)
),
sw AS (
  SELECT dl, hid, wi, groupBitOr(bit) AS bit FROM (
    SELECT dl, hid, intDiv(dateDiff(\'day\', toStartOfWeek(d, 0), wk0), 7) AS wi,
           toUInt8(2 + 4 * op + 8 * ul) AS bit FROM sxw WHERE d >= wk_awal
    UNION ALL
    SELECT dl, hid, intDiv(dateDiff(\'day\', toStartOfWeek(d, 0), wk0), 7), toUInt8(2 + 32 * ts) FROM dx WHERE d >= wk_awal
  ) GROUP BY dl, hid, wi
),
shd AS (
  SELECT dl, hid, di, groupBitOr(bit) AS bit FROM (
    SELECT dl, hid, dateDiff(\'day\', h0, d) AS di, toUInt8(2 + 4 * op + 8 * ul) AS bit FROM sxw WHERE d >= h0
    UNION ALL
    SELECT dl, hid, dateDiff(\'day\', h0, d), toUInt8(2 + 32 * ts) FROM dx WHERE d >= h0
    UNION ALL
    SELECT dl, hid, dateDiff(\'day\', h0, d), toUInt8(64) FROM ix
  ) GROUP BY dl, hid, di
),
swa AS (SELECT dl, hid, arrayStringConcat(groupArray(concat(toString(wi), \':\', toString(bit))), \',\') AS w FROM sw GROUP BY dl, hid),
sha AS (SELECT dl, hid, arrayStringConcat(groupArray(concat(toString(di), \':\', toString(bit))), \',\') AS h FROM shd GROUP BY dl, hid),
kunci AS (SELECT dl, hid FROM swa UNION DISTINCT SELECT dl, hid FROM sha)
SELECT toString(k.dl) AS dl, toString(k.hid) AS hid, ifNull(a.w, \'\') AS w, ifNull(b.h, \'\') AS h
FROM kunci k LEFT JOIN swa a ON a.dl = k.dl AND a.hid = k.hid LEFT JOIN sha b ON b.dl = k.dl AND b.hid = k.hid';
    }

    /**
     * Beranda PJA — panel "Insiden dan laporan SAP terkait root cause" (semua site sekaligus; kecil).
     * Root cause = obds.Hazard_Related_Incident berstatus ROOT CAUSE, insiden tahun berjalan. Laporan
     * HAZARD/INSPEKSI berlabel sama (ketidaksesuaian + sub) di detail lokasi insiden dihitung per bulan
     * kalender: 3 bulan sebelum, bulan kejadian, sesudah. Belum closed = semua laporan label itu sejak
     * 3 bulan sebelum awal tahun yang belum CLOSED/DONE (REJECT dihitung belum closed).
     */
    public function berandaInsiden(): string
    {
        $c = $this->cfg;
        $akhir = $this->sampai->toDateString();
        $sejak = $this->sampai->startOfYear()->subMonths(3)->toDateString();
        $selesai = $this->daftar(self::STATUS_SELESAI);
        $label = '(s.k = r.k AND s.sk = r.sk)';

        return '
WITH toDate('.$this->q($akhir).') AS t_end'.$this->rootCauseDasarCte().',
s0 AS (
  SELECT toInt64(`id detail lokasi`) AS dl, toDate(`date time`) AS d,
         lower(trim(ifNull(ketidaksesuaian, \'\'))) AS k, lower(trim(ifNull(subketidaksesuaian, \'\'))) AS sk,
         ifNull(status, \'\') AS st
  FROM obds.ch_rfid_sap_cuti_all_site
  WHERE jenis_data IN (\'HAZARD\', \'INSPEKSI\')
    AND `date time` >= toDate('.$this->q($sejak).') AND `date time` < t_end + 1
    AND `id detail lokasi` IN (SELECT dl FROM rci)
    AND ifNull(status, \'\') NOT IN ('.$this->daftar($c['status_buang']).')
),
ri AS (
  SELECT no, dl, tgl, any(kategori) AS kategori, any(site) AS site, any(lokasi) AS lokasi, any(detlok) AS detlok,
         k, sk, any(label) AS label, toStartOfMonth(tgl) AS bln
  FROM rci WHERE toYear(tgl) = toYear(t_end) AND sk != \'\'
  GROUP BY no, dl, tgl, k, sk
)
SELECT r.no AS no, toString(r.dl) AS dl, toString(r.tgl) AS tanggal, r.kategori AS kategori, r.site AS site,
       r.lokasi AS lokasi, r.detlok AS detlok, r.label AS label,
       countIf('.$label.' AND s.d >= addMonths(r.bln, -3) AND s.d < r.bln) AS sebelum,
       countIf('.$label.' AND s.d >= r.bln AND s.d < addMonths(r.bln, 1)) AS bulan,
       countIf('.$label.' AND s.d >= addMonths(r.bln, 1)) AS sesudah,
       countIf(s.k = r.k AND s.d >= addMonths(r.bln, -3) AND s.d < r.bln) AS k_sebelum,
       countIf(s.k = r.k AND s.d >= addMonths(r.bln, 1)) AS k_sesudah,
       toString(maxIf(s.d, '.$label.')) AS terakhir,
       countIf('.$label.' AND s.st NOT IN ('.$selesai.')) AS belum_closed
FROM ri r LEFT JOIN s0 s ON s.dl = r.dl
GROUP BY r.no, r.dl, r.tgl, r.kategori, r.site, r.lokasi, r.detlok, r.k, r.sk, r.label
ORDER BY r.tgl DESC';
    }

    /** CTE bersama Beranda: minggu kalender (Minggu awal), hari strip, basek, sh, bukti Hazard. */
    private function berandaDasarCte(): string
    {
        $b = $this->cfg['beranda'];

        return ',
toStartOfWeek(t_end, 0) AS wk0,
wk0 - '.(7 * ((int) $b['minggu'] - 1)).' AS wk_awal,
t_end - '.((int) $b['hari'] - 1).' AS h0,
basek AS (SELECT dl, hid FROM base GROUP BY dl, hid),
sh AS (
  SELECT dl, k, sk, d, cd, count() AS n FROM sap
  WHERE j IN (\'HAZARD\', \'INSPEKSI\') AND sk != \'\' GROUP BY dl, k, sk, d, cd
),
'.$this->hazardCte();
    }

    /** Root cause insiden (ROOT CAUSE) + detail lokasinya; tanpa prelude (dipakai juga oleh berandaInsiden). */
    private function rootCauseDasarCte(): string
    {
        return ',
rdl AS (
  SELECT lower(trim(site)) AS s, lower(trim(lokasi)) AS l, lower(trim(Detil_Lokasi)) AS d, any(toInt64(id_detil_lokasi)) AS dl
  FROM obds.bep_vw_site_lokasi_detil_lokasi GROUP BY s, l, d
),
rci AS (
  SELECT ifNull(r.No_Incident, \'\') AS no, toDate(parseDateTimeBestEffortUSOrNull(r.Tanggal_Kejadian)) AS tgl, x.dl AS dl,
         ifNull(r.Kategori_Incident, \'\') AS kategori, ifNull(r.Site, \'\') AS site, ifNull(r.Lokasi, \'\') AS lokasi,
         ifNull(r.Detail_Lokasi, \'\') AS detlok,
         lower(trim(ifNull(r.Ketidaksesuaian, \'\'))) AS k, lower(trim(ifNull(r.Sub_Ketidaksesuaian, \'\'))) AS sk,
         concat(trim(ifNull(r.Ketidaksesuaian, \'\')), \' › \', trim(ifNull(r.Sub_Ketidaksesuaian, \'\'))) AS label
  FROM obds.Hazard_Related_Incident AS r
  JOIN rdl x ON x.s = lower(trim(r.Site)) AND x.l = lower(trim(r.Lokasi)) AND x.d = lower(trim(r.Detail_Lokasi))
  WHERE r.Status_Causal = \'ROOT CAUSE\' AND parseDateTimeBestEffortUSOrNull(r.Tanggal_Kejadian) IS NOT NULL
    AND ifNull(r.Kategori_Incident, \'\') NOT IN ('.$this->daftar($this->cfg['insiden']['kategori_buang']).')
)';
    }

    /** Root cause + pemetaan labelnya ke bahaya (aturan sama dengan Master A); butuh prelude. */
    private function rootCauseCte(): string
    {
        $c = $this->cfg;

        return $this->rootCauseDasarCte().',
labr AS (
  SELECT k, sk, arrayFilter(t -> NOT has(tema_umum, t), arrayMap(i -> thn[i], multiMatchAllIndices(concat(k, \' \', sk), thr))) AS ta,
         arrayDistinct(arrayFilter(t -> length(t) >= 4 AND NOT has(sw, t), splitByNonAlpha(sk))) AS ts
  FROM (SELECT DISTINCT k, sk FROM rci WHERE sk != \'\')
),
mar AS (
  SELECT l.k AS k, l.sk AS sk, h.hid AS hid FROM labr l CROSS JOIN hbx h
  WHERE hasAny(l.ta, h.tb)
    AND length(arrayIntersect(l.ts, h.tsb)) >= '.(int) $c['pemetaan']['kata_min'].'
    AND length(arrayFilter(t -> NOT has((SELECT kata FROM umum_a), t), arrayIntersect(l.ts, h.tsb))) >= 1
)';
    }

    /** Temuan Hazard/Inspeksi yang masih open per tanggal akhir, di detail lokasi yang punya baseline. */
    public function temuanOpen(): string
    {
        $selesai = $this->daftar(self::STATUS_SELESAI);

        return $this->prelude().'
SELECT toString(`id detail lokasi`) AS dl, toString(toDate(`date time`)) AS tanggal, jenis_data AS jenis,
       ifNull(ketidaksesuaian, \'\') AS ket, ifNull(subketidaksesuaian, \'\') AS subket,
       substring(ifNull(deskripsi, \'\'), 1, 300) AS deskripsi, ifNull(status, \'\') AS status,
       ifNull(pic, \'\') AS pic, ifNull(pelapor, \'\') AS pelapor, ifNull(toString(nilai_resiko), \'\') AS nilai,
       ifNull(toString(toDate(tanggal_janji)), \'\') AS janji,
       arrayMap(x -> x, ifNull(mx.aktivitas, [])) AS aktivitas
FROM obds.ch_rfid_sap_cuti_all_site s
LEFT JOIN (
  SELECT m.k, m.sk, groupUniqArray(50)(h.an) AS aktivitas FROM ma m JOIN hira h ON h.hid = m.hid GROUP BY m.k, m.sk
) mx ON mx.k = lower(ifNull(s.ketidaksesuaian, \'\')) AND mx.sk = lower(ifNull(s.subketidaksesuaian, \'\'))
WHERE s.jenis_data IN (\'HAZARD\', \'INSPEKSI\')
  AND s.`date time` >= t_open AND s.`date time` < t_end + 1
  AND s.`id detail lokasi` IN (SELECT dl FROM dl)
  AND ifNull(s.status, \'\') NOT IN ('.$this->daftar($this->cfg['status_buang']).')
  AND ifNull(s.status, \'\') NOT IN ('.$selesai.')
ORDER BY tanggal DESC';
    }

    /** Insiden valid + root cause + aktivitas HIRA di detail lokasinya yang paling cocok dengan teks insiden. */
    public function insiden(): string
    {
        return $this->prelude().$this->insidenCte().'
SELECT x.no AS no, toString(x.dl) AS dl, toString(x.tgl) AS tanggal, x.kategori AS kategori, x.site AS site,
       x.lokasi AS lokasi, x.detlok AS detlok, x.kronologi AS kronologi, x.potensi AS potensi,
       x.pja_bc AS pja_bc, x.pja_mk AS pja_mk, x.akar AS akar,
       arrayMap(t -> toString(t.2), arraySlice(arrayReverseSort(groupArrayIf((c.kuat, c.hid), c.kuat > 0)), 1, 30)) AS bahaya
FROM incx x LEFT JOIN cocok c ON c.no = x.no
GROUP BY x.no, x.dl, x.tgl, x.kategori, x.site, x.lokasi, x.detlok, x.kronologi, x.potensi, x.pja_bc, x.pja_mk, x.akar
ORDER BY x.tgl DESC';
    }

    /**
     * Pasangan (detail lokasi, bahaya) yang perlu disorot: status ADA GAP, atau pernah terkait
     * insiden 12 bulan terakhir (laten = terkait insiden tapi sekarang tidak teridentifikasi SAP).
     */
    public function bahayaSorot(): string
    {
        return $this->prelude().$this->statusCte().$this->insidenCte().',
inc12 AS (
  SELECT c.hid AS hid, x.dl AS dl, count() AS n_inc, max(x.tgl) AS tgl_inc
  FROM cocok c JOIN incx x ON x.no = c.no WHERE x.tgl > t_end - 365 GROUP BY c.hid, x.dl
)
SELECT toString(st.dl) AS dl, toString(st.hid) AS hid, st.status AS status, st.open_n AS open_n, st.rep AS rep,
       st.stop AS stop, toString(st.last) AS terakhir,
       ifNull(i.n_inc, 0) AS n_inc, ifNull(toString(i.tgl_inc), \'\') AS tgl_inc
FROM st LEFT JOIN inc12 i ON i.dl = st.dl AND i.hid = st.hid
WHERE st.status = \'G\' OR ifNull(i.n_inc, 0) > 0';
    }

    private function insidenCte(): string
    {
        $buang = $this->daftar($this->cfg['insiden']['kategori_buang']);

        return ',
dlk AS (
  SELECT lower(trim(site)) AS s, lower(trim(lokasi)) AS l, lower(trim(Detil_Lokasi)) AS d, any(toInt64(id_detil_lokasi)) AS dl
  FROM obds.bep_vw_site_lokasi_detil_lokasi GROUP BY s, l, d
),
rc AS (
  SELECT No_Kecelakaan AS no,
         groupArray(concat(ifNull(Layer, \'\'), \' · \', ifNull(Activity_Layer, \'\'), \': \', ifNull(Keterangan_Layer, \'\'))) AS akar,
         arrayStringConcat(groupArray(lower(concat(ifNull(Activity_Layer, \'\'), \' \', ifNull(Keterangan_Layer, \'\')))), \' \') AS akar_tx
  FROM obds.bep_vw_beinvestigasi_rootcause GROUP BY no
),
inc AS (
  SELECT i.No_Kecelakaan AS no, k.dl AS dl, toDate(i.Tanggal_Kejadian) AS tgl, ifNull(i.Kategori_Kecelakaan, \'\') AS kategori,
         i.site AS site, i.lokasi AS lokasi, i.Detil_Lokasi AS detlok,
         substring(ifNull(i.Kronologi_Kecelakaan, \'\'), 1, 600) AS kronologi, ifNull(i.Potensi_Kejadian, \'\') AS potensi,
         ifNull(i.PJA_BC, \'\') AS pja_bc, ifNull(i.PJA_Mitra_Kerja, \'\') AS pja_mk,
         ifNull(rc.akar, []) AS akar,
         lower(concat(ifNull(i.Kronologi_Kecelakaan, \'\'), \' \', ifNull(i.Potensi_Kejadian, \'\'), \' \', ifNull(i.Fakta_Kecelakaan, \'\'), \' \', ifNull(rc.akar_tx, \'\'))) AS tx
  FROM obds.bep_vw_beinvestigasi_detail_incident i
  LEFT JOIN dlk k ON k.s = lower(trim(i.site)) AND k.l = lower(trim(i.lokasi)) AND k.d = lower(trim(i.Detil_Lokasi))
  LEFT JOIN rc ON rc.no = i.No_Kecelakaan
  WHERE i.ccr_status = '.$this->q($this->cfg['insiden']['ccr_status']).'
    AND ifNull(i.Kategori_Kecelakaan, \'\') NOT IN ('.$buang.')
),
incx AS (
  SELECT *, arrayFilter(t -> NOT has(tema_umum, t), arrayMap(i -> thn[i], multiMatchAllIndices(tx, thr))) AS ta,
         arrayDistinct(arrayFilter(t -> length(t) >= 4 AND NOT has(sw, t), splitByNonAlpha(tx))) AS ts
  FROM inc
),
-- Lokasi dulu, baru kata: kata insiden hanya dipasangkan dengan kata bahaya yang ada di
-- baseline DETAIL LOKASI insiden itu sendiri (JOIN pada dl + kata). Sisi kecil (kata insiden)
-- jadi tabel hash; pasangan baseline × kata dialirkan, sehingga memori tetap rendah.
it AS (SELECT no, toInt64(dl) AS dl, t FROM incx ARRAY JOIN ts AS t WHERE dl IS NOT NULL AND dl != 0),
ht AS (SELECT hid, t FROM hbx ARRAY JOIN tsb AS t),
bt AS (
  SELECT b.dl AS dl, b.hid AS hid, h.t AS t
  FROM (SELECT DISTINCT dl, hid FROM base WHERE dl IN (SELECT dl FROM it)) b
  JOIN ht h ON h.hid = b.hid
),
kandidat AS (
  SELECT i.no AS no, bt.dl AS dl, bt.hid AS hid, count() AS kuat, groupArray(i.t) AS kata
  FROM bt JOIN it i ON i.dl = bt.dl AND i.t = bt.t
  GROUP BY no, dl, hid
  HAVING kuat >= '.(int) $this->cfg['pemetaan']['kata_min'].' AND length(arrayFilter(x -> NOT has((SELECT kata FROM umum_a), x), kata)) >= 1
),
cocok AS (
  SELECT k.no AS no, k.hid AS hid, k.kuat AS kuat
  FROM kandidat k JOIN incx x ON x.no = k.no JOIN hbx hb ON hb.hid = k.hid
  WHERE hasAny(x.ta, hb.tb)
)';
    }

    /** Personil PJA (BC + Mitra) + jumlah laporan SAP harian 14 hari terakhir. */
    public function personil(): string
    {
        return '
WITH toDate('.$this->q($this->sampai->toDateString()).') AS t_end,
'.$this->personilCte().',
hr AS (
  SELECT kode_sid_pelapor AS sid, CAST((groupArray(d), groupArray(n)), \'Map(Date, UInt32)\') AS mp
  FROM (SELECT kode_sid_pelapor, toDate(`date time`) AS d, toUInt32(count()) AS n
        FROM obds.ch_rfid_sap_cuti_all_site
        WHERE jenis_data IN (\'HAZARD\', \'INSPEKSI\', \'OBSERVASI\', \'OBSERVASI AREA KRITIS\', \'COACHING\')
          AND `date time` >= t_end - 13 AND `date time` < t_end + 1
        GROUP BY kode_sid_pelapor, d)
  GROUP BY sid
)
SELECT o.pja_id AS pja_id, o.pja AS pja, o.tipe AS tipe, o.kategori AS kategori,
       o.sid AS sid, o.nama AS nama, o.perusahaan AS perusahaan, o.site AS site,
       arrayMap(i -> toUInt32(hr.mp[t_end - 13 + i]), range(14)) AS harian
FROM orang_pja o LEFT JOIN hr ON hr.sid = o.sid';
    }

    /**
     * Personil per PJA dari penugasan resmi `m_pja_emp` (berdasarkan ID PJA, lengkap tipe BC/Mitra
     * dan kategori Layer/Superior/Inspector). id_employee diterjemahkan ke SID lewat
     * bep_vw_safety_all_karyawan. (bep_vw_pja_employee tidak lengkap: ±420 orang di 120 PJA.)
     */
    private function personilCte(): string
    {
        return 'orang_pja AS (
  SELECT toString(pe.id_pja) AS pja_id, toInt64(pe.id_pja) AS pja_num, toInt64(ifNull(pj.parent, 0)) AS parent_num,
         ifNull(pj.name, \'\') AS pja, ifNull(t.name, \'\') AS tipe, ifNull(c.name, \'\') AS kategori,
         k.kode_sid AS sid, ifNull(k.nama, \'\') AS nama, ifNull(k.perusahaan, \'\') AS perusahaan, ifNull(k.site, \'\') AS site
  FROM (SELECT DISTINCT toInt64(id_pja) AS id_pja, toInt64(id_employee) AS id_emp, id_pja_type, category_id
        FROM obds.m_pja_emp WHERE is_active = 1) pe
  JOIN (SELECT toInt64(id) AS id, any(kode_sid) AS kode_sid, any(nama) AS nama, any(nama_perusahaan) AS perusahaan,
               any(site_dedicated) AS site
        FROM obds.bep_vw_safety_all_karyawan GROUP BY id) k ON k.id = pe.id_emp
  LEFT JOIN (SELECT id, any(name) AS name, any(id_parent) AS parent FROM obds.m_pja GROUP BY id) pj ON pj.id = pe.id_pja
  LEFT JOIN (SELECT id, any(name) AS name FROM obds.m_pja_type GROUP BY id) t ON t.id = pe.id_pja_type
  LEFT JOIN (SELECT id, any(name) AS name FROM obds.m_pja_category GROUP BY id) c ON c.id = pe.category_id
  WHERE ifNull(k.kode_sid, \'\') != \'\'
)';
    }

    /** Pemenuhan SAP mingguan (BeARC) per SID, beberapa minggu terakhir. */
    public function bearc(): string
    {
        $minggu = (int) $this->cfg['tren_minggu'];

        return '
WITH '.$this->personilCte().'
SELECT `sid pelapor all karyawan` AS sid, toString(`minggu mulai`) AS mulai, toString(`minggu selesai`) AS selesai,
       `layer pengawasan` AS layer, `bc mk` AS bcmk, `status banned` AS status_banned, toUInt8(is_banned) AS banned,
       toUInt8(position(`status banned`, \'BANNED SAP\') > 0) AS banned_sap,
       toFloat64OrNull(trim(replaceAll(`%sap`, \'%\', \'\'))) AS persen,
       toUInt32(ifNull(`target coach`, 0)) AS t_coach, toUInt32(ifNull(`n coach`, 0)) AS n_coach,
       toUInt32(ifNull(`target obs oak`, 0)) AS t_obs, toUInt32(ifNull(`n obs oak`, 0)) AS n_obs,
       toUInt32(ifNull(`target ins`, 0)) AS t_ins, toUInt32(ifNull(`n ins`, 0)) AS n_ins,
       toUInt32(ifNull(`target hzr`, 0)) AS t_hzr, toUInt32(ifNull(`n hzr`, 0)) AS n_hzr,
       toUInt8(`dihitung performa`) AS dihitung
FROM obds.ch_banned_layer_weekly
WHERE `minggu selesai` <= toDate('.$this->q($this->sampai->toDateString()).')
  AND `minggu selesai` > toDate('.$this->q($this->sampai->toDateString()).') - '.(7 * $minggu).'
  AND `sid pelapor all karyawan` IN (SELECT sid FROM orang_pja)';
    }

    /** Coverage per (PJA, detail lokasi) dalam jendela: semua pelapor vs personil PJA itu sendiri (BC + Mitra anak). */
    public function coverage(): string
    {
        return $this->prelude().',
'.$this->personilCte().',
orang AS (
  -- Personil PJA itu sendiri + personil PJA Mitra anaknya (dihitung juga untuk PJA BC induk).
  SELECT DISTINCT pja_num AS pja_id, sid FROM orang_pja
  UNION DISTINCT
  SELECT DISTINCT parent_num, sid FROM orang_pja WHERE parent_num != 0
),
lap AS (
  SELECT dl, sid, count() AS n FROM sap WHERE d >= t_win GROUP BY dl, sid
)
SELECT toString(pl.pja_id) AS pja, toString(d.dl) AS dl, sum(l.n) AS semua,
       sumIf(l.n, (pl.pja_id, l.sid) IN (SELECT pja_id, sid FROM orang)) AS dedicated
FROM pjadl pl JOIN dl d ON d.dl = pl.dl
LEFT JOIN lap l ON l.dl = d.dl
GROUP BY pl.pja_id, d.dl';
    }

    // ------------------------------------------------------------------ CTE

    /**
     * Prelude bersama. Fondasi baseline dari master DRM 2.1 (DrmMaster::literal, teks = CRC32):
     *   mh0   : baris HIRA v31 (M01) + kelompok aktivitas (M02) + kategori bahaya
     *   hira  : mh0 + atribut risiko dari mv_hira_master (cocok id_hira_master + teks bahaya)
     *   dl    : detail lokasi master (M06) dicocokkan ke bep_vw_site_lokasi_detil_lokasi lewat nama
     *   pjadl : PJA per detail lokasi = PJA lokasinya di obds.aaj_lokasi_dan_pja (M07/M08), id = m_pja
     *   ma    : label Hazard/Inspeksi → baris (M09);  mb : teks aktivitas OBS/OAK → baris (M11/M12)
     *   md    : butir checklist OAK → baris lewat tema (M15/M16) + kelompok aktivitas sama / bahaya umum
     *   evt   : teks OBS → baris lewat tema yang sama
     *   base  : baseline = tiap detail lokasi × baris grupnya (M05). Bukti di luar grup tidak dihitung
     *           (gerbang irisan: kandidat dari isi laporan ∩ baris grup lokasi laporan).
     */
    private function prelude(): string
    {
        $c = $this->cfg;
        $m = $this->lit;
        $akhir = $this->sampai->toDateString();
        // Semua data SAP dibatasi ke rentang_hari ke belakang (3 bulan) agar query ringan.
        $mulaiData = $this->sampai->subDays((int) $c['rentang_hari'] - 1)->toDateString();
        $selesai = $this->daftar(self::STATUS_SELESAI);
        $obsOak = '\'OBSERVASI\', \'OBSERVASI AREA KRITIS\'';
        $kelompok = array_filter(array_map(fn (array $p) => $p[2] === '' ? '' : '(?i)'.$p[2], $c['proses_bisnis']));

        return '
WITH
  toDate('.$this->q($akhir).') AS t_end,
  t_end - '.((int) $c['jendela_hari'] - 1).' AS t_win,
  t_end - '.((int) $c['strip_hari'] - 1).' AS t_strip,
  toDate('.$this->q($mulaiData).') AS t_open,
  '.$this->larik(array_keys($c['tema'])).' AS thn,
  '.$this->larik(array_values($c['tema'])).' AS thr,
  '.$this->larik($c['stopword']).' AS sw,
  '.$this->larik($c['pemetaan']['tema_umum']).' AS tema_umum,
  '.$this->pasangan($kelompok).' AS pb,
  '.$this->larik(array_values(array_map(fn (array $t) => $t[1], $c['tema_checklist']))).' AS tc,
  '.$this->larik(DrmMaster::KB).' AS kbn,
  '.$m['gn'].' AS gn,
  '.$m['mh'].' AS mh,
  '.$m['mg'].' AS mg,
  '.$m['m6'].' AS m6,
  '.$m['m9'].' AS m9,
  '.$m['mo'].' AS mo,
  '.$m['mt'].' AS mt,
mh0 AS (
  SELECT toUInt32(x.1) AS hid, arrayMap(i -> toInt64(i), x.2) AS idh, toUInt32(x.3) AS bc, toUInt32(x.4) AS aid,
         concat(\'K\', toString(x.5)) AS kel, kbn[x.6 + 1] AS kb, toUInt8(x.7) AS umum
  FROM (SELECT arrayJoin(mh) AS x)
),
mgx AS (SELECT toUInt32(x.1) AS gid, arrayMap(r -> toUInt32(r), x.2) AS rs FROM (SELECT arrayJoin(mg) AS x)),
m9x AS (SELECT toUInt32(x.1) AS kc, arrayMap(r -> toUInt32(r), x.2) AS rs FROM (SELECT arrayJoin(m9) AS x)),
mox AS (SELECT toUInt32(x.1) AS kc, arrayMap(r -> toUInt32(r), x.2) AS rs FROM (SELECT arrayJoin(mo) AS x)),
mtx AS (SELECT toUInt64(x.1) AS tema, arrayJoin(arrayMap(r -> toUInt32(r), x.2)) AS hid FROM (SELECT arrayJoin(mt) AS x)),
hraw AS (
  SELECT h.id_hira_master AS idh,
         CRC32('.$this->norm('JSONExtractString(c, \'bahaya_penyebab_potensial\')').') AS bc,
         trim(h.nama_aktivitas) AS an, trim(h.nama_sub_aktivitas) AS sn, trim(JSONExtractString(c, \'bahaya_penyebab_potensial\')) AS bn,
         JSONExtractString(c, \'potensi_insiden\') AS p,
         JSONExtract(ifNull(h.site, \'[]\'), \'Array(String)\') AS st,
         JSONExtractString(c, \'risiko_awal\', \'kekerapan\') AS k1, JSONExtractString(c, \'risiko_awal\', \'konsekuensi\') AS c1,
         JSONExtractString(c, \'risiko_awal\', \'nilai\') AS n1,
         JSONExtractString(c, \'risiko_residual\', \'kekerapan\') AS k2, JSONExtractString(c, \'risiko_residual\', \'konsekuensi\') AS c2,
         JSONExtractString(c, \'risiko_residual\', \'nilai\') AS n2,
         JSONExtractString(c, \'faktor_4m1e\') AS faktor, JSONExtractString(c, \'aspek\') AS aspek,
         arrayStringConcat(arrayMap(x -> concat(JSONExtractString(x, \'tipe_pengendalian\'), \': \', JSONExtractString(x, \'pengendalian_dilakukan\')),
                                    JSONExtractArrayRaw(c, \'pengendalian_ada\')), \' | \') AS kt,
         arrayStringConcat(arrayMap(x -> JSONExtractString(x, \'pengendalian_dilakukan\'), JSONExtractArrayRaw(c, \'pengendalian_ada\')), \' \') AS kd,
         toUInt8(ifNull(h.status_aktif, \'\') = \'1\') * 10000
           + multiIf(n1 = \'Significant\', 4, n1 = \'High\', 3, n1 = \'Medium\', 2, n1 = \'Low\', 1, 0) * 100
           + indexOf([\'Langka\', \'Kemungkinan Kecil\', \'Mungkin\', \'Sangat Mungkin\', \'Hampir Pasti\'], k1)
           * indexOf([\'Tidak Signifikan\', \'Minor\', \'Sedang\', \'Major\', \'Bencana\'], c1) AS sc
  FROM obds.mv_hira_master h ARRAY JOIN JSONExtractArrayRaw(assumeNotNull(h.causes)) AS c
  WHERE h.id_hira_master IN (SELECT arrayJoin(idh) FROM mh0)
),
hm AS (
  -- Atribut risiko baris master: cause HIRA dengan id_hira_master master DAN teks bahaya sama (aktif & terburuk dulu).
  SELECT m.hid AS hid, argMax(r.an, r.sc) AS an, argMax(r.sn, r.sc) AS sn, argMax(r.bn, r.sc) AS bn, argMax(r.p, r.sc) AS p,
         groupUniqArrayArray(r.st) AS sites,
         argMax(r.k1, r.sc) AS k1, argMax(r.c1, r.sc) AS c1, argMax(r.n1, r.sc) AS n1,
         argMax(r.k2, r.sc) AS k2, argMax(r.c2, r.sc) AS c2, argMax(r.n2, r.sc) AS n2,
         argMax(r.kt, r.sc) AS kt, argMax(r.kd, r.sc) AS kd, argMax(r.faktor, r.sc) AS faktor, argMax(r.aspek, r.sc) AS aspek
  FROM (SELECT hid, bc, arrayJoin(idh) AS i FROM mh0) m
  JOIN hraw r ON r.idh = m.i AND r.bc = m.bc
  GROUP BY m.hid
),
hg AS (SELECT r AS hid, groupUniqArray(gn[g]) AS grp FROM (SELECT gid AS g, arrayJoin(rs) AS r FROM mgx) GROUP BY hid),
hira AS (
  SELECT m.hid AS hid, toString(m.aid) AS a, toString(m.hid) AS s, lowerUTF8(x.bn) AS b,
         x.an AS an, x.sn AS sn, x.bn AS bn, x.p AS p, x.sites AS sites,
         x.k1 AS k1, x.c1 AS c1, if(m.kb = \'\', x.n1, m.kb) AS n1, x.k2 AS k2, x.c2 AS c2, x.n2 AS n2,
         x.kt AS kt, x.kd AS kd, x.faktor AS faktor, x.aspek AS aspek,
         g.grp AS grp, m.kel AS proses, m.umum AS umum
  FROM mh0 m LEFT JOIN hm x ON x.hid = m.hid LEFT JOIN hg g ON g.hid = m.hid
),
dl0 AS (
  SELECT toInt64(t.id_detil_lokasi) AS dl, any(t.site) AS site, any(toInt64(t.id_lokasi)) AS id_lok,
         any(t.lokasi) AS lokasi, any(t.Detil_Lokasi) AS detlok,
         any(CRC32(concat('.$this->norm('t.site').', \'|\', '.$this->norm('t.lokasi').', \'|\', '.$this->norm('t.Detil_Lokasi').'))) AS kd,
         any(CRC32(concat('.$this->norm('t.site').', \'|\', '.$this->norm('t.lokasi').'))) AS lk
  FROM obds.bep_vw_site_lokasi_detil_lokasi AS t
  WHERE ifNull(t.site, \'\') != \'\''
            // Kolom ditulis lengkap (t.site): tanpa itu ClickHouse membaca `site` di WHERE sebagai alias any(site).
            .($this->site !== null ? ' AND t.site = '.$this->q($this->site) : '').'
  GROUP BY dl
  HAVING kd IN (SELECT toUInt32(x.1) FROM (SELECT arrayJoin(m6) AS x))
),
dl AS (
  SELECT d.dl AS dl, d.site AS site, d.id_lok AS id_lok, d.lokasi AS lokasi, d.detlok AS detlok, d.lk AS lk,
         g.gid AS gid, gn[g.gid] AS grp
  FROM dl0 d JOIN (SELECT toUInt32(x.1) AS kd, toUInt32(x.2) AS gid FROM (SELECT arrayJoin(m6) AS x)) g ON g.kd = d.kd
),
'.$this->ajCte().',
mpn AS (
  SELECT nk, min(id) AS pja_id
  FROM (SELECT toInt64(id) AS id, CRC32('.$this->norm('any(name)').') AS nk FROM obds.m_pja GROUP BY id HAVING max(is_active) = 1)
  GROUP BY nk
),
pjadl AS (
  SELECT DISTINCT d.dl AS dl, p.pja_id AS pja_id FROM dl d JOIN aj ON aj.lk = d.lk JOIN mpn p ON p.nk = aj.nk
),
sap AS (
  SELECT jenis_data AS j, toDate(`date time`) AS d, toInt64(`id detail lokasi`) AS dl, kode_sid_pelapor AS sid,
         lower(ifNull(ketidaksesuaian, \'\')) AS k, lower(ifNull(subketidaksesuaian, \'\')) AS sk,
         lower(if(jenis_data = \'OBSERVASI\', ifNull(`aktivitas  OBS`, \'\'),
                  if(jenis_data = \'OBSERVASI AREA KRITIS\',
                     concat(ifNull(`aktivitas pekerjaan OAK`, \'\'), \' / \', ifNull(`sub aktivitas pekerjaan OAK`, \'\')), \'\'))) AS tx,
         positionCaseInsensitive(ifNull(`hasil OAK`, \'\'), \'stop\') > 0 AS stop,
         multiSearchAny(lower(concat(ifNull(deskripsi, \'\'), \' \', ifNull(`umpan_balik  OBS`, \'\'), \' \', ifNull(tindakan, \'\'))),
                        ['.$this->daftar((array) $c['obs_tanpa_aktivitas']).']) AS na,
         if(jenis_data = \'OBSERVASI\',
            multiMatchAllIndices(concat(\' \', lower(concat(ifNull(deskripsi, \'\'), \' \', ifNull(`umpan_balik  OBS`, \'\'))), \' \'), tc),
            CAST([], \'Array(UInt64)\')) AS tm,
         ifNull(status, \'\') AS st,
         if(ifNull(status, \'\') IN ('.$selesai.'), ifNull(toDate(tanggal_aktual_penyelesaian), toDate(`date time`)), NULL) AS cd
  FROM obds.ch_rfid_sap_cuti_all_site
  WHERE jenis_data IN (\'HAZARD\', \'INSPEKSI\', '.$obsOak.')
    AND `date time` >= toDate('.$this->q($mulaiData).') AND `date time` < t_end + 1
    AND `id detail lokasi` IN (SELECT dl FROM dl0)
    AND ifNull(status, \'\') NOT IN ('.$this->daftar($c['status_buang']).')
),
labx AS (
  SELECT k, sk, CRC32(concat('.$this->norm('k').', \' / \', '.$this->norm('sk').')) AS kc
  FROM (SELECT DISTINCT k, sk FROM sap WHERE j IN (\'HAZARD\', \'INSPEKSI\') AND sk != \'\')
),
ma AS (
  SELECT k, sk, arrayJoin(rs) AS hid FROM (SELECT l.k AS k, l.sk AS sk, m.rs AS rs FROM labx l JOIN m9x m ON m.kc = l.kc)
),
-- Teks aktivitas OBS / "aktivitas / sub" OAK: kelompok aktivitas (M02) dan pasangan baris persis (M11/M12).
okx AS (
  SELECT tx, CRC32('.$this->norm('tx').') AS kc, ifNull(nullIf(arrayFirst(r -> match(tx, r.2), pb).1, \'\'), \'K0\') AS kel
  FROM (SELECT DISTINCT tx FROM sap WHERE j IN ('.$obsOak.') AND trim(tx) NOT IN (\'\', \'/\'))
),
mb AS (
  SELECT o.tx AS tx, toString(h.aid) AS a, toString(h.hid) AS s, h.hid AS hid
  FROM (SELECT tx, arrayJoin(rs) AS hid FROM (SELECT k.tx AS tx, m.rs AS rs FROM okx k JOIN mox m ON m.kc = k.kc)) o
  JOIN mh0 h ON h.hid = o.hid
),
evo AS (
  SELECT s.dl AS dl, m.a AS a, m.s AS s, s.d AS d, max(s.stop) AS stop, 1 AS ok
  FROM sap s JOIN mb m ON m.tx = s.tx
  WHERE s.j IN ('.$obsOak.')
    AND NOT s.na
  GROUP BY s.dl, m.a, m.s, s.d
),
base AS (
  SELECT dl, arrayJoin(rs) AS hid, toUInt8(0) AS via_bukti FROM (SELECT d.dl AS dl, g.rs AS rs FROM dl d JOIN mgx g ON g.gid = d.gid)
),
-- Butir checklist OAK yang diisi dalam rentang data. Checklist versi baru tidak mengikat objek ke
-- aktivitas di master (id_act_observe kosong), jadi aktivitasnya diambil dari register (id_oak_activity).
oakr AS (
  SELECT toInt64(id) AS rid, toInt64(id_location) AS dl, toDate(submit_date) AS d, toInt64(id_oak_activity) AS aid
  FROM obds.oak_register
  WHERE submit_date >= t_open AND submit_date < t_end + 1 AND toInt64(id_location) IN (SELECT dl FROM dl0)
),
oj AS (
  SELECT r.dl AS dl, r.d AS d, r.aid AS aid, o.did AS did, o.sid AS sid
  FROM (
    SELECT toInt64(id_oak_register) AS rid, toInt64(id_detail_object) AS did, toInt64(id_suitability) AS sid
    FROM obds.oak_detail_object
    WHERE id_suitability IN ('.(int) $c['oak_checklist']['sesuai'].', '.(int) $c['oak_checklist']['tidak_sesuai'].')
      AND toInt64(id_oak_register) IN (SELECT rid FROM oakr)
  ) AS o
  JOIN oakr r ON r.rid = o.rid
),
okk AS (
  SELECT toInt64(id) AS aid, ifNull(nullIf(arrayFirst(r -> match(lower(name), r.2), pb).1, \'\'), \'K0\') AS kel
  FROM obds.m_oak_activity FINAL
),
itx AS (
  SELECT p.aid AS aid, p.did AS did, multiMatchAllIndices(concat(\' \', lower(concat(o.name, \' \', d.name)), \' \'), tc) AS tm
  FROM (SELECT DISTINCT aid, did FROM oj) AS p
  JOIN obds.m_oak_detail_objek AS d FINAL ON d.id = p.did
  JOIN obds.m_oak_objek_pengamatan AS o FINAL ON o.id = d.id_objek_pengamatan_observe
),
-- M16: butir → baris lewat tema; baris diidentifikasi bila kelompok aktivitas OAK = kelompok baris, atau bahaya umum.
md AS (
  SELECT DISTINCT i.aid AS aid, i.did AS did, r.hid AS hid
  FROM (SELECT aid, did, arrayJoin(tm) AS tema FROM itx) i
  JOIN mtx r ON r.tema = i.tema
  JOIN okk k ON k.aid = i.aid
  JOIN mh0 h ON h.hid = r.hid
  WHERE h.kel = k.kel OR h.umum = 1
),
-- Teks OBS → baris lewat tema yang sama (kelompok = kelompok teks aktivitas OBS), hanya baris baseline.
evt AS (
  SELECT s.dl AS dl, r.hid AS hid, s.d AS d
  FROM (SELECT dl, d, tx, arrayJoin(tm) AS tema FROM sap WHERE j = \'OBSERVASI\' AND NOT na AND notEmpty(tm)) s
  JOIN okx k ON k.tx = s.tx
  JOIN mtx r ON r.tema = s.tema
  JOIN mh0 h ON h.hid = r.hid
  WHERE (h.kel = k.kel OR h.umum = 1) AND (s.dl, r.hid) IN (SELECT dl, hid FROM base)
  GROUP BY s.dl, r.hid, s.d
),
-- Bukti spesifik per (detail lokasi, baris, hari) dari checklist OAK + teks OBS; ts = butir tidak sesuai (informasi).
evd AS (
  SELECT dl, hid, d, max(ts) AS ts FROM (
    SELECT j.dl AS dl, m.hid AS hid, j.d AS d, toUInt8(j.sid = '.(int) $c['oak_checklist']['tidak_sesuai'].') AS ts
    FROM oj j JOIN md m ON m.aid = j.aid AND m.did = j.did
    UNION ALL
    SELECT dl, hid, d, toUInt8(0) FROM evt
  ) GROUP BY dl, hid, d
),
-- Baris yang punya jalur identifikasi SAP di master (label M09, teks OBS/OAK M11/M12, atau tema M16).
-- Hanya penanda status X (tanpa jalur identifikasi); baseline tidak difilter.
petakan AS (
  SELECT hid FROM mh0
  WHERE hid IN (SELECT arrayJoin(rs) FROM m9x) OR hid IN (SELECT arrayJoin(rs) FROM mox) OR hid IN (SELECT hid FROM mtx)
),
-- Teks bahaya HIRA untuk pencocokan insiden / root cause (tema + kata kunci).
hbx AS (
  SELECT hid, a, s,
         arrayFilter(t -> NOT has(tema_umum, t), arrayMap(i -> thn[i], multiMatchAllIndices(concat(b, \' \', lower(p)), thr))) AS tb,
         arrayDistinct(arrayFilter(t -> length(t) >= 4 AND NOT has(sw, t), splitByNonAlpha(concat(b, \' \', lower(p))))) AS tsb
  FROM hira
),
umum_a AS (
  -- Kata yang muncul di banyak bahaya HIRA: tidak boleh menjadi satu-satunya alasan pasangan.
  SELECT groupArray(t) AS kata FROM (SELECT t, count() AS n FROM hbx ARRAY JOIN tsb AS t GROUP BY t)
  WHERE n > (SELECT count() FROM hbx) * '.(float) $c['pemetaan']['kata_umum_persen'].'
)';
    }

    /**
     * Lokasi → PJA (lk = CRC32 site|lokasi, nk = CRC32 nama PJA ternormalisasi): dari tabel master database
     * (literal lp, bisa dikoreksi) bila ada, selain itu langsung dari obds.aaj_lokasi_dan_pja.
     */
    private function ajCte(): string
    {
        if (isset($this->lit['lp'])) {
            return 'aj AS (SELECT toUInt32(x.1) AS lk, toUInt32(x.2) AS nk FROM (SELECT arrayJoin('.$this->lit['lp'].') AS x))';
        }

        return 'aj AS (
  SELECT DISTINCT CRC32(concat('.$this->norm('site').', \'|\', '.$this->norm('lokasi').')) AS lk, CRC32('.$this->norm('nama_pja').') AS nk
  FROM obds.aaj_lokasi_dan_pja WHERE ifNull(nama_pja, \'\') != \'\'
)';
    }

    /** Ekspresi SQL teks ternormalisasi (huruf kecil, spasi rapat) — pasangan DrmMaster::norm(). */
    private function norm(string $expr): string
    {
        return 'trimBoth(replaceRegexpAll(lowerUTF8(ifNull('.$expr.', \'\')), \'\\\\s+\', \' \'))';
    }

    /**
     * Status per (detail lokasi, bahaya) pada t_end + bukti per hari untuk strip (aktivitas).
     * evh   : temuan Hazard/Inspeksi terpetakan ke bahaya baseline (per hari)
     * st    : status akhir per pasangan baseline
     */
    private function statusCte(): string
    {
        $ambang = (int) $this->cfg['ambang_berulang'];

        return str_replace('{ambang}', (string) $ambang, ',
basek AS (SELECT dl, hid, max(via_bukti) AS via_bukti FROM base GROUP BY dl, hid),
sh AS (
  SELECT dl, k, sk, d, cd, count() AS n FROM sap
  WHERE j IN (\'HAZARD\', \'INSPEKSI\') AND sk != \'\' GROUP BY dl, k, sk, d, cd
),
'.$this->hazardCte().',
evh_a AS (
  SELECT e.dl AS dl, h.a AS a, e.d AS d, toUInt8(e.cd IS NULL) AS open_now FROM evh e JOIN hira h ON h.hid = e.hid
),
evo_a AS (SELECT dl, a, d, max(ok) AS ok, max(stop) AS stop FROM evo GROUP BY dl, a, d),
agd AS (
  SELECT dl, hid, countIf(d >= t_win) AS sig_d, countIf(d >= t_win AND ts = 1) AS ts_d, max(d) AS last_d
  FROM evd WHERE (dl, hid) IN (SELECT dl, hid FROM basek) GROUP BY dl, hid
),
agh AS (
  SELECT dl, hid,
         sumIf(n, d >= t_win) AS rep,
         sumIf(n, cd IS NULL OR cd > t_end) AS open_n,
         max(d) AS last_h
  FROM evh GROUP BY dl, hid
),
ago AS (
  SELECT dl, a, s, countIf(d >= t_win) AS sig_o, countIf(d >= t_win AND stop = 1) AS stop, max(d) AS last_o FROM eva GROUP BY dl, a, s
),
st AS (
  SELECT b.dl AS dl, h.hid AS hid, h.a AS a, h.n1 AS n1, h.proses AS proses, b.via_bukti AS via_bukti,
         ifNull(gh.rep, 0) AS rep, ifNull(gh.open_n, 0) AS open_n, ifNull(go.stop, 0) AS stop,
         greatest(ifNull(gh.last_h, toDate(0)), ifNull(go.last_o, toDate(0)), ifNull(gd.last_d, toDate(0))) AS last,
         -- Gap (spec DRM 2.1) = belum closed | berulang | OAK Stop. Butir checklist tidak sesuai bukan gap.
         multiIf(ifNull(gh.open_n, 0) > 0 OR ifNull(gh.rep, 0) >= {ambang} OR ifNull(go.stop, 0) > 0, \'G\',
                 ifNull(gh.rep, 0) > 0 OR ifNull(go.sig_o, 0) > 0 OR ifNull(gd.sig_d, 0) > 0, \'K\',
                 h.hid NOT IN (SELECT hid FROM petakan), \'X\',
                 \'T\') AS status
  FROM basek b
  JOIN hira h ON h.hid = b.hid
  LEFT JOIN agh gh ON gh.dl = b.dl AND gh.hid = b.hid
  LEFT JOIN ago go ON go.dl = b.dl AND go.a = h.a AND go.s = h.s
  LEFT JOIN agd gd ON gd.dl = b.dl AND gd.hid = b.hid
)');
    }

    /**
     * Bukti SAP mingguan per (detail lokasi, bahaya): sb.r = 4 (ADA GAP) atau 3 (TERKONTROL).
     * Tiap baris bukti hanya dipecah ke minggu yang dipengaruhinya (ARRAY JOIN terfilter),
     * bukan dikalikan ke semua minggu.
     */
    private function statusMingguanCte(): string
    {
        $ambang = (int) $this->cfg['ambang_berulang'];
        $win = (int) $this->cfg['jendela_hari'];
        $n = (int) $this->cfg['tren_minggu'];

        return ',
arrayMap(i -> t_end - 7 * i, range('.$n.')) AS ws,
basek AS (SELECT dl, hid FROM base GROUP BY dl, hid),
sh AS (
  SELECT dl, k, sk, d, cd, count() AS n FROM sap
  WHERE j IN (\'HAZARD\', \'INSPEKSI\') AND sk != \'\' GROUP BY dl, k, sk, d, cd
),
'.$this->hazardCte().',
eh AS (
  SELECT dl, hid, w AS minggu,
         sumIf(n, d > w - '.$win.') AS rep,
         sumIf(n, cd IS NULL OR cd > w) AS open_n
  FROM evh
  ARRAY JOIN arrayFilter(w -> d <= w AND (d > w - '.$win.' OR cd IS NULL OR cd > w), ws) AS w
  GROUP BY dl, hid, minggu
),
eo AS (
  SELECT dl, a, s, w AS minggu, max(stop) AS stop
  FROM eva
  ARRAY JOIN arrayFilter(w -> d <= w AND d > w - '.$win.', ws) AS w
  GROUP BY dl, a, s, minggu
),
ed AS (
  SELECT dl, hid, w AS minggu, max(ts) AS ts
  FROM evd
  ARRAY JOIN arrayFilter(w -> d <= w AND d > w - '.$win.', ws) AS w
  WHERE (dl, hid) IN (SELECT dl, hid FROM basek)
  GROUP BY dl, hid, minggu
),
sb AS (
  SELECT dl, hid, minggu, max(rr) AS r FROM (
    SELECT dl, hid, minggu, toUInt8(if(open_n > 0 OR rep >= '.$ambang.', 4, if(rep > 0, 3, 0))) AS rr FROM eh
    UNION ALL
    SELECT e.dl, h.hid, e.minggu, toUInt8(if(e.stop = 1, 4, 3))
    FROM eo e JOIN hira h ON h.a = e.a AND h.s = e.s
    WHERE (e.dl, h.hid) IN (SELECT dl, hid FROM basek)
    UNION ALL
    SELECT dl, hid, minggu, toUInt8(3) FROM ed
  ) WHERE rr > 0
  GROUP BY dl, hid, minggu
)';
    }

    /**
     * Bukti Hazard/Inspeksi (butuh CTE sh & basek):
     * evh  : temuan → bahaya baseline yang labelnya cocok, di detail lokasi temuan (konfirmasi bahaya).
     * spes : label SPESIFIK di detail lokasi itu = cocok ke bahaya dari ≤ N kelompok aktivitas (proses bisnis).
     * evs  : label spesifik ikut menandai aktivitas (a, s) bahaya yang cocok sebagai terdeteksi.
     * eva  : bukti tingkat aktivitas = Observasi/OAK (evo) + Hazard/Inspeksi spesifik (evs).
     */
    private function hazardCte(): string
    {
        return 'evh AS (
  SELECT sh.dl AS dl, sh.k AS k, sh.sk AS sk, m.hid AS hid, sh.d AS d, sh.cd AS cd, sh.n AS n
  FROM sh JOIN ma m ON m.k = sh.k AND m.sk = sh.sk
  WHERE (sh.dl, m.hid) IN (SELECT dl, hid FROM basek)
),
spes AS (
  SELECT e.dl AS dl, e.k AS k, e.sk AS sk FROM evh e JOIN hira h ON h.hid = e.hid
  GROUP BY e.dl, e.k, e.sk HAVING uniqExact(h.proses) <= '.(int) $this->cfg['hazard_spesifik_maks_aktivitas'].'
),
evs AS (
  SELECT DISTINCT e.dl AS dl, h.a AS a, h.s AS s, e.d AS d FROM evh e JOIN hira h ON h.hid = e.hid
  WHERE (e.dl, e.k, e.sk) IN (SELECT dl, k, sk FROM spes)
),
eva AS (
  SELECT dl, a, s, d, toUInt8(stop) AS stop FROM evo
  UNION ALL
  SELECT dl, a, s, d, toUInt8(0) FROM evs
)';
    }

    // ------------------------------------------------------------- literal

    /** @param array<string, string> $map */
    private function pasangan(array $map): string
    {
        $isi = [];
        foreach ($map as $k => $v) {
            $isi[] = '('.$this->q((string) $k).', '.$this->q((string) $v).')';
        }

        return '['.implode(', ', $isi).']';
    }

    /** @param list<string> $list */
    private function larik(array $list): string
    {
        return '['.implode(', ', array_map(fn ($v) => $this->q((string) $v), $list)).']';
    }

    /** @param list<string> $list */
    private function daftar(array $list): string
    {
        return implode(', ', array_map(fn ($v) => $this->q((string) $v), $list));
    }

    private function q(string $v): string
    {
        return "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $v)."'";
    }
}

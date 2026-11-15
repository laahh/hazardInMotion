<?php

namespace App\Services\PetaRisiko\Drm;

use Carbon\CarbonImmutable;

/**
 * Query ClickHouse OBDS untuk Peta Risiko PJA (DRM 2.1).
 *
 * Semua query berbagi satu prelude CTE:
 *   hira  : baseline bahaya-risiko unik (aktivitas, sub aktivitas, bahaya) dari mv_hira_master
 *   dl    : detail lokasi aktif + Group Lokasi (aturan kata kunci)
 *   sap   : laporan Hazard/Inspeksi/Observasi/OAK dari ch_rfid_sap_cuti_all_site
 *   ma    : Master A — label ketidaksesuaian/sub → bahaya (tema bersama + ≥1 kata kunci)
 *   mb    : Master B — teks aktivitas Observasi/OAK → aktivitas-sub HIRA
 *   base  : pasangan baseline (detail lokasi, bahaya) = grup cocok ∪ bukti Observasi/OAK
 *
 * Aturan teks disalin dari kartu Metabase 341-343 (DRM2.1); lihat config/peta_risiko_drm.php.
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

    /** @param array<string, mixed> $cfg */
    public function __construct(
        private readonly array $cfg,
        private readonly CarbonImmutable $sampai,
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
       (h.a, h.s) IN (SELECT a, s FROM mb) AS di_b
FROM hira h';
    }

    /** Detail lokasi aktif + Group Lokasi + PJA BC pemilik lokasinya. */
    public function detailLokasi(): string
    {
        return $this->prelude().'
SELECT toString(d.dl) AS dl, d.site AS site, d.id_lok AS id_lok, d.lokasi AS lokasi, d.detlok AS detlok, d.grp AS grp,
       arrayMap(x -> toString(x), groupUniqArrayIf(pl.pja_id, pl.pja_id != 0)) AS pja
FROM dl d
LEFT JOIN (SELECT DISTINCT toInt64(pja_id) AS pja_id, toInt64(location_id) AS location_id FROM obds.m_pja_locations) pl
       ON pl.location_id = d.id_lok
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
pjalok AS (
  SELECT location_id, groupUniqArray(pja_id) AS pjas
  FROM (SELECT DISTINCT toInt64(pja_id) AS pja_id, toInt64(location_id) AS location_id FROM obds.m_pja_locations)
  GROUP BY location_id
),
anggota AS (
  SELECT b.dl AS dl, b.hid AS hid,
         arrayConcat([(\'SITE\', d.site), (\'SITE\', \'ALL\'),
                      (\'GRUP\', concat(d.grp, \'|\', d.site)), (\'GRUP\', concat(d.grp, \'|ALL\')),
                      (\'PROSES\', concat(h.proses, \'|\', d.site)), (\'PROSES\', concat(h.proses, \'|ALL\'))],
                     arrayMap(x -> (\'PJA\', toString(x)), pl.pjas)) AS ks
  FROM basek b
  JOIN dl d ON d.dl = b.dl
  JOIN hira h ON h.hid = b.hid
  LEFT JOIN pjalok pl ON pl.location_id = d.id_lok
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
)
SELECT toString(h.hid) AS hid, ifNull(a.n, 0) AS n_a, a.contoh AS label_a, ifNull(b.n, 0) AS n_b, b.contoh AS teks_b
FROM hira h LEFT JOIN mapa a ON a.hid = h.hid LEFT JOIN mapb b ON b.hid = h.hid';
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
pl AS (SELECT DISTINCT toInt64(pja_id) AS pja_id, toInt64(location_id) AS location_id FROM obds.m_pja_locations),
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
FROM pl JOIN dl d ON d.id_lok = pl.location_id
LEFT JOIN lap l ON l.dl = d.dl
GROUP BY pl.pja_id, d.dl';
    }

    // ------------------------------------------------------------------ CTE

    private function prelude(): string
    {
        $c = $this->cfg;
        $akhir = $this->sampai->toDateString();
        // Semua data SAP dibatasi ke rentang_hari ke belakang (3 bulan) agar query ringan.
        $mulaiData = $this->sampai->subDays((int) $c['rentang_hari'] - 1)->toDateString();
        $selesai = $this->daftar(self::STATUS_SELESAI);

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
  '.$this->pasangan($c['grup_detail_lokasi']).' AS gd,
  '.$this->pasangan($c['grup_aktivitas']).' AS ga,
  '.$this->pasangan(array_map(fn (array $p) => $p[2], $c['proses_bisnis'])).' AS pb,
hraw AS (
  SELECT lower(trim(h.nama_aktivitas)) AS a, lower(trim(h.nama_sub_aktivitas)) AS s,
         lower(trim(JSONExtractString(c, \'bahaya_penyebab_potensial\'))) AS b,
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
         multiIf(n1 = \'Significant\', 4, n1 = \'High\', 3, n1 = \'Medium\', 2, n1 = \'Low\', 1, 0) * 100
           + indexOf([\'Langka\', \'Kemungkinan Kecil\', \'Mungkin\', \'Sangat Mungkin\', \'Hampir Pasti\'], k1)
           * indexOf([\'Tidak Signifikan\', \'Minor\', \'Sedang\', \'Major\', \'Bencana\'], c1) AS sc
  FROM obds.mv_hira_master h ARRAY JOIN JSONExtractArrayRaw(assumeNotNull(h.causes)) AS c
  WHERE h.status_aktif = \'1\' AND trim(JSONExtractString(c, \'bahaya_penyebab_potensial\')) != \'\'
),
hira AS (
  SELECT cityHash64(a, s, b) AS hid, a, s, b, any(an) AS an, any(sn) AS sn, any(bn) AS bn, argMax(p, sc) AS p,
         groupUniqArrayArray(st) AS sites,
         argMax(k1, sc) AS k1, argMax(c1, sc) AS c1, argMax(n1, sc) AS n1,
         argMax(k2, sc) AS k2, argMax(c2, sc) AS c2, argMax(n2, sc) AS n2,
         argMax(kt, sc) AS kt, argMax(faktor, sc) AS faktor, argMax(aspek, sc) AS aspek,
         arrayMap(r -> r.1, arrayFilter(r -> match(concat(a, \' \', s), r.2), ga)) AS grp,
         ifNull(nullIf(arrayFirst(r -> match(concat(a, \' \', s), r.2), pb).1, \'\'), \'X\') AS proses
  FROM hraw GROUP BY a, s, b
),
dl0 AS (
  SELECT toInt64(t.id_detil_lokasi) AS dl, any(t.site) AS site, any(toInt64(t.id_lokasi)) AS id_lok,
         any(t.lokasi) AS lokasi, any(t.Detil_Lokasi) AS detlok
  FROM obds.bep_vw_site_lokasi_detil_lokasi AS t
  WHERE t.status_detil_lokasi = \'1\' AND t.status_lokasi = \'1\' AND t.status_site = \'1\''
            // Kolom ditulis lengkap (t.site): tanpa itu ClickHouse membaca `site` di WHERE sebagai alias any(site).
            .($this->site !== null ? ' AND t.site = '.$this->q($this->site) : '').'
  GROUP BY dl
),
dl AS (
  SELECT dl, site, id_lok, lokasi, detlok,
         ifNull(nullIf(arrayFirst(r -> match(lower(concat(detlok, \' || \', lokasi)), r.2), gd).1, \'\'), \'Belum terpetakan\') AS grp
  FROM dl0
),
sap AS (
  SELECT jenis_data AS j, toDate(`date time`) AS d, toInt64(`id detail lokasi`) AS dl, kode_sid_pelapor AS sid,
         lower(ifNull(ketidaksesuaian, \'\')) AS k, lower(ifNull(subketidaksesuaian, \'\')) AS sk,
         lower(if(jenis_data = \'OBSERVASI\', ifNull(`aktivitas  OBS`, \'\'),
                  concat(ifNull(`aktivitas pekerjaan OAK`, \'\'), \' \', ifNull(`sub aktivitas pekerjaan OAK`, \'\')))) AS tx,
         ifNull(`hasil OAK`, \'\') = \'Stop Pekerjaan\' AS stop,
         ifNull(status, \'\') AS st,
         if(ifNull(status, \'\') IN ('.$selesai.'), ifNull(toDate(tanggal_aktual_penyelesaian), toDate(`date time`)), NULL) AS cd
  FROM obds.ch_rfid_sap_cuti_all_site
  WHERE jenis_data IN (\'HAZARD\', \'INSPEKSI\', \'OBSERVASI\', \'OBSERVASI AREA KRITIS\')
    AND `date time` >= toDate('.$this->q($mulaiData).') AND `date time` < t_end + 1
    AND `id detail lokasi` IN (SELECT dl FROM dl0)
    AND ifNull(status, \'\') NOT IN ('.$this->daftar($c['status_buang']).')
),
labx AS (
  SELECT k, sk, arrayFilter(t -> NOT has(tema_umum, t), arrayMap(i -> thn[i], multiMatchAllIndices(concat(k, \' \', sk), thr))) AS ta,
         arrayDistinct(arrayFilter(t -> length(t) >= 4 AND NOT has(sw, t), splitByNonAlpha(sk))) AS ts
  FROM (SELECT DISTINCT k, sk FROM sap WHERE j IN (\'HAZARD\', \'INSPEKSI\') AND sk != \'\')
),
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
),
ma AS (
  SELECT l.k AS k, l.sk AS sk, h.hid AS hid FROM labx l CROSS JOIN hbx h
  WHERE hasAny(l.ta, h.tb)
    AND length(arrayIntersect(l.ts, h.tsb)) >= '.(int) $c['pemetaan']['kata_min'].'
    AND length(arrayFilter(t -> NOT has((SELECT kata FROM umum_a), t), arrayIntersect(l.ts, h.tsb))) >= 1
),
otx AS (
  SELECT tx, arrayFilter(t -> NOT has(tema_umum, t), arrayMap(i -> thn[i], multiMatchAllIndices(tx, thr))) AS ta,
         arrayDistinct(arrayFilter(t -> length(t) >= 4 AND NOT has(sw, t), splitByNonAlpha(tx))) AS ts
  FROM (SELECT DISTINCT tx FROM sap WHERE j IN (\'OBSERVASI\', \'OBSERVASI AREA KRITIS\') AND trim(tx) != \'\')
),
asx AS (
  SELECT a, s, arrayFilter(t -> NOT has(tema_umum, t), arrayMap(i -> thn[i], multiMatchAllIndices(concat(a, \' \', s), thr))) AS tb,
         arrayDistinct(arrayFilter(t -> length(t) >= 4 AND NOT has(sw, t), splitByNonAlpha(concat(a, \' \', s)))) AS tsb
  FROM (SELECT DISTINCT a, s FROM hira)
),
umum_b AS (
  SELECT groupArray(t) AS kata FROM (SELECT t, count() AS n FROM asx ARRAY JOIN tsb AS t GROUP BY t)
  WHERE n > (SELECT count() FROM asx) * '.(float) $c['pemetaan']['kata_umum_persen'].'
),
mb AS (
  SELECT tx, a, s FROM (
    SELECT o.tx AS tx, x.a AS a, x.s AS s,
           length(arrayIntersect(o.ts, x.tsb)) + length(arrayIntersect(o.ta, x.tb)) AS kuat
    FROM otx o CROSS JOIN asx x
    WHERE length(arrayIntersect(o.ts, x.tsb)) >= '.(int) $c['master_b']['kata_min'].'
      AND length(arrayFilter(t -> NOT has((SELECT kata FROM umum_b), t), arrayIntersect(o.ts, x.tsb))) >= 1
  ) ORDER BY kuat DESC LIMIT '.(int) $c['master_b']['maks_per_teks'].' BY tx
),
evo AS (
  SELECT s.dl AS dl, m.a AS a, m.s AS s, s.d AS d, max(s.stop) AS stop, 1 AS ok
  FROM sap s JOIN mb m ON m.tx = s.tx
  WHERE s.j IN (\'OBSERVASI\', \'OBSERVASI AREA KRITIS\')
  GROUP BY s.dl, m.a, m.s, s.d
),
-- Bahaya yang BISA diidentifikasi lewat SAP: punya pasangan Master A (label Hazard/Inspeksi)
-- atau aktivitasnya punya pasangan Master B (Observasi/OAK). Hanya ini yang masuk baseline;
-- bahaya tanpa pasangan SAP tidak dihitung sama sekali (keputusan user 15 Nov 2026).
petakan AS (SELECT hid FROM hira WHERE hid IN (SELECT hid FROM ma) OR (a, s) IN (SELECT a, s FROM mb)),
base AS (
  SELECT d.dl AS dl, h.hid AS hid, 0 AS via_bukti
  FROM dl d JOIN (SELECT hid, st, g FROM hira ARRAY JOIN sites AS st ARRAY JOIN grp AS g) h ON h.st = d.site AND h.g = d.grp
  WHERE h.hid IN (SELECT hid FROM petakan)
  UNION DISTINCT
  SELECT e.dl, h.hid, 1 FROM (SELECT DISTINCT dl, a, s FROM evo) e JOIN hira h ON h.a = e.a AND h.s = e.s
  WHERE h.hid IN (SELECT hid FROM petakan)
)';
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
evh AS (
  SELECT sh.dl AS dl, m.hid AS hid, sh.d AS d, sh.cd AS cd, sh.n AS n
  FROM sh JOIN ma m ON m.k = sh.k AND m.sk = sh.sk
  WHERE (sh.dl, m.hid) IN (SELECT dl, hid FROM basek)
),
evh_a AS (
  SELECT e.dl AS dl, h.a AS a, e.d AS d, toUInt8(e.cd IS NULL) AS open_now FROM evh e JOIN hira h ON h.hid = e.hid
),
evo_a AS (SELECT dl, a, d, max(ok) AS ok, max(stop) AS stop FROM evo GROUP BY dl, a, d),
agh AS (
  SELECT dl, hid,
         sumIf(n, d >= t_win) AS rep,
         sumIf(n, cd IS NULL OR cd > t_end) AS open_n,
         max(d) AS last_h
  FROM evh GROUP BY dl, hid
),
ago AS (
  SELECT dl, a, s, countIf(d >= t_win) AS sig_o, countIf(d >= t_win AND stop) AS stop, max(d) AS last_o FROM evo GROUP BY dl, a, s
),
st AS (
  SELECT b.dl AS dl, h.hid AS hid, h.a AS a, h.n1 AS n1, h.proses AS proses, b.via_bukti AS via_bukti,
         ifNull(gh.rep, 0) AS rep, ifNull(gh.open_n, 0) AS open_n, ifNull(go.stop, 0) AS stop,
         greatest(ifNull(gh.last_h, toDate(0)), ifNull(go.last_o, toDate(0))) AS last,
         multiIf(ifNull(gh.open_n, 0) > 0 OR ifNull(gh.rep, 0) >= {ambang} OR ifNull(go.stop, 0) > 0, \'G\',
                 ifNull(gh.rep, 0) > 0 OR ifNull(go.sig_o, 0) > 0, \'K\',
                 h.hid NOT IN (SELECT hid FROM ma) AND (h.a, h.s) NOT IN (SELECT a, s FROM mb), \'X\',
                 \'T\') AS status
  FROM basek b
  JOIN hira h ON h.hid = b.hid
  LEFT JOIN agh gh ON gh.dl = b.dl AND gh.hid = b.hid
  LEFT JOIN ago go ON go.dl = b.dl AND go.a = h.a AND go.s = h.s
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
evh AS (
  SELECT sh.dl AS dl, m.hid AS hid, sh.d AS d, sh.cd AS cd, sh.n AS n
  FROM sh JOIN ma m ON m.k = sh.k AND m.sk = sh.sk
  WHERE (sh.dl, m.hid) IN (SELECT dl, hid FROM basek)
),
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
  FROM evo
  ARRAY JOIN arrayFilter(w -> d <= w AND d > w - '.$win.', ws) AS w
  GROUP BY dl, a, s, minggu
),
sb AS (
  SELECT dl, hid, minggu, max(rr) AS r FROM (
    SELECT dl, hid, minggu, toUInt8(if(open_n > 0 OR rep >= '.$ambang.', 4, if(rep > 0, 3, 0))) AS rr FROM eh
    UNION ALL
    SELECT e.dl, h.hid, e.minggu, toUInt8(if(e.stop, 4, 3))
    FROM eo e JOIN hira h ON h.a = e.a AND h.s = e.s
    WHERE (e.dl, h.hid) IN (SELECT dl, hid FROM basek)
  ) WHERE rr > 0
  GROUP BY dl, hid, minggu
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

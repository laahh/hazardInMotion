-- =====================================================================
-- Isi aturan_pelanggaran & lama_banned_hari untuk baris LAMA di
-- sid_roster_banned_master (sesudah migrasi 2026_10_28_110000).
--
--   ONSITE_70   = periode kerja > 70 hari on-site  → lama_banned_hari 14
--   BERUNTUN_13 = bekerja > 13 hari berturut-turut → lama_banned_hari 1
--
-- Hanya mengisi kolom yang masih NULL. Tidak ada baris yang dihapus, kolom
-- lain (termasuk updated_at) tidak diubah. Jalankan per langkah: lihat
-- PRATINJAU dulu, baru UPDATE, lalu cek hasilnya.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. PRATINJAU — berapa baris yang akan terisi per kelompok
-- ---------------------------------------------------------------------
SELECT
    COALESCE(aturan_pelanggaran, '(kosong)') AS aturan,
    COALESCE(CAST(lama_banned_hari AS CHAR), '(kosong)') AS lama,
    alasan_pelanggaran,
    created_by,
    COUNT(*) AS jumlah
FROM sid_roster_banned_master
GROUP BY aturan_pelanggaran, lama_banned_hari, alasan_pelanggaran, created_by
ORDER BY jumlah DESC;


START TRANSACTION;

-- ---------------------------------------------------------------------
-- 2a. Aturan sudah terisi, lama banned masih kosong → isi sesuai aturan
-- ---------------------------------------------------------------------
UPDATE sid_roster_banned_master
SET lama_banned_hari = 14
WHERE aturan_pelanggaran = 'ONSITE_70'
  AND lama_banned_hari IS NULL;

UPDATE sid_roster_banned_master
SET lama_banned_hari = 1
WHERE aturan_pelanggaran = 'BERUNTUN_13'
  AND lama_banned_hari IS NULL;

-- ---------------------------------------------------------------------
-- 2b. Aturan masih kosong tapi alasannya kepatuhan roster → dianggap
--     > 70 hari (auto-banned > 13 hari beruntun baru ada sejak kolom ini
--     dibuat, jadi baris lama berasal dari aturan > 70 hari).
--     LEWATI langkah ini bila ada baris lama yang sebenarnya kasus beruntun.
-- ---------------------------------------------------------------------
UPDATE sid_roster_banned_master
SET aturan_pelanggaran = 'ONSITE_70',
    lama_banned_hari = 14
WHERE aturan_pelanggaran IS NULL
  AND alasan_pelanggaran = 'BAN SID - Kepatuhan Roster Karyawan';

-- ---------------------------------------------------------------------
-- 3. CEK HASIL — sebelum COMMIT
--    Yang tersisa kosong = input manual dengan alasan lain (mis. "banned sid"),
--    sengaja tidak diisi karena aturannya tidak diketahui.
-- ---------------------------------------------------------------------
SELECT
    COALESCE(aturan_pelanggaran, '(kosong)') AS aturan,
    COALESCE(CAST(lama_banned_hari AS CHAR), '(kosong)') AS lama,
    COUNT(*) AS jumlah
FROM sid_roster_banned_master
GROUP BY aturan_pelanggaran, lama_banned_hari
ORDER BY aturan, lama;

-- Bila hasilnya sesuai:
COMMIT;
-- Bila tidak sesuai, jalankan ini sebagai ganti COMMIT:
-- ROLLBACK;

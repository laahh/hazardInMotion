-- Login usage audit: ringkasan login terakhir + tabel jejak login sukses/gagal.
-- JANGAN dijalankan otomatis — konfirmasi dulu sebelum:
--   mysql -u root -p health_app < mysql/migrations/018_login_audit.sql
-- Prasyarat: employee_profiles.

SET NAMES utf8mb4;

ALTER TABLE employee_profiles
  ADD COLUMN last_login_at DATETIME(3) NULL AFTER membership_tier,
  ADD COLUMN login_count INT NOT NULL DEFAULT 0 AFTER last_login_at,
  ADD COLUMN last_login_ip VARCHAR(64) NULL AFTER login_count,
  ADD COLUMN last_platform VARCHAR(24) NULL AFTER last_login_ip;

CREATE TABLE IF NOT EXISTS login_audit (
  id BIGINT NOT NULL AUTO_INCREMENT,
  user_id BIGINT NULL COMMENT 'null saat login gagal / SID tak dikenal',
  kode_sid VARCHAR(64) NULL COMMENT 'SID yang dicoba saat login',
  event VARCHAR(24) NOT NULL COMMENT 'login_success | login_failed',
  ip VARCHAR(64) NULL,
  user_agent VARCHAR(255) NULL,
  platform VARCHAR(24) NULL COMMENT 'web | android | ios',
  created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (id),
  KEY idx_login_audit_user (user_id),
  KEY idx_login_audit_created (created_at),
  KEY idx_login_audit_event (event)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

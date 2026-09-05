-- ================================================
-- MIGRATION: Alur Disposisi Berjenjang
-- ================================================

-- 1. Tabel master_jabatan
CREATE TABLE IF NOT EXISTS master_jabatan (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama        VARCHAR(100) NOT NULL,
  kode        VARCHAR(50)  NULL UNIQUE,
  level       INT UNSIGNED NOT NULL DEFAULT 1,
  deskripsi   TEXT NULL,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_level (level),
  INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Seed data master_jabatan
INSERT IGNORE INTO master_jabatan (id, nama, kode, level) VALUES
  (1, 'Staff',              'STAFF',        1),
  (2, 'Kepala Instalasi',   'KA_INSTALASI', 2),
  (3, 'Kepala Bidang',      'KABID',        3),
  (4, 'Wakil Direktur',     'WADIR',        4),
  (5, 'Direktur',           'DIREKTUR',     5),
  (6, 'Administrator',      'ADMIN',        99);

-- 3. Tabel jabatan_hierarki
CREATE TABLE IF NOT EXISTS jabatan_hierarki (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  jabatan_id  BIGINT UNSIGNED NOT NULL,
  parent_id   BIGINT UNSIGNED NOT NULL,
  UNIQUE KEY uq_child_parent (jabatan_id, parent_id),
  CONSTRAINT fk_jh_jabatan FOREIGN KEY (jabatan_id) REFERENCES master_jabatan(id) ON DELETE CASCADE,
  CONSTRAINT fk_jh_parent  FOREIGN KEY (parent_id) REFERENCES master_jabatan(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Seed data hierarki
INSERT IGNORE INTO jabatan_hierarki (jabatan_id, parent_id) VALUES
  (1, 2),  -- Staff -> Kepala Instalasi
  (2, 3),  -- Kepala Instalasi -> Kabid
  (3, 4),  -- Kabid -> Wadir
  (4, 5);  -- Wadir -> Direktur

-- 5. ALTER users - tambah jabatan_id
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS jabatan_id BIGINT UNSIGNED NULL AFTER jabatan;

-- 6. Mapping data jabatan_id ke users yang sudah ada
UPDATE users SET jabatan_id = 1 WHERE jabatan LIKE '%Staff%' AND role = 'STAF' AND jabatan_id IS NULL;
UPDATE users SET jabatan_id = 2 WHERE (jabatan LIKE '%Ka Ins%' OR jabatan LIKE '%Kepala Instalasi%') AND jabatan_id IS NULL;
UPDATE users SET jabatan_id = 3 WHERE (jabatan LIKE '%Kabid%' OR jabatan LIKE '%Kepala Bidang%') AND jabatan_id IS NULL;
UPDATE users SET jabatan_id = 4 WHERE jabatan LIKE '%Wadir%' AND jabatan_id IS NULL;
UPDATE users SET jabatan_id = 5 WHERE jabatan LIKE '%Direktur%' AND role = 'DIREKTUR' AND jabatan_id IS NULL;
UPDATE users SET jabatan_id = 6 WHERE role = 'ADMIN' AND jabatan_id IS NULL;

-- 7. ALTER disposisi - tambah is_berjenjang
ALTER TABLE disposisi
  ADD COLUMN IF NOT EXISTS is_berjenjang TINYINT(1) NOT NULL DEFAULT 0 AFTER catatan_direktur;

-- 8. ALTER disposisi_penerima - tambah urutan_level
ALTER TABLE disposisi_penerima
  ADD COLUMN IF NOT EXISTS urutan_level INT UNSIGNED NULL DEFAULT NULL AFTER user_id;

-- 9. ALTER rtl - tambah is_berjenjang
ALTER TABLE rtl
  ADD COLUMN IF NOT EXISTS is_berjenjang TINYINT(1) NOT NULL DEFAULT 0 AFTER status_progress;

-- 10. ALTER rtl_penerima - tambah urutan_level
ALTER TABLE rtl_penerima
  ADD COLUMN IF NOT EXISTS urutan_level INT UNSIGNED NULL DEFAULT NULL AFTER user_id;

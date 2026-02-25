-- ============================================================
-- SiDispo — RESET & SEED MINIMAL
-- Hapus semua data dummy, sisakan hanya 1 akun ADMIN
-- Password admin: Sidispo@2026
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- ── Bersihkan semua tabel (urutan: child → parent) ──────────
TRUNCATE TABLE notifikasi;
TRUNCATE TABLE progress_log;
TRUNCATE TABLE disposisi_penerima;
TRUNCATE TABLE disposisi;
TRUNCATE TABLE dokumen_file;
TRUNCATE TABLE surat_masuk;
TRUNCATE TABLE folders;
TRUNCATE TABLE users;

SET FOREIGN_KEY_CHECKS = 1;

-- ── Hanya 1 akun Admin ──────────────────────────────────────
-- Password: Sidispo@2026
-- Hash bcrypt: $2y$10$7vvlaOZg.zJ6K7hNasS7yeqyY7XR1lCZDYgSmqV1AMq.QhaTvGlUS
INSERT INTO users (id, nip, nama_lengkap, email, password_hash, jabatan, unit, role, is_active)
VALUES (
  1,
  '000000000000000000',
  'Administrator',
  'admin@rsud.go.id',
  '$2y$10$7vvlaOZg.zJ6K7hNasS7yeqyY7XR1lCZDYgSmqV1AMq.QhaTvGlUS',
  'Administrator Sistem',
  'IT',
  'ADMIN',
  1
);

DROP DATABASE IF EXISTS sidispo;
CREATE DATABASE sidispo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sidispo;
SET FOREIGN_KEY_CHECKS=0;

-- ================================================
-- TABEL: users
-- Pengguna & autentikasi sistem
-- ================================================
CREATE TABLE users (
  id             BIGINT UNSIGNED    NOT NULL AUTO_INCREMENT,
  nip            VARCHAR(20)        NOT NULL,
  nama_lengkap   VARCHAR(100)       NOT NULL,
  email          VARCHAR(100)       NOT NULL,
  no_hp          VARCHAR(25)        NULL,
  password_hash  VARCHAR(255)       NOT NULL,
  jabatan        VARCHAR(100)       NOT NULL,
  unit           VARCHAR(100)       NULL,
  role           ENUM('DIREKTUR','PEJABAT','STAF','ADMIN')
                                         NOT NULL DEFAULT 'STAF',
  foto_profil    VARCHAR(500)       NULL,
  is_active      BOOLEAN            NOT NULL DEFAULT TRUE,
  last_login_at  TIMESTAMP          NULL,
  created_at     TIMESTAMP          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP          NOT NULL DEFAULT CURRENT_TIMESTAMP
                                         ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_nip   (nip),
  UNIQUE KEY uq_email (email),
  KEY idx_role (role),
  KEY idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO users (id, nip, nama_lengkap, email, password_hash, jabatan, role) VALUES (1, '0000', 'Superadmin', 'admin@rsud.go.id', 'dummyhash', 'Admin', 'ADMIN');


-- ================================================
-- TABEL: folders
-- Hierarki folder dokumen (self-referencing)
-- ================================================
CREATE TABLE folders (
  id           BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  nama         VARCHAR(100)     NOT NULL,
  deskripsi    TEXT             NULL,
  parent_id    BIGINT UNSIGNED  NULL,   -- NULL = folder utama
  warna        VARCHAR(7)       NOT NULL DEFAULT '#2563eb',
  urutan       INT              NOT NULL DEFAULT 0,
  created_by   BIGINT UNSIGNED  NOT NULL,
  created_at   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY idx_parent  (parent_id),
  CONSTRAINT fk_folder_parent
    FOREIGN KEY (parent_id) REFERENCES folders(id)
    ON DELETE SET NULL,
  CONSTRAINT fk_folder_creator
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed data folder awal
INSERT INTO folders (nama, warna, created_by) VALUES
  ('Edaran BPJS Kesehatan', '#2563eb', 1),
  ('Telaah Staf Bid. Keperawatan', '#16a34a', 1),
  ('SK & Regulasi', '#7c3aed', 1),
  ('Surat Dinas / Dinkes', '#ca8a04', 1);
        


-- ================================================
-- TABEL: surat_masuk
-- ================================================
CREATE TABLE surat_masuk (
  id              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  nomor_agenda    VARCHAR(30)      NOT NULL,  -- SM-2026-00001
  nomor_surat     VARCHAR(100)     NOT NULL,
  tanggal_surat   DATE             NOT NULL,
  tanggal_terima  DATE             NOT NULL,
  asal_surat      VARCHAR(200)     NOT NULL,
  perihal         TEXT             NOT NULL,
  folder_id       BIGINT UNSIGNED  NULL,
  input_oleh      BIGINT UNSIGNED  NOT NULL,
  keterangan      TEXT             NULL,
  created_at      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_nomor_agenda (nomor_agenda),
  KEY idx_folder (folder_id),
  KEY idx_tgl (tanggal_terima),
  CONSTRAINT fk_sm_folder
    FOREIGN KEY (folder_id) REFERENCES folders(id)
    ON DELETE SET NULL,
  CONSTRAINT fk_sm_user
    FOREIGN KEY (input_oleh) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Trigger auto-generate nomor_agenda
DELIMITER $$
CREATE TRIGGER trg_nomor_agenda
  BEFORE INSERT ON surat_masuk
  FOR EACH ROW
BEGIN
  DECLARE v_seq INT;
  SELECT COALESCE(MAX(CAST(SUBSTRING(nomor_agenda,9) AS UNSIGNED)),0)+1
    INTO v_seq
    FROM surat_masuk
    WHERE nomor_agenda LIKE CONCAT('SM-',YEAR(NOW()),'-%');
  SET NEW.nomor_agenda = CONCAT('SM-',YEAR(NOW()),'-',LPAD(v_seq,5,'0'));
END$$
DELIMITER ;
        


-- ================================================
-- TABEL: dokumen_file
-- File lampiran surat
-- ================================================
CREATE TABLE dokumen_file (
  id              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  surat_masuk_id  BIGINT UNSIGNED  NOT NULL,
  nama_asli       VARCHAR(255)     NOT NULL,
  nama_file       VARCHAR(255)     NOT NULL,  -- nama di storage
  path_file       VARCHAR(500)     NOT NULL,
  mime_type       VARCHAR(100)     NOT NULL,
  ukuran_bytes    BIGINT           NOT NULL,
  upload_oleh     BIGINT UNSIGNED  NOT NULL,
  created_at      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY idx_surat (surat_masuk_id),
  CONSTRAINT fk_dok_surat
    FOREIGN KEY (surat_masuk_id) REFERENCES surat_masuk(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_dok_user
    FOREIGN KEY (upload_oleh) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        


-- ================================================
-- TABEL: disposisi  â­ TABEL UTAMA
-- ================================================
CREATE TABLE disposisi (
  id                BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  nomor_disposisi   VARCHAR(20)      NOT NULL,  -- D-260001
  surat_masuk_id    BIGINT UNSIGNED  NOT NULL,
  folder_id         BIGINT UNSIGNED  NULL,
  dibuat_oleh       BIGINT UNSIGNED  NOT NULL,
  isi_disposisi     TEXT             NOT NULL,
  prioritas         ENUM('URGENT','NORMAL','BIASA')
                                       NOT NULL DEFAULT 'NORMAL',
  batas_waktu       DATE             NULL,
  status_global     ENUM('AKTIF','SELESAI','ARSIP')
                                       NOT NULL DEFAULT 'AKTIF',
  catatan_direktur  TEXT             NULL,
  tanggal_disposisi TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                       ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_nomor (nomor_disposisi),
  KEY idx_status (status_global),
  KEY idx_deadline (batas_waktu),
  KEY idx_dibuat (dibuat_oleh),
  CONSTRAINT fk_disp_surat
    FOREIGN KEY (surat_masuk_id) REFERENCES surat_masuk(id),
  CONSTRAINT fk_disp_folder
    FOREIGN KEY (folder_id) REFERENCES folders(id)
    ON DELETE SET NULL,
  CONSTRAINT fk_disp_user
    FOREIGN KEY (dibuat_oleh) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        


-- ================================================
-- TABEL: disposisi_penerima
-- Status per penerima disposisi
-- ================================================
CREATE TABLE disposisi_penerima (
  id               BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  disposisi_id     BIGINT UNSIGNED  NOT NULL,
  user_id          BIGINT UNSIGNED  NOT NULL,
  status           ENUM('DITERIMA','PROSES','TUNGGU','SELESAI','OVERDUE')
                                      NOT NULL DEFAULT 'DITERIMA',
  tanggal_terima   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  tanggal_selesai  TIMESTAMP        NULL,
  catatan_akhir    TEXT             NULL,
  updated_at       TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                      ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_disp_user (disposisi_id, user_id),
  KEY idx_status (status),
  KEY idx_user (user_id),
  CONSTRAINT fk_dp_disposisi
    FOREIGN KEY (disposisi_id) REFERENCES disposisi(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_dp_user
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Trigger: auto update status_global disposisi
DELIMITER $$
CREATE TRIGGER trg_check_selesai
  AFTER UPDATE ON disposisi_penerima
  FOR EACH ROW
BEGIN
  DECLARE v_belum INT;
  IF NEW.status = 'SELESAI' THEN
    SELECT COUNT(*) INTO v_belum
      FROM disposisi_penerima
      WHERE disposisi_id = NEW.disposisi_id
        AND status != 'SELESAI';
    IF v_belum = 0 THEN
      UPDATE disposisi SET status_global = 'SELESAI'
        WHERE id = NEW.disposisi_id;
    END IF;
  END IF;
END$$
DELIMITER ;
        


-- ================================================
-- TABEL: progress_log
-- Log immutable riwayat perubahan status
-- ================================================
CREATE TABLE progress_log (
  id                     BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  disposisi_penerima_id  BIGINT UNSIGNED  NOT NULL,
  user_id                BIGINT UNSIGNED  NOT NULL,
  status_lama            VARCHAR(20)      NULL,
  status_baru            VARCHAR(20)      NOT NULL,
  catatan                TEXT             NULL,
  created_at             TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY idx_dp (disposisi_penerima_id),
  CONSTRAINT fk_log_dp
    FOREIGN KEY (disposisi_penerima_id)
    REFERENCES disposisi_penerima(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_log_user
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- PENTING: Tabel ini TIDAK boleh di-UPDATE atau DELETE
-- Semua operasi hanya INSERT (append-only log)
        


-- ================================================
-- TABEL: notifikasi
-- ================================================
CREATE TABLE notifikasi (
  id            BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  user_id       BIGINT UNSIGNED  NOT NULL,
  jenis         ENUM('DISPOSISI_BARU','UPDATE_PROGRESS',
                      'DEADLINE_REMINDER','SELESAI','OVERDUE')
                                   NOT NULL,
  judul         VARCHAR(200)     NOT NULL,
  pesan         TEXT             NOT NULL,
  disposisi_id  BIGINT UNSIGNED  NULL,
  dibaca_at     TIMESTAMP        NULL,  -- NULL = belum dibaca
  created_at    TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY idx_user_baca (user_id, dibaca_at),
  KEY idx_disposisi (disposisi_id),
  CONSTRAINT fk_notif_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_notif_disp
    FOREIGN KEY (disposisi_id) REFERENCES disposisi(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        


-- ================================================
-- VIEWS berguna untuk Dashboard Direktur
-- ================================================

-- View: Disposisi aktif dengan info lengkap
CREATE VIEW v_disposisi_aktif AS
SELECT
  d.id, d.nomor_disposisi,
  sm.perihal, sm.asal_surat,
  u.nama_lengkap  AS pembuat,
  f.nama          AS folder,
  d.prioritas, d.batas_waktu,
  d.status_global,
  COUNT(dp.id)    AS total_penerima,
  SUM(dp.status = 'SELESAI') AS sudah_selesai,
  DATEDIFF(d.batas_waktu, CURDATE()) AS sisa_hari
FROM disposisi d
JOIN surat_masuk sm ON d.surat_masuk_id = sm.id
JOIN users u ON d.dibuat_oleh = u.id
LEFT JOIN folders f ON d.folder_id = f.id
LEFT JOIN disposisi_penerima dp ON d.id = dp.disposisi_id
GROUP BY d.id;

-- View: Progress per unit untuk grafik batang
CREATE VIEW v_progress_per_unit AS
SELECT
  u.unit,
  COUNT(dp.id)                    AS total,
  SUM(dp.status='SELESAI')        AS selesai,
  SUM(dp.status='PROSES')         AS proses,
  SUM(dp.status='OVERDUE')        AS overdue,
  ROUND(SUM(dp.status='SELESAI')*100.0/COUNT(*),1) AS pct_selesai
FROM disposisi_penerima dp
JOIN users u ON dp.user_id = u.id
GROUP BY u.unit;

-- Index tambahan untuk performa query dashboard
CREATE INDEX idx_disp_tgl
  ON disposisi(tanggal_disposisi);
CREATE INDEX idx_disp_prioritas
  ON disposisi(prioritas, status_global);
CREATE INDEX idx_notif_unread
  ON notifikasi(user_id) ;
        




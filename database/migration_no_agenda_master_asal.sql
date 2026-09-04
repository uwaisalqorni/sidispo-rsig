-- Migration: Make nomor_agenda nullable, update trigger, create master_asal_surat

ALTER TABLE surat_masuk MODIFY nomor_agenda VARCHAR(30) NULL DEFAULT NULL;

DROP TRIGGER IF EXISTS trg_nomor_agenda;

DELIMITER $$
CREATE TRIGGER trg_nomor_agenda
BEFORE INSERT ON surat_masuk
FOR EACH ROW
BEGIN
  DECLARE v_seq INT;
  IF NEW.nomor_agenda = '__NONE__' OR NEW.nomor_agenda = '' THEN
    SET NEW.nomor_agenda = NULL;
  ELSEIF NEW.nomor_agenda = '__AUTO__' OR NEW.nomor_agenda IS NULL THEN
    SELECT COALESCE(MAX(CAST(SUBSTRING(nomor_agenda,9) AS UNSIGNED)),0)+1
      INTO v_seq
      FROM surat_masuk
      WHERE nomor_agenda LIKE CONCAT('SM-',YEAR(NOW()),'-%');
    SET NEW.nomor_agenda = CONCAT('SM-',YEAR(NOW()),'-',LPAD(v_seq,5,'0'));
  END IF;
END$$
DELIMITER ;

CREATE TABLE IF NOT EXISTS master_asal_surat (
  id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(255) NOT NULL,
  kode VARCHAR(50) NULL,
  kategori VARCHAR(100) NULL,
  alamat TEXT NULL,
  kontak VARCHAR(100) NULL,
  keterangan TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by BIGINT(20) UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_asal_active (is_active),
  INDEX idx_asal_nama (nama)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO master_asal_surat (nama, kode, kategori, keterangan, is_active)
SELECT * FROM (
  SELECT 'BPJS Kesehatan Cabang Malang' AS nama, 'BPJS' AS kode, 'Asuransi' AS kategori, 'Badan Penyelenggara Jaminan Sosial Kesehatan' AS keterangan, 1 AS is_active
  UNION ALL
  SELECT 'Dinas Kesehatan Kabupaten Malang', 'DINKES', 'Pemerintah', 'Dinas Kesehatan Kab. Malang', 1
  UNION ALL
  SELECT 'Kementerian Kesehatan RI', 'KEMENKES', 'Pemerintah', 'Kementerian Kesehatan Republik Indonesia', 1
  UNION ALL
  SELECT 'RSUD Kanjuruhan Kepanjen', 'RSUD', 'Fasilitas Kesehatan', 'Rumah Sakit Umum Daerah Kanjuruhan', 1
  UNION ALL
  SELECT 'Puskesmas Gondanglegi', 'PKM', 'Fasilitas Kesehatan', 'Puskesmas Wilayah Gondanglegi', 1
) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM master_asal_surat LIMIT 1);

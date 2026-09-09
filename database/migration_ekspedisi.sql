-- ============================================================
-- Migration: Fitur Ekspedisi Surat (Digital & Fisik)
-- ============================================================

CREATE TABLE IF NOT EXISTS `ekspedisi` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nomor_ekspedisi` varchar(50) NOT NULL,
  `disposisi_id` bigint(20) unsigned NOT NULL,
  `surat_masuk_id` bigint(20) unsigned NOT NULL,
  `pengirim_id` bigint(20) unsigned NOT NULL,
  `jenis_pengiriman` enum('DIGITAL','FISIK') NOT NULL DEFAULT 'DIGITAL',
  `status_global` enum('PENDING','PARTIAL','RECEIVED','REJECTED') NOT NULL DEFAULT 'PENDING',
  `catatan` text DEFAULT NULL,
  `tanggal_kirim` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_no_ekspedisi` (`nomor_ekspedisi`),
  KEY `idx_disp` (`disposisi_id`),
  KEY `idx_surat` (`surat_masuk_id`),
  KEY `idx_pengirim` (`pengirim_id`),
  KEY `idx_status` (`status_global`),
  CONSTRAINT `fk_eksp_disp` FOREIGN KEY (`disposisi_id`) REFERENCES `disposisi` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_eksp_surat` FOREIGN KEY (`surat_masuk_id`) REFERENCES `surat_masuk` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_eksp_user` FOREIGN KEY (`pengirim_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ekspedisi_tujuan` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ekspedisi_id` bigint(20) unsigned NOT NULL,
  `user_tujuan_id` bigint(20) unsigned DEFAULT NULL,
  `unit_tujuan` varchar(100) DEFAULT NULL,
  `status` enum('PENDING','RECEIVED','REJECTED') NOT NULL DEFAULT 'PENDING',
  `received_at` timestamp NULL DEFAULT NULL,
  `received_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `rejected_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `alasan_tolak` text DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_eksp_id` (`ekspedisi_id`),
  KEY `idx_unit` (`unit_tujuan`),
  KEY `idx_status` (`status`),
  KEY `fk_et_rec_user` (`received_by_user_id`),
  KEY `fk_et_rej_user` (`rejected_by_user_id`),
  CONSTRAINT `fk_et_eksp` FOREIGN KEY (`ekspedisi_id`) REFERENCES `ekspedisi` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_et_rec_user` FOREIGN KEY (`received_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_et_rej_user` FOREIGN KEY (`rejected_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Menambahkan kolom ekspedisi_id & mengubah kolom jenis notifikasi jika belum
ALTER TABLE `notifikasi` MODIFY COLUMN `jenis` varchar(50) NOT NULL;
ALTER TABLE `notifikasi` ADD COLUMN IF NOT EXISTS `ekspedisi_id` bigint(20) unsigned NULL DEFAULT NULL AFTER `disposisi_id`;

-- MariaDB dump 10.19  Distrib 10.4.22-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: sidispo
-- ------------------------------------------------------
-- Server version	10.4.22-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `app_settings`
--

DROP TABLE IF EXISTS `app_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `app_settings` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `disposisi`
--

DROP TABLE IF EXISTS `disposisi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `disposisi` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nomor_disposisi` varchar(20) NOT NULL,
  `surat_masuk_id` bigint(20) unsigned NOT NULL,
  `folder_id` bigint(20) unsigned DEFAULT NULL,
  `dibuat_oleh` bigint(20) unsigned NOT NULL,
  `isi_disposisi` text NOT NULL,
  `prioritas` enum('URGENT','NORMAL','BIASA') NOT NULL DEFAULT 'NORMAL',
  `batas_waktu` date DEFAULT NULL,
  `status_global` enum('AKTIF','SELESAI','ARSIP') NOT NULL DEFAULT 'AKTIF',
  `catatan_direktur` text DEFAULT NULL,
  `is_berjenjang` tinyint(1) NOT NULL DEFAULT 0,
  `tanggal_disposisi` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nomor` (`nomor_disposisi`),
  KEY `idx_status` (`status_global`),
  KEY `idx_deadline` (`batas_waktu`),
  KEY `idx_dibuat` (`dibuat_oleh`),
  KEY `fk_disp_surat` (`surat_masuk_id`),
  KEY `fk_disp_folder` (`folder_id`),
  KEY `idx_disp_tgl` (`tanggal_disposisi`),
  KEY `idx_disp_prioritas` (`prioritas`,`status_global`),
  CONSTRAINT `fk_disp_folder` FOREIGN KEY (`folder_id`) REFERENCES `folders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_disp_surat` FOREIGN KEY (`surat_masuk_id`) REFERENCES `surat_masuk` (`id`),
  CONSTRAINT `fk_disp_user` FOREIGN KEY (`dibuat_oleh`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `disposisi_penerima`
--

DROP TABLE IF EXISTS `disposisi_penerima`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `disposisi_penerima` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `disposisi_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `urutan_level` int(10) unsigned DEFAULT NULL,
  `status` enum('DITERIMA','PROSES','TUNGGU','SELESAI','OVERDUE') NOT NULL DEFAULT 'DITERIMA',
  `tanggal_terima` timestamp NOT NULL DEFAULT current_timestamp(),
  `tanggal_selesai` timestamp NULL DEFAULT NULL,
  `catatan_akhir` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_disp_user` (`disposisi_id`,`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `fk_dp_disposisi` FOREIGN KEY (`disposisi_id`) REFERENCES `disposisi` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = cp850 */ ;
/*!50003 SET character_set_results = cp850 */ ;
/*!50003 SET collation_connection  = cp850_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trg_check_selesai
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
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `dokumen_file`
--

DROP TABLE IF EXISTS `dokumen_file`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dokumen_file` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `surat_masuk_id` bigint(20) unsigned NOT NULL,
  `nama_asli` varchar(255) NOT NULL,
  `nama_file` varchar(255) NOT NULL,
  `path_file` varchar(500) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `ukuran_bytes` bigint(20) NOT NULL,
  `upload_oleh` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_surat` (`surat_masuk_id`),
  KEY `fk_dok_user` (`upload_oleh`),
  CONSTRAINT `fk_dok_surat` FOREIGN KEY (`surat_masuk_id`) REFERENCES `surat_masuk` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dok_user` FOREIGN KEY (`upload_oleh`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ekspedisi`
--

DROP TABLE IF EXISTS `ekspedisi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ekspedisi` (
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
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ekspedisi_tujuan`
--

DROP TABLE IF EXISTS `ekspedisi_tujuan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ekspedisi_tujuan` (
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
  KEY `idx_user_tujuan` (`user_tujuan_id`),
  CONSTRAINT `fk_et_eksp` FOREIGN KEY (`ekspedisi_id`) REFERENCES `ekspedisi` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_et_rec_user` FOREIGN KEY (`received_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_et_rej_user` FOREIGN KEY (`rejected_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_et_user_tujuan` FOREIGN KEY (`user_tujuan_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `folders`
--

DROP TABLE IF EXISTS `folders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `folders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nama` varchar(100) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `warna` varchar(7) NOT NULL DEFAULT '#2563eb',
  `urutan` int(11) NOT NULL DEFAULT 0,
  `created_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_parent` (`parent_id`),
  KEY `fk_folder_creator` (`created_by`),
  CONSTRAINT `fk_folder_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_folder_parent` FOREIGN KEY (`parent_id`) REFERENCES `folders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jabatan_hierarki`
--

DROP TABLE IF EXISTS `jabatan_hierarki`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jabatan_hierarki` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `jabatan_id` bigint(20) unsigned NOT NULL,
  `parent_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_child_parent` (`jabatan_id`,`parent_id`),
  KEY `fk_jh_parent` (`parent_id`),
  CONSTRAINT `fk_jh_jabatan` FOREIGN KEY (`jabatan_id`) REFERENCES `master_jabatan` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_jh_parent` FOREIGN KEY (`parent_id`) REFERENCES `master_jabatan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `master_asal_surat`
--

DROP TABLE IF EXISTS `master_asal_surat`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `master_asal_surat` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kode` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kategori` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alamat` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kontak` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_asal_active` (`is_active`),
  KEY `idx_asal_nama` (`nama`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `master_jabatan`
--

DROP TABLE IF EXISTS `master_jabatan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `master_jabatan` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nama` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kode` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `level` int(10) unsigned NOT NULL DEFAULT 1,
  `deskripsi` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode` (`kode`),
  KEY `idx_level` (`level`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `master_perihal`
--

DROP TABLE IF EXISTS `master_perihal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `master_perihal` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nama` varchar(300) COLLATE utf8mb4_unicode_ci NOT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_active` (`is_active`),
  KEY `fk_perihal_user` (`created_by`),
  CONSTRAINT `fk_perihal_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notifikasi`
--

DROP TABLE IF EXISTS `notifikasi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifikasi` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `jenis` varchar(50) NOT NULL,
  `judul` varchar(200) NOT NULL,
  `pesan` text NOT NULL,
  `disposisi_id` bigint(20) unsigned DEFAULT NULL,
  `ekspedisi_id` bigint(20) unsigned DEFAULT NULL,
  `dibaca_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_baca` (`user_id`,`dibaca_at`),
  KEY `idx_disposisi` (`disposisi_id`),
  KEY `idx_notif_unread` (`user_id`),
  CONSTRAINT `fk_notif_disp` FOREIGN KEY (`disposisi_id`) REFERENCES `disposisi` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=76 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `progress_log`
--

DROP TABLE IF EXISTS `progress_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `progress_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `disposisi_penerima_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `status_lama` varchar(20) DEFAULT NULL,
  `status_baru` varchar(20) NOT NULL,
  `catatan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_dp` (`disposisi_penerima_id`),
  KEY `fk_log_user` (`user_id`),
  CONSTRAINT `fk_log_dp` FOREIGN KEY (`disposisi_penerima_id`) REFERENCES `disposisi_penerima` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rtl`
--

DROP TABLE IF EXISTS `rtl`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rtl` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `disposisi_id` bigint(20) unsigned NOT NULL,
  `deskripsi_rtl` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `prioritas` enum('Biasa','Penting','Segera','Rahasia') COLLATE utf8mb4_unicode_ci DEFAULT 'Biasa',
  `batas_waktu` date DEFAULT NULL,
  `status_progress` enum('TO_DO','ON_PROGRESS','REVIEW','DONE') COLLATE utf8mb4_unicode_ci DEFAULT 'TO_DO',
  `is_berjenjang` tinyint(1) NOT NULL DEFAULT 0,
  `dibuat_oleh` bigint(20) unsigned NOT NULL,
  `dibuat_at` datetime DEFAULT current_timestamp(),
  `diupdate_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `disposisi_id` (`disposisi_id`),
  KEY `dibuat_oleh` (`dibuat_oleh`),
  CONSTRAINT `rtl_ibfk_1` FOREIGN KEY (`disposisi_id`) REFERENCES `disposisi` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rtl_ibfk_2` FOREIGN KEY (`dibuat_oleh`) REFERENCES `users` (`id`) ON DELETE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rtl_penerima`
--

DROP TABLE IF EXISTS `rtl_penerima`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rtl_penerima` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `rtl_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `urutan_level` int(10) unsigned DEFAULT NULL,
  `status` enum('TO_DO','ON_PROGRESS','REVIEW','DONE') COLLATE utf8mb4_unicode_ci DEFAULT 'TO_DO',
  PRIMARY KEY (`id`),
  KEY `rtl_id` (`rtl_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `rtl_penerima_ibfk_1` FOREIGN KEY (`rtl_id`) REFERENCES `rtl` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rtl_penerima_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rtl_progress`
--

DROP TABLE IF EXISTS `rtl_progress`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rtl_progress` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `rtl_id` bigint(20) unsigned NOT NULL,
  `rtl_penerima_id` bigint(20) unsigned DEFAULT NULL,
  `status_baru` enum('TO_DO','ON_PROGRESS','REVIEW','DONE') COLLATE utf8mb4_unicode_ci NOT NULL,
  `catatan` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dibuat_oleh` bigint(20) unsigned NOT NULL,
  `dibuat_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `rtl_id` (`rtl_id`),
  KEY `dibuat_oleh` (`dibuat_oleh`),
  KEY `fk_rtl_penerima` (`rtl_penerima_id`),
  CONSTRAINT `fk_rtl_penerima` FOREIGN KEY (`rtl_penerima_id`) REFERENCES `rtl_penerima` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rtl_progress_ibfk_1` FOREIGN KEY (`rtl_id`) REFERENCES `rtl` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rtl_progress_ibfk_2` FOREIGN KEY (`dibuat_oleh`) REFERENCES `users` (`id`) ON DELETE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `surat_masuk`
--

DROP TABLE IF EXISTS `surat_masuk`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `surat_masuk` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nomor_agenda` varchar(30) DEFAULT NULL,
  `nomor_surat` varchar(100) NOT NULL,
  `tanggal_surat` date NOT NULL,
  `tanggal_terima` date NOT NULL,
  `asal_surat` varchar(200) NOT NULL,
  `perihal` text NOT NULL,
  `folder_id` bigint(20) unsigned DEFAULT NULL,
  `input_oleh` bigint(20) unsigned NOT NULL,
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nomor_agenda` (`nomor_agenda`),
  KEY `idx_folder` (`folder_id`),
  KEY `idx_tgl` (`tanggal_terima`),
  KEY `fk_sm_user` (`input_oleh`),
  CONSTRAINT `fk_sm_folder` FOREIGN KEY (`folder_id`) REFERENCES `folders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sm_user` FOREIGN KEY (`input_oleh`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8 */ ;
/*!50003 SET character_set_results = utf8 */ ;
/*!50003 SET collation_connection  = utf8_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trg_nomor_agenda
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
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nip` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama_lengkap` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `no_hp` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jabatan` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jabatan_id` bigint(20) unsigned DEFAULT NULL,
  `unit` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` enum('DIREKTUR','PEJABAT','STAF','ADMIN') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'STAF',
  `foto_profil` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nip` (`nip`),
  UNIQUE KEY `uq_email` (`email`),
  KEY `idx_role` (`role`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary table structure for view `v_disposisi_aktif`
--

DROP TABLE IF EXISTS `v_disposisi_aktif`;
/*!50001 DROP VIEW IF EXISTS `v_disposisi_aktif`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE TABLE `v_disposisi_aktif` (
  `id` tinyint NOT NULL,
  `nomor_disposisi` tinyint NOT NULL,
  `perihal` tinyint NOT NULL,
  `asal_surat` tinyint NOT NULL,
  `pembuat` tinyint NOT NULL,
  `folder` tinyint NOT NULL,
  `prioritas` tinyint NOT NULL,
  `batas_waktu` tinyint NOT NULL,
  `status_global` tinyint NOT NULL,
  `total_penerima` tinyint NOT NULL,
  `sudah_selesai` tinyint NOT NULL,
  `sisa_hari` tinyint NOT NULL
) ENGINE=MyISAM */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_progress_per_unit`
--

DROP TABLE IF EXISTS `v_progress_per_unit`;
/*!50001 DROP VIEW IF EXISTS `v_progress_per_unit`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE TABLE `v_progress_per_unit` (
  `unit` tinyint NOT NULL,
  `total` tinyint NOT NULL,
  `selesai` tinyint NOT NULL,
  `proses` tinyint NOT NULL,
  `overdue` tinyint NOT NULL,
  `pct_selesai` tinyint NOT NULL
) ENGINE=MyISAM */;
SET character_set_client = @saved_cs_client;

--
-- Dumping events for database 'sidispo'
--

--
-- Dumping routines for database 'sidispo'
--

--
-- Final view structure for view `v_disposisi_aktif`
--

/*!50001 DROP TABLE IF EXISTS `v_disposisi_aktif`*/;
/*!50001 DROP VIEW IF EXISTS `v_disposisi_aktif`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = cp850 */;
/*!50001 SET character_set_results     = cp850 */;
/*!50001 SET collation_connection      = cp850_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_disposisi_aktif` AS select `d`.`id` AS `id`,`d`.`nomor_disposisi` AS `nomor_disposisi`,`sm`.`perihal` AS `perihal`,`sm`.`asal_surat` AS `asal_surat`,`u`.`nama_lengkap` AS `pembuat`,`f`.`nama` AS `folder`,`d`.`prioritas` AS `prioritas`,`d`.`batas_waktu` AS `batas_waktu`,`d`.`status_global` AS `status_global`,count(`dp`.`id`) AS `total_penerima`,sum(`dp`.`status` = 'SELESAI') AS `sudah_selesai`,to_days(`d`.`batas_waktu`) - to_days(curdate()) AS `sisa_hari` from ((((`disposisi` `d` join `surat_masuk` `sm` on(`d`.`surat_masuk_id` = `sm`.`id`)) join `users` `u` on(`d`.`dibuat_oleh` = `u`.`id`)) left join `folders` `f` on(`d`.`folder_id` = `f`.`id`)) left join `disposisi_penerima` `dp` on(`d`.`id` = `dp`.`disposisi_id`)) group by `d`.`id` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_progress_per_unit`
--

/*!50001 DROP TABLE IF EXISTS `v_progress_per_unit`*/;
/*!50001 DROP VIEW IF EXISTS `v_progress_per_unit`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = cp850 */;
/*!50001 SET character_set_results     = cp850 */;
/*!50001 SET collation_connection      = cp850_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_progress_per_unit` AS select `u`.`unit` AS `unit`,count(`dp`.`id`) AS `total`,sum(`dp`.`status` = 'SELESAI') AS `selesai`,sum(`dp`.`status` = 'PROSES') AS `proses`,sum(`dp`.`status` = 'OVERDUE') AS `overdue`,round(sum(`dp`.`status` = 'SELESAI') * 100.0 / count(0),1) AS `pct_selesai` from (`disposisi_penerima` `dp` join `users` `u` on(`dp`.`user_id` = `u`.`id`)) group by `u`.`unit` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

-- ============================================================================
-- SEED DATA (sanitized - sensitive data replaced with placeholders)
-- ============================================================================

LOCK TABLES `app_settings` WRITE;
INSERT INTO `app_settings` VALUES (1,'nama_rs','RSI GONDANGLEGI','2026-02-23 15:54:04'),(2,'alamat_rs','Jl. Hayam Wuruk No 66 Gondanglegi Kulon , Kec. Gondanglegi, Kabupaten Malang, Jawa Timur 65174, Indonesia','2026-03-03 02:43:12'),(3,'telp_rs','08xxxxxxxxxx','2026-03-03 02:43:12'),(4,'email_rs','admin@example.com','2026-05-07 02:22:39'),(5,'logo_rs','','2026-02-23 15:54:04'),(6,'batas_waktu_default','7','2026-02-23 15:54:04'),(7,'notif_email','1','2026-09-03 03:21:04'),(8,'notif_system','1','2026-02-23 15:54:04'),(9,'jwt_expired_hours','24','2026-02-23 15:54:04'),(10,'max_upload_mb','20','2026-09-04 02:03:00'),(11,'versi_aplikasi','1.1.0','2026-09-03 02:57:10'),(12,'maintenance_mode','0','2026-02-23 15:54:04'),(13,'smtp_host','smtp.gmail.com','2026-09-03 03:21:04'),(14,'smtp_port','587','2026-09-03 03:21:04'),(15,'smtp_user','your_email@gmail.com','2026-09-03 03:21:04'),(16,'smtp_pass','your_app_password_here','2026-09-03 03:21:04'),(17,'smtp_crypto','tls','2026-09-03 03:21:04');
UNLOCK TABLES;
LOCK TABLES `folders` WRITE;
INSERT INTO `folders` VALUES (1,'Kemenkes','ERM',NULL,'#1565c0',1,1,'2026-02-23 16:42:46'),(2,'ERM','ER',1,'#1565c0',1,1,'2026-02-24 03:37:50'),(3,'BPJS','BPJS',NULL,'#6a1b9a',2,1,'2026-02-24 06:37:08');
UNLOCK TABLES;
LOCK TABLES `jabatan_hierarki` WRITE;
INSERT INTO `jabatan_hierarki` VALUES (9,1,2),(2,2,3),(3,3,4),(4,4,5);
UNLOCK TABLES;
LOCK TABLES `master_asal_surat` WRITE;
INSERT INTO `master_asal_surat` VALUES (1,'BPJS Kesehatan Cabang Malang','BPJS','Asuransi',NULL,NULL,'Badan Penyelenggara Jaminan Sosial Kesehatan',1,NULL,'2026-09-03 07:40:11','2026-09-03 07:40:11'),(2,'Dinas Kesehatan Kabupaten Malang','DINKES','Pemerintah',NULL,NULL,'Dinas Kesehatan Kab. Malang',1,NULL,'2026-09-03 07:40:11','2026-09-03 07:40:11'),(3,'Kementerian Kesehatan RI','KEMENKES','Pemerintah',NULL,NULL,'Kementerian Kesehatan Republik Indonesia',1,NULL,'2026-09-03 07:40:11','2026-09-03 07:40:11'),(4,'RSUD Kanjuruhan Kepanjen','RSUD','Fasilitas Kesehatan',NULL,NULL,'Rumah Sakit Umum Daerah Kanjuruhan',1,NULL,'2026-09-03 07:40:11','2026-09-03 07:40:11'),(5,'Puskesmas Gondanglegi','PKM','Fasilitas Kesehatan',NULL,NULL,'Puskesmas Wilayah Gondanglegi',1,NULL,'2026-09-03 07:40:11','2026-09-03 07:40:11');
UNLOCK TABLES;
LOCK TABLES `master_jabatan` WRITE;
INSERT INTO `master_jabatan` VALUES (1,'Staff','STAFF',1,'',1,'2026-09-05 03:22:37','2026-09-05 04:05:15'),(2,'Kepala Instalasi','KA_INSTALASI',2,NULL,1,'2026-09-05 03:22:37','2026-09-05 03:22:37'),(3,'Kepala Bidang','KABID',3,NULL,1,'2026-09-05 03:22:37','2026-09-05 03:22:37'),(4,'Wakil Direktur','WADIR',4,NULL,1,'2026-09-05 03:22:37','2026-09-05 03:22:37'),(5,'Direktur','DIREKTUR',5,NULL,1,'2026-09-05 03:22:37','2026-09-05 03:22:37'),(6,'Administrator','ADMIN',99,NULL,1,'2026-09-05 03:22:37','2026-09-05 03:22:37');
UNLOCK TABLES;
LOCK TABLES `master_perihal` WRITE;
INSERT INTO `master_perihal` VALUES (1,'Undangan Rapat','Surat undangan untuk menghadiri rapat',1,NULL,'2026-05-25 06:22:47','2026-05-25 06:22:47'),(2,'Permohonan Cuti','Surat permohonan izin cuti pegawai',1,NULL,'2026-05-25 06:22:47','2026-05-25 06:22:47'),(3,'Laporan Kegiatan','Laporan pelaksanaan kegiatan',1,NULL,'2026-05-25 06:22:47','2026-05-25 06:22:47'),(4,'Surat Tugas','Surat penugasan pegawai',1,NULL,'2026-05-25 06:22:47','2026-05-25 06:22:47'),(5,'Pemberitahuan','Surat pemberitahuan resmi',1,NULL,'2026-05-25 06:22:47','2026-05-25 06:22:47'),(6,'Permohonan Dana','Permohonan pencairan atau pengajuan dana',1,NULL,'2026-05-25 06:22:47','2026-05-25 06:22:47'),(7,'Koordinasi Lintas Bidang','Koordinasi antar bidang atau unit kerja',1,NULL,'2026-05-25 06:22:47','2026-05-25 06:22:47'),(8,'Surat Edaran','Edaran kebijakan atau informasi',1,NULL,'2026-05-25 06:22:47','2026-05-25 06:22:47'),(9,'Persetujuan','Surat persetujuan suatu kegiatan atau pengajuan',1,NULL,'2026-05-25 06:22:47','2026-05-25 06:22:47'),(10,'Tindak Lanjut Disposisi','Tindak lanjut atas disposisi pimpinan',1,NULL,'2026-05-25 06:22:47','2026-05-25 06:22:47'),(11,'RKA','RaaaaKaaaaruAaaaannnnn',1,1,'2026-05-25 06:32:34','2026-05-25 06:32:34');
UNLOCK TABLES;

--
-- Sample admin user (password: password123 -- CHANGE AFTER DEPLOY)
-- password_hash = bcrypt('password123')
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` (`id`,`nip`,`nama_lengkap`,`email`,`no_hp`,`password_hash`,`jabatan`,`jabatan_id`,`unit`,`role`,`foto_profil`,`is_active`,`last_login_at`,`created_at`,`updated_at`) VALUES
  (1,'000000000000','Administrator','admin@sidispo.local',NULL,'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Administrator',6,NULL,'ADMIN',NULL,1,NULL,NOW(),NOW());
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

-- ============================================================================

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-10 10:46:38

-- Dump completed

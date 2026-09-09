<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Wa_model extends CI_Model {

    public function __construct() {
        parent::__construct();
    }

    /**
     * Ambil konfigurasi WhatsApp dari app_settings dengan nilai fallback
     */
    public function get_config() {
        $defaults = [
            'wa_enabled'            => '1',
            'wa_db_host'            => '192.168.0.194',
            'wa_db_port'            => '3306',
            'wa_db_user'            => 'root',
            'wa_db_pass'            => 'bismillah',
            'wa_db_name'            => 'wa_delphi3',
            'wa_outbox_table'       => 'wa_outbox',
            'wa_sender'             => 'NODEJS',
            'wa_source'             => 'SIDISPO',
            'wa_media_path'         => 'c:/xampp/htdocs/nodejs-gateway/media',
            'wa_send_file'          => '1',
            'wa_template_ekspedisi' => $this->default_template()
        ];

        // Pastikan tabel app_settings ada
        if ($this->db->table_exists('app_settings')) {
            $rows = $this->db->where_in('setting_key', array_keys($defaults))->get('app_settings')->result_array();
            foreach ($rows as $r) {
                if ($r['setting_value'] !== null && $r['setting_value'] !== '') {
                    $defaults[$r['setting_key']] = $r['setting_value'];
                }
            }
        }

        return $defaults;
    }

    /**
     * Default template pesan WhatsApp ekspedisi (UTF-8 safe)
     */
    public function default_template() {
        return "*NOTIFIKASI EKSPEDISI SURAT MASUK*\n"
             . "{nama_rs}\n\n"
             . "Yth. *{nama_penerima}*\n"
             . "({jabatan} - {unit})\n\n"
             . "Dokumen resmi disposisi telah diekspedisikan kepada Anda dengan rincian:\n"
             . "----------------------------------------\n"
             . "- *No. Ekspedisi:* {nomor_ekspedisi}\n"
             . "- *No. Surat:* {nomor_surat}\n"
             . "- *Asal Surat:* {asal_surat}\n"
             . "- *Perihal:* {perihal}\n"
             . "- *Tanggal Kirim:* {tanggal_kirim}\n"
             . "- *Jenis Pengiriman:* {jenis_pengiriman}\n"
             . "{catatan_pengiriman}\n"
             . "----------------------------------------\n"
             . "{keterangan_tambahan}\n\n"
             . "Silakan akses sistem *SiDispo* pada menu *Ekspedisi Masuk* untuk memeriksa berkas digital dan melakukan konfirmasi serah terima dokumen.\n\n"
             . "Terima kasih.\n"
             . "_Sistem Informasi Disposisi RSI Gondanglegi_";
    }

    /**
     * Membuat koneksi PDO ke database WA Gateway
     */
    public function get_db_connection($custom_config = null) {
        $cfg = $custom_config ? array_merge($this->get_config(), $custom_config) : $this->get_config();

        $host = $cfg['wa_db_host'] ?: '127.0.0.1';
        $port = !empty($cfg['wa_db_port']) ? (int)$cfg['wa_db_port'] : 3306;
        $db   = $cfg['wa_db_name'] ?: 'wa_delphi3';
        $user = $cfg['wa_db_user'] ?: 'root';
        $pass = $cfg['wa_db_pass'] !== null ? $cfg['wa_db_pass'] : '';

        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT            => 5
        ]);

        return $pdo;
    }

    /**
     * Uji koneksi ke database WA Gateway dan periksa tabel outbox
     */
    public function test_connection($custom_config = null) {
        try {
            $cfg = $custom_config ? array_merge($this->get_config(), $custom_config) : $this->get_config();
            $pdo = $this->get_db_connection($cfg);

            $tableName = $cfg['wa_outbox_table'] ?: 'wa_outbox';
            // Periksa apakah tabel ada
            $st = $pdo->prepare("SHOW TABLES LIKE ?");
            $st->execute([$tableName]);
            $tableExists = $st->fetchColumn();

            if (!$tableExists) {
                return [
                    'success' => false,
                    'message' => "Koneksi database '{$cfg['wa_db_name']}' berhasil, namun tabel antrean '{$tableName}' tidak ditemukan!"
                ];
            }

            // Hitung antrean saat ini
            $countSt = $pdo->query("SELECT COUNT(*) as total, 
                                           SUM(CASE WHEN status='ANTRIAN' THEN 1 ELSE 0 END) as antrian,
                                           SUM(CASE WHEN status='Terproses' OR success='1' THEN 1 ELSE 0 END) as terkirim
                                    FROM `{$tableName}`");
            $stats = $countSt->fetch();

            return [
                'success' => true,
                'message' => "Koneksi ke database WA '{$cfg['wa_db_name']}' berhasil! Tabel '{$tableName}' siap digunakan.",
                'stats'   => [
                    'total_record' => (int)($stats['total'] ?? 0),
                    'antrian'      => (int)($stats['antrian'] ?? 0),
                    'terkirim'     => (int)($stats['terkirim'] ?? 0)
                ]
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => "Gagal terhubung ke database WA: " . $e->getMessage()
            ];
        }
    }

    /**
     * Normalisasi nomor HP ke format WhatsApp (08xxxxxxxx@c.us)
     */
    public function normalize_phone($phone) {
        if (!$phone) return null;
        // Hapus karakter selain angka
        $clean = preg_replace('/[^0-9]/', '', (string)$phone);
        if (empty($clean)) return null;

        // Jika awalan 628 -> ubah ke 08 (standar nomor di wa_outbox Delphi)
        if (substr($clean, 0, 2) === '62') {
            $clean = '0' . substr($clean, 2);
        } elseif (substr($clean, 0, 1) !== '0') {
            $clean = '0' . $clean;
        }

        // Pastikan memiliki panjang wajar (minimal 10 digit)
        if (strlen($clean) < 9) return null;

        return $clean . '@c.us';
    }

    /**
     * Sanitasi teks agar kompatibel dengan kolom tabel MySQL ber-charset utf8 (3-byte)
     */
    public function sanitize_for_utf8($text) {
        if (!$text) return '';
        $replace = [
            '📌' => '•', '📨' => '•', '🏢' => '•', '📝' => '•',
            '📅' => '•', '📦' => '•', '💬' => '•', '⚠️' => '[PENTING]',
            'ℹ️' => '[INFO]', '📱' => '', '🔔' => '', '🏥' => '',
            '⚙️' => '', '💾' => '', '📊' => '', '📋' => '',
            '🔴' => '', '📩' => '', '🧪' => '', '👁️' => ''
        ];
        $text = strtr($text, $replace);
        // Hapus karakter 4-byte UTF-8 (misalnya emoji lain)
        return preg_replace('/[\xF0-\xF7][\x80-\xBF]{3}/', '', $text);
    }

    /**
     * Masukkan pesan WhatsApp ke tabel antrean wa_outbox
     */
    public function schedule_wa($phone, $message, $tanggal_jam = null, $custom_config = null) {
        $cfg = $custom_config ? array_merge($this->get_config(), $custom_config) : $this->get_config();

        // Jika WA dinonaktifkan di sistem
        if (isset($cfg['wa_enabled']) && $cfg['wa_enabled'] === '0') {
            return [
                'success' => false,
                'message' => 'Notifikasi WhatsApp sedang dinonaktifkan di pengaturan sistem.'
            ];
        }

        $nowa = $this->normalize_phone($phone);
        if (!$nowa) {
            return [
                'success' => false,
                'message' => 'Format nomor WhatsApp tidak valid.'
            ];
        }

        $safe_message = $this->sanitize_for_utf8($message);

        try {
            $pdo = $this->get_db_connection($cfg);
            $tableName = $cfg['wa_outbox_table'] ?: 'wa_outbox';
            $tgl = $tanggal_jam ?: date('Y-m-d H:i:s');
            $sender = $cfg['wa_sender'] ?: 'NODEJS';
            $source = $cfg['wa_source'] ?: 'SIDISPO';

            $sql = "INSERT INTO `{$tableName}` 
                    (nomor, nowa, pesan, tanggal_jam, status, source, sender, success, response, sender_name, request, type, file, sent_datetime)
                    VALUES (0, ?, ?, ?, 'ANTRIAN', ?, ?, '', '', '', '', 'TEXT', '', NULL)";
            
            $st = $pdo->prepare($sql);
            $res = $st->execute([
                $nowa,
                $safe_message,
                $tgl,
                $source,
                $sender
            ]);

            return [
                'success' => (bool)$res,
                'nowa'    => $nowa,
                'message' => 'Pesan berhasil dimasukkan ke antrean WhatsApp.'
            ];
        } catch (Throwable $e) {
            log_message('error', 'Gagal insert wa_outbox: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Gagal memasukkan pesan ke antrean: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Masukkan pesan WhatsApp dengan lampiran file ke tabel antrean wa_outbox (Opsi A)
     * File sumber disalin ke folder nodejs-gateway/media
     */
    public function schedule_wa_file($phone, $message, $source_file_path, $custom_file_name = null, $tanggal_jam = null, $custom_config = null) {
        $cfg = $custom_config ? array_merge($this->get_config(), $custom_config) : $this->get_config();

        // Jika WA dinonaktifkan di sistem
        if (isset($cfg['wa_enabled']) && $cfg['wa_enabled'] === '0') {
            return [
                'success' => false,
                'message' => 'Notifikasi WhatsApp sedang dinonaktifkan di pengaturan sistem.'
            ];
        }

        $nowa = $this->normalize_phone($phone);
        if (!$nowa) {
            return [
                'success' => false,
                'message' => 'Format nomor WhatsApp tidak valid.'
            ];
        }

        // Cek file sumber di server
        if (!file_exists($source_file_path)) {
            log_message('error', "File sumber tidak ditemukan di $source_file_path, fallback kirim pesan teks biasa.");
            return $this->schedule_wa($phone, $message, $tanggal_jam, $cfg);
        }

        // Tentukan folder media WA Gateway (Opsi A)
        $media_dir = !empty($cfg['wa_media_path']) ? rtrim($cfg['wa_media_path'], '/\\') : 'c:/xampp/htdocs/nodejs-gateway/media';

        if (!is_dir($media_dir)) {
            @mkdir($media_dir, 0777, true);
        }

        if (!is_dir($media_dir)) {
            log_message('error', "Folder media WA Gateway tidak valid: $media_dir, fallback ke teks biasa.");
            return $this->schedule_wa($phone, $message, $tanggal_jam, $cfg);
        }

        // Tentukan nama file yang aman dan rapi di folder media
        $ext = pathinfo($source_file_path, PATHINFO_EXTENSION);
        if ($custom_file_name) {
            $clean_name = preg_replace('/[^a-zA-Z0-9_\-\. ]/', '_', $custom_file_name);
            if (!preg_match('/\.' . preg_quote($ext, '/') . '$/i', $clean_name)) {
                $clean_name .= '.' . $ext;
            }
            $target_name = $clean_name;
        } else {
            $target_name = basename($source_file_path);
        }

        $dest_file_path = $media_dir . DIRECTORY_SEPARATOR . $target_name;

        // Salin file jika belum ada atau sumber lebih baru
        if (!file_exists($dest_file_path) || filemtime($source_file_path) > filemtime($dest_file_path)) {
            if (!@copy($source_file_path, $dest_file_path)) {
                log_message('error', "Gagal menyalin file dari $source_file_path ke $dest_file_path, fallback ke teks biasa.");
                return $this->schedule_wa($phone, $message, $tanggal_jam, $cfg);
            }
        }

        $safe_message = $this->sanitize_for_utf8($message);

        try {
            $pdo = $this->get_db_connection($cfg);
            $tableName = $cfg['wa_outbox_table'] ?: 'wa_outbox';
            $tgl = $tanggal_jam ?: date('Y-m-d H:i:s');
            $sender = $cfg['wa_sender'] ?: 'NODEJS';
            $source = $cfg['wa_source'] ?: 'SIDISPO';

            $sql = "INSERT INTO `{$tableName}` 
                    (nomor, nowa, pesan, tanggal_jam, status, source, sender, success, response, sender_name, request, type, file, sent_datetime)
                    VALUES (0, ?, ?, ?, 'ANTRIAN', ?, ?, '', '', '', '', 'FILE', ?, NULL)";
            
            $st = $pdo->prepare($sql);
            $res = $st->execute([
                $nowa,
                $safe_message,
                $tgl,
                $source,
                $sender,
                $target_name
            ]);

            return [
                'success' => (bool)$res,
                'nowa'    => $nowa,
                'type'    => 'FILE',
                'file'    => $target_name,
                'message' => "Pesan dan berkas dokumen '{$target_name}' berhasil dimasukkan ke antrean WhatsApp."
            ];
        } catch (Throwable $e) {
            log_message('error', 'Gagal insert wa_outbox (FILE): ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Gagal memasukkan file ke antrean WhatsApp: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Render template teks dengan konteks placeholder
     */
    public function render_template($template, $context = []) {
        if (empty($template)) {
            $template = $this->default_template();
        }

        $keys = array_map(function($k) {
            return '{' . $k . '}';
        }, array_keys($context));

        $values = array_values($context);

        return str_replace($keys, $values, $template);
    }

    /**
     * Kirim notifikasi ekspedisi ke seluruh penerima yang memiliki no_hp
     * Disertai lampiran file dokumen jika tersedia dan opsi wa_send_file aktif
     */
    public function kirim_notifikasi_ekspedisi($ekspedisi_id, $disp_info, $jenis_pengiriman, $users_data, $catatan = '', $files_data = []) {
        $cfg = $this->get_config();

        if (isset($cfg['wa_enabled']) && $cfg['wa_enabled'] === '0') {
            return [
                'sent_count'    => 0,
                'skipped_count' => count($users_data),
                'message'       => 'Notifikasi WA dinonaktifkan.'
            ];
        }

        // Ambil nama RS dari setting
        $nama_rs = 'RSI GONDANGLEGI';
        if ($this->db->table_exists('app_settings')) {
            $r = $this->db->get_where('app_settings', ['setting_key' => 'nama_rs'])->row_array();
            if ($r && !empty($r['setting_value'])) {
                $nama_rs = $r['setting_value'];
            }
        }

        // Ambil detail ekspedisi untuk nomor ekspedisi dan tanggal kirim
        $eksp = $this->db->get_where('ekspedisi', ['id' => $ekspedisi_id])->row_array();
        $nomor_ekspedisi = $eksp ? $eksp['nomor_ekspedisi'] : '-';
        $tanggal_kirim   = $eksp && !empty($eksp['tanggal_kirim']) ? date('d-m-Y', strtotime($eksp['tanggal_kirim'])) : date('d-m-Y');

        $template = !empty($cfg['wa_template_ekspedisi']) ? $cfg['wa_template_ekspedisi'] : $this->default_template();

        $catatan_text = !empty($catatan) ? "💬 *Catatan:* " . trim($catatan) : "";
        
        $keterangan_tambahan = "";
        if (strtoupper($jenis_pengiriman) === 'FISIK') {
            $keterangan_tambahan = "⚠️ *Perhatian Dokumen Fisik:*\nBerkas fisik/hardcopy sedang dalam pengiriman kurir internal. Mohon lakukan konfirmasi terima setelah dokumen fisik Anda terima.";
        } else {
            $keterangan_tambahan = "ℹ️ *Dokumen Digital:*\nLembar disposisi selesai dan file berkas surat dapat langsung Anda unduh & tinjau melalui aplikasi SiDispo.";
        }

        // Periksa berkas lampiran surat masuk
        $primary_file = null;
        $primary_file_name = null;
        $is_send_file = (!isset($cfg['wa_send_file']) || $cfg['wa_send_file'] !== '0');

        if ($is_send_file && !empty($files_data) && is_array($files_data)) {
            $first_file = $files_data[0];
            $candidate_path = FCPATH . $first_file['path_file'];
            if (file_exists($candidate_path)) {
                $primary_file = $candidate_path;
                // Buat nama file rapi untuk penerima WA
                $ext = pathinfo($candidate_path, PATHINFO_EXTENSION);
                $clean_no_agenda = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $disp_info['nomor_agenda'] ?? 'SM');
                $clean_orig_name = preg_replace('/[^a-zA-Z0-9_\-\. ]/', '_', $first_file['nama_asli'] ?? 'Dokumen');
                $primary_file_name = "Surat_{$clean_no_agenda}_{$clean_orig_name}";
                if (!preg_match('/\.' . preg_quote($ext, '/') . '$/i', $primary_file_name)) {
                    $primary_file_name .= '.' . $ext;
                }
            }
        }

        $sent_count = 0;
        $skipped_users = [];
        $debug_dispatches = [];

        foreach ($users_data as $u) {
            $no_hp = isset($u['no_hp']) ? trim($u['no_hp']) : '';
            if (empty($no_hp)) {
                $skipped_users[] = $u['nama_lengkap'];
                continue;
            }

            $ctx = [
                'nama_rs'             => $nama_rs,
                'nama_penerima'       => $u['nama_lengkap'] ?? '',
                'nip'                 => $u['nip'] ?? '-',
                'jabatan'             => $u['jabatan'] ?? ($u['unit'] ?? '-'),
                'unit'                => $u['unit'] ?? '-',
                'nomor_ekspedisi'     => $nomor_ekspedisi,
                'nomor_surat'         => $disp_info['nomor_surat'] ?? '-',
                'asal_surat'          => $disp_info['asal_surat'] ?? '-',
                'perihal'             => $disp_info['perihal'] ?? '-',
                'tanggal_kirim'       => $tanggal_kirim,
                'jenis_pengiriman'    => strtoupper($jenis_pengiriman),
                'catatan_pengiriman'  => $catatan_text,
                'keterangan_tambahan' => $keterangan_tambahan
            ];

            $pesan = $this->render_template($template, $ctx);

            // Jika ada file dokumen yang valid, kirim sekaligus file lampiran
            if ($primary_file) {
                $res = $this->schedule_wa_file($no_hp, $pesan, $primary_file, $primary_file_name);
            } else {
                $res = $this->schedule_wa($no_hp, $pesan);
            }

            $debug_dispatches[] = [
                'user'   => $u['nama_lengkap'],
                'no_hp'  => $no_hp,
                'file'   => $primary_file_name,
                'res'    => $res
            ];

            if (!empty($res['success'])) {
                $sent_count++;
            }
        }

        return [
            'sent_count'    => $sent_count,
            'skipped_count' => count($skipped_users),
            'skipped_users' => $skipped_users,
            'dispatches'    => $debug_dispatches ?? []
        ];
    }
}

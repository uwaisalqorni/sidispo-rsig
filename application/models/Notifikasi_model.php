<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Notifikasi_model extends CI_Model {

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    /**
     * Get user notifications
     */
    public function get_by_user($user_id, $limit = 50, $offset = 0)
    {
        $this->db->where('user_id', $user_id);
        $this->db->order_by('id', 'DESC');
        $this->db->limit($limit, $offset);
        return $this->db->get('notifikasi')->result_array();
    }
    
    /**
     * Get unread count
     */
    public function get_unread_count($user_id)
    {
        $this->db->where('user_id', $user_id);
        $this->db->where('dibaca_at IS NULL', null, false);
        return $this->db->count_all_results('notifikasi');
    }

    /**
     * Insert new notification
     */
    public function insert($data)
    {
        $this->db->insert('notifikasi', $data);
        $insert_id = $this->db->insert_id();

        if (!empty($data['user_id'])) {
            $this->send_email_notification(
                $data['user_id'],
                $data['judul'] ?? 'Notifikasi Disposisi',
                $data['pesan'] ?? '',
                $data['disposisi_id'] ?? null
            );
        }

        return $insert_id;
    }
    
    /**
     * Insert multiple notifications
     */
    public function insert_batch($data)
    {
        $res = $this->db->insert_batch('notifikasi', $data);
        
        foreach ($data as $item) {
            if (!empty($item['user_id'])) {
                $this->send_email_notification(
                    $item['user_id'],
                    $item['judul'] ?? 'Notifikasi Disposisi',
                    $item['pesan'] ?? '',
                    $item['disposisi_id'] ?? null
                );
            }
        }

        return $res;
    }

    /**
     * Kirim email notifikasi jika setting notif_email aktif
     */
    public function send_email_notification($user_id, $judul, $pesan, $disposisi_id = null)
    {
        try {
            $setting_rows = $this->db->get('app_settings')->result_array();
            $settings = [];
            foreach ($setting_rows as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }

            if (($settings['notif_email'] ?? '0') !== '1') {
                return false;
            }

            $user = $this->db->select('email, nama_lengkap')->where('id', $user_id)->get('users')->row_array();
            if (empty($user) || empty($user['email'])) {
                return false;
            }

            $smtp_host   = $settings['smtp_host'] ?? 'smtp.gmail.com';
            $smtp_port   = (int)($settings['smtp_port'] ?? 587);
            $smtp_user   = $settings['smtp_user'] ?? 'oktaimtiziliffa@gmail.com';
            $smtp_pass   = $settings['smtp_pass'] ?? 'zzua ooyl opsq kdqi';
            $smtp_crypto = $settings['smtp_crypto'] ?? 'tls';
            $nama_rs     = $settings['nama_rs'] ?? 'SiDispo RSI Gondanglegi';

            if (empty($smtp_user) || empty($smtp_pass)) {
                return false;
            }

            $config = [
                'protocol'    => 'smtp',
                'smtp_host'   => $smtp_host,
                'smtp_port'   => $smtp_port,
                'smtp_user'   => $smtp_user,
                'smtp_pass'   => $smtp_pass,
                'smtp_crypto' => $smtp_crypto,
                'mailtype'    => 'html',
                'charset'     => 'utf-8',
                'newline'     => "\r\n",
                'crlf'        => "\r\n"
            ];

            $this->load->library('email');
            $this->email->initialize($config);
            $this->email->from($smtp_user, $nama_rs);
            $this->email->to($user['email']);
            $this->email->subject("[SiDispo] " . $judul);

            // Ambil data detail disposisi & surat masuk jika disposisi_id tersedia
            $disposisi = null;
            if (!empty($disposisi_id)) {
                $this->db->select('
                    d.id as disposisi_id,
                    d.nomor_disposisi,
                    d.isi_disposisi,
                    d.prioritas,
                    d.batas_waktu,
                    d.status_global,
                    sm.nomor_surat,
                    sm.asal_surat,
                    sm.perihal,
                    sm.tanggal_surat,
                    dp.status as status_penerima
                ');
                $this->db->from('disposisi d');
                $this->db->join('surat_masuk sm', 'sm.id = d.surat_masuk_id', 'left');
                $this->db->join('disposisi_penerima dp', "dp.disposisi_id = d.id AND dp.user_id = {$user_id}", 'left');
                $this->db->where('d.id', $disposisi_id);
                $disposisi = $this->db->get()->row_array();
            }

            // Styling badges
            $prioritas = strtoupper($disposisi['prioritas'] ?? 'NORMAL');
            $prioritas_badges = [
                'URGENT' => '<span style="display:inline-block; padding:4px 12px; border-radius:20px; font-size:11px; font-weight:bold; background:#ffebee; color:#c62828; border:1px solid #ffcdd2;">🔴 URGENT</span>',
                'BIASA'  => '<span style="display:inline-block; padding:4px 12px; border-radius:20px; font-size:11px; font-weight:bold; background:#e8f5e9; color:#2e7d32; border:1px solid #c8e6c9;">🟢 BIASA</span>',
                'NORMAL' => '<span style="display:inline-block; padding:4px 12px; border-radius:20px; font-size:11px; font-weight:bold; background:#e3f2fd; color:#1565c0; border:1px solid #bbdefb;">🔵 NORMAL</span>'
            ];
            $prioritas_badge = $prioritas_badges[$prioritas] ?? $prioritas_badges['NORMAL'];

            $status_text = strtoupper($disposisi['status_penerima'] ?? $disposisi['status_global'] ?? 'AKTIF');
            $status_badges = [
                'SELESAI' => '<span style="display:inline-block; padding:4px 12px; border-radius:20px; font-size:11px; font-weight:bold; background:#e8f5e9; color:#2e7d32; border:1px solid #c8e6c9;">✓ SELESAI</span>',
                'OVERDUE' => '<span style="display:inline-block; padding:4px 12px; border-radius:20px; font-size:11px; font-weight:bold; background:#ffebee; color:#c62828; border:1px solid #ffcdd2;">⚠️ OVERDUE</span>',
                'PROSES'  => '<span style="display:inline-block; padding:4px 12px; border-radius:20px; font-size:11px; font-weight:bold; background:#fff8e1; color:#f57f17; border:1px solid #ffecb3;">⏳ PROSES</span>',
                'TUNGGU'  => '<span style="display:inline-block; padding:4px 12px; border-radius:20px; font-size:11px; font-weight:bold; background:#ede7f6; color:#512da8; border:1px solid #d1c4e9;">🕒 MENUNGGU</span>',
                'DITERIMA'=> '<span style="display:inline-block; padding:4px 12px; border-radius:20px; font-size:11px; font-weight:bold; background:#e0f2f1; color:#00695c; border:1px solid #b2dfdb;">📩 DITERIMA</span>'
            ];
            $status_badge = $status_badges[$status_text] ?? ('<span style="display:inline-block; padding:4px 12px; border-radius:20px; font-size:11px; font-weight:bold; background:#f5f5f5; color:#424242; border:1px solid #e0e0e0;">' . htmlspecialchars($status_text) . '</span>');

            // Format batas waktu
            $batas_waktu_str = '-';
            if (!empty($disposisi['batas_waktu']) && $disposisi['batas_waktu'] !== '0000-00-00') {
                $bulan = [
                    1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
                ];
                $time = strtotime($disposisi['batas_waktu']);
                $tgl  = (int)date('d', $time);
                $bln  = (int)date('m', $time);
                $thn  = date('Y', $time);
                $batas_waktu_str = $tgl . ' ' . ($bulan[$bln] ?? '') . ' ' . $thn;

                $diff_days = (int)floor((strtotime(date('Y-m-d', $time)) - strtotime(date('Y-m-d'))) / 86400);
                if ($diff_days < 0) {
                    $batas_waktu_str .= ' <span style="color:#c62828; font-weight:bold; font-size:11px;">(Lewat ' . abs($diff_days) . ' hari)</span>';
                } elseif ($diff_days === 0) {
                    $batas_waktu_str .= ' <span style="color:#f57f17; font-weight:bold; font-size:11px;">(Hari ini)</span>';
                } else {
                    $batas_waktu_str .= ' <span style="color:#2e7d32; font-weight:bold; font-size:11px;">(' . $diff_days . ' hari lagi)</span>';
                }
            }

            // Detail table block
            $detail_html = '';
            if ($disposisi) {
                $detail_html = '
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; margin: 20px 0 24px 0;">
                    <div style="background: #f8fafc; padding: 10px 16px; border-bottom: 1px solid #e2e8f0; font-size: 12px; font-weight: bold; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">
                        📋 Rincian Dokumen Disposisi
                    </div>
                    <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 10px 16px; color: #64748b; width: 30%; vertical-align: top; font-weight: 600;">Asal Surat</td>
                            <td style="padding: 10px 16px; color: #0f172a; font-weight: 700;">' . htmlspecialchars($disposisi['asal_surat'] ?? '-') . '</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #f1f5f9; background: #fbfcfd;">
                            <td style="padding: 10px 16px; color: #64748b; vertical-align: top; font-weight: 600;">Perihal</td>
                            <td style="padding: 10px 16px; color: #0f172a; font-weight: 600; line-height: 1.5;">' . htmlspecialchars($disposisi['perihal'] ?? '-') . '</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 10px 16px; color: #64748b; vertical-align: top; font-weight: 600;">Nomor Surat</td>
                            <td style="padding: 10px 16px; color: #334155;">' . htmlspecialchars($disposisi['nomor_surat'] ?? '-') . '</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #f1f5f9; background: #fbfcfd;">
                            <td style="padding: 10px 16px; color: #64748b; vertical-align: top; font-weight: 600;">Nomor Disposisi</td>
                            <td style="padding: 10px 16px; color: #334155; font-weight: 600;">' . htmlspecialchars($disposisi['nomor_disposisi'] ?? '-') . '</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 10px 16px; color: #64748b; vertical-align: top; font-weight: 600;">Prioritas</td>
                            <td style="padding: 10px 16px;">' . $prioritas_badge . '</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #f1f5f9; background: #fbfcfd;">
                            <td style="padding: 10px 16px; color: #64748b; vertical-align: top; font-weight: 600;">Status</td>
                            <td style="padding: 10px 16px;">' . $status_badge . '</td>
                        </tr>
                        <tr style="border-bottom: ' . (!empty($disposisi['isi_disposisi']) ? '1px solid #f1f5f9;' : 'none;') . '">
                            <td style="padding: 10px 16px; color: #64748b; vertical-align: top; font-weight: 600;">Batas Waktu</td>
                            <td style="padding: 10px 16px; color: #0f172a; font-weight: 600;">' . $batas_waktu_str . '</td>
                        </tr>
                        ' . (!empty($disposisi['isi_disposisi']) ? '
                        <tr style="background: #fbfcfd;">
                            <td style="padding: 10px 16px; color: #64748b; vertical-align: top; font-weight: 600;">Instruksi Disposisi</td>
                            <td style="padding: 10px 16px; color: #1e293b; font-style: italic; line-height: 1.5;">' . nl2br(htmlspecialchars($disposisi['isi_disposisi'])) . '</td>
                        </tr>' : '') . '
                    </table>
                </div>';
            }

            $app_url = base_url('rsig');

            $body = '
            <div style="font-family: Arial, Helvetica, sans-serif; max-width: 620px; margin: 0 auto; padding: 28px; border: 1px solid #cbd5e1; border-radius: 14px; background: #ffffff; box-shadow: 0 4px 12px rgba(0,0,0,0.04);">
                <div style="border-bottom: 2px solid #52b788; padding-bottom: 14px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <h2 style="color: #2d6a4f; margin: 0; font-size: 20px; font-weight: 800;">' . htmlspecialchars($judul) . '</h2>
                        <span style="font-size: 12px; color: #52b788; font-weight: 600;">SiDispo &bull; RSI Gondanglegi</span>
                    </div>
                </div>

                <p style="color: #334155; font-size: 14px; margin: 0 0 12px 0;">Kepada Yth. <strong>' . htmlspecialchars($user['nama_lengkap']) . '</strong>,</p>
                
                <div style="background: #f0fdf4; border-left: 4px solid #52b788; padding: 14px 18px; margin: 16px 0; border-radius: 0 8px 8px 0;">
                    <p style="color: #166534; font-size: 14px; margin: 0; line-height: 1.6; font-weight: 500;">' . nl2br(htmlspecialchars($pesan)) . '</p>
                </div>

                ' . $detail_html . '

                <div style="text-align: center; margin: 28px 0 20px 0;">
                    <a href="' . $app_url . '" style="background: linear-gradient(135deg, #2d6a4f 0%, #52b788 100%); color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-weight: 700; font-size: 13px; display: inline-block; box-shadow: 0 4px 12px rgba(45, 106, 79, 0.25);">
                        Buka Aplikasi SiDispo &rarr;
                    </a>
                </div>

                <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 24px 0 16px 0;">
                <p style="font-size: 11px; color: #94a3b8; margin: 0; line-height: 1.5; text-align: center;">
                    Email ini dikirim otomatis oleh Sistem Disposisi Digital (SiDispo) RSI Gondanglegi pada ' . date('d-m-Y H:i:s') . ' WIB.
                </p>
            </div>';

            $this->email->message($body);
            return @$this->email->send();
        } catch (Exception $e) {
            log_message('error', 'Gagal kirim email notifikasi: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark notification as read
     */
    public function mark_as_read($id, $user_id)
    {
        $this->db->where('id', $id);
        $this->db->where('user_id', $user_id);
        return $this->db->update('notifikasi', ['dibaca_at' => date('Y-m-d H:i:s')]);
    }
    
    /**
     * Mark all as read for user
     */
    public function mark_all_as_read($user_id)
    {
        $this->db->where('user_id', $user_id);
        $this->db->where('dibaca_at IS NULL', null, false);
        return $this->db->update('notifikasi', ['dibaca_at' => date('Y-m-d H:i:s')]);
    }
}

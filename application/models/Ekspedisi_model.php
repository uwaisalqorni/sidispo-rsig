<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Ekspedisi_model extends CI_Model {

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->model('Notifikasi_model', 'notifikasi_m');
    }

    /**
     * Generate Nomor Ekspedisi unik: EXP-YYYYMMDD-XXXX
     */
    public function generate_nomor_ekspedisi()
    {
        $today = date('Ymd');
        $prefix = 'EXP-' . $today . '-';

        $this->db->select('nomor_ekspedisi');
        $this->db->like('nomor_ekspedisi', $prefix, 'after');
        $this->db->order_by('id', 'DESC');
        $this->db->limit(1);
        $last = $this->db->get('ekspedisi')->row_array();

        if ($last) {
            $last_num = (int)substr($last['nomor_ekspedisi'], -4);
            $new_num = $last_num + 1;
        } else {
            $new_num = 1;
        }

        return $prefix . str_pad($new_num, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Tab 1 Admin: Daftar Disposisi SELESAI yang belum pernah diekspedisi (Siap Kirim)
     */
    public function get_siap_kirim($limit = 100, $offset = 0, $filters = [])
    {
        $this->db->select('d.id as disposisi_id, d.nomor_disposisi, d.tanggal_disposisi, d.prioritas, d.status_global as status_disposisi,
                           sm.id as surat_masuk_id, sm.nomor_surat, sm.nomor_agenda, sm.asal_surat, sm.perihal, sm.tanggal_surat, sm.tanggal_terima,
                           u.nama_lengkap as pembuat_disposisi,
                           (SELECT COUNT(*) FROM ekspedisi e WHERE e.disposisi_id = d.id) as total_ekspedisi');
        $this->db->from('disposisi d');
        $this->db->join('surat_masuk sm', 'sm.id = d.surat_masuk_id');
        $this->db->join('users u', 'u.id = d.dibuat_oleh', 'left');
        $this->db->where('d.status_global', 'SELESAI');

        // Saring hanya yang belum diekspedisi jika tidak ada parameter include_sent
        if (empty($filters['include_sent'])) {
            $this->db->having('total_ekspedisi', 0);
        }

        if (!empty($filters['q'])) {
            $q = trim($filters['q']);
            $this->db->group_start();
            $this->db->like('sm.nomor_surat', $q);
            $this->db->or_like('sm.asal_surat', $q);
            $this->db->or_like('sm.perihal', $q);
            $this->db->or_like('sm.nomor_agenda', $q);
            $this->db->or_like('d.nomor_disposisi', $q);
            $this->db->group_end();
        }

        $this->db->order_by('d.id', 'DESC');
        $this->db->limit($limit, $offset);
        $list = $this->db->get()->result_array();

        // Attach files
        if (!empty($list)) {
            $sm_ids = array_unique(array_column($list, 'surat_masuk_id'));
            $files_map = $this->get_files_by_surat_masuk_ids($sm_ids);
            foreach ($list as &$item) {
                $item['files'] = $files_map[$item['surat_masuk_id']] ?? [];
            }
            unset($item);
        }

        return $list;
    }

    /**
     * Tab 2 Admin: Riwayat Pengiriman Ekspedisi Lengkap
     */
    public function get_all_pengiriman($limit = 100, $offset = 0, $filters = [])
    {
        $this->db->select('e.*, 
                           sm.nomor_surat, sm.nomor_agenda, sm.asal_surat, sm.perihal, sm.tanggal_surat, sm.tanggal_terima,
                           d.nomor_disposisi, d.prioritas,
                           u.nama_lengkap as nama_pengirim, u.nip as nip_pengirim');
        $this->db->from('ekspedisi e');
        $this->db->join('surat_masuk sm', 'sm.id = e.surat_masuk_id');
        $this->db->join('disposisi d', 'd.id = e.disposisi_id');
        $this->db->join('users u', 'u.id = e.pengirim_id', 'left');

        if (!empty($filters['status'])) {
            $this->db->where('e.status_global', $filters['status']);
        }
        if (!empty($filters['jenis'])) {
            $this->db->where('e.jenis_pengiriman', $filters['jenis']);
        }
        if (!empty($filters['tanggal_dari'])) {
            $this->db->where('DATE(e.tanggal_kirim) >=', $filters['tanggal_dari']);
        }
        if (!empty($filters['tanggal_sampai'])) {
            $this->db->where('DATE(e.tanggal_kirim) <=', $filters['tanggal_sampai']);
        }
        if (!empty($filters['q'])) {
            $q = trim($filters['q']);
            $this->db->group_start();
            $this->db->like('e.nomor_ekspedisi', $q);
            $this->db->or_like('sm.nomor_surat', $q);
            $this->db->or_like('sm.asal_surat', $q);
            $this->db->or_like('sm.perihal', $q);
            $this->db->or_like('sm.nomor_agenda', $q);
            $this->db->or_like('d.nomor_disposisi', $q);
            $this->db->group_end();
        }

        $this->db->order_by('e.id', 'DESC');
        $this->db->limit($limit, $offset);
        $ekspedisi_list = $this->db->get()->result_array();

        if (!empty($ekspedisi_list)) {
            $ids = array_column($ekspedisi_list, 'id');
            $tujuan_list = $this->get_tujuan_by_ekspedisi_ids($ids);

            // Grouping tujuan per ekspedisi_id
            $grouped = [];
            foreach ($tujuan_list as $t) {
                $grouped[$t['ekspedisi_id']][] = $t;
            }

            // Get files
            $sm_ids = array_unique(array_column($ekspedisi_list, 'surat_masuk_id'));
            $files_map = $this->get_files_by_surat_masuk_ids($sm_ids);

            foreach ($ekspedisi_list as &$item) {
                $item['tujuan'] = $grouped[$item['id']] ?? [];
                $item['files']  = $files_map[$item['surat_masuk_id']] ?? [];
            }
            unset($item);
        }

        return $ekspedisi_list;
    }

    /**
     * Sisi Penerima: Daftar Ekspedisi Masuk berdasarkan user_tujuan_id atau unit
     */
    public function get_ekspedisi_masuk_by_user($user_id, $unit = null, $limit = 100, $offset = 0, $filters = [])
    {
        $this->db->select('et.id as ekspedisi_tujuan_id, et.ekspedisi_id, et.user_tujuan_id, et.unit_tujuan, et.status as status_tujuan,
                           et.received_at, et.received_by_user_id, et.rejected_at, et.rejected_by_user_id,
                           et.alasan_tolak, et.catatan as catatan_tujuan,
                           e.nomor_ekspedisi, e.jenis_pengiriman, e.status_global, e.catatan as catatan_pengirim, e.tanggal_kirim,
                           sm.id as surat_masuk_id, sm.nomor_surat, sm.nomor_agenda, sm.asal_surat, sm.perihal, sm.tanggal_surat, sm.tanggal_terima,
                           d.id as disposisi_id, d.nomor_disposisi, d.prioritas,
                           u_tujuan.nama_lengkap as nama_user_tujuan, u_tujuan.nip as nip_user_tujuan, u_tujuan.jabatan as jabatan_user_tujuan,
                           u_pengirim.nama_lengkap as nama_pengirim,
                           u_rec.nama_lengkap as nama_penerima, u_rec.nip as nip_penerima,
                           u_rej.nama_lengkap as nama_penolak, u_rej.nip as nip_penolak');
        $this->db->from('ekspedisi_tujuan et');
        $this->db->join('ekspedisi e', 'e.id = et.ekspedisi_id');
        $this->db->join('surat_masuk sm', 'sm.id = e.surat_masuk_id');
        $this->db->join('disposisi d', 'd.id = e.disposisi_id');
        $this->db->join('users u_tujuan', 'u_tujuan.id = et.user_tujuan_id', 'left');
        $this->db->join('users u_pengirim', 'u_pengirim.id = e.pengirim_id', 'left');
        $this->db->join('users u_rec', 'u_rec.id = et.received_by_user_id', 'left');
        $this->db->join('users u_rej', 'u_rej.id = et.rejected_by_user_id', 'left');

        // Filter: match user_tujuan_id, or match unit_tujuan jika record lama belum ada user_tujuan_id
        if (!empty($user_id)) {
            $this->db->group_start();
            $this->db->where('et.user_tujuan_id', $user_id);
            if (!empty($unit)) {
                $this->db->or_group_start();
                $this->db->where('et.user_tujuan_id IS NULL', null, false);
                $this->db->where('et.unit_tujuan', $unit);
                $this->db->group_end();
            }
            $this->db->group_end();
        }

        if (!empty($filters['status'])) {
            $this->db->where('et.status', $filters['status']);
        }
        if (!empty($filters['jenis'])) {
            $this->db->where('e.jenis_pengiriman', $filters['jenis']);
        }
        if (!empty($filters['q'])) {
            $q = trim($filters['q']);
            $this->db->group_start();
            $this->db->like('e.nomor_ekspedisi', $q);
            $this->db->or_like('sm.nomor_surat', $q);
            $this->db->or_like('sm.asal_surat', $q);
            $this->db->or_like('sm.perihal', $q);
            $this->db->or_like('sm.nomor_agenda', $q);
            $this->db->or_like('d.nomor_disposisi', $q);
            $this->db->group_end();
        }

        $this->db->order_by('et.id', 'DESC');
        $this->db->limit($limit, $offset);
        $masuk_list = $this->db->get()->result_array();

        // Attach files lampiran surat ke setiap item
        if (!empty($masuk_list)) {
            $sm_ids = array_unique(array_column($masuk_list, 'surat_masuk_id'));
            $files_map = $this->get_files_by_surat_masuk_ids($sm_ids);
            foreach ($masuk_list as &$item) {
                $item['files'] = $files_map[$item['surat_masuk_id']] ?? [];
            }
            unset($item);
        }

        return $masuk_list;
    }

    /**
     * Helper: Ambil file lampiran surat_masuk
     */
    public function get_files_by_surat_masuk_ids($sm_ids)
    {
        if (empty($sm_ids)) return [];
        $files = $this->db->where_in('surat_masuk_id', $sm_ids)
                          ->get('dokumen_file')
                          ->result_array();
        $map = [];
        foreach ($files as $f) {
            $map[$f['surat_masuk_id']][] = $f;
        }
        return $map;
    }

    /**
     * Ambil list unit/user tujuan untuk beberapa ID ekspedisi
     */
    private function get_tujuan_by_ekspedisi_ids($ekspedisi_ids)
    {
        if (empty($ekspedisi_ids)) return [];

        $this->db->select('et.*, 
                           u_tujuan.nama_lengkap as nama_user_tujuan, u_tujuan.nip as nip_user_tujuan, u_tujuan.jabatan as jabatan_user_tujuan, u_tujuan.unit as unit_user_tujuan,
                           u_rec.nama_lengkap as nama_penerima, u_rec.nip as nip_penerima,
                           u_rej.nama_lengkap as nama_penolak, u_rej.nip as nip_penolak');
        $this->db->from('ekspedisi_tujuan et');
        $this->db->join('users u_tujuan', 'u_tujuan.id = et.user_tujuan_id', 'left');
        $this->db->join('users u_rec', 'u_rec.id = et.received_by_user_id', 'left');
        $this->db->join('users u_rej', 'u_rej.id = et.rejected_by_user_id', 'left');
        $this->db->where_in('et.ekspedisi_id', $ekspedisi_ids);
        $this->db->order_by('et.id', 'ASC');
        return $this->db->get()->result_array();
    }

    /**
     * Get Detail Lengkap 1 Ekspedisi
     */
    public function get_detail($id)
    {
        $this->db->select('e.*, 
                           sm.id as surat_masuk_id, sm.nomor_surat, sm.nomor_agenda, sm.asal_surat, sm.perihal, sm.tanggal_surat, sm.tanggal_terima, sm.keterangan as keterangan_surat,
                           d.id as disposisi_id, d.nomor_disposisi, d.tanggal_disposisi, d.prioritas, d.batas_waktu, d.isi_disposisi, d.status_global as status_disposisi,
                           u.nama_lengkap as nama_pengirim, u.nip as nip_pengirim, u.jabatan as jabatan_pengirim, u.unit as unit_pengirim');
        $this->db->from('ekspedisi e');
        $this->db->join('surat_masuk sm', 'sm.id = e.surat_masuk_id');
        $this->db->join('disposisi d', 'd.id = e.disposisi_id');
        $this->db->join('users u', 'u.id = e.pengirim_id', 'left');
        $this->db->where('e.id', $id);
        $ekspedisi = $this->db->get()->row_array();

        if (!$ekspedisi) return null;

        // Ambil daftar tujuan
        $ekspedisi['tujuan'] = $this->get_tujuan_by_ekspedisi_ids([$id]);

        // Ambil file lampiran surat
        $this->db->where('surat_masuk_id', $ekspedisi['surat_masuk_id']);
        $ekspedisi['files'] = $this->db->get('dokumen_file')->result_array();

        return $ekspedisi;
    }

    /**
     * Buat Ekspedisi Baru (Multi-User tujuan + Tanggal Kirim)
     */
    public function create_ekspedisi($disposisi_id, $jenis_pengiriman, $catatan, $user_tujuan_list, $pengirim_id, $tanggal_kirim = null)
    {
        // 1. Ambil data disposisi & surat
        $this->db->select('d.id, d.nomor_disposisi, d.surat_masuk_id, sm.nomor_surat, sm.asal_surat, sm.perihal');
        $this->db->from('disposisi d');
        $this->db->join('surat_masuk sm', 'sm.id = d.surat_masuk_id');
        $this->db->where('d.id', $disposisi_id);
        $disp = $this->db->get()->row_array();

        if (!$disp) return false;

        $nomor_ekspedisi = $this->generate_nomor_ekspedisi();

        $tgl_kirim_final = date('Y-m-d H:i:s');
        if (!empty($tanggal_kirim)) {
            $parsed = strtotime($tanggal_kirim);
            if ($parsed !== false) {
                // Jika input hanya YYYY-MM-DD, tambahkan jam saat ini
                if (strlen($tanggal_kirim) === 10) {
                    $tgl_kirim_final = date('Y-m-d', $parsed) . ' ' . date('H:i:s');
                } else {
                    $tgl_kirim_final = date('Y-m-d H:i:s', $parsed);
                }
            }
        }

        $this->db->trans_start();

        // 2. Insert ke tabel ekspedisi
        $data_ekspedisi = [
            'nomor_ekspedisi'  => $nomor_ekspedisi,
            'disposisi_id'     => $disposisi_id,
            'surat_masuk_id'   => $disp['surat_masuk_id'],
            'pengirim_id'      => $pengirim_id,
            'jenis_pengiriman' => strtoupper($jenis_pengiriman) === 'FISIK' ? 'FISIK' : 'DIGITAL',
            'status_global'    => 'PENDING',
            'catatan'          => $catatan,
            'tanggal_kirim'    => $tgl_kirim_final
        ];
        $this->db->insert('ekspedisi', $data_ekspedisi);
        $ekspedisi_id = $this->db->insert_id();

        // 3. Ambil data users tujuan untuk disimpan
        $clean_user_ids = array_unique(array_filter(array_map('intval', (array)$user_tujuan_list)));
        if (empty($clean_user_ids)) {
            $this->db->trans_rollback();
            return false;
        }

        $users_data = $this->db->select('id, nama_lengkap, nip, jabatan, unit, no_hp')
                               ->where_in('id', $clean_user_ids)
                               ->where('is_active', 1)
                               ->get('users')
                               ->result_array();

        $batch_tujuan = [];
        $notif_users = [];
        foreach ($users_data as $u) {
            $batch_tujuan[] = [
                'ekspedisi_id'   => $ekspedisi_id,
                'user_tujuan_id' => $u['id'],
                'unit_tujuan'    => !empty($u['unit']) ? $u['unit'] : (!empty($u['jabatan']) ? $u['jabatan'] : 'Unit'),
                'status'         => 'PENDING'
            ];
            $notif_users[] = $u;
        }

        if (!empty($batch_tujuan)) {
            $this->db->insert_batch('ekspedisi_tujuan', $batch_tujuan);
        }

        $this->db->trans_complete();

        if ($this->db->trans_status()) {
            // 4. Kirim Notifikasi in-app ke user-user tujuan terpilih
            $this->kirim_notifikasi_ekspedisi_baru_user($ekspedisi_id, $disp, $data_ekspedisi['jenis_pengiriman'], $notif_users);

            // 5. Kirim Notifikasi WhatsApp ke antrean wa_outbox (beserta berkas lampiran jika ada)
            try {
                $this->load->model('Wa_model');
                $files_sm = $this->db->get_where('dokumen_file', ['surat_masuk_id' => $disp['surat_masuk_id']])->result_array();
                $this->Wa_model->kirim_notifikasi_ekspedisi($ekspedisi_id, $disp, $data_ekspedisi['jenis_pengiriman'], $notif_users, $catatan, $files_sm);
            } catch (Throwable $e) {
                log_message('error', 'Gagal kirim WA ekspedisi: ' . $e->getMessage());
            }

            return $ekspedisi_id;
        }

        return false;
    }

    /**
     * Konfirmasi Terima Ekspedisi oleh User Penerima
     */
    public function konfirmasi_terima($ekspedisi_tujuan_id, $user_id, $catatan = null)
    {
        $tujuan = $this->db->get_where('ekspedisi_tujuan', ['id' => $ekspedisi_tujuan_id])->row_array();
        if (!$tujuan) return false;

        $this->db->trans_start();

        $now = date('Y-m-d H:i:s');
        $update_data = [
            'status'              => 'RECEIVED',
            'received_at'         => $now,
            'received_by_user_id' => $user_id,
            'catatan'             => !empty($catatan) ? $catatan : $tujuan['catatan']
        ];
        $this->db->where('id', $ekspedisi_tujuan_id)->update('ekspedisi_tujuan', $update_data);

        // Update status global ekspedisi
        $this->update_status_global_ekspedisi($tujuan['ekspedisi_id']);

        $this->db->trans_complete();

        if ($this->db->trans_status()) {
            // Kirim notifikasi konfirmasi terima ke pengirim
            $this->kirim_notifikasi_terima($tujuan['ekspedisi_id'], $tujuan['unit_tujuan'], $user_id, $now);
            return true;
        }

        return false;
    }

    /**
     * Tolak / Kembalikan Ekspedisi oleh User Penerima
     */
    public function tolak_ekspedisi($ekspedisi_tujuan_id, $user_id, $alasan_tolak)
    {
        $tujuan = $this->db->get_where('ekspedisi_tujuan', ['id' => $ekspedisi_tujuan_id])->row_array();
        if (!$tujuan) return false;

        $this->db->trans_start();

        $now = date('Y-m-d H:i:s');
        $update_data = [
            'status'              => 'REJECTED',
            'rejected_at'         => $now,
            'rejected_by_user_id' => $user_id,
            'alasan_tolak'        => $alasan_tolak
        ];
        $this->db->where('id', $ekspedisi_tujuan_id)->update('ekspedisi_tujuan', $update_data);

        // Update status global ekspedisi
        $this->update_status_global_ekspedisi($tujuan['ekspedisi_id']);

        $this->db->trans_complete();

        if ($this->db->trans_status()) {
            // Kirim notifikasi penolakan ke pengirim
            $this->kirim_notifikasi_tolak($tujuan['ekspedisi_id'], $tujuan['unit_tujuan'], $user_id, $alasan_tolak, $now);
            return true;
        }

        return false;
    }

    /**
     * Revisi & Kirim Ulang Ekspedisi (Admin)
     */
    public function revisi_kirim_ulang($ekspedisi_id, $catatan_baru = null, $jenis_baru = null, $tanggal_kirim_baru = null)
    {
        $eksp = $this->db->get_where('ekspedisi', ['id' => $ekspedisi_id])->row_array();
        if (!$eksp) return false;

        $this->db->trans_start();

        $update_data = ['updated_at' => date('Y-m-d H:i:s')];
        if ($catatan_baru !== null) $update_data['catatan'] = $catatan_baru;
        if ($jenis_baru !== null) $update_data['jenis_pengiriman'] = $jenis_baru;
        if ($tanggal_kirim_baru !== null) $update_data['tanggal_kirim'] = $tanggal_kirim_baru;

        $this->db->where('id', $ekspedisi_id)->update('ekspedisi', $update_data);

        // Reset status tujuan yang tadinya REJECTED kembali menjadi PENDING
        $this->db->where('ekspedisi_id', $ekspedisi_id)
                 ->where('status', 'REJECTED')
                 ->update('ekspedisi_tujuan', [
                     'status'              => 'PENDING',
                     'rejected_at'         => null,
                     'rejected_by_user_id' => null,
                     'alasan_tolak'        => null
                 ]);

        $this->update_status_global_ekspedisi($ekspedisi_id);

        $this->db->trans_complete();
        return $this->db->trans_status();
    }

    /**
     * Update Status Global Ekspedisi
     */
    private function update_status_global_ekspedisi($ekspedisi_id)
    {
        $tujuan_list = $this->db->get_where('ekspedisi_tujuan', ['ekspedisi_id' => $ekspedisi_id])->result_array();
        if (empty($tujuan_list)) return;

        $total = count($tujuan_list);
        $received = 0;
        $rejected = 0;
        $pending = 0;

        foreach ($tujuan_list as $t) {
            if ($t['status'] === 'RECEIVED') $received++;
            else if ($t['status'] === 'REJECTED') $rejected++;
            else $pending++;
        }

        if ($received === $total) {
            $new_status = 'RECEIVED';
        } else if ($rejected === $total) {
            $new_status = 'REJECTED';
        } else if ($received > 0 || $rejected > 0) {
            $new_status = 'PARTIAL';
        } else {
            $new_status = 'PENDING';
        }

        $this->db->where('id', $ekspedisi_id)->update('ekspedisi', ['status_global' => $new_status]);
    }

    /**
     * Daftar User Aktif untuk Opsi Pilihan Tujuan Ekspedisi (seperti Disposisi)
     */
    public function get_user_options($search = null, $role = null)
    {
        $this->db->select('u.id, u.nip, u.nama_lengkap, u.email, u.no_hp, u.jabatan, u.unit, u.role, u.jabatan_id,
                           mj.nama as jabatan_master, mj.level as jabatan_level');
        $this->db->from('users u');
        $this->db->join('master_jabatan mj', 'mj.id = u.jabatan_id', 'left');
        $this->db->where('u.is_active', 1);

        if (!empty($role) && $role !== 'SEMUA') {
            $this->db->where('u.role', $role);
        }

        if (!empty($search)) {
            $search = trim($search);
            $this->db->group_start();
            $this->db->like('u.nama_lengkap', $search);
            $this->db->or_like('u.nip', $search);
            $this->db->or_like('u.jabatan', $search);
            $this->db->or_like('u.unit', $search);
            $this->db->or_like('mj.nama', $search);
            $this->db->group_end();
        }

        $this->db->order_by('u.role', 'ASC');
        $this->db->order_by('u.nama_lengkap', 'ASC');
        return $this->db->get()->result_array();
    }

    /**
     * Helper Notifikasi Ekspedisi Baru ke User Terpilih
     */
    private function kirim_notifikasi_ekspedisi_baru_user($ekspedisi_id, $disp, $jenis_pengiriman, $users)
    {
        if (empty($users)) return;

        $batch_notif = [];
        foreach ($users as $u) {
            $batch_notif[] = [
                'user_id'      => $u['id'],
                'jenis'        => 'EKSPEDISI_BARU',
                'judul'        => "Ekspedisi Surat Baru ($jenis_pengiriman)",
                'pesan'        => "Surat No: {$disp['nomor_surat']} dari {$disp['asal_surat']} (Perihal: {$disp['perihal']}) telah diekspedisikan kepada Anda. Silakan periksa berkas dan konfirmasi penerimaan.",
                'disposisi_id' => $disp['id'],
                'ekspedisi_id' => $ekspedisi_id,
                'created_at'   => date('Y-m-d H:i:s')
            ];
        }

        if (!empty($batch_notif)) {
            $this->db->insert_batch('notifikasi', $batch_notif);
        }
    }

    /**
     * Helper Notifikasi Dokumen Diterima ke Pengirim
     */
    private function kirim_notifikasi_terima($ekspedisi_id, $unit_tujuan, $penerima_user_id, $tanggal_jam)
    {
        $eksp = $this->get_detail($ekspedisi_id);
        if (!$eksp || empty($eksp['pengirim_id'])) return;

        $penerima = $this->db->get_where('users', ['id' => $penerima_user_id])->row_array();
        $nama_penerima = $penerima ? $penerima['nama_lengkap'] : 'Petugas';
        $tgl_fmt = date('d M Y H:i', strtotime($tanggal_jam));

        $this->db->insert('notifikasi', [
            'user_id'      => $eksp['pengirim_id'],
            'jenis'        => 'EKSPEDISI_TERIMA',
            'judul'        => "Ekspedisi Diterima oleh $nama_penerima",
            'pesan'        => "Surat No: {$eksp['nomor_surat']} telah dikonfirmasi DITERIMA oleh $nama_penerima ($unit_tujuan) pada $tgl_fmt.",
            'disposisi_id' => $eksp['disposisi_id'],
            'ekspedisi_id' => $ekspedisi_id,
            'created_at'   => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Helper Notifikasi Dokumen Ditolak ke Pengirim
     */
    private function kirim_notifikasi_tolak($ekspedisi_id, $unit_tujuan, $penolak_user_id, $alasan_tolak, $tanggal_jam)
    {
        $eksp = $this->get_detail($ekspedisi_id);
        if (!$eksp || empty($eksp['pengirim_id'])) return;

        $penolak = $this->db->get_where('users', ['id' => $penolak_user_id])->row_array();
        $nama_penolak = $penolak ? $penolak['nama_lengkap'] : 'Petugas';
        $tgl_fmt = date('d M Y H:i', strtotime($tanggal_jam));

        $this->db->insert('notifikasi', [
            'user_id'      => $eksp['pengirim_id'],
            'jenis'        => 'EKSPEDISI_TOLAK',
            'judul'        => "Ekspedisi Ditolak oleh $nama_penolak",
            'pesan'        => "Surat No: {$eksp['nomor_surat']} DITOLAK oleh $nama_penolak ($unit_tujuan) pada $tgl_fmt. Alasan: \"$alasan_tolak\".",
            'disposisi_id' => $eksp['disposisi_id'],
            'ekspedisi_id' => $ekspedisi_id,
            'created_at'   => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Update Data Ekspedisi (Admin only)
     */
    public function update_ekspedisi($id, $data, $user_tujuan_list = null)
    {
        $eksp = $this->db->get_where('ekspedisi', ['id' => $id])->row_array();
        if (!$eksp) return false;

        $this->db->trans_start();

        $update_data = ['updated_at' => date('Y-m-d H:i:s')];
        if (isset($data['jenis_pengiriman'])) {
            $update_data['jenis_pengiriman'] = strtoupper($data['jenis_pengiriman']) === 'FISIK' ? 'FISIK' : 'DIGITAL';
        }
        if (isset($data['catatan'])) {
            $update_data['catatan'] = $data['catatan'];
        }
        if (!empty($data['tanggal_kirim'])) {
            $parsed = strtotime($data['tanggal_kirim']);
            if ($parsed !== false) {
                if (strlen($data['tanggal_kirim']) === 10) {
                    $update_data['tanggal_kirim'] = date('Y-m-d', $parsed) . ' ' . date('H:i:s');
                } else {
                    $update_data['tanggal_kirim'] = date('Y-m-d H:i:s', $parsed);
                }
            }
        }

        $this->db->where('id', $id)->update('ekspedisi', $update_data);

        // Jika user_tujuan_list diubah
        if ($user_tujuan_list !== null && is_array($user_tujuan_list)) {
            $new_user_ids = array_unique(array_filter(array_map('intval', $user_tujuan_list)));
            
            // Ambil data tujuan saat ini
            $existing_tujuan = $this->db->get_where('ekspedisi_tujuan', ['ekspedisi_id' => $id])->result_array();
            $existing_user_ids = [];
            foreach ($existing_tujuan as $et) {
                if (!empty($et['user_tujuan_id'])) {
                    $existing_user_ids[] = (int)$et['user_tujuan_id'];
                }
            }

            // User yang di-remove: hanya remove yang statusnya PENDING
            foreach ($existing_tujuan as $et) {
                if (!in_array((int)$et['user_tujuan_id'], $new_user_ids) && $et['status'] === 'PENDING') {
                    $this->db->where('id', $et['id'])->delete('ekspedisi_tujuan');
                }
            }

            // User yang baru ditambahkan
            $to_add = array_diff($new_user_ids, $existing_user_ids);
            if (!empty($to_add)) {
                $users_data = $this->db->select('id, nama_lengkap, nip, jabatan, unit, no_hp')
                                       ->where_in('id', $to_add)
                                       ->where('is_active', 1)
                                       ->get('users')
                                       ->result_array();
                $batch_tujuan = [];
                foreach ($users_data as $u) {
                    $batch_tujuan[] = [
                        'ekspedisi_id'   => $id,
                        'user_tujuan_id' => $u['id'],
                        'unit_tujuan'    => !empty($u['unit']) ? $u['unit'] : (!empty($u['jabatan']) ? $u['jabatan'] : 'Unit'),
                        'status'         => 'PENDING',
                        'created_at'     => date('Y-m-d H:i:s')
                    ];
                }
                if (!empty($batch_tujuan)) {
                    $this->db->insert_batch('ekspedisi_tujuan', $batch_tujuan);
                }

                // Kirim notifikasi ke user baru
                $disp = $this->db->select('d.id, sm.nomor_surat, sm.asal_surat, sm.perihal')
                                 ->from('disposisi d')
                                 ->join('surat_masuk sm', 'sm.id = d.surat_masuk_id')
                                 ->where('d.id', $eksp['disposisi_id'])
                                 ->get()
                                 ->row_array();
                if ($disp) {
                    $this->kirim_notifikasi_ekspedisi_baru_user($id, $disp, $update_data['jenis_pengiriman'] ?? $eksp['jenis_pengiriman'], $users_data);

                    try {
                        $this->load->model('Wa_model');
                        $files_sm = $this->db->get_where('dokumen_file', ['surat_masuk_id' => $eksp['surat_masuk_id']])->result_array();
                        $this->Wa_model->kirim_notifikasi_ekspedisi($id, $disp, $update_data['jenis_pengiriman'] ?? $eksp['jenis_pengiriman'], $users_data, $update_data['catatan'] ?? $eksp['catatan'], $files_sm);
                    } catch (Throwable $e) {
                        log_message('error', 'Gagal kirim WA ekspedisi update: ' . $e->getMessage());
                    }
                }
            }
        }

        $this->update_status_global_ekspedisi($id);

        $this->db->trans_complete();
        return $this->db->trans_status();
    }

    /**
     * Hapus Ekspedisi
     */
    public function delete($id)
    {
        $this->db->trans_start();
        $this->db->where('ekspedisi_id', $id)->delete('ekspedisi_tujuan');
        $this->db->where('id', $id)->delete('ekspedisi');
        $this->db->trans_complete();
        return $this->db->trans_status();
    }

    /**
     * Report Ekspedisi: Daftar semua serah terima ekspedisi masuk lengkap
     */
    public function get_report_ekspedisi($limit = 500, $offset = 0, $filters = [])
    {
        $this->db->select('
            et.id as ekspedisi_tujuan_id,
            et.ekspedisi_id,
            et.user_tujuan_id,
            et.unit_tujuan,
            et.status as status_tujuan,
            et.received_at,
            et.received_by_user_id,
            et.rejected_at,
            et.rejected_by_user_id,
            et.alasan_tolak,
            et.catatan as catatan_tujuan,
            e.nomor_ekspedisi,
            e.jenis_pengiriman,
            e.status_global,
            e.catatan as catatan_pengirim,
            e.tanggal_kirim,
            sm.id as surat_masuk_id,
            sm.nomor_surat,
            sm.nomor_agenda,
            sm.asal_surat,
            sm.perihal,
            sm.tanggal_surat,
            sm.tanggal_terima,
            sm.keterangan as keterangan_surat,
            f.nama as nama_folder,
            f.warna as warna_folder,
            d.id as disposisi_id,
            d.nomor_disposisi,
            d.prioritas,
            d.batas_waktu,
            d.isi_disposisi,
            u_tujuan.nama_lengkap as nama_user_tujuan,
            u_tujuan.nip as nip_user_tujuan,
            u_tujuan.jabatan as jabatan_user_tujuan,
            u_tujuan.unit as unit_user_tujuan,
            u_pengirim.nama_lengkap as nama_pengirim,
            u_pengirim.nip as nip_pengirim,
            u_rec.nama_lengkap as nama_penerima,
            u_rec.nip as nip_penerima,
            u_rec.jabatan as jabatan_penerima,
            u_rej.nama_lengkap as nama_penolak,
            u_rej.nip as nip_penolak
        ');
        $this->db->from('ekspedisi_tujuan et');
        $this->db->join('ekspedisi e', 'e.id = et.ekspedisi_id');
        $this->db->join('surat_masuk sm', 'sm.id = e.surat_masuk_id');
        $this->db->join('disposisi d', 'd.id = e.disposisi_id');
        $this->db->join('folders f', 'f.id = sm.folder_id', 'left');
        $this->db->join('users u_tujuan', 'u_tujuan.id = et.user_tujuan_id', 'left');
        $this->db->join('users u_pengirim', 'u_pengirim.id = e.pengirim_id', 'left');
        $this->db->join('users u_rec', 'u_rec.id = et.received_by_user_id', 'left');
        $this->db->join('users u_rej', 'u_rej.id = et.rejected_by_user_id', 'left');

        $this->apply_report_filters($filters);

        $this->db->order_by('e.tanggal_kirim', 'DESC');
        $this->db->order_by('et.id', 'DESC');
        $this->db->limit($limit, $offset);
        $list = $this->db->get()->result_array();

        if (!empty($list)) {
            $sm_ids = array_unique(array_column($list, 'surat_masuk_id'));
            $files_map = $this->get_files_by_surat_masuk_ids($sm_ids);

            $ekspedisi_ids = array_unique(array_column($list, 'ekspedisi_id'));
            $all_tujuan = $this->get_tujuan_by_ekspedisi_ids($ekspedisi_ids);
            $grouped_tujuan = [];
            foreach ($all_tujuan as $t) {
                $grouped_tujuan[$t['ekspedisi_id']][] = $t;
            }

            foreach ($list as &$item) {
                $item['files'] = $files_map[$item['surat_masuk_id']] ?? [];
                $item['tujuan'] = $grouped_tujuan[$item['ekspedisi_id']] ?? [];
            }
            unset($item);
        }

        return $list;
    }

    /**
     * Hitung total baris laporan ekspedisi berdasarkan filter
     */
    public function count_report_ekspedisi($filters = [])
    {
        $this->db->from('ekspedisi_tujuan et');
        $this->db->join('ekspedisi e', 'e.id = et.ekspedisi_id');
        $this->db->join('surat_masuk sm', 'sm.id = e.surat_masuk_id');
        $this->db->join('disposisi d', 'd.id = e.disposisi_id');
        $this->db->join('users u_tujuan', 'u_tujuan.id = et.user_tujuan_id', 'left');
        $this->db->join('users u_pengirim', 'u_pengirim.id = e.pengirim_id', 'left');
        $this->db->join('users u_rec', 'u_rec.id = et.received_by_user_id', 'left');

        $this->apply_report_filters($filters);
        return $this->db->count_all_results();
    }

    /**
     * Statistik ringkasan untuk kartu metrik laporan ekspedisi
     */
    public function get_report_stats($filters = [])
    {
        $this->db->select('
            COUNT(et.id) as total,
            SUM(CASE WHEN et.status = "RECEIVED" THEN 1 ELSE 0 END) as total_received,
            SUM(CASE WHEN et.status = "PENDING" THEN 1 ELSE 0 END) as total_pending,
            SUM(CASE WHEN et.status = "REJECTED" THEN 1 ELSE 0 END) as total_rejected,
            SUM(CASE WHEN e.jenis_pengiriman = "DIGITAL" THEN 1 ELSE 0 END) as total_digital,
            SUM(CASE WHEN e.jenis_pengiriman = "FISIK" THEN 1 ELSE 0 END) as total_fisik
        ');
        $this->db->from('ekspedisi_tujuan et');
        $this->db->join('ekspedisi e', 'e.id = et.ekspedisi_id');
        $this->db->join('surat_masuk sm', 'sm.id = e.surat_masuk_id');
        $this->db->join('disposisi d', 'd.id = e.disposisi_id');
        $this->db->join('users u_tujuan', 'u_tujuan.id = et.user_tujuan_id', 'left');
        $this->db->join('users u_rec', 'u_rec.id = et.received_by_user_id', 'left');

        if (!empty($filters['unit'])) {
            $this->db->where('et.unit_tujuan', $filters['unit']);
        }
        if (!empty($filters['user_id'])) {
            $this->db->where('et.user_tujuan_id', (int)$filters['user_id']);
        }
        if (!empty($filters['tanggal_dari'])) {
            $this->db->where('DATE(e.tanggal_kirim) >=', $filters['tanggal_dari']);
        }
        if (!empty($filters['tanggal_sampai'])) {
            $this->db->where('DATE(e.tanggal_kirim) <=', $filters['tanggal_sampai']);
        }
        if (!empty($filters['q'])) {
            $q = trim($filters['q']);
            $this->db->group_start();
            $this->db->like('e.nomor_ekspedisi', $q);
            $this->db->or_like('sm.nomor_surat', $q);
            $this->db->or_like('sm.asal_surat', $q);
            $this->db->or_like('sm.perihal', $q);
            $this->db->or_like('sm.nomor_agenda', $q);
            $this->db->or_like('sm.keterangan', $q);
            $this->db->or_like('d.nomor_disposisi', $q);
            $this->db->or_like('u_tujuan.nama_lengkap', $q);
            $this->db->or_like('u_rec.nama_lengkap', $q);
            $this->db->or_like('et.unit_tujuan', $q);
            $this->db->group_end();
        }

        $row = $this->db->get()->row_array();
        return [
            'total'    => (int)($row['total'] ?? 0),
            'received' => (int)($row['total_received'] ?? 0),
            'pending'  => (int)($row['total_pending'] ?? 0),
            'rejected' => (int)($row['total_rejected'] ?? 0),
            'digital'  => (int)($row['total_digital'] ?? 0),
            'fisik'    => (int)($row['total_fisik'] ?? 0),
        ];
    }

    /**
     * Ambil daftar unit unik untuk dropdown filter
     */
    public function get_distinct_units()
    {
        $this->db->distinct();
        $this->db->select('unit_tujuan');
        $this->db->from('ekspedisi_tujuan');
        $this->db->where('unit_tujuan IS NOT NULL', null, false);
        $this->db->where('unit_tujuan !=', '');
        $this->db->order_by('unit_tujuan', 'ASC');
        $rows = $this->db->get()->result_array();
        return array_values(array_filter(array_column($rows, 'unit_tujuan')));
    }

    /**
     * Helper privat untuk klausul filter laporan
     */
    private function apply_report_filters($filters = [])
    {
        if (!empty($filters['status'])) {
            $this->db->where('et.status', $filters['status']);
        }
        if (!empty($filters['jenis'])) {
            $this->db->where('e.jenis_pengiriman', $filters['jenis']);
        }
        if (!empty($filters['unit'])) {
            $this->db->where('et.unit_tujuan', $filters['unit']);
        }
        if (!empty($filters['user_id'])) {
            $this->db->where('et.user_tujuan_id', (int)$filters['user_id']);
        }
        if (!empty($filters['tanggal_dari'])) {
            $this->db->where('DATE(e.tanggal_kirim) >=', $filters['tanggal_dari']);
        }
        if (!empty($filters['tanggal_sampai'])) {
            $this->db->where('DATE(e.tanggal_kirim) <=', $filters['tanggal_sampai']);
        }
        if (!empty($filters['q'])) {
            $q = trim($filters['q']);
            $this->db->group_start();
            $this->db->like('e.nomor_ekspedisi', $q);
            $this->db->or_like('sm.nomor_surat', $q);
            $this->db->or_like('sm.asal_surat', $q);
            $this->db->or_like('sm.perihal', $q);
            $this->db->or_like('sm.nomor_agenda', $q);
            $this->db->or_like('sm.keterangan', $q);
            $this->db->or_like('d.nomor_disposisi', $q);
            $this->db->or_like('u_tujuan.nama_lengkap', $q);
            $this->db->or_like('u_rec.nama_lengkap', $q);
            $this->db->or_like('et.unit_tujuan', $q);
            $this->db->group_end();
        }
    }
}

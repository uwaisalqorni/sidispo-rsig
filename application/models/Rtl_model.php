<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Rtl_model extends CI_Model {

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    private function apply_filters($filters = [])
    {
        if (!empty($filters['q'])) {
            $q = $filters['q'];
            $this->db->group_start();
            $this->db->like('d.nomor_disposisi', $q);
            $this->db->or_like('sm.perihal', $q);
            $this->db->or_like('rtl.deskripsi_rtl', $q);
            $this->db->or_like('u.nama_lengkap', $q);
            $this->db->group_end();
        }

        if (!empty($filters['status']) && $filters['status'] !== 'Semua') {
            $this->db->where('rtl.status_progress', $filters['status']);
        }

        if (!empty($filters['prioritas']) && $filters['prioritas'] !== 'Semua') {
            $this->db->where('rtl.prioritas', $filters['prioritas']);
        }

        $date_field = (!empty($filters['date_by']) && $filters['date_by'] === 'dibuat_at') ? 'DATE(rtl.dibuat_at)' : 'rtl.batas_waktu';

        if (!empty($filters['tanggal_dari'])) {
            $this->db->where($date_field . ' >=', $filters['tanggal_dari']);
        }

        if (!empty($filters['tanggal_sampai'])) {
            $this->db->where($date_field . ' <=', $filters['tanggal_sampai']);
        }
    }

    public function count_filtered($user_id = null, $role = null, $filters = [])
    {
        $this->db->from('rtl');
        $this->db->join('disposisi d', 'd.id = rtl.disposisi_id');
        $this->db->join('surat_masuk sm', 'sm.id = d.surat_masuk_id');
        $this->db->join('users u', 'u.id = rtl.dibuat_oleh');

        // Role-based visibility
        if ($role && !in_array($role, ['ADMIN', 'DIREKTUR'])) {
            $this->db->join('rtl_penerima my_rp', 'my_rp.rtl_id = rtl.id');
            $this->db->where('my_rp.user_id', $user_id);
        }

        $this->apply_filters($filters);

        return $this->db->count_all_results();
    }

    public function get_all($limit = 1000, $offset = 0, $user_id = null, $role = null, $filters = [])
    {
        $this->db->select('rtl.*, rtl.prioritas, d.nomor_disposisi, sm.perihal as perihal_surat, u.nama_lengkap as pembuat');
        $this->db->from('rtl');
        $this->db->join('disposisi d', 'd.id = rtl.disposisi_id');
        $this->db->join('surat_masuk sm', 'sm.id = d.surat_masuk_id');
        $this->db->join('users u', 'u.id = rtl.dibuat_oleh');

        // Role-based visibility
        if ($role && !in_array($role, ['ADMIN', 'DIREKTUR'])) {
            $this->db->join('rtl_penerima my_rp', 'my_rp.rtl_id = rtl.id');
            $this->db->where('my_rp.user_id', $user_id);
        }

        $this->apply_filters($filters);

        $this->db->order_by('rtl.id', 'DESC');
        if ($limit > 0) {
            $this->db->limit($limit, $offset);
        }
        $results = $this->db->get()->result_array();

        // Fetch penerima names for display in card and table payload
        foreach ($results as &$row) {
            $this->db->select('u.id as user_id, u.nama_lengkap, u.jabatan, rp.status as status_penerima');
            $this->db->from('rtl_penerima rp');
            $this->db->join('users u', 'u.id = rp.user_id');
            $this->db->where('rp.rtl_id', $row['id']);
            $penerimas = $this->db->get()->result_array();
            $row['penerima_names'] = implode(', ', array_column($penerimas, 'nama_lengkap'));
            $row['penerima_list']  = $penerimas;
        }
        return $results;
    }

    public function get_by_id($id)
    {
        $this->db->select('rtl.*, rtl.prioritas, d.nomor_disposisi, d.isi_disposisi, d.tanggal_disposisi, sm.nomor_surat, sm.asal_surat, sm.perihal as perihal_surat, sm.tanggal_surat, u.nama_lengkap as pembuat');
        $this->db->from('rtl');
        $this->db->join('disposisi d', 'd.id = rtl.disposisi_id');
        $this->db->join('surat_masuk sm', 'sm.id = d.surat_masuk_id');
        $this->db->join('users u', 'u.id = rtl.dibuat_oleh');
        $this->db->where('rtl.id', $id);
        return $this->db->get()->row_array();
    }

    public function get_detail_with_timeline($id)
    {
        $rtl = $this->get_by_id($id);
        if (!$rtl) return null;

        // Get penerima list
        $this->db->select('rp.id, rp.user_id, rp.status, u.nama_lengkap, u.jabatan');
        $this->db->from('rtl_penerima rp');
        $this->db->join('users u', 'u.id = rp.user_id');
        $this->db->where('rp.rtl_id', $id);
        $rtl['penerima'] = $this->db->get()->result_array();

        // Get timeline
        $this->db->select('rp.*, p.user_id as penerima_user_id, upen.nama_lengkap as nama_penerima, u.nama_lengkap as pembuat, u.jabatan');
        $this->db->from('rtl_progress rp');
        $this->db->join('rtl_penerima p', 'p.id = rp.rtl_penerima_id', 'left');
        $this->db->join('users upen', 'upen.id = p.user_id', 'left');
        $this->db->join('users u', 'u.id = rp.dibuat_oleh', 'left');
        $this->db->where('rp.rtl_id', $id);
        $this->db->order_by('rp.id', 'DESC');
        $timeline = $this->db->get()->result_array();

        $rtl['timeline'] = $timeline;
        return $rtl;
    }

    public function get_disposisi_selesai()
    {
        // Get all disposisi that are SELESAI
        $this->db->select('d.id, d.nomor_disposisi, d.tanggal_disposisi, sm.perihal');
        $this->db->from('disposisi d');
        $this->db->join('surat_masuk sm', 'sm.id = d.surat_masuk_id');
        $this->db->where('d.status_global', 'SELESAI');
        $this->db->order_by('d.id', 'DESC');
        return $this->db->get()->result_array();
    }

    public function insert($data, $penerima_ids = [])
    {
        $this->db->trans_start();
        $this->db->insert('rtl', $data);
        $rtl_id = $this->db->insert_id();

        if (!empty($penerima_ids)) {
            $batch = [];
            foreach ($penerima_ids as $uid) {
                $batch[] = ['rtl_id' => $rtl_id, 'user_id' => $uid, 'status' => 'TO_DO'];
            }
            $this->db->insert_batch('rtl_penerima', $batch);
        }

        $this->db->trans_complete();
        return $this->db->trans_status() ? $rtl_id : false;
    }

    public function update_progress_penerima($rtl_penerima_id, $status, $catatan, $user_id)
    {
        $this->db->trans_start();

        // 1. Get rtl_penerima
        $penerima = $this->db->get_where('rtl_penerima', ['id' => $rtl_penerima_id])->row_array();
        if (!$penerima) return false;

        $rtl_id = $penerima['rtl_id'];

        // 2. Update status in rtl_penerima
        $this->db->where('id', $rtl_penerima_id);
        $this->db->update('rtl_penerima', ['status' => $status]);

        // 3. Insert into rtl_progress for timeline
        $this->db->insert('rtl_progress', [
            'rtl_id' => $rtl_id,
            'rtl_penerima_id' => $rtl_penerima_id,
            'status_baru' => $status,
            'catatan' => $catatan,
            'dibuat_oleh' => $user_id
        ]);

        // 4. Recalculate master status
        $this->update_master_status($rtl_id);

        $this->db->trans_complete();
        return $this->db->trans_status();
    }

    private function update_master_status($rtl_id) {
        $penerimas = $this->db->get_where('rtl_penerima', ['rtl_id' => $rtl_id])->result_array();
        if (empty($penerimas)) return;

        $has_review = false;
        $has_on_progress = false;
        $all_done = true;
        $all_todo = true;

        foreach ($penerimas as $p) {
            if ($p['status'] === 'REVIEW') $has_review = true;
            if ($p['status'] === 'ON_PROGRESS') $has_on_progress = true;
            if ($p['status'] !== 'DONE') $all_done = false;
            if ($p['status'] !== 'TO_DO') $all_todo = false;
        }

        if ($has_review) $status = 'REVIEW';
        elseif ($has_on_progress || (!$all_done && !$all_todo)) $status = 'ON_PROGRESS';
        elseif ($all_done) $status = 'DONE';
        else $status = 'TO_DO';

        $this->db->where('id', $rtl_id);
        $this->db->update('rtl', ['status_progress' => $status]);
    }

    public function get_progress_log_by_id($id)
    {
        $this->db->select('rp.*, p.user_id as penerima_user_id');
        $this->db->from('rtl_progress rp');
        $this->db->join('rtl_penerima p', 'p.id = rp.rtl_penerima_id', 'left');
        $this->db->where('rp.id', $id);
        return $this->db->get()->row_array();
    }

    public function update_progress_log($id, $status_baru, $catatan)
    {
        $log = $this->get_progress_log_by_id($id);
        if (!$log) return false;

        $this->db->trans_start();

        // 1. Update baris rtl_progress
        $this->db->where('id', $id)->update('rtl_progress', [
            'status_baru' => $status_baru,
            'catatan'     => $catatan
        ]);

        // 2. Jika log ini adalah log paling baru untuk penerima ini, selaraskan status penerima
        if (!empty($log['rtl_penerima_id'])) {
            $latest = $this->db->where('rtl_penerima_id', $log['rtl_penerima_id'])
                               ->order_by('id', 'DESC')
                               ->limit(1)
                               ->get('rtl_progress')
                               ->row_array();
            if ($latest && (int)$latest['id'] === (int)$id) {
                $this->db->where('id', $log['rtl_penerima_id'])->update('rtl_penerima', [
                    'status' => $status_baru
                ]);
                $this->update_master_status($log['rtl_id']);
            }
        }

        $this->db->trans_complete();
        return $this->db->trans_status();
    }

    public function delete_progress_log($id)
    {
        $log = $this->get_progress_log_by_id($id);
        if (!$log) return false;

        $this->db->trans_start();

        // 1. Hapus catatan log
        $this->db->where('id', $id)->delete('rtl_progress');

        // 2. Ambil log terbaru yang tersisa untuk penerima ini
        if (!empty($log['rtl_penerima_id'])) {
            $remaining = $this->db->where('rtl_penerima_id', $log['rtl_penerima_id'])
                                  ->order_by('id', 'DESC')
                                  ->limit(1)
                                  ->get('rtl_progress')
                                  ->row_array();
            if ($remaining) {
                $this->db->where('id', $log['rtl_penerima_id'])->update('rtl_penerima', [
                    'status' => $remaining['status_baru']
                ]);
            } else {
                $this->db->where('id', $log['rtl_penerima_id'])->update('rtl_penerima', [
                    'status' => 'TO_DO'
                ]);
            }
            $this->update_master_status($log['rtl_id']);
        }

        $this->db->trans_complete();
        return $this->db->trans_status();
    }

    public function update($id, $data, $penerima_ids = null)
    {
        $this->db->trans_start();
        $this->db->where('id', $id)->update('rtl', $data);

        if ($penerima_ids !== null && is_array($penerima_ids)) {
            // Ambil penerima yang sudah terdaftar sebelumnya
            $existing = $this->db->where('rtl_id', $id)->get('rtl_penerima')->result_array();
            $existing_uids = array_map(function($p) { return (int)$p['user_id']; }, $existing);
            $new_uids = array_map('intval', $penerima_ids);

            // Hapus penerima yang di-uncheck
            foreach ($existing as $ex) {
                if (!in_array((int)$ex['user_id'], $new_uids)) {
                    $this->db->where('rtl_penerima_id', $ex['id'])->delete('rtl_progress');
                    $this->db->where('id', $ex['id'])->delete('rtl_penerima');
                }
            }

            // Tambah penerima baru yang belum ada
            $batch = [];
            foreach ($new_uids as $uid) {
                if (!in_array($uid, $existing_uids)) {
                    $batch[] = ['rtl_id' => $id, 'user_id' => $uid, 'status' => 'TO_DO'];
                }
            }
            if (!empty($batch)) {
                $this->db->insert_batch('rtl_penerima', $batch);
            }

            // Hitung ulang status master RTL jika perlu
            $this->update_master_status($id);
        }

        $this->db->trans_complete();
        return $this->db->trans_status();
    }

    public function delete($id)
    {
        $this->db->trans_start();
        $this->db->where('rtl_id', $id)->delete('rtl_progress');
        $this->db->where('rtl_id', $id)->delete('rtl_penerima');
        $this->db->where('id', $id)->delete('rtl');
        $this->db->trans_complete();
        return $this->db->trans_status();
    }
}

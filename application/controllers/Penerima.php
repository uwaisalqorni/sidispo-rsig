<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Penerima extends MY_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->require_auth();
        $this->load->model('Penerima_model', 'penerima');
        $this->load->model('Progress_model', 'progress');
        $this->load->model('Notifikasi_model', 'notifikasi');
    }

    /**
     * GET /api/v1/penerima/{disposisi_id}
     * List semua penerima + status tiap orang untuk 1 disposisi
     */
    public function index($disposisi_id = null)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        if (!$disposisi_id) {
            return $this->response(['status' => 'error', 'message' => 'ID Disposisi dibutuhkan'], 400);
        }

        $data = $this->penerima->get_by_disposisi($disposisi_id);

        $this->response(['status' => 'success', 'data' => $data], 200);
    }

    /**
     * PUT /api/v1/penerima/{id}/status
     * Update status penerima (PROSES / TUNGGU / SELESAI)
     * Hanya bisa dilakukan oleh penerima sendiri (STAF)
     */
    public function update_status($id = null)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        if (!$id) {
            return $this->response(['status' => 'error', 'message' => 'ID Penerima dibutuhkan'], 400);
        }

        $input = json_decode(trim(file_get_contents('php://input')), true);
        $new_status = $input['status'] ?? null;
        $catatan    = $input['catatan'] ?? null;

        $allowed_statuses = ['PROSES', 'TUNGGU', 'SELESAI'];
        if (!$new_status || !in_array($new_status, $allowed_statuses)) {
            return $this->response([
                'status'  => 'error',
                'message' => 'Status tidak valid. Pilihan: PROSES, TUNGGU, SELESAI'
            ], 400);
        }

        // Get current record first (for logging status_lama)
        $penerima_record = $this->penerima->get_by_id($id);
        if (!$penerima_record) {
            return $this->response(['status' => 'error', 'message' => 'Penerima tidak ditemukan'], 404);
        }

        $status_lama = $penerima_record['status'];

        $this->db->trans_start();

        // 1. Update status di disposisi_penerima
        $updated = $this->penerima->update_status($id, $this->current_user->id, $new_status, $catatan);

        if (!$updated) {
            $this->db->trans_rollback();
            return $this->response([
                'status'  => 'error',
                'message' => 'Gagal update status atau Anda tidak berhak mengubah data ini'
            ], 403);
        }

        // 2. Log ke progress_log (append-only — WAJIB setiap update status)
        $this->progress->store($id, $this->current_user->id, $status_lama, $new_status, $catatan);

        // 3. Kirim notifikasi ke pembuat disposisi jika status = SELESAI
        if ($new_status === 'SELESAI') {
            $this->db->select('d.dibuat_oleh, d.id as disposisi_id, d.isi_disposisi');
            $this->db->from('disposisi_penerima dp');
            $this->db->join('disposisi d', 'd.id = dp.disposisi_id');
            $this->db->where('dp.id', $id);
            $disposisi = $this->db->get()->row_array();

            if ($disposisi) {
                $this->notifikasi->insert([
                    'user_id'      => $disposisi['dibuat_oleh'],
                    'jenis'        => 'PROGRESS',
                    'judul'        => 'Tindak Lanjut Selesai',
                    'pesan'        => $this->current_user->nama_lengkap . ' telah menyelesaikan tindak lanjut disposisi.',
                    'disposisi_id' => $disposisi['disposisi_id']
                ]);
            }
        }

        $this->db->trans_complete();

        if ($this->db->trans_status() === FALSE) {
            return $this->response(['status' => 'error', 'message' => 'Terjadi kesalahan saat menyimpan'], 500);
        }

        $this->response([
            'status'  => 'success',
            'message' => 'Status berhasil diperbarui menjadi ' . $new_status
        ], 200);
    }
}

<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Progress extends MY_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->require_auth();
        $this->load->model('Progress_model', 'progress');
        $this->load->model('Penerima_model', 'penerima');
    }

    /**
     * GET /api/v1/progress/{penerima_id}
     * Ambil timeline progress (log) untuk satu penerima — urut by created_at ASC
     */
    public function index($penerima_id = null)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        if (!$penerima_id) {
            return $this->response(['status' => 'error', 'message' => 'ID Penerima dibutuhkan'], 400);
        }

        $timeline = $this->progress->get_timeline($penerima_id);

        $this->response([
            'status' => 'success',
            'data'   => $timeline
        ], 200);
    }

    /**
     * POST /api/v1/progress/{penerima_id}
     * Tambah log progress dengan catatan (append-only, tidak bisa dihapus/edit)
     * Status penerima juga ikut diperbarui
     */
    public function store($penerima_id = null)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        if (!$penerima_id) {
            return $this->response(['status' => 'error', 'message' => 'ID Penerima dibutuhkan'], 400);
        }

        $input = json_decode(trim(file_get_contents('php://input')), true);

        $status_baru = $input['status'] ?? null;
        $catatan     = $input['catatan'] ?? null;

        if (!$catatan) {
            return $this->response(['status' => 'error', 'message' => 'Catatan progress wajib diisi'], 400);
        }

        $allowed_statuses = ['PROSES', 'TUNGGU', 'SELESAI'];
        if ($status_baru && !in_array($status_baru, $allowed_statuses)) {
            return $this->response(['status' => 'error', 'message' => 'Status tidak valid'], 400);
        }

        // Verifikasi penerima milik user ini
        $penerima_record = $this->penerima->get_by_id($penerima_id);
        if (!$penerima_record) {
            return $this->response(['status' => 'error', 'message' => 'Penerima tidak ditemukan'], 404);
        }
        if ($penerima_record['user_id'] != $this->current_user->id) {
            return $this->response(['status' => 'error', 'message' => 'Anda tidak berhak menambah progress ini'], 403);
        }

        $status_lama = $penerima_record['status'];
        $status_aktual = $status_baru ?? $status_lama; // Jika tidak ada status baru, tetap gunakan status lama

        $this->db->trans_start();

        // 1. Simpan log (append-only)
        $log_id = $this->progress->store($penerima_id, $this->current_user->id, $status_lama, $status_aktual, $catatan);

        // 2. Jika ada perubahan status, update disposisi_penerima
        if ($status_baru && $status_baru !== $status_lama) {
            $this->penerima->update_status($penerima_id, $this->current_user->id, $status_baru, $catatan);
        }

        $this->db->trans_complete();

        if ($this->db->trans_status() === FALSE) {
            return $this->response(['status' => 'error', 'message' => 'Gagal menyimpan progress'], 500);
        }

        $this->response([
            'status'  => 'success',
            'message' => 'Progress berhasil dicatat',
            'log_id'  => $log_id
        ], 201);
    }

    /**
     * GET /api/v1/progress/disposisi/{disposisi_id}
     * Ambil semua timeline dari semua penerima dalam 1 disposisi (untuk Detail View)
     */
    public function by_disposisi($disposisi_id = null)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        if (!$disposisi_id) {
            return $this->response(['status' => 'error', 'message' => 'ID Disposisi dibutuhkan'], 400);
        }

        $timeline = $this->progress->get_timeline_by_disposisi($disposisi_id);

        $this->response([
            'status' => 'success',
            'data'   => $timeline
        ], 200);
    }
}

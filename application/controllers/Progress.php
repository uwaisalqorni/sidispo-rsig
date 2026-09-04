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

    /**
     * PUT or POST /api/v1/progress/log/{id}
     * Update catatan dan status progress milik sendiri
     */
    public function update($id = null)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        if (!$id) {
            return $this->response(['status' => 'error', 'message' => 'ID progress dibutuhkan'], 400);
        }

        $log = $this->progress->get_by_id($id);
        if (!$log) {
            return $this->response(['status' => 'error', 'message' => 'Catatan progress tidak ditemukan'], 404);
        }

        // Pengguna hanya bisa mengupdate progressnya sendiri
        if ((int)$log['user_id'] !== (int)$this->current_user->id) {
            return $this->response(['status' => 'error', 'message' => 'Anda hanya dapat mengubah catatan progress Anda sendiri.'], 403);
        }

        $input = json_decode(trim(file_get_contents('php://input')), true);
        $status_baru = $input['status'] ?? $log['status_baru'];
        $catatan     = trim($input['catatan'] ?? '');

        if ($catatan === '') {
            return $this->response(['status' => 'error', 'message' => 'Catatan progress wajib diisi.'], 400);
        }

        $allowed_statuses = ['PROSES', 'TUNGGU', 'SELESAI'];
        if (!in_array($status_baru, $allowed_statuses)) {
            return $this->response(['status' => 'error', 'message' => 'Status tidak valid.'], 400);
        }

        $this->db->trans_start();

        // 1. Update baris progress_log
        $this->progress->update_log($id, [
            'status_baru' => $status_baru,
            'catatan'     => $catatan
        ]);

        // 2. Jika log ini adalah log paling terakhir untuk penerima ini, selaraskan status penerima
        $latest = $this->progress->get_latest_log($log['disposisi_penerima_id']);
        if ($latest && (int)$latest['id'] === (int)$id) {
            $penerima_update = [
                'status'        => $status_baru,
                'catatan_akhir' => $catatan
            ];
            if ($status_baru === 'SELESAI') {
                $penerima_update['tanggal_selesai'] = date('Y-m-d H:i:s');
            } else {
                $penerima_update['tanggal_selesai'] = null;
                // Jika berubah dari SELESAI menjadi selain SELESAI, kembalikan status_global disposisi ke AKTIF jika tadinya SELESAI
                $this->db->where('id', $log['disposisi_id'])
                         ->where('status_global', 'SELESAI')
                         ->update('disposisi', ['status_global' => 'AKTIF']);
            }
            $this->db->where('id', $log['disposisi_penerima_id'])->update('disposisi_penerima', $penerima_update);
        }

        $this->db->trans_complete();

        if ($this->db->trans_status() === FALSE) {
            return $this->response(['status' => 'error', 'message' => 'Gagal memperbarui progress'], 500);
        }

        return $this->response([
            'status'  => 'success',
            'message' => 'Progress berhasil diperbarui'
        ], 200);
    }

    /**
     * DELETE or POST /api/v1/progress/log/{id}
     * Hapus catatan progress milik sendiri (tidak bisa hapus progress user lain)
     */
    public function destroy($id = null)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        if (!$id) {
            return $this->response(['status' => 'error', 'message' => 'ID progress dibutuhkan'], 400);
        }

        $log = $this->progress->get_by_id($id);
        if (!$log) {
            return $this->response(['status' => 'error', 'message' => 'Catatan progress tidak ditemukan'], 404);
        }

        // Pengguna hanya bisa menghapus progress miliknya sendiri
        if ((int)$log['user_id'] !== (int)$this->current_user->id) {
            return $this->response(['status' => 'error', 'message' => 'Anda tidak dapat menghapus progress pengguna lain.'], 403);
        }

        $dp_id = $log['disposisi_penerima_id'];
        $disp_id = $log['disposisi_id'];

        $this->db->trans_start();

        // 1. Hapus catatan log
        $this->progress->delete_log($id);

        // 2. Ambil log terbaru yang tersisa untuk penerima ini
        $remaining_latest = $this->progress->get_latest_log($dp_id);
        if ($remaining_latest) {
            $penerima_update = [
                'status'        => $remaining_latest['status_baru'],
                'catatan_akhir' => $remaining_latest['catatan']
            ];
            if ($remaining_latest['status_baru'] === 'SELESAI') {
                $penerima_update['tanggal_selesai'] = $remaining_latest['created_at'];
            } else {
                $penerima_update['tanggal_selesai'] = null;
                $this->db->where('id', $disp_id)
                         ->where('status_global', 'SELESAI')
                         ->update('disposisi', ['status_global' => 'AKTIF']);
            }
            $this->db->where('id', $dp_id)->update('disposisi_penerima', $penerima_update);
        } else {
            // Jika sudah tidak ada log lagi, kembalikan status penerima ke DITERIMA
            $this->db->where('id', $dp_id)->update('disposisi_penerima', [
                'status'          => 'DITERIMA',
                'catatan_akhir'   => null,
                'tanggal_selesai' => null
            ]);
            $this->db->where('id', $disp_id)
                     ->where('status_global', 'SELESAI')
                     ->update('disposisi', ['status_global' => 'AKTIF']);
        }

        $this->db->trans_complete();

        if ($this->db->trans_status() === FALSE) {
            return $this->response(['status' => 'error', 'message' => 'Gagal menghapus progress'], 500);
        }

        return $this->response([
            'status'  => 'success',
            'message' => 'Progress berhasil dihapus'
        ], 200);
    }
}

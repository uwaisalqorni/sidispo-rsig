<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Ekspedisi extends MY_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->require_auth();
        $this->load->model('Ekspedisi_model', 'ekspedisi_m');
    }

    /**
     * GET /api/v1/ekspedisi
     * List ekspedisi (role-aware: Admin/Direktur -> all pengiriman; Staf/Pejabat -> ekspedisi masuk)
     */
    public function index()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        $limit = $this->input->get('limit') ? (int)$this->input->get('limit') : 100;
        $offset = $this->input->get('offset') ? (int)$this->input->get('offset') : 0;
        $role = $this->current_user->role;

        $filters = [];
        $q = $this->input->get('q');
        $status = $this->input->get('status');
        $jenis = $this->input->get('jenis');
        $tanggal_dari = $this->input->get('tanggal_dari');
        $tanggal_sampai = $this->input->get('tanggal_sampai');

        if (!empty($q)) $filters['q'] = trim($q);
        if (!empty($status)) $filters['status'] = trim($status);
        if (!empty($jenis)) $filters['jenis'] = trim($jenis);
        if (!empty($tanggal_dari)) $filters['tanggal_dari'] = $tanggal_dari;
        if (!empty($tanggal_sampai)) $filters['tanggal_sampai'] = $tanggal_sampai;

        if ($role === 'ADMIN' || $role === 'DIREKTUR') {
            $data = $this->ekspedisi_m->get_all_pengiriman($limit, $offset, $filters);
        } else {
            $data = $this->ekspedisi_m->get_ekspedisi_masuk_by_user(
                $this->current_user->id,
                $this->current_user->unit ?? '',
                $limit,
                $offset,
                $filters
            );
        }

        $this->response(['status' => 'success', 'data' => $data], 200);
    }

    /**
     * GET /api/v1/ekspedisi/siap-kirim
     * Tab 1 Admin: Daftar disposisi SELESAI yang siap dikirim
     */
    public function siap_kirim()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        $limit = $this->input->get('limit') ? (int)$this->input->get('limit') : 100;
        $offset = $this->input->get('offset') ? (int)$this->input->get('offset') : 0;

        $filters = [];
        $q = $this->input->get('q');
        $include_sent = $this->input->get('include_sent');

        if (!empty($q)) $filters['q'] = trim($q);
        if ($include_sent === 'true' || $include_sent === '1') $filters['include_sent'] = true;

        $data = $this->ekspedisi_m->get_siap_kirim($limit, $offset, $filters);
        $this->response(['status' => 'success', 'data' => $data], 200);
    }

    /**
     * GET /api/v1/ekspedisi/masuk
     * Tab / View Ekspedisi Masuk untuk User login (termasuk berkas surat)
     */
    public function masuk()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        $limit = $this->input->get('limit') ? (int)$this->input->get('limit') : 100;
        $offset = $this->input->get('offset') ? (int)$this->input->get('offset') : 0;

        $filters = [];
        $q = $this->input->get('q');
        $status = $this->input->get('status');
        $jenis = $this->input->get('jenis');

        if (!empty($q)) $filters['q'] = trim($q);
        if (!empty($status)) $filters['status'] = trim($status);
        if (!empty($jenis)) $filters['jenis'] = trim($jenis);

        $data = $this->ekspedisi_m->get_ekspedisi_masuk_by_user(
            $this->current_user->id,
            $this->current_user->unit ?? '',
            $limit,
            $offset,
            $filters
        );

        $this->response(['status' => 'success', 'data' => $data], 200);
    }

    /**
     * GET /api/v1/ekspedisi/user-options
     * Pilihan user penerima ekspedisi (seperti menu disposisi)
     */
    public function user_options()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        $q = $this->input->get('q');
        $role = $this->input->get('role');

        $data = $this->ekspedisi_m->get_user_options($q, $role);
        $this->response(['status' => 'success', 'data' => $data], 200);
    }

    /**
     * GET /api/v1/ekspedisi/{id}
     * Detail 1 Ekspedisi (termasuk berkas lampiran file surat)
     */
    public function detail($id = null)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        if (!$id) {
            return $this->response(['status' => 'error', 'message' => 'ID ekspedisi wajib diisi'], 400);
        }

        $data = $this->ekspedisi_m->get_detail($id);
        if (!$data) {
            return $this->response(['status' => 'error', 'message' => 'Data ekspedisi tidak ditemukan'], 404);
        }

        $this->response(['status' => 'success', 'data' => $data], 200);
    }

    /**
     * GET /api/v1/ekspedisi/lembar-disposisi/{disposisi_id}
     * Ambil data disposisi resmi untuk Lembar Disposisi Selesai bagi penerima ekspedisi
     */
    public function lembar_disposisi($disposisi_id = null)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        if (!$disposisi_id) {
            return $this->response(['status' => 'error', 'message' => 'ID Disposisi dibutuhkan'], 400);
        }

        $this->load->model('Disposisi_model', 'disposisi_m');
        $disp = $this->disposisi_m->get_by_id($disposisi_id);
        if (!$disp) {
            return $this->response(['status' => 'error', 'message' => 'Disposisi tidak ditemukan'], 404);
        }

        $role = $this->current_user->role;
        $user_id = (int)$this->current_user->id;

        // Validasi: Boleh dilihat jika ADMIN, DIREKTUR, pembuat/penerima disposisi, ATAU penerima ekspedisi surat terkait
        if ($role !== 'ADMIN' && $role !== 'DIREKTUR' && (int)$disp['dibuat_oleh'] !== $user_id) {
            $is_authorized = false;

            // Cek apakah penerima disposisi
            foreach ($disp['penerima'] ?? [] as $p) {
                if ((int)$p['user_id'] === $user_id) {
                    $is_authorized = true;
                    break;
                }
            }

            // Jika bukan penerima disposisi, cek apakah penerima ekspedisi
            if (!$is_authorized) {
                $check_exp = $this->db->select('et.id')
                    ->from('ekspedisi_tujuan et')
                    ->join('ekspedisi e', 'e.id = et.ekspedisi_id')
                    ->where('e.disposisi_id', $disposisi_id)
                    ->group_start()
                        ->where('et.user_tujuan_id', $user_id)
                        ->or_where('et.unit_tujuan', $this->current_user->unit ?? '')
                    ->group_end()
                    ->limit(1)
                    ->get()
                    ->row_array();

                if ($check_exp) {
                    $is_authorized = true;
                }
            }

            if (!$is_authorized) {
                return $this->response([
                    'status'  => 'error',
                    'message' => 'Akses ditolak. Anda tidak memiliki izin untuk melihat dokumen ini.'
                ], 403);
            }
        }

        return $this->response([
            'status' => 'success',
            'data'   => $disp
        ], 200);
    }

    /**
     * POST /api/v1/ekspedisi
     * Buat Ekspedisi baru dengan User Tujuan & Tanggal Kirim
     */
    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        if (!in_array($this->current_user->role, ['ADMIN', 'DIREKTUR'])) {
            return $this->response(['status' => 'error', 'message' => 'Hanya Admin/Sekretariat yang dapat membuat ekspedisi'], 403);
        }

        $body = json_decode(file_get_contents('php://input'), true);
        if (empty($body)) $body = $this->input->post();

        $disposisi_id     = (int)($body['disposisi_id'] ?? 0);
        $jenis_pengiriman = trim($body['jenis_pengiriman'] ?? 'DIGITAL');
        $catatan          = trim($body['catatan'] ?? '');
        $tanggal_kirim    = trim($body['tanggal_kirim'] ?? '');
        $user_tujuan      = $body['user_tujuan'] ?? ($body['unit_tujuan'] ?? []);

        if (!$disposisi_id) {
            return $this->response(['status' => 'error', 'message' => 'Disposisi ID wajib dipilih'], 400);
        }

        if (empty($user_tujuan) || !is_array($user_tujuan)) {
            return $this->response(['status' => 'error', 'message' => 'Minimal pilih 1 user/penerima tujuan ekspedisi'], 400);
        }

        $new_id = $this->ekspedisi_m->create_ekspedisi(
            $disposisi_id,
            $jenis_pengiriman,
            $catatan,
            $user_tujuan,
            $this->current_user->id,
            $tanggal_kirim
        );

        if ($new_id) {
            $data = $this->ekspedisi_m->get_detail($new_id);
            return $this->response([
                'status'  => 'success',
                'message' => 'Ekspedisi surat berhasil dibuat dan dikirim ke user tujuan',
                'data'    => $data
            ], 201);
        }

        return $this->response(['status' => 'error', 'message' => 'Gagal membuat ekspedisi'], 500);
    }

    /**
     * PUT /api/v1/ekspedisi/terima/{tujuan_id}
     * Konfirmasi terima oleh user penerima
     */
    public function terima($tujuan_id = null)
    {
        if (!in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'POST'])) {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        if (!$tujuan_id) {
            return $this->response(['status' => 'error', 'message' => 'ID tujuan ekspedisi wajib diisi'], 400);
        }

        $body = json_decode(file_get_contents('php://input'), true);
        $catatan = trim($body['catatan'] ?? '');

        $success = $this->ekspedisi_m->konfirmasi_terima($tujuan_id, $this->current_user->id, $catatan);
        if ($success) {
            return $this->response([
                'status'  => 'success',
                'message' => 'Dokumen ekspedisi berhasil dikonfirmasi DITERIMA'
            ], 200);
        }

        return $this->response(['status' => 'error', 'message' => 'Gagal mengonfirmasi penerimaan dokumen'], 500);
    }

    /**
     * PUT /api/v1/ekspedisi/tolak/{tujuan_id}
     * Tolak/kembalikan dokumen oleh user penerima
     */
    public function tolak($tujuan_id = null)
    {
        if (!in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'POST'])) {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        if (!$tujuan_id) {
            return $this->response(['status' => 'error', 'message' => 'ID tujuan ekspedisi wajib diisi'], 400);
        }

        $body = json_decode(file_get_contents('php://input'), true);
        $alasan_tolak = trim($body['alasan_tolak'] ?? '');

        if (empty($alasan_tolak)) {
            return $this->response(['status' => 'error', 'message' => 'Alasan penolakan wajib diisi'], 400);
        }

        $success = $this->ekspedisi_m->tolak_ekspedisi($tujuan_id, $this->current_user->id, $alasan_tolak);
        if ($success) {
            return $this->response([
                'status'  => 'success',
                'message' => 'Dokumen ekspedisi telah DITOLAK dan dikembalikan ke pengirim'
            ], 200);
        }

        return $this->response(['status' => 'error', 'message' => 'Gagal menolak dokumen ekspedisi'], 500);
    }

    /**
     * PUT /api/v1/ekspedisi/{id}/revisi
     * Revisi & kirim ulang ekspedisi (Admin)
     */
    public function revisi($id = null)
    {
        if (!in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'POST'])) {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        if (!$id) {
            return $this->response(['status' => 'error', 'message' => 'ID ekspedisi wajib diisi'], 400);
        }

        $body = json_decode(file_get_contents('php://input'), true);
        $catatan_baru       = $body['catatan'] ?? null;
        $jenis_baru         = $body['jenis_pengiriman'] ?? null;
        $tanggal_kirim_baru = $body['tanggal_kirim'] ?? null;

        $success = $this->ekspedisi_m->revisi_kirim_ulang($id, $catatan_baru, $jenis_baru, $tanggal_kirim_baru);
        if ($success) {
            return $this->response([
                'status'  => 'success',
                'message' => 'Ekspedisi berhasil direvisi dan dikirim ulang'
            ], 200);
        }

        return $this->response(['status' => 'error', 'message' => 'Gagal merevisi ekspedisi'], 500);
    }

    /**
     * PUT/POST /api/v1/ekspedisi/{id}
     * Update data ekspedisi (Hanya Admin)
     */
    public function update($id = null)
    {
        if (!in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'POST'])) {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        if ($this->current_user->role !== 'ADMIN') {
            return $this->response(['status' => 'error', 'message' => 'Hanya Admin yang memiliki hak akses untuk mengubah data ekspedisi'], 403);
        }

        if (!$id) {
            return $this->response(['status' => 'error', 'message' => 'ID ekspedisi wajib diisi'], 400);
        }

        $body = json_decode(file_get_contents('php://input'), true);
        if (empty($body)) $body = $this->input->post();

        $data_update = [];
        if (isset($body['jenis_pengiriman'])) $data_update['jenis_pengiriman'] = trim($body['jenis_pengiriman']);
        if (isset($body['tanggal_kirim'])) $data_update['tanggal_kirim'] = trim($body['tanggal_kirim']);
        if (isset($body['catatan'])) $data_update['catatan'] = trim($body['catatan']);

        $user_tujuan = isset($body['user_tujuan']) ? $body['user_tujuan'] : (isset($body['unit_tujuan']) ? $body['unit_tujuan'] : null);

        $success = $this->ekspedisi_m->update_ekspedisi($id, $data_update, $user_tujuan);
        if ($success) {
            $updated = $this->ekspedisi_m->get_detail($id);
            return $this->response([
                'status'  => 'success',
                'message' => 'Data ekspedisi berhasil diperbarui',
                'data'    => $updated
            ], 200);
        }

        return $this->response(['status' => 'error', 'message' => 'Gagal memperbarui data ekspedisi'], 500);
    }

    /**
     * DELETE /api/v1/ekspedisi/{id}
     * Hapus ekspedisi (Hanya Admin)
     */
    public function destroy($id = null)
    {
        if (!in_array($_SERVER['REQUEST_METHOD'], ['DELETE', 'POST'])) {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        if ($this->current_user->role !== 'ADMIN') {
            return $this->response(['status' => 'error', 'message' => 'Hanya Admin yang memiliki hak akses untuk menghapus ekspedisi'], 403);
        }

        if (!$id) {
            return $this->response(['status' => 'error', 'message' => 'ID ekspedisi wajib diisi'], 400);
        }

        $success = $this->ekspedisi_m->delete($id);
        if ($success) {
            return $this->response(['status' => 'success', 'message' => 'Ekspedisi berhasil dihapus'], 200);
        }

        return $this->response(['status' => 'error', 'message' => 'Gagal menghapus data ekspedisi'], 500);
    }

    /**
     * GET /api/v1/ekspedisi/report
     * Laporan komprehensif semua ekspedisi masuk dengan filter dan statistik
     */
    public function report()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        $limit = $this->input->get('limit') ? (int)$this->input->get('limit') : 20;
        $offset = $this->input->get('offset') ? (int)$this->input->get('offset') : 0;

        $filters = [];
        $q = $this->input->get('q');
        $status = $this->input->get('status');
        $jenis = $this->input->get('jenis');
        $unit = $this->input->get('unit');
        $user_id = $this->input->get('user_id');
        $tanggal_dari = $this->input->get('tanggal_dari');
        $tanggal_sampai = $this->input->get('tanggal_sampai');

        if (!empty($q)) $filters['q'] = trim($q);
        if (!empty($status)) $filters['status'] = trim($status);
        if (!empty($jenis)) $filters['jenis'] = trim($jenis);
        if (!empty($unit)) $filters['unit'] = trim($unit);
        if (!empty($user_id)) $filters['user_id'] = (int)$user_id;
        if (!empty($tanggal_dari)) $filters['tanggal_dari'] = $tanggal_dari;
        if (!empty($tanggal_sampai)) $filters['tanggal_sampai'] = $tanggal_sampai;

        // Jika bukan ADMIN atau DIREKTUR, batasi ke unit / user sendiri jika belum difilter
        if ($this->current_user->role !== 'ADMIN' && $this->current_user->role !== 'DIREKTUR') {
            if (empty($filters['unit']) && empty($filters['user_id'])) {
                if (!empty($this->current_user->unit)) {
                    $filters['unit'] = $this->current_user->unit;
                } else {
                    $filters['user_id'] = $this->current_user->id;
                }
            }
        }

        $data = $this->ekspedisi_m->get_report_ekspedisi($limit, $offset, $filters);
        $total = $this->ekspedisi_m->count_report_ekspedisi($filters);
        $stats = $this->ekspedisi_m->get_report_stats($filters);
        $units = $this->ekspedisi_m->get_distinct_units();

        return $this->response([
            'status' => 'success',
            'data'   => $data,
            'total'  => $total,
            'stats'  => $stats,
            'units'  => $units
        ], 200);
    }
}


<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Perihal Controller — Master Data Perihal Surat
 * CRUD endpoint untuk mengelola daftar perihal baku
 */
class Perihal extends MY_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->require_auth();
        $this->load->model('Perihal_model', 'perihal');
    }

    /** Middleware: hanya ADMIN yang bisa mengubah master perihal */
    private function require_admin()
    {
        if ($this->current_user->role !== 'ADMIN') {
            $this->response(['status' => 'error', 'message' => 'Akses ditolak. Hanya Admin yang dapat mengelola Master Perihal.'], 403);
            exit();
        }
    }

    /**
     * GET /api/v1/perihal
     * List semua perihal (semua role yang sudah login dapat mengakses)
     */
    public function index()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        // ?active=1 untuk hanya ambil yang aktif (dipakai oleh dropdown form)
        $active_only = $this->input->get('active') == '1';
        $perihal = $this->perihal->get_all($active_only);
        $this->response(['status' => 'success', 'data' => $perihal]);
    }

    /**
     * GET /api/v1/perihal/:id
     * Detail satu perihal
     */
    public function show($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        $row = $this->perihal->get_by_id($id);
        if (!$row) {
            return $this->response(['status' => 'error', 'message' => 'Perihal tidak ditemukan.'], 404);
        }
        $this->response(['status' => 'success', 'data' => $row]);
    }

    /**
     * POST /api/v1/admin/perihal
     * Buat perihal baru (ADMIN only)
     */
    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }
        $this->require_admin();

        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) $data = $this->input->post();

        if (empty($data['nama'])) {
            return $this->response(['status' => 'error', 'message' => 'Nama perihal wajib diisi.'], 422);
        }

        // Cek duplikat
        if ($this->perihal->nama_exists(trim($data['nama']))) {
            return $this->response(['status' => 'error', 'message' => 'Perihal dengan nama tersebut sudah ada.'], 409);
        }

        $insert = [
            'nama'       => trim($data['nama']),
            'kode'       => !empty($data['kode']) ? strtoupper(trim($data['kode'])) : null,
            'keterangan' => $data['keterangan'] ?? null,
            'is_active'  => isset($data['is_active']) ? (int)$data['is_active'] : 1,
            'created_by' => $this->current_user->id,
        ];

        $id = $this->perihal->create($insert);
        if ($id) {
            $row = $this->perihal->get_by_id($id);
            $this->response(['status' => 'success', 'message' => 'Perihal berhasil ditambahkan.', 'data' => $row], 201);
        } else {
            $this->response(['status' => 'error', 'message' => 'Gagal menambahkan perihal.'], 500);
        }
    }

    /**
     * PUT /api/v1/admin/perihal/:id
     * Update perihal (ADMIN only)
     */
    public function update($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }
        $this->require_admin();

        $row = $this->perihal->get_by_id($id);
        if (!$row) {
            return $this->response(['status' => 'error', 'message' => 'Perihal tidak ditemukan.'], 404);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) $data = $this->input->post();

        if (empty($data['nama'])) {
            return $this->response(['status' => 'error', 'message' => 'Nama perihal wajib diisi.'], 422);
        }

        // Cek duplikat (kecuali diri sendiri)
        if ($this->perihal->nama_exists(trim($data['nama']), $id)) {
            return $this->response(['status' => 'error', 'message' => 'Perihal dengan nama tersebut sudah ada.'], 409);
        }

        $update = [
            'nama'       => trim($data['nama']),
            'kode'       => isset($data['kode']) ? (trim($data['kode']) !== '' ? strtoupper(trim($data['kode'])) : null) : $row['kode'],
            'keterangan' => $data['keterangan'] ?? $row['keterangan'],
            'is_active'  => isset($data['is_active']) ? (int)$data['is_active'] : (int)$row['is_active'],
        ];

        $this->perihal->update($id, $update);
        $row = $this->perihal->get_by_id($id);
        $this->response(['status' => 'success', 'message' => 'Perihal berhasil diperbarui.', 'data' => $row]);
    }

    /**
     * DELETE /api/v1/admin/perihal/:id
     * Hapus perihal (ADMIN only)
     */
    public function destroy($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }
        $this->require_admin();

        $row = $this->perihal->get_by_id($id);
        if (!$row) {
            return $this->response(['status' => 'error', 'message' => 'Perihal tidak ditemukan.'], 404);
        }

        $this->perihal->delete($id);
        $this->response(['status' => 'success', 'message' => 'Perihal berhasil dihapus.']);
    }

    /**
     * PUT /api/v1/admin/perihal/:id/toggle
     * Aktifkan / nonaktifkan perihal (ADMIN only)
     */
    public function toggle($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }
        $this->require_admin();

        $row = $this->perihal->get_by_id($id);
        if (!$row) {
            return $this->response(['status' => 'error', 'message' => 'Perihal tidak ditemukan.'], 404);
        }

        $new_status = $row['is_active'] ? 0 : 1;
        $this->perihal->update($id, ['is_active' => $new_status]);
        $row = $this->perihal->get_by_id($id);
        $label = $new_status ? 'diaktifkan' : 'dinonaktifkan';
        $this->response(['status' => 'success', 'message' => "Perihal berhasil $label.", 'data' => $row]);
    }
}

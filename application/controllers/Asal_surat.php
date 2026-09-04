<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Asal_surat Controller — Master Data Asal Surat / Instansi Pengirim
 * CRUD endpoint untuk mengelola instansi pengirim surat
 */
class Asal_surat extends MY_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->require_auth();
        $this->load->model('Asal_surat_model', 'asal_surat');
    }

    /** Middleware: hanya ADMIN yang bisa mengelola master asal surat */
    private function require_admin()
    {
        if ($this->current_user->role !== 'ADMIN') {
            $this->response(['status' => 'error', 'message' => 'Akses ditolak. Hanya Admin yang dapat mengelola Master Asal Surat.'], 403);
            exit();
        }
    }

    /**
     * GET /api/v1/asal-surat
     * List semua asal surat (semua role yang login dapat mengakses)
     */
    public function index()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        $active_only = $this->input->get('active') == '1';
        $q = $this->input->get('q');
        $data = $this->asal_surat->get_all($active_only, $q);
        $this->response(['status' => 'success', 'data' => $data]);
    }

    /**
     * GET /api/v1/asal-surat/:id
     * Detail satu asal surat
     */
    public function show($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        $row = $this->asal_surat->get_by_id($id);
        if (!$row) {
            return $this->response(['status' => 'error', 'message' => 'Asal surat tidak ditemukan.'], 404);
        }
        $this->response(['status' => 'success', 'data' => $row]);
    }

    /**
     * POST /api/v1/admin/asal-surat
     * Buat asal surat baru (ADMIN only)
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
            return $this->response(['status' => 'error', 'message' => 'Nama instansi/asal surat wajib diisi.'], 422);
        }

        // Cek duplikat
        if ($this->asal_surat->nama_exists(trim($data['nama']))) {
            return $this->response(['status' => 'error', 'message' => 'Asal surat dengan nama tersebut sudah ada.'], 409);
        }

        $insert = [
            'nama'       => trim($data['nama']),
            'kode'       => !empty($data['kode']) ? trim($data['kode']) : null,
            'kategori'   => !empty($data['kategori']) ? trim($data['kategori']) : null,
            'alamat'     => !empty($data['alamat']) ? trim($data['alamat']) : null,
            'kontak'     => !empty($data['kontak']) ? trim($data['kontak']) : null,
            'keterangan' => !empty($data['keterangan']) ? trim($data['keterangan']) : null,
            'is_active'  => isset($data['is_active']) ? (int)$data['is_active'] : 1,
            'created_by' => $this->current_user->id,
        ];

        $id = $this->asal_surat->create($insert);
        if ($id) {
            $row = $this->asal_surat->get_by_id($id);
            $this->response(['status' => 'success', 'message' => 'Asal surat berhasil ditambahkan.', 'data' => $row], 201);
        } else {
            $this->response(['status' => 'error', 'message' => 'Gagal menambahkan asal surat.'], 500);
        }
    }

    /**
     * PUT /api/v1/admin/asal-surat/:id
     * Update asal surat (ADMIN only)
     */
    public function update($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }
        $this->require_admin();

        $row = $this->asal_surat->get_by_id($id);
        if (!$row) {
            return $this->response(['status' => 'error', 'message' => 'Asal surat tidak ditemukan.'], 404);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) $data = $this->input->post();

        if (empty($data['nama'])) {
            return $this->response(['status' => 'error', 'message' => 'Nama instansi/asal surat wajib diisi.'], 422);
        }

        // Cek duplikat (kecuali diri sendiri)
        if ($this->asal_surat->nama_exists(trim($data['nama']), $id)) {
            return $this->response(['status' => 'error', 'message' => 'Asal surat dengan nama tersebut sudah ada.'], 409);
        }

        $update = [
            'nama'       => trim($data['nama']),
            'kode'       => isset($data['kode']) ? trim($data['kode']) : $row['kode'],
            'kategori'   => isset($data['kategori']) ? trim($data['kategori']) : $row['kategori'],
            'alamat'     => isset($data['alamat']) ? trim($data['alamat']) : $row['alamat'],
            'kontak'     => isset($data['kontak']) ? trim($data['kontak']) : $row['kontak'],
            'keterangan' => isset($data['keterangan']) ? trim($data['keterangan']) : $row['keterangan'],
            'is_active'  => isset($data['is_active']) ? (int)$data['is_active'] : (int)$row['is_active'],
        ];

        $this->asal_surat->update($id, $update);
        $row = $this->asal_surat->get_by_id($id);
        $this->response(['status' => 'success', 'message' => 'Asal surat berhasil diperbarui.', 'data' => $row]);
    }

    /**
     * DELETE /api/v1/admin/asal-surat/:id
     * Hapus asal surat (ADMIN only)
     */
    public function destroy($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }
        $this->require_admin();

        $row = $this->asal_surat->get_by_id($id);
        if (!$row) {
            return $this->response(['status' => 'error', 'message' => 'Asal surat tidak ditemukan.'], 404);
        }

        $this->asal_surat->delete($id);
        $this->response(['status' => 'success', 'message' => 'Asal surat berhasil dihapus.']);
    }

    /**
     * PUT /api/v1/admin/asal-surat/:id/toggle
     * Aktifkan / nonaktifkan asal surat (ADMIN only)
     */
    public function toggle($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }
        $this->require_admin();

        $row = $this->asal_surat->get_by_id($id);
        if (!$row) {
            return $this->response(['status' => 'error', 'message' => 'Asal surat tidak ditemukan.'], 404);
        }

        $new_status = $row['is_active'] ? 0 : 1;
        $this->asal_surat->update($id, ['is_active' => $new_status]);
        $row = $this->asal_surat->get_by_id($id);
        $label = $new_status ? 'diaktifkan' : 'dinonaktifkan';
        $this->response(['status' => 'success', 'message' => "Asal surat berhasil $label.", 'data' => $row]);
    }
}

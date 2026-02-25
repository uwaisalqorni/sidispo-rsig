<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Notifikasi extends MY_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->require_auth();
        $this->load->model('Notifikasi_model', 'notifikasi');
    }

    /**
     * GET /notifikasi
     * List user notifications
     */
    public function index()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        $limit = $this->input->get('limit') ? (int)$this->input->get('limit') : 50;
        $offset = $this->input->get('offset') ? (int)$this->input->get('offset') : 0;
        
        $data = $this->notifikasi->get_by_user($this->current_user->id, $limit, $offset);
        $unread_count = $this->notifikasi->get_unread_count($this->current_user->id);

        $this->response([
            'status' => 'success', 
            'data' => $data,
            'unread_count' => $unread_count
        ], 200);
    }

    /**
     * PUT /notifikasi/read/{id}
     * Mark specific notification as read
     */
    public function read($id = null)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        if (!$id) {
            return $this->response(['status' => 'error', 'message' => 'ID Notifikasi dibutuhkan'], 400);
        }

        $updated = $this->notifikasi->mark_as_read($id, $this->current_user->id);

        if ($updated) {
            $this->response(['status' => 'success', 'message' => 'Notifikasi ditandai sudah dibaca'], 200);
        } else {
            $this->response(['status' => 'error', 'message' => 'Notifikasi tidak ditemukan atau gagal diupdate'], 400);
        }
    }
    
    /**
     * PUT /notifikasi/read_all
     * Mark all notifications as read
     */
    public function read_all()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        $updated = $this->notifikasi->mark_all_as_read($this->current_user->id);

        $this->response(['status' => 'success', 'message' => 'Semua notifikasi ditandai sudah dibaca'], 200);
    }
}

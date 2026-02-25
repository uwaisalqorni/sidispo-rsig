<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Progress_model extends CI_Model {

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    /**
     * Get full timeline (progress log) for a penerima record
     * Returns append-only log ordered by timestamp ascending (chronological)
     */
    public function get_timeline($disposisi_penerima_id)
    {
        $this->db->select('pl.*, u.nama_lengkap, u.jabatan');
        $this->db->from('progress_log pl');
        $this->db->join('users u', 'u.id = pl.user_id', 'left');
        $this->db->where('pl.disposisi_penerima_id', $disposisi_penerima_id);
        $this->db->order_by('pl.created_at', 'ASC');
        return $this->db->get()->result_array();
    }

    /**
     * Get combined timeline for a whole disposisi (all penerima)
     * Useful for the detail view's unified timeline
     */
    public function get_timeline_by_disposisi($disposisi_id)
    {
        $this->db->select('pl.*, u.nama_lengkap, u.jabatan, dp.disposisi_id');
        $this->db->from('progress_log pl');
        $this->db->join('disposisi_penerima dp', 'dp.id = pl.disposisi_penerima_id');
        $this->db->join('users u', 'u.id = pl.user_id', 'left');
        $this->db->where('dp.disposisi_id', $disposisi_id);
        $this->db->order_by('pl.created_at', 'ASC');
        return $this->db->get()->result_array();
    }

    /**
     * Append a new progress log entry (never update, always insert)
     */
    public function store($disposisi_penerima_id, $user_id, $status_lama, $status_baru, $catatan)
    {
        $data = [
            'disposisi_penerima_id' => $disposisi_penerima_id,
            'user_id'               => $user_id,
            'status_lama'           => $status_lama,
            'status_baru'           => $status_baru,
            'catatan'               => $catatan,
        ];
        $this->db->insert('progress_log', $data);
        return $this->db->insert_id();
    }
}

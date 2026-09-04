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
     * Get single progress log by ID
     */
    public function get_by_id($id)
    {
        $this->db->select('pl.*, u.nama_lengkap, u.jabatan, dp.disposisi_id');
        $this->db->from('progress_log pl');
        $this->db->join('disposisi_penerima dp', 'dp.id = pl.disposisi_penerima_id');
        $this->db->join('users u', 'u.id = pl.user_id', 'left');
        $this->db->where('pl.id', $id);
        return $this->db->get()->row_array();
    }

    /**
     * Get latest log for a disposisi_penerima record
     */
    public function get_latest_log($disposisi_penerima_id)
    {
        return $this->db->where('disposisi_penerima_id', $disposisi_penerima_id)
                        ->order_by('id', 'DESC')
                        ->limit(1)
                        ->get('progress_log')
                        ->row_array();
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

    /**
     * Update an existing progress log entry
     */
    public function update_log($id, $data)
    {
        $this->db->where('id', $id);
        return $this->db->update('progress_log', $data);
    }

    /**
     * Delete an existing progress log entry
     */
    public function delete_log($id)
    {
        $this->db->where('id', $id);
        return $this->db->delete('progress_log');
    }
}

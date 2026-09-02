<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Perihal_model — Operasi CRUD untuk master data perihal surat
 */
class Perihal_model extends CI_Model {

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    /**
     * Ambil semua perihal aktif
     */
    public function get_all($active_only = false)
    {
        if ($active_only) {
            $this->db->where('is_active', 1);
        }
        $this->db->order_by('nama', 'ASC');
        return $this->db->get('master_perihal')->result_array();
    }

    /**
     * Ambil perihal berdasarkan ID
     */
    public function get_by_id($id)
    {
        return $this->db->where('id', $id)->get('master_perihal')->row_array();
    }

    /**
     * Cek apakah nama perihal sudah ada
     */
    public function nama_exists($nama, $exclude_id = null)
    {
        $this->db->where('nama', $nama);
        if ($exclude_id) {
            $this->db->where('id !=', $exclude_id);
        }
        return $this->db->count_all_results('master_perihal') > 0;
    }

    /**
     * Buat perihal baru
     */
    public function create($data)
    {
        $this->db->insert('master_perihal', $data);
        return $this->db->insert_id();
    }

    /**
     * Update perihal
     */
    public function update($id, $data)
    {
        $this->db->where('id', $id);
        return $this->db->update('master_perihal', $data);
    }

    /**
     * Hapus perihal (hard delete)
     */
    public function delete($id)
    {
        $this->db->where('id', $id);
        return $this->db->delete('master_perihal');
    }
}

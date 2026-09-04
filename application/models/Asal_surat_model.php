<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Asal_surat_model — Operasi CRUD untuk master data asal surat / instansi
 */
class Asal_surat_model extends CI_Model {

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    /**
     * Ambil semua data master asal surat
     */
    public function get_all($active_only = false, $q = null)
    {
        if ($active_only) {
            $this->db->where('is_active', 1);
        }
        if (!empty($q)) {
            $this->db->group_start();
            $this->db->like('nama', $q);
            $this->db->or_like('kode', $q);
            $this->db->or_like('kategori', $q);
            $this->db->group_end();
        }
        $this->db->order_by('nama', 'ASC');
        return $this->db->get('master_asal_surat')->result_array();
    }

    /**
     * Ambil asal surat berdasarkan ID
     */
    public function get_by_id($id)
    {
        return $this->db->where('id', $id)->get('master_asal_surat')->row_array();
    }

    /**
     * Cek apakah nama asal surat sudah ada
     */
    public function nama_exists($nama, $exclude_id = null)
    {
        $this->db->where('nama', $nama);
        if ($exclude_id) {
            $this->db->where('id !=', $exclude_id);
        }
        return $this->db->count_all_results('master_asal_surat') > 0;
    }

    /**
     * Buat asal surat baru
     */
    public function create($data)
    {
        $this->db->insert('master_asal_surat', $data);
        return $this->db->insert_id();
    }

    /**
     * Update asal surat
     */
    public function update($id, $data)
    {
        $this->db->where('id', $id);
        return $this->db->update('master_asal_surat', $data);
    }

    /**
     * Hapus asal surat (hard delete)
     */
    public function delete($id)
    {
        $this->db->where('id', $id);
        return $this->db->delete('master_asal_surat');
    }
}

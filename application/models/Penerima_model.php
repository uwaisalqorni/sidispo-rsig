<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Penerima_model extends CI_Model {

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    /**
     * Get all penerima for a disposisi with their status
     */
    public function get_by_disposisi($disposisi_id)
    {
        $this->db->select('dp.*, u.nama_lengkap, u.jabatan, u.unit, u.nip');
        $this->db->from('disposisi_penerima dp');
        $this->db->join('users u', 'u.id = dp.user_id');
        $this->db->where('dp.disposisi_id', $disposisi_id);
        $this->db->order_by('dp.id', 'ASC');
        return $this->db->get()->result_array();
    }

    /**
     * Get single penerima record by ID
     */
    public function get_by_id($id)
    {
        return $this->db->get_where('disposisi_penerima', ['id' => $id])->row_array();
    }

    /**
     * Update status of a penerima
     * Only the penerima themselves (user_id must match) can update their own status
     */
    public function update_status($id, $user_id, $new_status, $catatan = null)
    {
        $penerima = $this->get_by_id($id);
        if (!$penerima || $penerima['user_id'] != $user_id) {
            return false;
        }

        $data = ['status' => $new_status];
        if ($catatan !== null) {
            $data['catatan_akhir'] = $catatan;
        }
        if ($new_status === 'SELESAI') {
            $data['tanggal_selesai'] = date('Y-m-d H:i:s');
        }

        $this->db->where('id', $id);
        return $this->db->update('disposisi_penerima', $data);
    }
}

<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Folder_model extends CI_Model {

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    /** Get all folders, ordered by parenthood and sequence */
    public function get_all()
    {
        $this->db->order_by('parent_id', 'ASC');
        $this->db->order_by('urutan', 'ASC');
        return $this->db->get('folders')->result_array();
    }

    /** Alias get_all_flat() — used by Admin controller */
    public function get_all_flat()
    {
        return $this->get_all();
    }

    /** Get single folder */
    public function get_by_id($id)
    {
        return $this->db->get_where('folders', ['id' => $id])->row_array();
    }

    /** Create new folder */
    public function insert($data)
    {
        $this->db->insert('folders', $data);
        return $this->db->insert_id();
    }

    /** Alias create() — used by Admin controller */
    public function create($data)
    {
        return $this->insert($data);
    }

    /** Update folder */
    public function update($id, $data)
    {
        $this->db->where('id', $id);
        return $this->db->update('folders', $data);
    }

    /** Delete folder */
    public function delete($id)
    {
        $this->db->where('id', $id);
        return $this->db->delete('folders');
    }
}

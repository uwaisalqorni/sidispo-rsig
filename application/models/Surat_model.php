<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Surat_model extends CI_Model {

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    /**
     * Get all surat masuk
     */
    public function get_all($limit = 100, $offset = 0, $filters = [])
    {
        $this->db->select('
            surat_masuk.*, 
            folders.nama as nama_folder, 
            folders.warna as warna_folder,
            users.nama_lengkap as nama_penginput,
            (SELECT COUNT(*) FROM dokumen_file WHERE surat_masuk_id = surat_masuk.id) as jumlah_file,
            (SELECT COUNT(*) FROM disposisi WHERE surat_masuk_id = surat_masuk.id) as jumlah_disposisi
        ');
        $this->db->from('surat_masuk');
        $this->db->join('folders', 'folders.id = surat_masuk.folder_id', 'left');
        $this->db->join('users', 'users.id = surat_masuk.input_oleh', 'left');

        // Filter pencarian teks (No. Surat, No. Agenda, Perihal, Asal Surat, Keterangan)
        if (!empty($filters['q'])) {
            $q = trim($filters['q']);
            $this->db->group_start();
            $this->db->like('surat_masuk.nomor_surat', $q);
            $this->db->or_like('surat_masuk.nomor_agenda', $q);
            $this->db->or_like('surat_masuk.perihal', $q);
            $this->db->or_like('surat_masuk.asal_surat', $q);
            $this->db->or_like('surat_masuk.keterangan', $q);
            $this->db->group_end();
        }

        // Filter folder/kategori
        if (!empty($filters['folder_id'])) {
            $this->db->where('surat_masuk.folder_id', $filters['folder_id']);
        }

        // Filter rentang tanggal terima
        if (!empty($filters['tanggal_dari'])) {
            $this->db->where('surat_masuk.tanggal_terima >=', $filters['tanggal_dari']);
        }
        if (!empty($filters['tanggal_sampai'])) {
            $this->db->where('surat_masuk.tanggal_terima <=', $filters['tanggal_sampai']);
        }

        $this->db->order_by('surat_masuk.id', 'DESC');
        $this->db->limit($limit, $offset);
        return $this->db->get()->result_array();
    }

    /**
     * Hitung total surat (dipakai untuk info jumlah hasil filter)
     */
    public function count_filtered($filters = [])
    {
        $this->db->from('surat_masuk');

        if (!empty($filters['q'])) {
            $q = trim($filters['q']);
            $this->db->group_start();
            $this->db->like('surat_masuk.nomor_surat', $q);
            $this->db->or_like('surat_masuk.nomor_agenda', $q);
            $this->db->or_like('surat_masuk.perihal', $q);
            $this->db->or_like('surat_masuk.asal_surat', $q);
            $this->db->or_like('surat_masuk.keterangan', $q);
            $this->db->group_end();
        }

        if (!empty($filters['folder_id'])) {
            $this->db->where('surat_masuk.folder_id', $filters['folder_id']);
        }

        if (!empty($filters['tanggal_dari'])) {
            $this->db->where('tanggal_terima >=', $filters['tanggal_dari']);
        }
        if (!empty($filters['tanggal_sampai'])) {
            $this->db->where('tanggal_terima <=', $filters['tanggal_sampai']);
        }
        return $this->db->count_all_results();
    }
    
    /**
     * Get single surat by ID including files
     */
    public function get_by_id($id)
    {
        $this->db->select('surat_masuk.*, folders.nama as nama_folder, users.nama_lengkap as nama_penginput');
        $this->db->from('surat_masuk');
        $this->db->join('folders', 'folders.id = surat_masuk.folder_id', 'left');
        $this->db->join('users', 'users.id = surat_masuk.input_oleh', 'left');
        $this->db->where('surat_masuk.id', $id);
        $surat = $this->db->get()->row_array();
        
        if ($surat) {
            $this->db->where('surat_masuk_id', $id);
            $surat['files'] = $this->db->get('dokumen_file')->result_array();
        }
        
        return $surat;
    }

    /**
     * Create new surat
     */
    public function insert($data)
    {
        $this->db->insert('surat_masuk', $data);
        return $this->db->insert_id();
    }

    /**
     * Insert file records
     */
    public function insert_file($data)
    {
        $this->db->insert('dokumen_file', $data);
        return $this->db->insert_id();
    }

    /**
     * Update surat
     */
    public function update($id, $data)
    {
        $this->db->where('id', $id);
        return $this->db->update('surat_masuk', $data);
    }
    
    /**
     * Delete surat
     */
    public function delete($id)
    {
        $this->db->where('id', $id);
        return $this->db->delete('surat_masuk'); // Cascade should handle files in DB, physical files need manual unlink
    }
    
    /**
     * Get files
     */
    public function get_files($surat_id)
    {
        return $this->db->get_where('dokumen_file', ['surat_masuk_id' => $surat_id])->result_array();
    }

    /**
     * Delete single file
     */
    public function delete_file($file_id)
    {
        $file = $this->db->get_where('dokumen_file', ['id' => $file_id])->row_array();
        if ($file) {
            if (!empty($file['path_file']) && file_exists(FCPATH . $file['path_file'])) {
                @unlink(FCPATH . $file['path_file']);
            }
            return $this->db->delete('dokumen_file', ['id' => $file_id]);
        }
        return false;
    }
}

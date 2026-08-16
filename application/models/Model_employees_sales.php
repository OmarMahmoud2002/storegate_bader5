<?php 
defined('BASEPATH') OR exit('No direct script access allowed');

class Model_employees_sales extends CI_Model {
    protected $table;
    public function __construct()
    {
        parent::__construct();
        $this->table = 'tbl_employees_sales';
    }

    public function get_upload_record_by_date($individualsDate) {
        $this->db->where('individuals_date', $individualsDate);
        $this->db->order_by('id', 'DESC');
        $query = $this->db->get($this->table);
        return $query->row();
    }

    public function insert_upload_record($filepath, $filename, $individualsDate, $businessDate) {
        // كل رفعة تمثل نسخة تاريخية مستقلة حتى لو تطابق الاسم أو التاريخان.
        // لا يتم تحديث سجل سابق لأن ذلك يفقد مرجع الملف القديم من سجل الرفعات.
        $inserted = $this->db->insert($this->table, array(
            'filepath' => $filepath,
            'filename' => $filename,
            'individuals_date' => $individualsDate,
            'business_date' => $businessDate,
        ));

        return $inserted ? (int) $this->db->insert_id() : false;
    }

    public function get_upload_record_by_id($id) {
        $query = $this->db->get_where($this->table, array('id' => (int) $id));
        return $query->row();
    }

    public function get_all_upload_records() {
        $this->db->order_by('id', 'DESC');
        return $this->db->get($this->table)->result();
    }

    public function get_latest_upload_record() {
        $this->db->order_by('id', 'DESC');
        $this->db->limit(1);
        return $this->db->get($this->table)->row();
    }

    public function get_upload_record_by_filepath($filepath) {
        $query = $this->db->get_where($this->table, array('filepath' => $filepath));
        return $query->row();
    }
}

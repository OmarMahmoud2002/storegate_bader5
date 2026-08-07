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
        $query = $this->db->get_where($this->table, array('individuals_date' => $individualsDate));
        return $query->row();
    }

    public function insert_upload_record($filepath, $filename, $individualsDate, $businessDate) {
        # كل رفعة الآن لها مسار فريد دائمًا (انظر handle_employees_sales_upload)، لذا التطابق على
        # filepath لن يتحقق أبدًا عمليًا؛ المطابقة الصحيحة لتحديث سجل موجود لنفس الفترة هي زوج
        # التاريخين معًا، وإلا يُنشأ سجلان بنفس التاريخ ويصبح اختيارهما لاحقًا غير محدد
        $query = $this->db->get_where($this->table, array('individuals_date' => $individualsDate, 'business_date' => $businessDate));
        if ($query->num_rows() > 0) {
            $this->db->update($this->table, array('filepath' => $filepath, 'filename' => $filename), array('individuals_date' => $individualsDate, 'business_date' => $businessDate));
        } else {
            $this->db->insert($this->table, array('filepath' => $filepath, 'filename' => $filename, 'individuals_date' => $individualsDate, 'business_date' => $businessDate,));
        }
    }

    public function get_upload_record_by_filepath($filepath) {
        $query = $this->db->get_where($this->table, array('filepath' => $filepath));
        return $query->row();
    }
}
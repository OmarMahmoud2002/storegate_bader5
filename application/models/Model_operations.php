<?php 

defined('BASEPATH') OR exit('No direct script access allowed');

class Model_operations extends CI_Model {
    protected $table;
    function __construct() {
        parent::__construct();
        $this->table = 'tbl_operations';
    }

    function get_operations($where = array()) {
        $this->db->order_by('id', 'asc');
        $this->db->where($where);
        $query = $this->db->get($this->table);
        return $query->result_array();
    }

    function add_operation($data, $user_id) {
        $operationData = array(
            'serial' => isset($data['serial']) ? $data['serial'] : '',
            'operation' => isset($data['operation']) ? $data['operation'] : '',
            'sales_order' => isset($data['sales_order']) ? $data['sales_order'] : '0',
            'made_by' => $user_id,
            'date_created' => date('Y-m-d H:i:s')
        );

        $this->db->insert($this->table, $operationData);
    }

}

?>
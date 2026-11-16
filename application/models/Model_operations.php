<?php 

defined('BASEPATH') OR exit('No direct script access allowed');

class Model_operations extends CI_Model {
    protected $table;
    function __construct() {
        parent::__construct();
        $this->table = 'tbl_operations';
        $this->ensure_product_metadata_columns();
    }

    /**
     * Keep the product identity with the operation itself so historical serials
     * still have a name and item code after they leave the current stock table.
     */
    private function ensure_product_metadata_columns() {
        $schema_changed = false;
        $columns = array(
            'item_code' => array(
                'type' => 'VARCHAR',
                'constraint' => 120,
                'null' => FALSE,
                'default' => ''
            ),
            'item_description' => array(
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => FALSE,
                'default' => ''
            )
        );

        foreach ($columns as $column_name => $definition) {
            if (!$this->db->field_exists($column_name, $this->table)) {
                $this->load->dbforge();
                $this->dbforge->add_column($this->table, array($column_name => $definition));
                $schema_changed = true;
            }
        }

        if ($schema_changed) {
            $this->backfill_product_metadata_from_current_stock();
        }
    }

    private function backfill_product_metadata_from_current_stock() {
        $source_table = $this->db->table_exists('tbl_product_serial_history')
            ? 'tbl_product_serial_history'
            : 'current_store_items';

        if (!$this->db->table_exists($source_table)) {
            return;
        }

        $this->db->query(
            "UPDATE `{$this->table}` AS o "
            . "INNER JOIN `{$source_table}` AS i ON "
            . "NULLIF(TRIM(o.serial), '') IS NOT NULL AND "
            . "NULLIF(TRIM(i.serial_number), '') IS NOT NULL AND "
            . "TRIM(LEADING 'S' FROM UPPER(TRIM(i.serial_number))) "
            . "= TRIM(LEADING 'S' FROM UPPER(TRIM(o.serial))) "
            . "SET o.item_code = i.item_code, o.item_description = i.item_description "
            . "WHERE TRIM(o.item_code) = '' OR TRIM(o.item_description) = ''"
        );
    }

    function get_operations($where = array()) {
        $this->db->order_by('date_created', 'desc');
        $this->db->order_by('id', 'desc');
        $this->db->where($where);
        $query = $this->db->get($this->table);
        return $query->result_array();
    }

    function get_operations_by_serial($serial) {
        $serial = strtoupper(trim((string) $serial));

        if ($serial === '') {
            return array();
        }

        $serial_without_s = preg_replace('/^S/', '', $serial, 1);
        $serial_with_s = 'S' . $serial_without_s;

        $this->db->where(
            'UPPER(TRIM(serial)) IN ('
            . $this->db->escape($serial) . ', '
            . $this->db->escape($serial_without_s) . ', '
            . $this->db->escape($serial_with_s) . ')',
            null,
            false
        );
        $this->db->order_by('date_created', 'desc');
        $this->db->order_by('id', 'desc');
        $query = $this->db->get($this->table);

        return $query->result_array();
    }

    function add_operation($data, $user_id) {
        $operationData = array(
            'serial' => isset($data['serial']) ? $data['serial'] : '',
            'item_code' => isset($data['item_code']) ? $data['item_code'] : '',
            'item_description' => isset($data['item_description']) ? $data['item_description'] : '',
            'operation' => isset($data['operation']) ? $data['operation'] : '',
            'sales_order' => isset($data['sales_order']) ? $data['sales_order'] : '0',
            'made_by' => $user_id,
            'date_created' => date('Y-m-d H:i:s')
        );

        return $this->db->insert($this->table, $operationData);
    }

}

?>

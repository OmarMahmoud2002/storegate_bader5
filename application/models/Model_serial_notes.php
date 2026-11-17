<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Model_serial_notes extends CI_Model
{
    protected $table;

    public function __construct()
    {
        parent::__construct();
        $this->table = 'tbl_serial_notes';
        $this->ensure_table();
    }

    /**
     * الملاحظات مستقلة عن جدول المخزون عمدًا، لأن المطلوب يسمح بتسجيل
     * ملاحظة على سيريال غير موجود حاليًا ثم إظهارها عند البحث عنه لاحقًا.
     */
    private function ensure_table()
    {
        if ($this->db->table_exists($this->table)) {
            return;
        }

        $this->db->query(
            "CREATE TABLE IF NOT EXISTS `{$this->table}` ("
            . "`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,"
            . "`serial_number` VARCHAR(190) NOT NULL,"
            . "`normalized_serial` VARCHAR(190) NOT NULL,"
            . "`note` TEXT NOT NULL,"
            . "`created_by` INT NULL DEFAULT NULL,"
            . "`created_at` DATETIME NOT NULL,"
            . "PRIMARY KEY (`id`),"
            . "KEY `normalized_serial_index` (`normalized_serial`),"
            . "KEY `created_at_index` (`created_at`)"
            . ") ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );
    }

    public function normalize_serial($serial)
    {
        $serial = strtoupper(trim((string) $serial));
        $without_s = preg_replace('/^S/', '', $serial, 1);

        // لا نحول السيريال S وحده إلى قيمة فارغة.
        return $without_s === '' ? $serial : $without_s;
    }

    public function get_by_serial($serial)
    {
        $normalized = $this->normalize_serial($serial);
        if ($normalized === '') {
            return array();
        }

        $this->db->select('n.id, n.serial_number, n.note, n.created_by, n.created_at, u.user_fillname AS created_by_name');
        $this->db->from($this->table . ' AS n');
        $this->db->join('tbl_user AS u', 'u.user_id = n.created_by', 'left');
        $this->db->where('n.normalized_serial', $normalized);
        $this->db->order_by('n.created_at', 'DESC');
        $this->db->order_by('n.id', 'DESC');

        return $this->db->get()->result_array();
    }

    public function has_notes($serial)
    {
        $normalized = $this->normalize_serial($serial);
        if ($normalized === '') {
            return false;
        }

        return $this->db
            ->where('normalized_serial', $normalized)
            ->count_all_results($this->table) > 0;
    }

    public function add_note($serial, $note, $created_by)
    {
        $serial = strtoupper(trim((string) $serial));
        $normalized = $this->normalize_serial($serial);

        if ($serial === '' || $normalized === '' || trim((string) $note) === '') {
            return false;
        }

        $inserted = $this->db->insert($this->table, array(
            'serial_number' => $serial,
            'normalized_serial' => $normalized,
            'note' => trim((string) $note),
            'created_by' => ($created_by !== null && $created_by !== '') ? (int) $created_by : null,
            'created_at' => date('Y-m-d H:i:s'),
        ));

        return $inserted ? (int) $this->db->insert_id() : false;
    }

    public function get_by_id($id)
    {
        return $this->db->get_where($this->table, array('id' => (int) $id))->row_array();
    }

    public function delete_note($id)
    {
        return $this->db->delete($this->table, array('id' => (int) $id));
    }
}


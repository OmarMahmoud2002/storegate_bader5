<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Manual closing amounts (تمارا / المحفظة / رصيد إضافي) captured from the
 * التقفيلة page. They are stored per employee per day so a date range on the
 * report simply sums the days it covers, the same way settlements do.
 */
class Model_lock_extras extends CI_Model
{
    protected $table = 'tbl_user_lock_extras';

    /** Report row key => database column. */
    public static $fields = array(
        'tamara' => 'lock_extra_tamara',
        'wallet' => 'lock_extra_wallet',
        'extra_balance' => 'lock_extra_balance',
    );

    public function __construct()
    {
        parent::__construct();
        $this->ensure_schema();
    }

    /**
     * Keep CHARACTER SET and COLLATE self-consistent: CI appends the collation
     * from the connection config when the statement carries none, which MySQL
     * rejects if it does not belong to the charset. See Model_return_requests.
     */
    private function table_attributes()
    {
        $charset = 'utf8mb4';
        $collation = trim((string) $this->db->dbcollat);

        if ($collation === '' || strpos($collation, $charset . '_') !== 0) {
            $collation = $charset . '_unicode_ci';
        }

        return array(
            'ENGINE' => 'InnoDB',
            'DEFAULT CHARACTER SET' => $charset,
            'COLLATE' => $collation,
        );
    }

    private function ensure_schema()
    {
        if ($this->db->table_exists($this->table)) {
            return;
        }

        $this->load->dbforge();
        $this->dbforge->add_field(array(
            'lock_extra_id' => array('type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true),
            'lock_extra_userid' => array('type' => 'INT', 'constraint' => 11, 'unsigned' => true),
            'lock_extra_date' => array('type' => 'DATE'),
            'lock_extra_tamara' => array('type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0),
            'lock_extra_wallet' => array('type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0),
            'lock_extra_balance' => array('type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0),
            'lock_extra_updated_at' => array('type' => 'DATETIME', 'null' => true),
            'lock_extra_updated_by' => array('type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true),
        ));
        $this->dbforge->add_key('lock_extra_id', true);
        $this->dbforge->create_table($this->table, true, $this->table_attributes());

        // One row per employee per day - the save path relies on this to update
        // in place instead of piling duplicate rows onto the same closing.
        $this->db->query('ALTER TABLE `' . $this->table . '` ADD UNIQUE KEY `lock_extra_user_date` (`lock_extra_userid`, `lock_extra_date`)');
    }

    public function empty_amounts()
    {
        return array('tamara' => 0.0, 'wallet' => 0.0, 'extra_balance' => 0.0);
    }

    /**
     * Summed amounts for one employee over the reported period.
     */
    public function get_totals($user_id, $start_date, $end_date)
    {
        $totals = $this->get_totals_map(array($user_id), $start_date, $end_date);

        return isset($totals[(int) $user_id]) ? $totals[(int) $user_id] : $this->empty_amounts();
    }

    /**
     * Summed amounts for several employees in one query, keyed by user id.
     * The report renders up to seventeen columns, so per-employee queries here
     * would multiply the page's round trips for no gain.
     */
    public function get_totals_map($user_ids, $start_date, $end_date)
    {
        $user_ids = array_values(array_unique(array_map('intval', (array) $user_ids)));
        if (empty($user_ids)) {
            return array();
        }

        $this->db->select('lock_extra_userid');
        foreach (self::$fields as $key => $column) {
            $this->db->select('SUM(' . $column . ') AS ' . $key, false);
        }
        $this->db->from($this->table);
        $this->db->where_in('lock_extra_userid', $user_ids);
        if ($start_date != '') {
            $this->db->where('lock_extra_date >=', $start_date);
        }
        if ($end_date != '') {
            $this->db->where('lock_extra_date <=', $end_date);
        }
        $this->db->group_by('lock_extra_userid');

        $totals = array();
        foreach ($this->db->get()->result_array() as $row) {
            $totals[(int) $row['lock_extra_userid']] = array(
                'tamara' => (float) $row['tamara'],
                'wallet' => (float) $row['wallet'],
                'extra_balance' => (float) $row['extra_balance'],
            );
        }

        return $totals;
    }

    public function get_day($user_id, $date)
    {
        $row = $this->db
            ->where('lock_extra_userid', (int) $user_id)
            ->where('lock_extra_date', $date)
            ->get($this->table)
            ->row_array();

        if (empty($row)) {
            return $this->empty_amounts();
        }

        $amounts = array();
        foreach (self::$fields as $key => $column) {
            $amounts[$key] = (float) $row[$column];
        }

        return $amounts;
    }

    /**
     * Replace the amount shown for a whole report range without erasing the
     * employee's values from the other days in that range.
     *
     * The report cell is a sum of daily rows. Writing the displayed total
     * directly into the end date would add it to earlier days. Instead, store
     * only the value needed on the target date to make the range equal the
     * newly entered total.
     */
    public function replace_range_total($user_id, $start_date, $end_date, $date, $field, $amount, $admin_id = null)
    {
        if (!isset(self::$fields[$field])) {
            return false;
        }

        $range_amounts = $this->get_totals($user_id, $start_date, $end_date);
        $day_amounts = $this->get_day($user_id, $date);
        $other_days_amount = round((float) $range_amounts[$field] - (float) $day_amounts[$field], 2);
        $replacement_day_amount = round((float) $amount - $other_days_amount, 2);

        return $this->save_day(
            $user_id,
            $date,
            array($field => $replacement_day_amount),
            $admin_id
        );
    }

    /**
     * Write the amounts for a single closing day. Only the keys present in
     * $amounts are touched, so saving تمارا alone leaves المحفظة untouched.
     */
    public function save_day($user_id, $date, $amounts, $admin_id = null)
    {
        $data = array();
        foreach (self::$fields as $key => $column) {
            if (array_key_exists($key, $amounts)) {
                $data[$column] = round((float) $amounts[$key], 2);
            }
        }

        if (empty($data)) {
            return false;
        }

        $data['lock_extra_updated_at'] = date('Y-m-d H:i:s');
        $data['lock_extra_updated_by'] = $admin_id !== null ? (int) $admin_id : null;

        $exists = $this->db
            ->where('lock_extra_userid', (int) $user_id)
            ->where('lock_extra_date', $date)
            ->count_all_results($this->table);

        if ($exists > 0) {
            $this->db->where('lock_extra_userid', (int) $user_id);
            $this->db->where('lock_extra_date', $date);
            $this->db->update($this->table, $data);
            return true;
        }

        $data['lock_extra_userid'] = (int) $user_id;
        $data['lock_extra_date'] = $date;

        return $this->db->insert($this->table, $data);
    }
}

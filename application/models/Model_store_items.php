<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Model_store_items extends CI_Model
{
    protected $table;
    protected $shelves_table;
    protected $table_images;
    protected $serial_history_table;
	public function __construct()
	{
		parent::__construct();
        $this->table = 'current_store_items';
        $this->shelves_table = 'tbl_shelves_items';
        $this->table_images = 'tbl_products_images';
        $this->serial_history_table = 'tbl_product_serial_history';
        $this->ensure_serial_history_table();
	}

    /**
     * A permanent serial reference prevents product identity from disappearing
     * when a stock refresh removes a sold serial from current_store_items.
     */
    private function ensure_serial_history_table() {
        if ($this->db->table_exists($this->serial_history_table)) {
            return;
        }

        $this->db->query(
            "CREATE TABLE IF NOT EXISTS `{$this->serial_history_table}` ("
            . "`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,"
            . "`serial_number` VARCHAR(191) NOT NULL,"
            . "`barcode` VARCHAR(191) NOT NULL DEFAULT '',"
            . "`item_code` VARCHAR(120) NOT NULL DEFAULT '',"
            . "`item_description` VARCHAR(255) NOT NULL DEFAULT '',"
            . "`updated_at` DATETIME NOT NULL,"
            . "PRIMARY KEY (`id`),"
            . "UNIQUE KEY `serial_number_unique` (`serial_number`),"
            . "KEY `item_code_index` (`item_code`)"
            . ") ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );

        // Seed the reference once with everything that is currently in stock.
        $this->db->query(
            "INSERT INTO `{$this->serial_history_table}` "
            . "(`serial_number`, `barcode`, `item_code`, `item_description`, `updated_at`) "
            . "SELECT UPPER(TRIM(serial_number)), COALESCE(barcode, ''), item_code, item_description, NOW() "
            . "FROM `{$this->table}` "
            . "WHERE NULLIF(TRIM(serial_number), '') IS NOT NULL "
            . "ON DUPLICATE KEY UPDATE "
            . "`barcode` = VALUES(`barcode`), "
            . "`item_code` = VALUES(`item_code`), "
            . "`item_description` = VALUES(`item_description`), "
            . "`updated_at` = VALUES(`updated_at`)"
        );
    }

    private function archive_serial_reference($item) {
        $serial_number = isset($item['serial_number'])
            ? strtoupper(trim((string) $item['serial_number']))
            : '';

        if ($serial_number === '') {
            return;
        }

        $data = array(
            'serial_number' => $serial_number,
            'barcode' => isset($item['barcode']) ? trim((string) $item['barcode']) : '',
            'item_code' => isset($item['item_code']) ? trim((string) $item['item_code']) : '',
            'item_description' => isset($item['item_description']) ? trim((string) $item['item_description']) : '',
            'updated_at' => date('Y-m-d H:i:s')
        );

        $existing = $this->db->get_where(
            $this->serial_history_table,
            array('serial_number' => $serial_number)
        )->row_array();

        if ($existing) {
            $this->db->where('id', $existing['id']);
            $this->db->update($this->serial_history_table, $data);
            return;
        }

        $this->db->insert($this->serial_history_table, $data);
    }

    public function get_serial_history_by_number($number) {
        $number = strtoupper(trim((string) $number));

        if ($number === '') {
            return null;
        }

        $serial_without_s = preg_replace('/^S/', '', $number, 1);
        $serial_with_s = 'S' . $serial_without_s;

        $this->db->where(
            '(UPPER(TRIM(serial_number)) IN ('
            . $this->db->escape($number) . ', '
            . $this->db->escape($serial_without_s) . ', '
            . $this->db->escape($serial_with_s) . ')'
            . ' OR UPPER(TRIM(barcode)) = ' . $this->db->escape($number) . ')',
            null,
            false
        );
        $this->db->order_by('updated_at', 'DESC');

        return $this->db->get($this->serial_history_table)->row_array();
    }

    private function counted_subinventory_codes() {
        return array(
            '23012' => '23012',
            'SAGENT_STK' => 'SAgent_STK',
            'RESV_23012' => 'RESV_23012'
        );
    }

    /**
     * إجمالي العرض في الواجهة موزعًا على المخازن الثلاثة المعتمدة فقط.
     * المنتج ذو السيريال يُحسب كسيريال واحد، والمنتج بدون سيريال تُجمع كميته.
     */
    public function get_counted_subinventory_breakdown($item_code) {
        $canonicalCodes = $this->counted_subinventory_codes();
        $items = array();
        foreach ($canonicalCodes as $canonicalCode) {
            $items[$canonicalCode] = 0;
        }
        $serialItems = $items;
        $quantityItems = $items;

        $serialSql = "
            SELECT
                UPPER(TRIM(i.subinventory_code)) AS normalized_code,
                COUNT(DISTINCT i.serial_number) AS available_quantity
            FROM {$this->table} i
            LEFT JOIN tbl_user_custody c
                ON i.item_code = c.item_code AND i.serial_number = c.serial_number
            LEFT JOIN tbl_requests r
                ON i.item_code = r.item_code AND i.serial_number = r.serial_number
            WHERE i.item_code = ?
              AND LOWER(TRIM(i.serial_control)) = 'yes'
              AND COALESCE(i.quantity_total, 0) > 0
              AND NULLIF(TRIM(i.serial_number), '') IS NOT NULL
              AND c.serial_number IS NULL
              AND r.serial_number IS NULL
              AND UPPER(TRIM(i.subinventory_code)) IN (?, ?, ?)
            GROUP BY UPPER(TRIM(i.subinventory_code))
        ";

        $serialQuery = $this->db->query($serialSql, array($item_code, '23012', 'SAGENT_STK', 'RESV_23012'));
        foreach ($serialQuery->result_array() as $row) {
            $normalizedCode = isset($row['normalized_code']) ? $row['normalized_code'] : '';
            if (isset($canonicalCodes[$normalizedCode])) {
                $serialItems[$canonicalCodes[$normalizedCode]] += (int) $row['available_quantity'];
            }
        }

        $quantitySql = "
            SELECT
                UPPER(TRIM(subinventory_code)) AS normalized_code,
                SUM(GREATEST(COALESCE(quantity_total, 0), 0)) AS available_quantity
            FROM {$this->table}
            WHERE item_code = ?
              AND LOWER(TRIM(serial_control)) = 'no'
              AND UPPER(TRIM(subinventory_code)) IN (?, ?, ?)
            GROUP BY UPPER(TRIM(subinventory_code))
        ";

        $quantityQuery = $this->db->query($quantitySql, array($item_code, '23012', 'SAGENT_STK', 'RESV_23012'));
        $hasNonSerialQuantity = false;
        foreach ($quantityQuery->result_array() as $row) {
            $normalizedCode = isset($row['normalized_code']) ? $row['normalized_code'] : '';
            if (isset($canonicalCodes[$normalizedCode])) {
                $quantity = (int) $row['available_quantity'];
                $quantityItems[$canonicalCodes[$normalizedCode]] += $quantity;
                $hasNonSerialQuantity = $hasNonSerialQuantity || $quantity > 0;
            }
        }

        if ($hasNonSerialQuantity) {
            // العهدة والتوصيل للمنتج بدون سيريال لا يخزنان اسم المخزن؛ تُخصم الكمية
            // بالترتيب من المخزن الرئيسي ثم مخزن الوكيل ثم المحجوز حتى يظل المجموع دقيقًا.
            $allocationSql = "
                SELECT
                    (SELECT COUNT(*) FROM tbl_user_custody
                        WHERE item_code = ? AND (serial_number IS NULL OR TRIM(serial_number) = ''))
                    +
                    (SELECT COUNT(*) FROM tbl_requests
                        WHERE item_code = ? AND (serial_number IS NULL OR TRIM(serial_number) = ''))
                    AS allocated_quantity
            ";
            $allocationRow = $this->db->query($allocationSql, array($item_code, $item_code))->row_array();
            $allocatedQuantity = isset($allocationRow['allocated_quantity']) ? (int) $allocationRow['allocated_quantity'] : 0;

            foreach (array('23012', 'SAgent_STK', 'RESV_23012') as $canonicalCode) {
                if ($allocatedQuantity <= 0) {
                    break;
                }

                $deduction = min($quantityItems[$canonicalCode], $allocatedQuantity);
                $quantityItems[$canonicalCode] -= $deduction;
                $allocatedQuantity -= $deduction;
            }
        }

        foreach ($items as $canonicalCode => $unusedQuantity) {
            $items[$canonicalCode] = $serialItems[$canonicalCode] + $quantityItems[$canonicalCode];
        }

        return array(
            'total' => array_sum($items),
            'items' => $items
        );
    }

    /**
     * سيريالات المنتج الموجودة داخل المخازن الثلاثة المعتمدة مع مخزن كل سيريال.
     */
    public function get_counted_subinventory_serials($item_code) {
        $sql = "
            SELECT i.serial_number, i.subinventory_code
            FROM {$this->table} i
            LEFT JOIN tbl_user_custody c
                ON i.item_code = c.item_code AND i.serial_number = c.serial_number
            LEFT JOIN tbl_requests r
                ON i.item_code = r.item_code AND i.serial_number = r.serial_number
            WHERE i.item_code = ?
              AND LOWER(TRIM(i.serial_control)) = 'yes'
              AND COALESCE(i.quantity_total, 0) > 0
              AND NULLIF(TRIM(i.serial_number), '') IS NOT NULL
              AND c.serial_number IS NULL
              AND r.serial_number IS NULL
              AND UPPER(TRIM(i.subinventory_code)) IN (?, ?, ?)
            ORDER BY
                FIELD(UPPER(TRIM(i.subinventory_code)), '23012', 'SAGENT_STK', 'RESV_23012'),
                i.serial_number ASC
        ";

        $query = $this->db->query($sql, array($item_code, '23012', 'SAGENT_STK', 'RESV_23012'));
        $rows = $query->result_array();
        $canonicalCodes = $this->counted_subinventory_codes();

        foreach ($rows as $index => $row) {
            $normalizedCode = strtoupper(trim((string) $row['subinventory_code']));
            $rows[$index]['subinventory_code'] = isset($canonicalCodes[$normalizedCode])
                ? $canonicalCodes[$normalizedCode]
                : trim((string) $row['subinventory_code']);
        }

        return $rows;
    }

    public function total_quantity_select_query() {
        $this->db->select("
            CASE
                WHEN LOWER(TRIM(i.serial_control)) = 'no'
                    THEN i.quantity_total - COUNT(DISTINCT CASE WHEN c.item_code = i.item_code THEN c.id END) - COUNT(DISTINCT CASE WHEN r.item_code = i.item_code THEN r.id END)
                ELSE
                    COUNT(DISTINCT CASE WHEN LOWER(TRIM(i.serial_control)) = 'yes' AND c.serial_number IS NULL AND r.serial_number IS NULL THEN i.serial_number END)
            END AS total_quantity
        ", False);
    }

    public function join_custodys() {
        $this->db->join('tbl_user_custody c', 'i.serial_number = c.serial_number OR (i.serial_control = "no" AND i.item_code = c.item_code)', 'left');
    }

    public function join_requests() {
        $this->db->join('tbl_requests r', 'i.serial_number = r.serial_number OR (i.serial_control = "no" AND i.item_code = r.item_code)', 'left');
    }

	public function get_store_items($where = array())
	{
		$this->db->from($this->table);
		if (count($where) > 0) {
			$this->db->where($where);
		}
		$query = $this->db->get();
		return $query->result_array();
	}

    public function get_store_items_per_page(
        $limit,
        $start,
        $sortColumn = 'item_description',
        $sortOrder = 'ASC',
        $searchCategory = '',
        $searchName = ''
    ) {
        // جلب البيانات مع تجميع العدد - CodeIgniter Query Builder (PHP 5.6 compatible)
        $this->db->select("
            i.item_description,
            i.item_category,
            i.item_code
        ", FALSE);
        $this->total_quantity_select_query();
        
        $this->db->from($this->table . ' AS i');
        $this->join_custodys();
        $this->join_requests();
        
        if (!empty($searchName)) {
            $this->db->group_start();
            $this->db->like('i.item_description', $searchName);
            $this->db->or_like('i.item_code', $searchName);
            $this->db->group_end();
        }

        if (!empty($searchCategory)) {
            $this->db->where('i.item_category', $searchCategory);
        }
        
        $this->db->group_by(array('i.item_description', 'i.item_category', 'i.item_code'));
        $this->db->order_by($sortColumn, $sortOrder);
        $this->db->limit($limit, $start);
        

        $query = $this->db->get();
        return $query->result_array();
    }


    public function get_low_products() {
        // جلب البيانات مع تجميع العدد - CodeIgniter Query Builder (PHP 5.6 compatible)
        $sql = "
            SELECT
                i.item_description,
                i.item_category,
                i.item_code,
                i.low_quantity,
                i.updated_at,
                CASE
                    WHEN LOWER(TRIM(i.serial_control)) = 'no'
                        THEN i.quantity_total - COUNT(DISTINCT CASE WHEN c.item_code = i.item_code THEN c.id END) - COUNT(DISTINCT CASE WHEN r.item_code = i.item_code THEN r.id END)
                    ELSE
                        COUNT(DISTINCT CASE WHEN LOWER(TRIM(i.serial_control)) = 'yes' AND c.serial_number IS NULL AND r.serial_number IS NULL THEN i.serial_number END)
                END AS total_quantity

            FROM current_store_items i

            LEFT JOIN tbl_user_custody c
                ON i.serial_number = c.serial_number OR (i.serial_control = 'no' AND i.item_code = c.item_code)

            LEFT JOIN tbl_requests r
                ON i.serial_number = r.serial_number OR (i.serial_control = 'no' AND i.item_code = r.item_code)


            GROUP BY
                i.item_description,
                i.item_category,
                i.item_code

            HAVING total_quantity <= i.low_quantity

            ORDER BY total_quantity ASC;
        ";

        $query = $this->db->query($sql);
        return $query->result_array();
    }

    public function get_stats() {
        // Get dashboard statistics - CodeIgniter Query Builder (PHP 5.6 compatible)
        $this->db->select('
            COUNT(DISTINCT item_description) as total_products,
            COUNT(DISTINCT item_category) as total_categories,
            SUM(quantity_total) as total_quantity
        ', FALSE);
        
        $this->db->from($this->table);
        
        $query = $this->db->get();
        $stats = $query->row_array();
        
        return array(
            'total_products' => isset($stats['total_products']) ? $stats['total_products'] : 0,
            'total_categories' => isset($stats['total_categories']) ? $stats['total_categories'] : 0,
            'total_quantity' => isset($stats['total_quantity']) ? $stats['total_quantity'] : 0
        );
    }

    function get_unique_store_items_count_by_search($searchName, $searchCategory)
    {
        $this->db->from($this->table);
        $this->db->select('COUNT(DISTINCT item_description) AS total');
        
        if (!empty($searchName)) {
            $this->db->group_start();
            $this->db->like('item_description', $searchName);
            $this->db->or_like('item_code', $searchName);
            $this->db->group_end();
        }

        if (!empty($searchCategory)) {
            $this->db->where('item_category', $searchCategory);
        }
        
        $query = $this->db->get();
        return $query->row_array()['total'];
    }

	public function get_store_items_length($where = array())
	{
		$this->db->from($this->table);
		$this->db->select('COUNT(*) AS count');
		if (count($where) > 0) {
			$this->db->where($where);
		}
		$query = $this->db->get();
		return $query->row_array()['count'];
	}


    function get_store_unique_items_count($where = array()) {
        $this->total_quantity_select_query();
        
        $this->db->from($this->table . ' AS i');
        $this->join_custodys();
        $this->join_requests();

        $this->db->where($where);
        $query = $this->db->get();
        return $query->row_array()['total_quantity'];
    }

    function get_store_item($where = array()) {
        $this->db->select("*");
        $this->db->from($this->table);
        $this->db->where($where);
        $query = $this->db->get();
        return $query->row_array();
    }

    function get_remaining_quantity_by_item_code($item_code) {
        $breakdown = $this->get_counted_subinventory_breakdown($item_code);
        return $breakdown['total'];
    }

    function get_serial_control_statuses($where = array()) {
        $this->db->select("DISTINCT LOWER(TRIM(serial_control)) AS serial_status");
        $this->db->from($this->table);
        $this->db->where($where);
        $query = $this->db->get();
        return array_column($query->result_array(), 'serial_status');
    }

    function get_serials($where = array()) {
        $this->db->select('i.serial_number AS serial_number');

        $this->db->from($this->table . ' AS i');
        $this->join_custodys();
        $this->join_requests();

        $this->db->where($where);
        $this->db->order_by('i.serial_number', 'ASC');

        $query = $this->db->get();
        return array_column($query->result_array(), 'serial_number');
    }


    function get_items_by_serialpart($serialpart, $where = array()) {
        $this->db->select('*');
        $this->db->from($this->table);
        $this->db->like('serial_number', $serialpart, 'after');
        $this->db->where($where);
        $query = $this->db->get();        
        return $query->result_array();
    }

    function get_items_count_in_shelf($shelf_id) {
        $this->total_quantity_select_query();

        $this->db->from($this->shelves_table . ' AS i');
        $this->db->where('shelf_id', $shelf_id);

        $this->db->join('tbl_user_custody c', 'i.serial_number = c.serial_number OR (i.serial_control = "no" AND i.item_code = c.item_code)', 'left');
        $this->db->join('tbl_requests r', 'i.serial_number = r.serial_number OR (i.serial_control = "no" AND i.item_code = r.item_code)', 'left');

        $query = $this->db->get();
        return array_column($query->result_array(), 'total_quantity')[0];
    }

    function get_items_in_shelf($shelf_id) {
        $this->db->select('*');

        $this->db->from($this->shelves_table . ' AS i');
        $this->db->where(['i.shelf_id' => $shelf_id]);

        $query = $this->db->get();
        return $query->result_array();
    }

    // السيريال هو المعرّف الثابت للمنتجات ذات السيريال؛ الباركود حقل قابل للتعديل من
    // صفحة تعديل المنتج، فاستخدامه في المطابقة كان بيكسر الربط مع صف الرف كل ما يتغيّر.
    private function shelf_identity_where($item_code, $serial_number, $barcode) {
        $where = ['item_code' => $item_code];

        if ($serial_number !== null && $serial_number !== '') {
            $where['serial_number'] = $serial_number;
        } else {
            $where['barcode'] = $barcode;
        }

        return $where;
    }

    function check_item_exists_in_shelves_table($shelf_id, $item_code, $serial_control, $serial_number, $barcode) {
        $this->db->select('*');
        $this->db->from($this->shelves_table);
        $this->db->where(array_merge(['serial_control' => $serial_control], $this->shelf_identity_where($item_code, $serial_number, $barcode)));
        $query = $this->db->get();
        return $query->row_array();
    }

    function get_item_shelf($item_code, $serial_number, $barcode) {
        $this->db->select('*');
        $this->db->from($this->shelves_table);
        $this->db->where($this->shelf_identity_where($item_code, $serial_number, $barcode));
        $query = $this->db->get();
        $result = $query->row_array();

        if (empty($result)) return null;

        return $result['shelf_id'];
    }


    function add_item_to_shelf($item_code, $serial_control, $serial_number, $barcode,  $shelf_id) {
        $this->db->insert($this->shelves_table, ['item_code' => $item_code, 'serial_control' => $serial_control, 'serial_number' => $serial_number, 'barcode' => $barcode, 'shelf_id' => $shelf_id, 'quantity_total' => 1]);
        return $this->db->insert_id();
    }

    function move_item_to_shelf($item_code, $serial_control, $serial_number, $barcode, $shelf_id) {
        if ($this->check_item_exists_in_shelves_table($shelf_id, $item_code, $serial_control, $serial_number, $barcode)) {
            # move it to the wanted shelf
            $this->db->where(array_merge(['serial_control' => $serial_control], $this->shelf_identity_where($item_code, $serial_number, $barcode)));
            # نحدّث الباركود كمان عشان يفضل متزامن مع أحدث قيمة في المنتج
            $this->db->update($this->shelves_table, ['shelf_id' => $shelf_id, 'barcode' => $barcode]);

        } else {
            # add it to the wanted shelf
            $this->add_item_to_shelf($item_code, $serial_control, $serial_number, $barcode, $shelf_id);
        }
    }

    public function remove_item_from_shelf($item_code, $serial_control, $serial_number, $barcode) {
        $this->db->where(array_merge(['serial_control' => $serial_control], $this->shelf_identity_where($item_code, $serial_number, $barcode)));
        $this->db->delete($this->shelves_table);
        return $this->db->affected_rows();
    }

    public function get_store_item_for_shelf_row($shelf_item) {
        return $this->get_store_item($this->shelf_identity_where($shelf_item['item_code'], $shelf_item['serial_number'], $shelf_item['barcode']));
    }

	public function add_store_item($data)
	{
		$this->db->insert($this->table, $data);
		$insert_id = $this->db->insert_id();
        $this->archive_serial_reference($data);
		return $insert_id;
	}

	public function update_store_item($data, $where = array())
	{
		$existing_items = $this->get_store_items($where);
		$this->db->where($where);
		$this->db->update($this->table, $data);
		$affected_rows = $this->db->affected_rows();

        foreach ($existing_items as $existing_item) {
            $this->archive_serial_reference(array_merge($existing_item, $data));
        }

		return $affected_rows;
	}

	public function delete_store_item($data)
	{
		$existing_items = $this->get_store_items($data);

        foreach ($existing_items as $existing_item) {
            $this->archive_serial_reference($existing_item);
        }

		$this->db->where($data);
		$this->db->delete($this->table);
		return $this->db->affected_rows();
	}

	public function delete_store_items_of($of, $ofField, $whereField)
	{
		$this->db->where($ofField, $of);
		$this->db->delete($this->table);
		return $this->db->affected_rows();
	}

    public function add_item_image($item_code, $image) {
        $this->db->insert($this->table_images, ['item_code' => $item_code, 'filename' => $image]);
        return $this->db->affected_rows();
    }

    public function get_store_item_images($item_code) {
        $this->db->select('*');
        $this->db->from($this->table_images);
        $this->db->where(['item_code' => $item_code]);
        $query = $this->db->get();
        return $query->result_array();
    }

    public function get_store_item_image($image_id) {
        $this->db->select('*');
        $this->db->from($this->table_images);
        $this->db->where(['id' => $image_id]);
        $query = $this->db->get();
        return $query->row_array();
    }

    public function delete_item_image($image_id) {
        $this->db->where(['id' => $image_id]);
        $this->db->delete($this->table_images);
        return $this->db->affected_rows();
    }
}

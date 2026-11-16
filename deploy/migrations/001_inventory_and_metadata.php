<?php
// Additive baseline for the schema initialization already present in the models.
return static function (DeploymentSchema $s): void {
    foreach (['current_store_items', 'tbl_operations', 'tbl_insert_excel', 'tbl_shipments'] as $table) {
        if (!$s->tableExists($table)) { throw new RuntimeException("Required application table is missing: $table"); }
    }
    $s->column('current_store_items', 'poster_number', "VARCHAR(255) NULL DEFAULT ''");
    $s->create('tbl_product_serial_history', "
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        serial_number VARCHAR(191) NOT NULL, barcode VARCHAR(191) NOT NULL DEFAULT '',
        item_code VARCHAR(120) NOT NULL DEFAULT '', item_description VARCHAR(255) NOT NULL DEFAULT '',
        subinventory_code VARCHAR(100) NOT NULL DEFAULT '', updated_at DATETIME NOT NULL,
        UNIQUE KEY serial_number_unique (serial_number), KEY item_code_index (item_code)");
    if ($s->tableExists('tbl_product_serial_history')) {
        $s->column('tbl_product_serial_history', 'subinventory_code', "VARCHAR(100) NOT NULL DEFAULT ''");
    }
    $s->sql("INSERT INTO tbl_product_serial_history
        (serial_number, barcode, item_code, item_description, subinventory_code, updated_at)
        SELECT UPPER(TRIM(i.serial_number)), COALESCE(i.barcode, ''), COALESCE(i.item_code, ''),
        COALESCE(i.item_description, ''), COALESCE(i.subinventory_code, ''), NOW()
        FROM current_store_items i WHERE NULLIF(TRIM(i.serial_number), '') IS NOT NULL
        ON DUPLICATE KEY UPDATE subinventory_code = IF(TRIM(tbl_product_serial_history.subinventory_code) = '', VALUES(subinventory_code), tbl_product_serial_history.subinventory_code)");
    $s->create('tbl_product_stock_movements', "
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, serial_number VARCHAR(191) NOT NULL,
        item_code VARCHAR(120) NOT NULL DEFAULT '', item_description VARCHAR(255) NOT NULL DEFAULT '',
        subinventory_code VARCHAR(100) NOT NULL DEFAULT '', movement_type VARCHAR(10) NOT NULL,
        source_filename VARCHAR(255) NOT NULL DEFAULT '', uploaded_by INT NULL, occurred_at DATETIME NOT NULL,
        KEY serial_number_index (serial_number), KEY occurred_at_index (occurred_at)");
    if ($s->tableExists('tbl_product_stock_movements')) {
        $s->column('tbl_product_stock_movements', 'subinventory_code', "VARCHAR(100) NOT NULL DEFAULT ''");
    }
    // Seed only serials with no history. This remains safe after an interrupted run.
    $s->sql("INSERT INTO tbl_product_stock_movements
        (serial_number, item_code, item_description, subinventory_code, movement_type, source_filename, uploaded_by, occurred_at)
        SELECT UPPER(TRIM(i.serial_number)), COALESCE(i.item_code, ''), COALESCE(i.item_description, ''),
        COALESCE(i.subinventory_code, ''), 'in', 'Initial inventory baseline', NULL, COALESCE(i.created_at, NOW())
        FROM current_store_items i WHERE NULLIF(TRIM(i.serial_number), '') IS NOT NULL
        AND NOT EXISTS (SELECT 1 FROM tbl_product_stock_movements m WHERE CONVERT(m.serial_number USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(UPPER(TRIM(i.serial_number)) USING utf8mb4) COLLATE utf8mb4_unicode_ci)");
    $s->sql("UPDATE tbl_product_stock_movements m INNER JOIN tbl_product_serial_history h ON CONVERT(h.serial_number USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(m.serial_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
        SET m.subinventory_code = h.subinventory_code WHERE TRIM(m.subinventory_code) = '' AND TRIM(h.subinventory_code) <> ''");
    $s->column('tbl_operations', 'item_code', "VARCHAR(120) NOT NULL DEFAULT ''");
    $s->column('tbl_operations', 'item_description', "VARCHAR(255) NOT NULL DEFAULT ''");
    // Idempotent backfill also completes a previously interrupted migration.
    $s->sql("UPDATE tbl_operations o INNER JOIN tbl_product_serial_history i
        ON NULLIF(TRIM(o.serial), '') IS NOT NULL AND
        CONVERT(TRIM(LEADING 'S' FROM UPPER(TRIM(i.serial_number))) USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(TRIM(LEADING 'S' FROM UPPER(TRIM(o.serial))) USING utf8mb4) COLLATE utf8mb4_unicode_ci
        SET o.item_code = IF(TRIM(o.item_code) = '', i.item_code, o.item_code),
            o.item_description = IF(TRIM(o.item_description) = '', i.item_description, o.item_description)
        WHERE TRIM(o.item_code) = '' OR TRIM(o.item_description) = ''");
    foreach (['insert_excel_new_ordern' => 120, 'insert_excel_description' => 255,
              'insert_excel_product_serial_number' => 255, 'insert_excel_duplicate_identifier' => 120] as $column => $length) {
        $s->column('tbl_insert_excel', $column, "VARCHAR($length) NOT NULL DEFAULT ''");
    }
    $s->column('tbl_shipments', 'delivery_date', 'DATE NULL');
    $s->create('tbl_custody_return_requests', "
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, custody_id INT UNSIGNED NOT NULL,
        employee_id INT UNSIGNED NOT NULL, item_code VARCHAR(120) NULL, item_description VARCHAR(500) NULL,
        serial_number VARCHAR(120) NULL, barcode VARCHAR(255) NULL, order_number TEXT NULL,
        custody_type INT NOT NULL DEFAULT 1, custody_date DATETIME NULL, employee_note VARCHAR(500) NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending', requested_at DATETIME NOT NULL,
        reviewed_at DATETIME NULL, reviewed_by INT UNSIGNED NULL, admin_note VARCHAR(500) NULL,
        KEY custody_id (custody_id), KEY employee_id (employee_id), KEY status (status)");
    if ($s->tableExists('tbl_custody_return_requests')) {
        foreach (['item_description' => 'VARCHAR(500) NULL', 'custody_type' => 'INT NOT NULL DEFAULT 1',
                  'custody_date' => 'DATETIME NULL', 'employee_note' => 'VARCHAR(500) NULL',
                  'reviewed_at' => 'DATETIME NULL', 'reviewed_by' => 'INT UNSIGNED NULL',
                  'admin_note' => 'VARCHAR(500) NULL'] as $column => $definition) {
            $s->column('tbl_custody_return_requests', $column, $definition);
        }
    }
};

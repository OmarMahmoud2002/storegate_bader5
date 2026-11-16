<?php
return static function (DeploymentSchema $s): void {
    $s->create('employee_leaves', "
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, name VARCHAR(150) NOT NULL,
        start_date DATE NOT NULL, end_date DATE NOT NULL, all_employees TINYINT(1) NOT NULL DEFAULT 1,
        employee_ids TEXT NOT NULL, created_by INT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY leave_dates (start_date, end_date)");
    $s->create('employee_leave_overrides', "
        user_id INT NOT NULL, schedule_date DATE NOT NULL, leave_signature TEXT NOT NULL,
        schedule_type VARCHAR(20) NOT NULL, description TEXT NOT NULL, PRIMARY KEY (user_id, schedule_date)");
    $s->create('tbl_user_lock_extras', "
        lock_extra_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, lock_extra_userid INT UNSIGNED NOT NULL,
        lock_extra_date DATE NOT NULL, lock_extra_tamara DECIMAL(14,2) NOT NULL DEFAULT 0,
        lock_extra_wallet DECIMAL(14,2) NOT NULL DEFAULT 0, lock_extra_balance DECIMAL(14,2) NOT NULL DEFAULT 0,
        lock_extra_updated_at DATETIME NULL, lock_extra_updated_by INT UNSIGNED NULL,
        UNIQUE KEY lock_extra_user_date (lock_extra_userid, lock_extra_date)");
    // Recover the unique index if an older model stopped between CREATE and ALTER.
    if ($s->tableExists('tbl_user_lock_extras')) {
        $indexes = $s->db->query("SHOW INDEX FROM tbl_user_lock_extras WHERE Key_name = 'lock_extra_user_date'")->fetchAll();
        if (!$indexes) { $s->sql('ALTER TABLE tbl_user_lock_extras ADD UNIQUE KEY lock_extra_user_date (lock_extra_userid, lock_extra_date)'); }
    }
    $s->create('tbl_serial_notes', "
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, serial_number VARCHAR(190) NOT NULL,
        normalized_serial VARCHAR(190) NOT NULL, note TEXT NOT NULL, created_by INT NULL DEFAULT NULL,
        created_at DATETIME NOT NULL, KEY normalized_serial_index (normalized_serial), KEY created_at_index (created_at)");
    $s->create('tbl_serial_shelf_returns', "
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, serial_number VARCHAR(191) NOT NULL,
        item_code VARCHAR(120) NOT NULL DEFAULT '', shelf_id INT NOT NULL, absent_since DATETIME NOT NULL,
        UNIQUE KEY serial_number_unique (serial_number)");
    $column = $s->db->query("SELECT CHARACTER_SET_NAME, COLLATION_NAME FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'current_store_items' AND COLUMN_NAME = 'item_code'")->fetch();
    if (!$column || !preg_match('/^[a-zA-Z0-9_]+$/', $column['CHARACTER_SET_NAME']) || !preg_match('/^[a-zA-Z0-9_]+$/', $column['COLLATION_NAME'])) {
        throw new RuntimeException('Cannot determine the stock item_code character set.');
    }
    $charset = $column['CHARACTER_SET_NAME'];
    $collation = $column['COLLATION_NAME'];
    $s->create('tbl_product_quantity_updates', "
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, item_code VARCHAR(120) NOT NULL,
        quantity INT NOT NULL DEFAULT 0, previous_quantity INT NOT NULL DEFAULT 0,
        change_amount INT NOT NULL DEFAULT 0, changed_at DATETIME NULL DEFAULT NULL,
        source VARCHAR(100) NOT NULL DEFAULT '', changed_by INT NULL DEFAULT NULL,
        UNIQUE KEY item_code_unique (item_code), KEY changed_at_index (changed_at)",
        "ENGINE=InnoDB DEFAULT CHARSET=$charset COLLATE=$collation");
};

<?php
require dirname(__DIR__) . '/migration_support.php';
$admin = new PDO('mysql:host=127.0.0.1;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database = 'storegate_deploy_test_' . bin2hex(random_bytes(5));
$temp = sys_get_temp_dir() . '/storegate-migration-test-' . bin2hex(random_bytes(5));
function check(bool $condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
}
function quietMigration(PDO $pdo, string $directory, bool $plan = false): string {
    ob_start();
    try { deploymentMigrate($pdo, $directory, $plan); return ob_get_contents(); }
    finally { ob_end_clean(); }
}
try {
    $admin->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
    $db = new PDO("mysql:host=127.0.0.1;dbname=$database;charset=utf8mb4", 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec("CREATE TABLE current_store_items (id INT AUTO_INCREMENT PRIMARY KEY,
        serial_number VARCHAR(191), barcode VARCHAR(191), item_code VARCHAR(120), item_description VARCHAR(255),
        subinventory_code VARCHAR(100), serial_control VARCHAR(10), quantity_total INT, created_at DATETIME) ENGINE=InnoDB");
    $db->exec('CREATE TABLE tbl_operations (id INT PRIMARY KEY, serial VARCHAR(191)) ENGINE=InnoDB');
    $db->exec('CREATE TABLE tbl_insert_excel (id INT PRIMARY KEY) ENGINE=InnoDB');
    $db->exec('CREATE TABLE tbl_shipments (id INT PRIMARY KEY) ENGINE=InnoDB');
    $db->exec("INSERT INTO current_store_items (serial_number, barcode, item_code, item_description, subinventory_code, serial_control, quantity_total, created_at)
        VALUES ('SERIAL1', '', 'P1', 'منتج عربي', '23012', 'yes', 20, '2026-01-01'),
               ('', 'BAR1', 'P1', 'منتج عربي', 'SAGENT_STK', 'no', 3, '2026-01-02'),
               ('SERIAL2', '', 'P2', 'Other stock', 'OTHER', 'yes', 1, '2026-01-03')");
    $db->exec("INSERT INTO tbl_operations VALUES (1, 'SSERIAL1')");
    $directory = dirname(__DIR__) . '/migrations';
    $plan = quietMigration($db, $directory, true);
    check(str_contains($plan, 'Pending:'), 'Plan did not list pending migrations.');
    check((int) $db->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')->fetchColumn() === 4, 'Plan wrote to the database.');
    quietMigration($db, $directory);
    check((int) $db->query('SELECT COUNT(*) FROM _storegate_deploy_migrations')->fetchColumn() === 2, 'Migration ledger incomplete.');
    check((int) $db->query("SELECT quantity FROM tbl_product_quantity_updates WHERE item_code = 'P1'")->fetchColumn() === 4, 'Quantity baseline differs from the application.');
    check($db->query('SELECT item_description FROM tbl_operations WHERE id = 1')->fetchColumn() === 'منتج عربي', 'Operation metadata backfill failed.');
    check((int) $db->query('SELECT COUNT(*) FROM current_store_items')->fetchColumn() === 3, 'Stock rows changed.');
    $movementCount = (int) $db->query('SELECT COUNT(*) FROM tbl_product_stock_movements')->fetchColumn();
    check(str_contains(quietMigration($db, $directory), 'Already applied:'), 'Second run did not skip applied migrations.');
    check((int) $db->query('SELECT COUNT(*) FROM tbl_product_stock_movements')->fetchColumn() === $movementCount, 'Repeated run duplicated movements.');
    // Existing partial schema is completed without replacing previously saved data.
    $db->exec('DELETE FROM _storegate_deploy_migrations');
    $db->exec("INSERT INTO tbl_serial_notes (serial_number, normalized_serial, note, created_at) VALUES ('SERIAL1', 'ERIAL1', 'Keep this note', NOW())");
    quietMigration($db, $directory);
    check((int) $db->query('SELECT COUNT(*) FROM tbl_product_stock_movements')->fetchColumn() === $movementCount, 'Retry duplicated movements.');
    check($db->query('SELECT note FROM tbl_serial_notes LIMIT 1')->fetchColumn() === 'Keep this note', 'Existing notes were overwritten.');
    mkdir($temp);
    foreach (glob($directory . '/*.php') as $file) { copy($file, $temp . '/' . basename($file)); }
    $first = $temp . '/001_inventory_and_metadata.php';
    file_put_contents($first, "\n// changed\n", FILE_APPEND);
    $rejected = false;
    try { quietMigration($db, $temp); } catch (RuntimeException $error) { $rejected = str_contains($error->getMessage(), 'modified'); }
    check($rejected, 'Changed applied migration was accepted.');
    copy($directory . '/001_inventory_and_metadata.php', $first);
    $failed = $temp . '/003_failure_recovery.php';
    file_put_contents($failed, '<?php return static function (DeploymentSchema $s): void { $s->create("test_retry", "id INT PRIMARY KEY"); $s->sql("INVALID SQL"); };');
    $failedAsExpected = false;
    try { quietMigration($db, $temp); } catch (PDOException $error) { $failedAsExpected = true; }
    check($failedAsExpected, 'Expected failure was ignored.');
    check((int) $db->query('SELECT COUNT(*) FROM _storegate_deploy_migrations')->fetchColumn() === 2, 'Failed migration marked complete.');
    file_put_contents($failed, '<?php return static function (DeploymentSchema $s): void { $s->create("test_retry", "id INT PRIMARY KEY"); $s->sql("INSERT INTO test_retry VALUES (1) ON DUPLICATE KEY UPDATE id = VALUES(id)"); };');
    quietMigration($db, $temp);
    check((int) $db->query('SELECT COUNT(*) FROM test_retry')->fetchColumn() === 1, 'Recovery after partial DDL failed.');
    echo "PASS: read-only plan, schema creation, Arabic backfill, quantity baseline, repeat/retry, checksums and failure recovery.\n";
} finally {
    $admin->exec("DROP DATABASE IF EXISTS `$database`");
    if (is_dir($temp)) { foreach (glob($temp . '/*.php') as $file) { unlink($file); } rmdir($temp); }
}

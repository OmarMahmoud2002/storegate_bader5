<?php
// CLI only: reads the live CI database config without booting web controllers.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/migration_support.php';

try {
    $mode = $argv[1] ?? '';
    $site = rtrim($argv[2] ?? '', '/\\');
    if (!in_array($mode, ['plan', 'migrate', 'dump-config', 'inspect'], true) || !is_dir($site)) {
        throw new RuntimeException('Usage: database.php plan|migrate|dump-config|inspect SITE [PATH]');
    }
    $config = deploymentDatabaseConfig($site);
    $pdo = deploymentDatabase($config);
    if ($mode === 'dump-config') {
        deploymentDumpConfig($pdo, $config, $argv[3] ?? '');
        exit;
    }
    if ($mode === 'inspect') {
        foreach ($pdo->query('SELECT ENGINE, COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() GROUP BY ENGINE') as $row) {
            echo ($row['ENGINE'] ?? 'VIEW') . ': ' . $row['total'] . PHP_EOL;
        }
        exit;
    }
    $path = $argv[3] ?? __DIR__ . '/migrations';
    deploymentMigrate($pdo, $path, $mode === 'plan');
} catch (Throwable $error) {
    // PDO's connection error can contain host/user details; never print config.
    fwrite(STDERR, 'Database operation stopped: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}

<?php
// CLI only: reads the live CI database config without booting web controllers.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/migration_support.php';

try {
    $mode = $argv[1] ?? '';
    $site = rtrim($argv[2] ?? '', '/\\');
    if (!in_array($mode, ['plan', 'migrate', 'dump-config', 'inspect', 'fingerprint-save', 'fingerprint-check'], true) || !is_dir($site)) {
        throw new RuntimeException('Usage: database.php plan|migrate|dump-config|inspect|fingerprint-save|fingerprint-check SITE [PATH]');
    }
    $config = deploymentDatabaseConfig($site);
    $pdo = deploymentDatabase($config);
    if ($mode === 'fingerprint-save') {
        $fingerprint = deploymentFingerprint($pdo);
        $json = json_encode($fingerprint, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        if (file_put_contents($argv[3], $json, LOCK_EX) !== strlen($json)) { throw new RuntimeException('Cannot save database fingerprint.'); }
        chmod($argv[3], 0600);
        echo 'Saved fingerprints for ' . count($fingerprint) . " existing tables.\n";
        exit;
    }
    if ($mode === 'fingerprint-check') {
        $before = json_decode(file_get_contents($argv[3]), true, 512, JSON_THROW_ON_ERROR);
        $after = deploymentFingerprint($pdo, $before);
        foreach ($before as $table => $expected) {
            if ($table === '_storegate_schema_migrations') { continue; }
            if ($after[$table] !== $expected) { throw new RuntimeException("Existing data changed during deployment: $table"); }
        }
        echo 'Verified: all existing application table rows and values are unchanged.' . PHP_EOL;
        exit;
    }
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
    $path = $argv[3] ?? __DIR__ . '/schema_migrations';
    deploymentMigrate($pdo, $path, $mode === 'plan');
} catch (Throwable $error) {
    // PDO's connection error can contain host/user details; never print config.
    fwrite(STDERR, 'Database operation stopped: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}

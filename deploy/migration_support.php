<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

function deploymentDatabaseConfig(string $site): array
{
    defined('BASEPATH') || define('BASEPATH', $site . '/system/');
    defined('APPPATH') || define('APPPATH', $site . '/application/');
    defined('ENVIRONMENT') || define('ENVIRONMENT', 'production');
    $db = [];
    $active_group = 'default';
    require $site . '/application/config/database.php';
    $override = $site . '/application/config/production/database.php';
    if (is_file($override)) { require $override; }
    $config = $db[$active_group] ?? [];
    if (empty($config['database']) || !empty($config['dsn']) || !empty($config['dbprefix']) || !empty($config['encrypt'])) {
        throw new RuntimeException('Unsupported database configuration; review DSN, prefix or SSL settings.');
    }
    return $config;
}

function deploymentDatabase(array $config): PDO
{
    $host = $config['hostname'] ?? 'localhost';
    $dsn = 'mysql:host=' . $host . ';dbname=' . $config['database'] . ';charset=utf8mb4';
    if (!empty($config['port'])) { $dsn .= ';port=' . (int) $config['port']; }
    return new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
    ]);
}

function deploymentDumpConfig(PDO $pdo, array $config, string $destination): void
{
    // An InnoDB snapshot is consistent without blocking normal site writes.
    $nonTransactional = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' AND ENGINE <> 'InnoDB'")->fetchColumn();
    if (!preg_match('/^[A-Za-z0-9_-]+$/', $config['database'])) { throw new RuntimeException('Unsupported database name.'); }
    $handle = fopen($destination, 'x');
    if ($handle === false) { throw new RuntimeException('Cannot create private dump configuration.'); }
    chmod($destination, 0600);
    try {
        $ini = "[client]\n";
        foreach (['host' => 'hostname', 'user' => 'username', 'password' => 'password', 'port' => 'port'] as $option => $key) {
            if (!isset($config[$key]) || $config[$key] === '') { continue; }
            $value = str_replace(["\\", '"', "\n", "\r"], ["\\\\", '\\"', '\\n', '\\r'], (string) $config[$key]);
            $ini .= $option . '="' . $value . '"' . "\n";
        }
        if (fwrite($handle, $ini) !== strlen($ini)) { throw new RuntimeException('Cannot write dump configuration.'); }
        echo $config['database'] . PHP_EOL . ($nonTransactional ? '--lock-all-tables' : '--single-transaction') . PHP_EOL;
    } finally {
        fclose($handle);
    }
}

final class DeploymentSchema
{
    public array $statements = [];
    public function __construct(public PDO $db) {}
    public function tableExists(string $table): bool
    {
        $query = $this->db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $query->execute([$table]);
        return (bool) $query->fetchColumn();
    }
    public function columnExists(string $table, string $column): bool
    {
        $query = $this->db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $query->execute([$table, $column]);
        return (bool) $query->fetchColumn();
    }
    public function create(string $table, string $definition, string $options = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'): bool
    {
        if ($this->tableExists($table)) { return false; }
        $this->statements[] = "CREATE TABLE IF NOT EXISTS `$table` ($definition) $options";
        return true;
    }
    public function column(string $table, string $column, string $definition): bool
    {
        if (!$this->tableExists($table)) { throw new RuntimeException("Required base table is missing: $table"); }
        if ($this->columnExists($table, $column)) { return false; }
        $this->statements[] = "ALTER TABLE `$table` ADD COLUMN `$column` $definition";
        return true;
    }
    public function sql(string $sql): void { $this->statements[] = $sql; }
}

function deploymentMigrate(PDO $pdo, string $directory, bool $preview): void
{
    if (!is_dir($directory)) { throw new RuntimeException('Migration directory is missing.'); }
    $locked = false;
    try {
        if (!$preview) {
            $locked = (int) $pdo->query("SELECT GET_LOCK(CONCAT(DATABASE(), ':storegate-deploy'), 0)")->fetchColumn() === 1;
            if (!$locked) { throw new RuntimeException('Another database migration is running.'); }
            $pdo->exec('CREATE TABLE IF NOT EXISTS `_storegate_deploy_migrations` (version VARCHAR(190) PRIMARY KEY, checksum CHAR(64) NOT NULL, applied_at DATETIME NOT NULL) ENGINE=InnoDB');
        }
        $schema = new DeploymentSchema($pdo);
        $applied = [];
        if ($schema->tableExists('_storegate_deploy_migrations')) {
            foreach ($pdo->query('SELECT version, checksum FROM `_storegate_deploy_migrations`') as $row) { $applied[$row['version']] = $row['checksum']; }
        }
        $files = glob(rtrim($directory, '/\\') . '/[0-9]*.php');
        sort($files, SORT_STRING);
        $available = array_map('basename', $files);
        if (array_diff(array_keys($applied), $available)) { throw new RuntimeException('Previously applied migrations are missing; refusing an older deployment.'); }
        foreach ($files as $file) {
            $version = basename($file);
            $checksum = hash('sha256', str_replace("\r\n", "\n", file_get_contents($file)));
            if (isset($applied[$version])) {
                if (!hash_equals($applied[$version], $checksum)) { throw new RuntimeException("Applied migration was modified: $version"); }
                echo "Already applied: $version\n";
                continue;
            }
            if ($applied && strcmp($version, max(array_keys($applied))) < 0) {
                throw new RuntimeException("New migration is older than an applied migration: $version");
            }
            $migration = require $file;
            if (!is_callable($migration)) { throw new RuntimeException("Invalid migration: $version"); }
            $schema = new DeploymentSchema($pdo);
            $migration($schema);
            echo ($preview ? 'Pending: ' : 'Applying: ') . $version . ' (' . count($schema->statements) . " statements)\n";
            if ($preview) { foreach ($schema->statements as $sql) { echo $sql . ";\n"; } continue; }
            // MySQL DDL auto-commits. Each migration must be safe to retry after failure.
            foreach ($schema->statements as $sql) { $pdo->exec($sql); }
            $query = $pdo->prepare('INSERT INTO `_storegate_deploy_migrations` (version, checksum, applied_at) VALUES (?, ?, ?)');
            $query->execute([$version, $checksum, gmdate('Y-m-d H:i:s')]);
        }
        echo $preview ? "Database preview only; no writes.\n" : "Database migrations complete.\n";
    } finally {
        if ($locked) { $pdo->query("SELECT RELEASE_LOCK(CONCAT(DATABASE(), ':storegate-deploy'))"); }
    }
}

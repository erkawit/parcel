<?php
/**
 * Database Connection & Neon Services Configuration
 * ระบบจัดการครุภัณฑ์สำนักงาน (Supports Neon PostgreSQL & MySQL/MariaDB)
 */

if (!function_exists('loadProjectEnv')) {
    function loadProjectEnv(): void
    {
        $envFiles = [
            __DIR__ . '/../.env',
            __DIR__ . '/../.env.local',
        ];
        foreach ($envFiles as $file) {
            if (!is_file($file)) {
                continue;
            }
            $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (!$lines) {
                continue;
            }
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#') {
                    continue;
                }
                $parts = explode('=', $line, 2);
                if (count($parts) !== 2) {
                    continue;
                }
                $key = trim($parts[0]);
                $val = trim($parts[1]);
                if (
                    (str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                    (str_starts_with($val, "'") && str_ends_with($val, "'"))
                ) {
                    $val = substr($val, 1, -1);
                }
                if ($key !== '') {
                    $_ENV[$key] = $val;
                    $_SERVER[$key] = $val;
                    @putenv("{$key}={$val}");
                }
            }
        }
    }
}

if (!function_exists('envVal')) {
    function envVal(string $key, ?string $default = null): ?string
    {
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return (string) $_ENV[$key];
        }
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return (string) $_SERVER[$key];
        }
        $v = getenv($key);
        if ($v !== false && $v !== '') {
            return (string) $v;
        }
        return $default;
    }
}

loadProjectEnv();

/**
 * Return configured Neon Services (Postgres, Auth, Realtime) from environment variables
 */
if (!function_exists('getNeonServicesConfig')) {
    function getNeonServicesConfig(): array
    {
        return [
            'postgres' => [
                'database_url' => envVal('DATABASE_URL') ?? envVal('POSTGRES_URL'),
                'direct_url' => envVal('DATABASE_URL_UNPOOLED') ?? envVal('POSTGRES_URL_NON_POOLING'),
            ],
            'auth' => [
                'url' => envVal('NEON_AUTH_BASE_URL')
                    ?? envVal('NEXT_PUBLIC_NEON_AUTH_URL')
                    ?? envVal('VITE_NEON_AUTH_URL')
                    ?? envVal('STACK_PROJECT_ID'),
                'jwks_url' => envVal('NEON_AUTH_JWKS_URL')
                    ?? envVal('STACK_JWKS_URL'),
                'publishable_key' => envVal('NEXT_PUBLIC_STACK_PUBLISHABLE_CLIENT_KEY')
                    ?? envVal('NEON_AUTH_PUBLISHABLE_KEY'),
                'secret_key' => envVal('STACK_SECRET_SERVER_KEY')
                    ?? envVal('NEON_AUTH_SECRET_KEY'),
            ],
            'realtime' => [
                'url' => envVal('NEON_REALTIME_URL')
                    ?? envVal('NEXT_PUBLIC_NEON_REALTIME_URL')
                    ?? envVal('VITE_NEON_REALTIME_URL'),
                'secret' => envVal('NEON_REALTIME_SECRET'),
            ],
        ];
    }
}

/**
 * Compatibility PDO wrapper for Neon PostgreSQL
 * Automatically translates common MySQL idioms used in legacy queries into valid PostgreSQL
 */
if (!class_exists('NeonPostgresPDO')) {
    class NeonPostgresPDO extends PDO
    {
        public static function translateSql(string $sql): string
        {
            $trimmed = trim($sql);

            // 1. SHOW TABLES
            if (preg_match('/^SHOW\s+TABLES$/i', $trimmed)) {
                return "SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename";
            }

            // 2. SHOW COLUMNS FROM <table> LIKE '<col>'
            if (preg_match('/^SHOW\s+COLUMNS\s+FROM\s+[`"]?([a-zA-Z0-9_]+)[`"]?\s+LIKE\s+\'([^\']+)\'/i', $trimmed, $m)) {
                $tbl = strtolower($m[1]);
                $col = $m[2];
                return "SELECT column_name AS \"Field\", data_type AS \"Type\", is_nullable AS \"Null\", column_default AS \"Default\" FROM information_schema.columns WHERE table_schema = 'public' AND table_name = '{$tbl}' AND column_name LIKE '{$col}'";
            }

            // 3. SHOW COLUMNS FROM <table> / DESCRIBE <table>
            if (preg_match('/^(?:SHOW\s+COLUMNS\s+FROM|DESCRIBE)\s+[`"]?([a-zA-Z0-9_]+)[`"]?$/i', $trimmed, $m)) {
                $tbl = strtolower($m[1]);
                return "SELECT column_name AS \"Field\", data_type AS \"Type\", is_nullable AS \"Null\", column_default AS \"Default\" FROM information_schema.columns WHERE table_schema = 'public' AND table_name = '{$tbl}' ORDER BY ordinal_position";
            }

            // 4. Strip backticks
            $sql = str_replace('`', '', $sql);

            // 5. INSERT IGNORE INTO -> INSERT INTO ... ON CONFLICT DO NOTHING
            if (preg_match('/^INSERT\s+IGNORE\s+INTO\s+/i', $sql)) {
                $sql = preg_replace('/^INSERT\s+IGNORE\s+INTO\s+/i', 'INSERT INTO ', $sql);
                $sql = rtrim($sql, "; \t\n\r") . ' ON CONFLICT DO NOTHING';
            }

            // 6. FIELD(role, 'chairman', 'member', 'secretary')
            $sql = preg_replace(
                "/FIELD\s*\(\s*role\s*,\s*'chairman'\s*,\s*'member'\s*,\s*'secretary'\s*\)/i",
                "(CASE role WHEN 'chairman' THEN 1 WHEN 'member' THEN 2 WHEN 'secretary' THEN 3 ELSE 4 END)",
                $sql
            );

            // 7. ALTER TABLE ... ADD COLUMN ... AFTER ... / COMMENT '...'
            if (preg_match('/^ALTER\s+TABLE\s+/i', $trimmed)) {
                $sql = preg_replace("/\s+COMMENT\s+'[^']*'/i", '', $sql);
                $sql = preg_replace('/\s+AFTER\s+[a-zA-Z0-9_]+/i', '', $sql);
            }

            // 8. Runtime CREATE TABLE IF NOT EXISTS adjustments
            if (preg_match('/^CREATE\s+TABLE\s+/i', $trimmed)) {
                $sql = preg_replace('/\bINT\s+AUTO_INCREMENT\s+PRIMARY\s+KEY\b/i', 'SERIAL PRIMARY KEY', $sql);
                $sql = preg_replace('/\bTINYINT\s*\(\s*1\s*\)/i', 'SMALLINT', $sql);
                $sql = preg_replace('/\bDATETIME\b/i', 'TIMESTAMP', $sql);
                $sql = preg_replace('/\s+ON\s+UPDATE\s+CURRENT_TIMESTAMP/i', '', $sql);
                $sql = preg_replace('/\s*ENGINE\s*=\s*[a-zA-Z0-9_]+.*$/is', '', $sql);
            }

            return $sql;
        }

        #[\ReturnTypeWillChange]
        public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
        {
            $pgSql = self::translateSql($query);
            if ($fetchMode === null) {
                return parent::query($pgSql);
            }
            return parent::query($pgSql, $fetchMode, ...$fetchModeArgs);
        }

        #[\ReturnTypeWillChange]
        public function prepare(string $query, array $options = []): PDOStatement|false
        {
            $pgSql = self::translateSql($query);
            return parent::prepare($pgSql, $options);
        }

        #[\ReturnTypeWillChange]
        public function exec(string $statement): int|false
        {
            $pgSql = self::translateSql($statement);
            return parent::exec($pgSql);
        }

        #[\ReturnTypeWillChange]
        public function lastInsertId(?string $name = null): string|false
        {
            try {
                $id = parent::lastInsertId($name);
                if ($id !== false && $id !== '' && $id !== '0') {
                    return $id;
                }
            } catch (PDOException $e) {
            }
            try {
                $stmt = parent::query('SELECT LASTVAL()');
                if ($stmt) {
                    return (string) $stmt->fetchColumn();
                }
            } catch (PDOException $e) {
            }
            return '0';
        }
    }
}

$neonServices = getNeonServicesConfig();
$databaseUrl = $neonServices['postgres']['database_url'];
$dbDriver = envVal('DB_CONNECTION');

$isPostgres = false;
if ($databaseUrl && preg_match('/^postgres(?:ql)?:\/\//i', $databaseUrl)) {
    $isPostgres = true;
} elseif ($dbDriver === 'pgsql' || $dbDriver === 'postgres') {
    $isPostgres = true;
}

if (!function_exists('isPostgresDb')) {
    function isPostgresDb(): bool
    {
        global $isPostgres;
        return (bool) $isPostgres;
    }
}

if ($isPostgres) {
    try {
        if ($databaseUrl) {
            $parsed = parse_url($databaseUrl);
            $pgHost = $parsed['host'] ?? 'localhost';
            $pgPort = $parsed['port'] ?? 5432;
            $pgUser = isset($parsed['user']) ? rawurldecode($parsed['user']) : 'neondb_owner';
            $pgPass = isset($parsed['pass']) ? rawurldecode($parsed['pass']) : '';
            $pgDb = isset($parsed['path']) ? ltrim($parsed['path'], '/') : 'neondb';
            $queryParams = [];
            if (!empty($parsed['query'])) {
                parse_str($parsed['query'], $queryParams);
            }
            $sslmode = $queryParams['sslmode'] ?? 'require';
        } else {
            $pgHost = envVal('DB_HOST', 'localhost');
            $pgPort = envVal('DB_PORT', '5432');
            $pgDb = envVal('DB_NAME', 'neondb');
            $pgUser = envVal('DB_USER', 'postgres');
            $pgPass = envVal('DB_PASS', '');
            $sslmode = envVal('DB_SSLMODE', 'require');
        }

        // Support Neon SNI endpoint parameter for older libpq clients if needed
        $dsn = "pgsql:host={$pgHost};port={$pgPort};dbname={$pgDb};sslmode={$sslmode}";
        if (str_contains($pgHost, '.neon.tech') && !str_contains($pgHost, '-pooler')) {
            $endpointId = explode('.', $pgHost)[0];
            $dsn .= ";options=endpoint%3D{$endpointId}";
        } elseif (str_contains($pgHost, '-pooler') && str_contains($pgHost, '.neon.tech')) {
            $endpointId = str_replace('-pooler', '', explode('.', $pgHost)[0]);
            $dsn .= ";options=endpoint%3D{$endpointId}";
        }

        $pdo = new NeonPostgresPDO(
            $dsn,
            $pgUser,
            $pgPass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    } catch (PDOException $e) {
        die("Neon PostgreSQL connection failed: " . $e->getMessage());
    }
} else {
    // Fallback to MySQL / MariaDB
    $db_host = envVal('DB_HOST', 'localhost');
    $db_port = envVal('DB_PORT', '3306');
    $db_name = envVal('DB_NAME', 'asset_management_db');
    $db_user = envVal('DB_USER', 'root');
    $db_pass = envVal('DB_PASS', '');

    try {
        $pdo = new PDO(
            "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4",
            $db_user,
            $db_pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }

    /**
     * Self-healing check for local XAMPP corrupted InnoDB tables (Error 1932 / 1813 / 1146)
     */
    function ensureDatabaseHealthy(PDO $pdo, string $dbName): void
    {
        $flagFile = __DIR__ . '/../database/.db_healthy_flag';
        if (file_exists($flagFile)) {
            try {
                $pdo->query("SELECT 1 FROM `service_requests` LIMIT 1");
                $pdo->query("SELECT 1 FROM `assets` LIMIT 1");
                return;
            } catch (PDOException $e) {
                @unlink($flagFile);
            }
        }

        $needsRepair = false;
        $existingTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $requiredTables = [
            'activity_logs', 'asset_categories', 'assets', 'committee_members',
            'committee_orders', 'inspection_group_members', 'inspection_groups',
            'inspection_items', 'inspection_projects', 'notifications',
            'procurement_items', 'procurements', 'service_requests',
            'system_settings', 'transfer_requests', 'users'
        ];

        foreach ($requiredTables as $reqTable) {
            if (!in_array($reqTable, $existingTables, true)) {
                $needsRepair = true;
                break;
            }
        }

        if (!$needsRepair) {
            foreach ($existingTables as $t) {
                try {
                    $pdo->query("SELECT 1 FROM `{$t}` LIMIT 1");
                } catch (PDOException $e) {
                    $needsRepair = true;
                    break;
                }
            }
        }

        if (!$needsRepair) {
            @file_put_contents($flagFile, date('Y-m-d H:i:s'));
            return;
        }

        @set_time_limit(300);
        $sqlFile = __DIR__ . '/../database/asset_management_db_2026-02-20.sql';
        if (!file_exists($sqlFile)) {
            return;
        }

        $dataDir = $pdo->query("SELECT @@datadir")->fetchColumn();
        $dbDir = $dataDir ? rtrim($dataDir, '/\\') . DIRECTORY_SEPARATOR . $dbName : null;

        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

        foreach ($existingTables as $t) {
            try {
                $pdo->query("SELECT 1 FROM `{$t}` LIMIT 1");
            } catch (PDOException $e) {
                try {
                    $pdo->exec("DROP TABLE IF EXISTS `{$t}`");
                } catch (PDOException $ignore) {
                }
                if ($dbDir && is_dir($dbDir)) {
                    @unlink($dbDir . DIRECTORY_SEPARATOR . $t . '.frm');
                    @unlink($dbDir . DIRECTORY_SEPARATOR . $t . '.ibd');
                }
            }
        }

        if ($dbDir && is_dir($dbDir)) {
            foreach (glob($dbDir . DIRECTORY_SEPARATOR . '*.ibd') ?: [] as $ibdPath) {
                $base = basename($ibdPath, '.ibd');
                if (!file_exists($dbDir . DIRECTORY_SEPARATOR . $base . '.frm')) {
                    @unlink($ibdPath);
                }
            }
        }

        $healthyTables = [];
        foreach ($pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN) as $t) {
            try {
                $pdo->query("SELECT 1 FROM `{$t}` LIMIT 1");
                $healthyTables[$t] = true;
            } catch (PDOException $e) {
            }
        }

        $lines = file($sqlFile);
        $inComment = false;
        $currentStmt = '';

        $pdo->exec("SET autocommit = 0");
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || strpos($trimmed, '--') === 0) {
                continue;
            }
            if (strpos($trimmed, '/*') === 0) {
                $inComment = true;
            }
            if ($inComment) {
                if (substr($trimmed, -2) === '*/') {
                    $inComment = false;
                }
                continue;
            }

            $currentStmt .= $line;
            if (substr($trimmed, -1) === ';') {
                $stmtSql = trim($currentStmt);
                $currentStmt = '';

                if (preg_match('/^DROP TABLE IF EXISTS `([^`]+)`/i', $stmtSql, $m)) {
                    $tbl = $m[1];
                    if (!isset($healthyTables[$tbl])) {
                        try {
                            $pdo->exec($stmtSql);
                        } catch (PDOException $e) {
                        }
                        if ($dbDir && is_dir($dbDir)) {
                            @unlink($dbDir . DIRECTORY_SEPARATOR . $tbl . '.frm');
                            @unlink($dbDir . DIRECTORY_SEPARATOR . $tbl . '.ibd');
                        }
                    }
                } elseif (preg_match('/^CREATE TABLE `([^`]+)`/i', $stmtSql, $m)) {
                    $tbl = $m[1];
                    if (!isset($healthyTables[$tbl])) {
                        try {
                            $pdo->exec($stmtSql);
                        } catch (PDOException $e) {
                            if ($dbDir && is_dir($dbDir)) {
                                @unlink($dbDir . DIRECTORY_SEPARATOR . $tbl . '.frm');
                                @unlink($dbDir . DIRECTORY_SEPARATOR . $tbl . '.ibd');
                            }
                            $pdo->exec($stmtSql);
                        }
                    }
                } elseif (preg_match('/^INSERT INTO `([^`]+)`/i', $stmtSql, $m)) {
                    $tbl = $m[1];
                    if (!isset($healthyTables[$tbl])) {
                        $pdo->exec($stmtSql);
                    }
                }
            }
        }
        $pdo->exec("COMMIT");
        $pdo->exec("SET autocommit = 1");

        if (!isset($healthyTables['line_settings'])) {
            if ($dbDir && is_dir($dbDir)) {
                @unlink($dbDir . DIRECTORY_SEPARATOR . 'line_settings.frm');
                @unlink($dbDir . DIRECTORY_SEPARATOR . 'line_settings.ibd');
            }
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `line_settings` (
                    `id` INT PRIMARY KEY DEFAULT 1,
                    `channel_access_token` TEXT DEFAULT NULL,
                    `group_id` VARCHAR(100) DEFAULT NULL,
                    `notify_enabled` TINYINT(1) NOT NULL DEFAULT 0,
                    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            $pdo->exec("INSERT IGNORE INTO `line_settings` (`id`, `notify_enabled`) VALUES (1, 0)");
        }

        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        @file_put_contents($flagFile, date('Y-m-d H:i:s'));
    }

    if ($db_host === 'localhost' || $db_host === '127.0.0.1') {
        ensureDatabaseHealthy($pdo, $db_name);
    }
}

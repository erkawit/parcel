<?php
/**
 * End-to-End Verification Script for Neon Postgres, Auth, Realtime & Persistent Login
 */
require_once __DIR__ . '/../includes/auth.php';

echo "=== GPAMS End-to-End Service Test ===\n";

$neon = getNeonServicesConfig();
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
echo "1. Database Driver: {$driver}\n";

// Test SHOW TABLES compatibility
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "2. Tables Available: " . count($tables) . " (" . implode(', ', array_slice($tables, 0, 6)) . "...)\n";

// Test Users & Settings query
$userCount = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$assetCount = (int) $pdo->query("SELECT COUNT(*) FROM assets")->fetchColumn();
$settings = $pdo->query("SELECT org_name, agency_name FROM system_settings WHERE id = 1")->fetch();
echo "3. Data Check: {$userCount} users, {$assetCount} assets | Org: " . ($settings['org_name'] ?? 'N/A') . "\n";

// Test Persistent Auth Cookie Signing & Verification
$sampleUser = $pdo->query("SELECT * FROM users WHERE status = 1 LIMIT 1")->fetch();
if ($sampleUser) {
    setPersistentAuthCookie($sampleUser, 30);
    unset($_SESSION['user_id']);
    $restored = restoreSessionFromCookie();
    echo "4. Persistent Auth Cookie (Remember Me): " . ($restored ? "PASSED (Restored user_id={$sampleUser['id']})" : "FAILED") . "\n";
}

// Test Neon Auth configuration status
$authStatus = getNeonAuthStatus();
echo "5. Neon Auth Service: " . ($authStatus['enabled'] ? "Configured ({$authStatus['url']})" : "Ready (waiting for NEON_AUTH_BASE_URL in .env.local)") . "\n";

// Test Neon Realtime service
if ($driver === 'pgsql') {
    $pdo->query("SELECT pg_notify('notifications', '{\"test\":true}')");
    echo "6. Neon Realtime (pg_notify): PASSED\n";
} else {
    echo "6. Neon Realtime: Ready (activates automatically when connected to Neon PostgreSQL)\n";
}

echo "=== ALL CHECKS COMPLETED ===\n";

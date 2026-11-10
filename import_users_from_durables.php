<?php
/**
 * Import Users from durables_db to asset_management_db
 * Data Mapping Script
 */

require_once __DIR__ . '/includes/auth.php';

// Connect to source database (durables_db)
try {
    $sourcePdo = new PDO(
        "mysql:host=localhost;dbname=durables_db;charset=utf8mb4",
        "root",
        "",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("Cannot connect to source database: " . $e->getMessage());
}

// Data mapping configuration
$roleMapping = [
    'admin' => 'admin',
    'director' => 'admin',
    'supply_head' => 'supply_officer',
    'supply_officer' => 'supply_officer',
    'head' => 'user',
    'user' => 'user'
];

$statusMapping = [
    'active' => 1,
    'inactive' => 0
];

// Section to Department mapping (ใช้ section เป็น department)
$sectionToDepartment = [
    'ฝ่ายบริหาร' => 'ฝ่ายบริหาร',
    'ฝ่ายพัสดุ' => 'ฝ่ายพัสดุ',
    'ฝ่ายการเงิน' => 'ฝ่ายการเงิน',
    'ฝ่ายทะเบียน' => 'ฝ่ายทะเบียน',
    'ฝ่ายวิชาการ' => 'ฝ่ายวิชาการ',
    'ฝ่ายกิจการนักเรียน' => 'ฝ่ายกิจการนักเรียน',
    'ฝ่ายประชาสัมพันธ์' => 'ฝ่ายประชาสัมพันธ์',
    'ฝ่ายอำนวยการ' => 'ฝ่ายอำนวยการ'
];

echo "=== Starting User Import from durables_db ===\n\n";

// Fetch users from source database
$stmt = $sourcePdo->query("SELECT * FROM users ORDER BY id");
$sourceUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($sourceUsers) . " users in source database\n\n";

$imported = 0;
$updated = 0;
$skipped = 0;
$errors = [];

foreach ($sourceUsers as $user) {
    try {
        // Build full_name from prefix + firstname + lastname
        $fullName = trim(
            ($user['prefix'] ?? '') . ' ' . 
            ($user['firstname'] ?? '') . ' ' . 
            ($user['lastname'] ?? '')
        );
        
        if (empty($fullName) || $fullName === '  ') {
            $fullName = $user['username'];
        }
        
        // Map role
        $role = $roleMapping[$user['role']] ?? 'user';
        
        // Map status
        $status = $statusMapping[$user['status']] ?? 1;
        
        // Map department (use section)
        $department = $user['section'] ?? null;
        if ($department && isset($sectionToDepartment[$department])) {
            $department = $sectionToDepartment[$department];
        }
        
        // Check if user already exists
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $checkStmt->execute([$user['username']]);
        $existingUser = $checkStmt->fetch();
        
        if ($existingUser) {
            // Update existing user
            $updateStmt = $pdo->prepare("
                UPDATE users 
                SET full_name = ?,
                    password = ?,
                    department = ?,
                    position = ?,
                    role = ?,
                    status = ?,
                    updated_at = NOW()
                WHERE username = ?
            ");
            
            $updateStmt->execute([
                $fullName,
                $user['password'], // Keep existing password hash
                $department,
                $user['position'],
                $role,
                $status,
                $user['username']
            ]);
            
            $updated++;
            echo "✓ Updated: {$user['username']} ({$fullName})\n";
        } else {
            // Insert new user
            $insertStmt = $pdo->prepare("
                INSERT INTO users (
                    username, 
                    password, 
                    full_name, 
                    department, 
                    position, 
                    role, 
                    status,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $insertStmt->execute([
                $user['username'],
                $user['password'], // Keep existing password hash
                $fullName,
                $department,
                $user['position'],
                $role,
                $status,
                $user['created_at']
            ]);
            
            $imported++;
            echo "✓ Imported: {$user['username']} ({$fullName})\n";
        }
        
    } catch (Exception $e) {
        $skipped++;
        $errors[] = "Error with user {$user['username']}: " . $e->getMessage();
        echo "✗ Error: {$user['username']} - " . $e->getMessage() . "\n";
    }
}

echo "\n=== Import Summary ===\n";
echo "Total users processed: " . count($sourceUsers) . "\n";
echo "Imported (new): $imported\n";
echo "Updated (existing): $updated\n";
echo "Skipped (errors): $skipped\n";

if (!empty($errors)) {
    echo "\n=== Errors ===\n";
    foreach ($errors as $error) {
        echo "- $error\n";
    }
}

echo "\n=== Data Mapping Information ===\n";
echo "Role Mapping:\n";
foreach ($roleMapping as $old => $new) {
    echo "  $old → $new\n";
}

echo "\nStatus Mapping:\n";
foreach ($statusMapping as $old => $new) {
    echo "  $old → " . ($new ? 'active (1)' : 'inactive (0)') . "\n";
}

echo "\nField Mapping:\n";
echo "  prefix + firstname + lastname → full_name\n";
echo "  section → department\n";
echo "  position → position (same)\n";
echo "  password → password (kept as-is)\n";

echo "\n=== Import Complete ===\n";

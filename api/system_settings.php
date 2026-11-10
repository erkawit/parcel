<?php
/**
 * System Settings API
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Ensure system_settings table exists
function ensureSettingsTable()
{
    global $pdo;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
            id INT PRIMARY KEY,
            org_name VARCHAR(255) DEFAULT 'ระบบบริหารจัดการครุภัณฑ์',
            org_name_en VARCHAR(255) DEFAULT 'Asset Management System',
            agency_name VARCHAR(255) DEFAUlT 'ชื่อหน่วยงานของคุณ',
            theme_color VARCHAR(50) DEFAULT 'blue',
            logo_path VARCHAR(255) DEFAULT 'img/default_logo.png',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");

        // Insert default if not exists
        $stmt = $pdo->query("SELECT COUNT(*) FROM system_settings WHERE id = 1");
        if ($stmt->fetchColumn() == 0) {
            $pdo->exec("INSERT INTO system_settings (id, org_name, org_name_en, agency_name, theme_color, logo_path) VALUES (1, 'ระบบบริหารจัดการครุภัณฑ์', 'Asset Management System', 'ชื่อหน่วยงานของคุณ', 'blue', 'img/default_logo.png')");
        } else {
            // Check if agency_name column exists (for migration)
            $colCheck = $pdo->query("SHOW COLUMNS FROM system_settings LIKE 'agency_name'");
            if ($colCheck->rowCount() == 0) {
                $pdo->exec("ALTER TABLE system_settings ADD COLUMN agency_name VARCHAR(255) DEFAULT 'ชื่อหน่วยงานของคุณ' AFTER org_name_en");
            }
        }
    } catch (PDOException $e) {
        // Log error or handle gracefully
        error_log("Settings Table Error: " . $e->getMessage());
    }
}

// GET Settings
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    ensureSettingsTable();
    $stmt = $pdo->query("SELECT * FROM system_settings WHERE id = 1");
    $settings = $stmt->fetch();

    if (!$settings) {
        // Paranoia: Force re-insert if fetch failed but table creation succeeded
        $pdo->exec("INSERT INTO system_settings (id, org_name, org_name_en, agency_name, theme_color, logo_path) VALUES (1, 'ระบบบริหารจัดการครุภัณฑ์', 'Asset Management System', 'ชื่อหน่วยงานของคุณ', 'blue', 'img/default_logo.png')");
        $stmt = $pdo->query("SELECT * FROM system_settings WHERE id = 1");
        $settings = $stmt->fetch();
    }

    echo json_encode(['success' => true, 'settings' => $settings]);
    exit;
}

// POST Update Settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ensureSettingsTable();
    requireRole(['admin']); // Only admin can change settings

    $data = $_POST; // Using $_POST for potential file upload (multipart/form-data)

    $orgName = $data['org_name'] ?? '';
    $orgNameEn = $data['org_name_en'] ?? '';
    $agencyName = $data['agency_name'] ?? '';
    $themeColor = $data['theme_color'] ?? 'blue';

    // Handle Logo Upload
    $logoPath = null;
    if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $filename = $_FILES['logo_file']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($ext, $allowed)) {
            $uploadDir = __DIR__ . '/../img/';
            if (!is_dir($uploadDir))
                mkdir($uploadDir, 0777, true);

            $newFilename = 'system_logo_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['logo_file']['tmp_name'], $uploadDir . $newFilename)) {
                $logoPath = 'img/' . $newFilename;
                @copy($uploadDir . $newFilename, __DIR__ . '/../favicon.ico');
            }
        }
    }

    try {
        $sql = "UPDATE system_settings SET org_name = ?, org_name_en = ?, agency_name = ?, theme_color = ?";
        $params = [$orgName, $orgNameEn, $agencyName, $themeColor];

        if ($logoPath) {
            $sql .= ", logo_path = ?";
            $params[] = $logoPath;
        }

        $sql .= " WHERE id = 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        echo json_encode(['success' => true, 'logo_path' => $logoPath]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to update settings']);
    }
    exit;
}

<?php
/**
 * Service Request API
 * Handle Repair & Purchase Requests
 */

// Prevent any output before JSON
ob_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

use PhpOffice\PhpWord\TemplateProcessor;

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../includes/auth.php';
    require_once __DIR__ . '/../vendor/autoload.php';

    // API Authentication Check
    if (!function_exists('isLoggedIn')) {
        require_once __DIR__ . '/../includes/auth.php'; // Just in case
    }

    if (!isLoggedIn()) {
        ob_clean();
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบใหม่ (Session Expired)', 'error' => 'unauthorized']);
        exit;
    }

    // Ensure tables and columns exist
    function ensureSchema()
    {
        global $pdo;

        // Service Requests Table
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS service_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                request_type ENUM('repair', 'purchase') NOT NULL,
                asset_id INT DEFAULT NULL,
                asset_name VARCHAR(255),
                asset_description TEXT,
                details TEXT,
                status VARCHAR(50) DEFAULT 'pending',
                file_path VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )");

            // Add columns for Purchase Request
            $cols = $pdo->query("SHOW COLUMNS FROM service_requests");
            $existingCols = $cols->fetchAll(PDO::FETCH_COLUMN);

            if (!in_array('asset_volume', $existingCols)) {
                $pdo->exec("ALTER TABLE service_requests ADD COLUMN asset_volume INT DEFAULT NULL AFTER details");
            }
            if (!in_array('type_asset', $existingCols)) {
                $pdo->exec("ALTER TABLE service_requests ADD COLUMN type_asset INT DEFAULT NULL AFTER asset_volume");
            }
            if (!in_array('details_buy', $existingCols)) {
                $pdo->exec("ALTER TABLE service_requests ADD COLUMN details_buy TEXT DEFAULT NULL AFTER type_asset");
            }

        } catch (PDOException $e) { /* Ignore */
        }

        // Check users columns
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'position'");
            if ($stmt->rowCount() == 0) {
                $pdo->exec("ALTER TABLE users ADD COLUMN position VARCHAR(100) AFTER department");
            }
            $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'position_level'");
            if ($stmt->rowCount() == 0) {
                $pdo->exec("ALTER TABLE users ADD COLUMN position_level VARCHAR(100) AFTER position");
            }
        } catch (PDOException $e) { /* Ignore */
        }
    }

    ensureSchema();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_GET['action']) && $_GET['action'] === 'get' && isset($_GET['id'])) {
            $stmt = $pdo->prepare("SELECT * FROM service_requests WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            $req = $stmt->fetch();

            ob_clean(); // Clean any previous output
            if ($req) {
                echo json_encode(['success' => true, 'data' => $req]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Not found']);
            }
        } else {
            // Default response for GET
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? 'create';
        $userId = $_SESSION['user_id'];
        global $currentUser;

        // Re-fetch current user to ensure role is up to date (though requireLogin handles session)
        if (!isset($currentUser)) {
            $currentUser = getCurrentUser();
            if (!$currentUser) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'User not found']);
                exit;
            }
        }

        if ($action === 'delete') {
            $id = $_POST['id'];
            // Get file path to delete
            $stmt = $pdo->prepare("SELECT file_path, user_id FROM service_requests WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();

            if ($row) {
                // Verify ownership or admin
                if ($row['user_id'] != $userId && $currentUser['role'] !== 'admin') {
                    ob_clean();
                    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                    exit;
                }

                if ($row['file_path'] && file_exists('../' . $row['file_path'])) {
                    @unlink('../' . $row['file_path']);
                }
                $pdo->prepare("DELETE FROM service_requests WHERE id = ?")->execute([$id]);

                ob_clean();
                echo json_encode(['success' => true]);
            } else {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Not found']);
            }
            exit;
        }

        // Common Data
        $requestType = $_POST['request_type'] ?? 'repair';
        $assetId = !empty($_POST['asset_id']) ? $_POST['asset_id'] : null;
        $assetName = $_POST['asset_name'] ?? '';
        $assetDesc = $_POST['asset_description'] ?? '';

        // Initialize Variables
        $details = '';
        $detailsBuy = null;
        $assetVolume = null;
        $typeAssetId = null;
        $unitName = '';

        if ($requestType === 'repair') {
            $details = $_POST['details_maintain'] ?? '';
            $templateFile = '../templates/template_request_maintain.docx';
        } else {
            // Purchase
            $details = $_POST['details'] ?? ''; // Reason
            $detailsBuy = $_POST['details_buy'] ?? ''; // Extra Details
            $assetVolume = $_POST['asset_volume'] ?? null;
            $typeAssetId = $_POST['type_asset'] ?? null;

            // Fetch Unit Name
            if ($typeAssetId) {
                $stmt = $pdo->prepare("SELECT type_volume FROM type_volume WHERE id = ?");
                $stmt->execute([$typeAssetId]);
                $unitName = $stmt->fetchColumn() ?: '';
            }
            $templateFile = '../templates/template_request_buy.docx';
        }

        // Generate Word File Logic (Shared for Create/Update)
        // fetch User Data
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        // fetch Agency Name
        $stmt = $pdo->query("SELECT agency_name FROM system_settings WHERE id = 1");
        $settings = $stmt->fetch();
        $agencyName = $settings ? ($settings['agency_name'] ?? 'ชื่อหน่วยงานของคุณ') : 'ชื่อหน่วยงานของคุณ';

        // fetch Director
        $stmt = $pdo->query("SELECT full_name FROM users WHERE position LIKE '%ผู้อำนวยการ%' LIMIT 1");
        $director = $stmt->fetchColumn();
        if (!$director)
            $director = '......................................................';

        if (!file_exists($templateFile)) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Template file not found: ' . basename($templateFile)]);
            exit;
        }


        $templateProcessor = new TemplateProcessor($templateFile);

        // Prepare Variables
        $thaiDate = formatThaiDate(date('Y-m-d'));
        $position = $user['position'] ?? '';
        $positionLevel = $user['position_level'] ?? '';

        // Common Replacements
        $templateProcessor->setValue('court', $agencyName);
        $templateProcessor->setValue('date_create', $thaiDate);
        $templateProcessor->setValue('department', $user['department'] ?? '');
        $templateProcessor->setValue('full_name', $user['full_name']);
        $templateProcessor->setValue('fullname', $user['full_name']);
        $templateProcessor->setValue('position', $position);
        $templateProcessor->setValue('position_level', $positionLevel ? ' ' . $positionLevel : '');
        $templateProcessor->setValue('asset_name', $assetName);
        $templateProcessor->setValue('asset_description', $assetDesc ? ' ' . $assetDesc : '');
        $templateProcessor->setValue('director', $director);

        // Specific Replacements
        if ($requestType === 'repair') {
            $templateProcessor->setValue('details_maintain', $details);
        } else {
            // Purchase variables
            $templateProcessor->setValue('details', $details); // Reason
            $templateProcessor->setValue('details_buy', $detailsBuy); // Extra Details
            $templateProcessor->setValue('asset_volume', $assetVolume);
            $templateProcessor->setValue('type_asset', $unitName);
        }

        // Save File
        // Make sure directory exists
        if (!is_dir('../templates/word_jobs')) {
            mkdir('../templates/word_jobs', 0777, true);
        }

        $fileName = $user['username'] . '_' . date('dmY_Hi') . '.docx';
        $savePath = '../templates/word_jobs/' . $fileName;

        $templateProcessor->saveAs($savePath);
        $dbFilePath = 'templates/word_jobs/' . $fileName;

        if ($action === 'create') {
            // Save DB Record
            $stmt = $pdo->prepare("INSERT INTO service_requests 
                (user_id, request_type, asset_id, asset_name, asset_description, details, details_buy, asset_volume, type_asset, file_path, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");

            $stmt->execute([
                $userId,
                $requestType,
                $assetId,
                $assetName,
                $assetDesc,
                $details,
                $detailsBuy,
                $assetVolume,
                $typeAssetId, // Store ID in DB for reference, though Word used Name
                $dbFilePath
            ]);

            ob_clean();
            echo json_encode(['success' => true, 'file_url' => $dbFilePath]);

        } elseif ($action === 'update') {
            $id = $_POST['id'];
            // Check ownership
            $stmt = $pdo->prepare("SELECT user_id, file_path FROM service_requests WHERE id = ?");
            $stmt->execute([$id]);
            $existing = $stmt->fetch();

            if (!$existing || ($existing['user_id'] != $userId && $currentUser['role'] !== 'admin')) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }

            // Delete old file
            if ($existing['file_path'] && file_exists('../' . $existing['file_path'])) {
                @unlink('../' . $existing['file_path']);
            }

            $stmt = $pdo->prepare("UPDATE service_requests SET 
                request_type=?, asset_id=?, asset_name=?, asset_description=?, details=?, details_buy=?, asset_volume=?, type_asset=?, file_path=?, updated_at=NOW()
                WHERE id=?");
            $stmt->execute([
                $requestType,
                $assetId,
                $assetName,
                $assetDesc,
                $details,
                $detailsBuy,
                $assetVolume,
                $typeAssetId,
                $dbFilePath,
                $id
            ]);

            ob_clean();
            echo json_encode(['success' => true, 'file_url' => $dbFilePath]);
        }
    }
} catch (Throwable $e) {
    ob_end_clean(); // Discard any output generated before exception
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
}
// Final catch-all to ensure we don't just die silently if something slipped through
exit;

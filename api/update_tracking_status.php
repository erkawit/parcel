<?php
/**
 * Update Tracking Status API
 */
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// Authorization Logic
$canUpdate = false;
$currentUser = getCurrentUser();

// Check if reporter_name is provided (Public Mode)
if (!empty($_POST['reporter_name'])) {
    $canUpdate = true;
}
// Check if logged in and has role (Admin Mode)
elseif ($currentUser && in_array($currentUser['role'], ['admin', 'supply_officer', 'department_head', 'director'])) {
    $canUpdate = true;
}

if (!$canUpdate) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $status = $_POST['status'] ?? null;
    $note = $_POST['note'] ?? '';
    $reporterName = $_POST['reporter_name'] ?? '';

    if (!$id || !$status) {
        echo json_encode(['success' => false, 'message' => 'Missing ID or Status']);
        exit;
    }

    // Append Reporter Name to Note if public update
    if (!empty($reporterName)) {
        $note = "[ผู้รายงาน: $reporterName] " . $note;
    }

    try {
        // Ensure admin_note column exists
        try {
            $check = $pdo->query("SHOW COLUMNS FROM service_requests LIKE 'admin_note'");
            if ($check->rowCount() == 0) {
                $pdo->exec("ALTER TABLE service_requests ADD COLUMN admin_note TEXT DEFAULT NULL AFTER status");
            }
        } catch (PDOException $e) { /* Ignore */
        }

        // Update
        $stmt = $pdo->prepare("UPDATE service_requests SET status = ?, admin_note = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $note, $id]);

        // Log Activity
        $logUserId = $currentUser ? $currentUser['id'] : 0; // 0 or System User ID
        $logDesc = "Updated request #$id status to $status";
        if (!empty($reporterName)) {
            $logDesc .= " by $reporterName";
        }

        // Custom log function might fail if foreign key constraint on user_id exists and 0 is not valid
        // Check if user 0 exists or use NULL if allowed, or just skip log if no user
        if ($logUserId) {
            logActivity($logUserId, 'update_request', $logDesc);
        }

        echo json_encode(['success' => true]);

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

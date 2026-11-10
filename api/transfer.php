<?php
/**
 * Transfer API
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'list_pending_acceptance') {
        $userId = $_SESSION['user_id'];
        $isManager = hasRole(['admin', 'supply_officer']);

        // Users see transfers sent to them. Managers see transfers to location/supply.
        $sql = "SELECT tr.*, a.name as asset_name, a.asset_code, u.full_name as from_name, 
                CASE 
                    WHEN tr.transfer_type = 'supply' AND tr.reason LIKE 'โอนไปที่ตั้ง:%' THEN 'location'
                    ELSE tr.transfer_type 
                END as display_type
                FROM transfer_requests tr
                JOIN assets a ON tr.asset_id = a.id
                JOIN users u ON tr.from_user_id = u.id
                WHERE tr.status = 'pending' AND (
                    (tr.transfer_type = 'user' AND tr.to_user_id = ?)
                    OR (tr.transfer_type = 'supply' AND ?)
                )
                ORDER BY tr.created_at ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $isManager ? 1 : 0]);
        echo json_encode(['success' => true, 'transfers' => $stmt->fetchAll()]);
        exit;
    }

    if ($action === 'list_my_requests') {
        $userId = $_SESSION['user_id'];
        // Get active requests (pending) or recently approved/rejected (last 7 days?)
        // User wants notification until all are done.
        // We fetch everything pending, plus approved/rejected ones that are recent to show status.
        // Actually, fetching all recent ones is safer.
        $stmt = $pdo->prepare("SELECT tr.*, a.name as asset_name, u.full_name as to_name
                               FROM transfer_requests tr
                               JOIN assets a ON tr.asset_id = a.id
                               LEFT JOIN users u ON tr.to_user_id = u.id
                               WHERE tr.from_user_id = ? 
                               AND (tr.status = 'pending' OR tr.updated_at > DATE_SUB(NOW(), INTERVAL 7 DAY))
                               ORDER BY tr.created_at DESC");
        $stmt->execute([$userId]);
        echo json_encode(['success' => true, 'transfers' => $stmt->fetchAll()]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $data['action'] ?? '';

    if ($action === 'create') {
        try {
            $transferType = $data['transfer_type'];
            $toUserId = $data['to_user_id'] ?? null;
            $newLocation = $data['new_location'] ?? null;

            // Store new location in reason field for location transfers
            $reason = $data['reason'] ?? '';
            if ($transferType === 'supply' && $newLocation) {
                $reason = "โอนไปที่ตั้ง: " . $newLocation . ($reason ? "\n" . $reason : '');
            }

            $stmt = $pdo->prepare("INSERT INTO transfer_requests (asset_id, from_user_id, to_user_id, transfer_type, reason) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['asset_id'],
                $_SESSION['user_id'],
                $toUserId,
                $transferType,
                $reason
            ]);

            // Send notification
            $stmtAsset = $pdo->prepare("SELECT name FROM assets WHERE id = ?");
            $stmtAsset->execute([$data['asset_id']]);
            $asset = $stmtAsset->fetchColumn();
            $fromName = $_SESSION['user_name'];

            // Notify supply officers and admins
            $officers = $pdo->query("SELECT id FROM users WHERE role IN ('admin','supply_officer') AND status = 1")->fetchAll();
            foreach ($officers as $officer) {
                $notifMsg = $transferType === 'supply' && $newLocation
                    ? "{$fromName} ขอโอนย้าย \"{$asset}\" ไปยัง {$newLocation}"
                    : "{$fromName} ขอโอนย้าย \"{$asset}\"";

                sendNotification(
                    $officer['id'],
                    'คำขอโอนย้ายครุภัณฑ์',
                    $notifMsg,
                    'transfer.php',
                    'info'
                );
            }

            // Notify target user if person transfer
            if ($toUserId) {
                sendNotification(
                    $toUserId,
                    'ครุภัณฑ์ถูกโอนมาให้คุณ',
                    "{$fromName} ต้องการโอน \"{$asset}\" ให้คุณ",
                    'transfer.php',
                    'info'
                );
            }

            logActivity($_SESSION['user_id'], 'transfer_request', "ส่งคำขอโอนย้าย: {$asset}");
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'ไม่สามารถส่งคำขอได้']);
        }
        exit;
    }

    if ($action === 'approve') {
        // requireRole(['admin', 'supply_officer']); // Removed strict check

        $id = intval($data['id'] ?? 0);
        $stmtTr = $pdo->prepare("SELECT * FROM transfer_requests WHERE id = ?");
        $stmtTr->execute([$id]);
        $transfer = $stmtTr->fetch();

        if (!$transfer) {
            echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูล']);
            exit;
        }

        $isAuthorized = false;
        if (hasRole(['admin', 'supply_officer'])) {
            $isAuthorized = true;
        } elseif ($transfer['transfer_type'] === 'user' && $transfer['to_user_id'] == $_SESSION['user_id']) {
            $isAuthorized = true;
        }

        if (!$isAuthorized) {
            echo json_encode(['success' => false, 'message' => 'ไม่มีสิทธิ์อนุมัติ']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("UPDATE transfer_requests SET status='approved', approved_by=?, approved_at=NOW() WHERE id=?");
            $stmt->execute([$_SESSION['user_id'], $id]);

            // Get transfer info and update asset
            $stmtTr = $pdo->prepare("SELECT * FROM transfer_requests WHERE id = ?");
            $stmtTr->execute([$id]);
            $transfer = $stmtTr->fetch();

            if ($transfer) {
                if ($transfer['transfer_type'] === 'user' && $transfer['to_user_id']) {
                    // Transfer to person
                    $pdo->prepare("UPDATE assets SET holder_id = ? WHERE id = ?")->execute([$transfer['to_user_id'], $transfer['asset_id']]);
                } elseif ($transfer['transfer_type'] === 'supply') {
                    // Transfer to location or supply
                    $pdo->prepare("UPDATE assets SET holder_id = NULL WHERE id = ?")->execute([$transfer['asset_id']]);

                    // Extract and update location if specified
                    if ($transfer['reason'] && preg_match('/โอนไปที่ตั้ง:\s*(.+?)(?:\n|$)/', $transfer['reason'], $matches)) {
                        $newLocation = trim($matches[1]);
                        $pdo->prepare("UPDATE assets SET location = ? WHERE id = ?")->execute([$newLocation, $transfer['asset_id']]);
                    }
                } elseif ($transfer['transfer_type'] === 'it') {
                    // Transfer to IT
                    $pdo->prepare("UPDATE assets SET holder_id = NULL WHERE id = ?")->execute([$transfer['asset_id']]);
                }
            }

            // Notify requester
            sendNotification(
                $transfer['from_user_id'],
                'คำขอโอนย้ายได้รับอนุมัติ',
                'คำขอโอนย้ายของคุณได้รับการอนุมัติแล้ว',
                'transfer.php',
                'success'
            );

            logActivity($_SESSION['user_id'], 'transfer_approve', "อนุมัติโอนย้าย ID: {$data['id']}");
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'ไม่สามารถอนุมัติได้']);
        }
        exit;
    }

    if ($action === 'reject') {
        // requireRole(['admin', 'supply_officer']); // Removed strict check

        $id = intval($data['id'] ?? 0);
        $stmtTr = $pdo->prepare("SELECT * FROM transfer_requests WHERE id = ?");
        $stmtTr->execute([$id]);
        $transfer = $stmtTr->fetch();

        if (!$transfer) {
            echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูล']);
            exit;
        }

        $isAuthorized = false;
        if (hasRole(['admin', 'supply_officer'])) {
            $isAuthorized = true;
        } elseif ($transfer['transfer_type'] === 'user' && $transfer['to_user_id'] == $_SESSION['user_id']) {
            $isAuthorized = true;
        }

        if (!$isAuthorized) {
            echo json_encode(['success' => false, 'message' => 'ไม่มีสิทธิ์ปฏิเสธ']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("UPDATE transfer_requests SET status='rejected', approved_by=?, approved_at=NOW(), notes=? WHERE id=?");
            $stmt->execute([$_SESSION['user_id'], $data['notes'] ?? '', $id]);

            $stmtTr = $pdo->prepare("SELECT from_user_id FROM transfer_requests WHERE id = ?");
            $stmtTr->execute([$id]);
            $transfer = $stmtTr->fetch();
            sendNotification(
                $transfer['from_user_id'],
                'คำขอโอนย้ายถูกปฏิเสธ',
                'คำขอโอนย้ายของคุณถูกปฏิเสธ: ' . ($data['notes'] ?? ''),
                'transfer.php',
                'danger'
            );

            logActivity($_SESSION['user_id'], 'transfer_reject', "ปฏิเสธโอนย้าย ID: {$data['id']}");
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'ไม่สามารถปฏิเสธได้']);
        }
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);

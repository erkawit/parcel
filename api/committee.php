<?php
/**
 * Committee API
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

// GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'members') {
        $orderId = $_GET['order_id'] ?? 0;
        $stm = $pdo->prepare("SELECT cm.role, u.id as user_id, u.full_name, u.department, u.position 
                               FROM committee_members cm JOIN users u ON cm.user_id = u.id 
                               WHERE cm.order_id = ? 
                               ORDER BY FIELD(cm.role, 'chairman', 'member', 'secretary'), u.full_name");
        $stm->execute([$orderId]);
        echo json_encode(['success' => true, 'members' => $stm->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action === 'get') {
        $orderId = $_GET['id'] ?? 0;
        $stm = $pdo->prepare("SELECT * FROM committee_orders WHERE id = ?");
        $stm->execute([$orderId]);
        $order = $stm->fetch(PDO::FETCH_ASSOC);

        if ($order) {
            // Get members
            $stmMem = $pdo->prepare("SELECT user_id, role FROM committee_members WHERE order_id = ?");
            $stmMem->execute([$orderId]);
            $order['members'] = $stmMem->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'order' => $order]);
        } else {
            echo json_encode(['success' => false, 'message' => 'ไม่พบคำสั่ง']);
        }
        exit;
    }

    if ($action === 'latest_members') {
        $stm = $pdo->query("SELECT id, fiscal_year, order_number FROM committee_orders ORDER BY fiscal_year DESC, id DESC LIMIT 1");
        $latestOrder = $stm->fetch(PDO::FETCH_ASSOC);
        if ($latestOrder) {
            $stmMem = $pdo->prepare("SELECT user_id, role FROM committee_members WHERE order_id = ? ORDER BY FIELD(role, 'chairman', 'member', 'secretary'), id");
            $stmMem->execute([$latestOrder['id']]);
            echo json_encode([
                'success' => true,
                'order' => $latestOrder,
                'members' => $stmMem->fetchAll(PDO::FETCH_ASSOC)
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'ยังไม่มีคำสั่งแต่งตั้งก่อนหน้าในระบบ']);
        }
        exit;
    }
}

// POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireRole(['admin', 'supply_officer']);

    // Handle file upload (FormData) or JSON body
    $jsonData = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $_POST['action'] ?? ($jsonData['action'] ?? '');

    if ($action === 'create') {
        try {
            $pdo->beginTransaction();

            // Handle PDF upload
            $pdfPath = null;
            if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['pdf_file']['name'], PATHINFO_EXTENSION));
                if ($ext !== 'pdf')
                    throw new Exception('อนุญาตเฉพาะไฟล์ PDF');
                $filename = 'order_' . time() . '_' . uniqid() . '.pdf';
                $uploadDir = __DIR__ . '/../uploads/committee_orders/';
                if (!is_dir($uploadDir))
                    mkdir($uploadDir, 0755, true);
                move_uploaded_file($_FILES['pdf_file']['tmp_name'], $uploadDir . $filename);
                $pdfPath = 'uploads/committee_orders/' . $filename;
            }

            // Insert order
            $stmt = $pdo->prepare("INSERT INTO committee_orders (fiscal_year, order_number, order_date, pdf_file, created_by) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['fiscal_year'],
                $_POST['order_number'] ?: null,
                $_POST['order_date'] ?: null,
                $pdfPath,
                $_SESSION['user_id']
            ]);
            $orderId = $pdo->lastInsertId();

            // Insert members (deduplicate by user_id)
            $members = json_decode($_POST['members'] ?? '[]', true);
            $seenUsers = [];
            $stmtMember = $pdo->prepare("INSERT INTO committee_members (order_id, user_id, role) VALUES (?, ?, ?)");
            foreach ($members as $m) {
                if (empty($m['user_id']) || isset($seenUsers[$m['user_id']])) {
                    continue;
                }
                $seenUsers[$m['user_id']] = true;
                $stmtMember->execute([$orderId, $m['user_id'], $m['role']]);

                // Send notification
                $roleLabels = ['chairman' => 'ประธานกรรมการ', 'member' => 'กรรมการ', 'secretary' => 'เลขานุการ'];
                sendNotification(
                    $m['user_id'],
                    'แต่งตั้งคณะกรรมการตรวจสอบ',
                    "คุณได้รับการแต่งตั้งเป็น{$roleLabels[$m['role']]} ปีงบ {$_POST['fiscal_year']}",
                    'inspection.php',
                    'info'
                );
            }

            $pdo->commit();
            logActivity($_SESSION['user_id'], 'committee_create', "สร้างคำสั่งแต่งตั้ง ปี {$_POST['fiscal_year']}");
            echo json_encode(['success' => true, 'id' => $orderId]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'update') {
        try {
            $pdo->beginTransaction();
            $orderId = $_POST['id'];

            // Handle PDF upload (optional update)
            $pdfPath = null;
            if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['pdf_file']['name'], PATHINFO_EXTENSION));
                if ($ext !== 'pdf')
                    throw new Exception('อนุญาตเฉพาะไฟล์ PDF');
                $filename = 'order_' . time() . '_' . uniqid() . '.pdf';
                $uploadDir = __DIR__ . '/../uploads/committee_orders/';
                if (!is_dir($uploadDir))
                    mkdir($uploadDir, 0755, true);
                move_uploaded_file($_FILES['pdf_file']['tmp_name'], $uploadDir . $filename);
                $pdfPath = 'uploads/committee_orders/' . $filename;
            }

            // Update order
            $sql = "UPDATE committee_orders SET fiscal_year=?, order_number=?, order_date=? " . ($pdfPath ? ", pdf_file=?" : "") . " WHERE id=?";
            $params = [
                $_POST['fiscal_year'],
                $_POST['order_number'] ?: null,
                $_POST['order_date'] ?: null
            ];
            if ($pdfPath)
                $params[] = $pdfPath;
            $params[] = $orderId;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            // Get existing members before clearing so we only notify newly added members
            $oldMemStmt = $pdo->prepare("SELECT user_id FROM committee_members WHERE order_id = ?");
            $oldMemStmt->execute([$orderId]);
            $oldUserIds = array_flip($oldMemStmt->fetchAll(PDO::FETCH_COLUMN));

            // Update members (Clear existing and re-insert deduplicated)
            $pdo->prepare("DELETE FROM committee_members WHERE order_id = ?")->execute([$orderId]);

            $members = json_decode($_POST['members'] ?? '[]', true);
            $seenUsers = [];
            $roleLabels = ['chairman' => 'ประธานกรรมการ', 'member' => 'กรรมการ', 'secretary' => 'เลขานุการ'];
            $stmtMember = $pdo->prepare("INSERT INTO committee_members (order_id, user_id, role) VALUES (?, ?, ?)");
            foreach ($members as $m) {
                if (empty($m['user_id']) || isset($seenUsers[$m['user_id']])) {
                    continue;
                }
                $seenUsers[$m['user_id']] = true;
                $stmtMember->execute([$orderId, $m['user_id'], $m['role']]);

                if (!isset($oldUserIds[$m['user_id']])) {
                    sendNotification(
                        $m['user_id'],
                        'แต่งตั้งคณะกรรมการตรวจสอบ',
                        "คุณได้รับการแต่งตั้งเป็น{$roleLabels[$m['role']]} ปีงบ {$_POST['fiscal_year']}",
                        'inspection.php',
                        'info'
                    );
                }
            }

            $pdo->commit();
            logActivity($_SESSION['user_id'], 'committee_update', "แก้ไขคำสั่งแต่งตั้ง ID: {$orderId}");
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'toggle_status') {
        try {
            $orderId = $jsonData['id'] ?? ($_POST['id'] ?? 0);
            $newStatus = $jsonData['status'] ?? ($_POST['status'] ?? 'active');
            if (!in_array($newStatus, ['active', 'cancelled'])) {
                $newStatus = 'active';
            }
            $stmt = $pdo->prepare("UPDATE committee_orders SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $orderId]);
            logActivity($_SESSION['user_id'], 'committee_status', "เปลี่ยนสถานะคำสั่ง ID: {$orderId} เป็น {$newStatus}");
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'delete') {
        try {
            $orderId = $jsonData['id'] ?? ($_POST['id'] ?? 0);
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE inspection_projects SET committee_order_id = NULL WHERE committee_order_id = ?")->execute([$orderId]);
            $pdo->prepare("DELETE FROM committee_members WHERE order_id = ?")->execute([$orderId]);
            $pdo->prepare("DELETE FROM committee_orders WHERE id = ?")->execute([$orderId]);
            $pdo->commit();
            logActivity($_SESSION['user_id'], 'committee_delete', "ลบคำสั่งแต่งตั้ง ID: {$orderId}");
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'upload_meeting_report') {
        try {
            $orderId = $_POST['order_id'] ?? 0;

            // Validate order exists
            $stmt = $pdo->prepare("SELECT id FROM committee_orders WHERE id = ?");
            $stmt->execute([$orderId]);
            if (!$stmt->fetch()) {
                throw new Exception('ไม่พบคำสั่งแต่งตั้ง');
            }

            // Handle PDF upload
            if (!isset($_FILES['meeting_report_file']) || $_FILES['meeting_report_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('กรุณาเลือกไฟล์');
            }

            $file = $_FILES['meeting_report_file'];

            // Validate file type
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($ext !== 'pdf') {
                throw new Exception('อนุญาตเฉพาะไฟล์ PDF');
            }

            // Validate file size (10MB)
            if ($file['size'] > 10 * 1024 * 1024) {
                throw new Exception('ขนาดไฟล์ต้องไม่เกิน 10 MB');
            }

            // Create upload directory
            $uploadDir = __DIR__ . '/../uploads/committee_reports/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Generate unique filename
            $filename = 'meeting_report_' . $orderId . '_' . time() . '_' . uniqid() . '.pdf';
            $uploadPath = $uploadDir . $filename;
            $dbPath = 'uploads/committee_reports/' . $filename;

            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                throw new Exception('ไม่สามารถอัปโหลดไฟล์ได้');
            }

            // Update database
            $stmt = $pdo->prepare("UPDATE committee_orders 
                                   SET meeting_report_file = ?, 
                                       meeting_report_uploaded_at = NOW(), 
                                       meeting_report_uploaded_by = ? 
                                   WHERE id = ?");
            $stmt->execute([$dbPath, $_SESSION['user_id'], $orderId]);

            logActivity($_SESSION['user_id'], 'committee_report_upload', "อัปโหลดรายงานสรุปการประชุม ID: {$orderId}");

            echo json_encode(['success' => true, 'file_path' => $dbPath]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);

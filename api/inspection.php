<?php
/**
 * Inspection API
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

// GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'project_imported_ids') {
        $projectId = $_GET['project_id'] ?? 0;
        $stmt = $pdo->prepare("SELECT asset_id FROM inspection_items WHERE project_id = ?");
        $stmt->execute([$projectId]);
        echo json_encode(['success' => true, 'asset_ids' => $stmt->fetchAll(PDO::FETCH_COLUMN)]);
        exit;
    }

    if ($action === 'project_summary') {
        $projectId = $_GET['project_id'] ?? 0;
        $stmt = $pdo->prepare("SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'normal' THEN 1 ELSE 0 END) as normal,
            SUM(CASE WHEN status = 'damaged' THEN 1 ELSE 0 END) as damaged,
            SUM(CASE WHEN status = 'deteriorated' THEN 1 ELSE 0 END) as deteriorated,
            SUM(CASE WHEN status = 'not_found' THEN 1 ELSE 0 END) as not_found
            FROM inspection_items WHERE project_id = ?");
        $stmt->execute([$projectId]);
        echo json_encode(['success' => true, 'summary' => $stmt->fetch(PDO::FETCH_ASSOC)]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check for file upload (FormData)
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
    } else {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $action = $data['action'] ?? '';
    }

    // Create inspection project (with optional 1-click auto import & auto start)
    if ($action === 'create_project') {
        requireRole(['admin', 'supply_officer']);
        try {
            $pdo->beginTransaction();
            $initialStatus = !empty($data['auto_start']) ? 'in_progress' : 'draft';
            $stmt = $pdo->prepare("INSERT INTO inspection_projects (name, fiscal_year, committee_order_id, start_date, end_date, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['name'],
                $data['fiscal_year'],
                $data['committee_order_id'] ?: null,
                $data['start_date'] ?: null,
                $data['end_date'] ?: null,
                $initialStatus,
                $_SESSION['user_id']
            ]);
            $projectId = $pdo->lastInsertId();

            $importedCount = 0;
            if (!empty($data['auto_import_active'])) {
                $impStmt = $pdo->prepare("INSERT INTO inspection_items (project_id, asset_id)
                                          SELECT ?, id FROM assets WHERE status = 'active'");
                $impStmt->execute([$projectId]);
                $importedCount = $impStmt->rowCount();
            }

            // Notify committee members if linked to a committee order
            if (!empty($data['committee_order_id'])) {
                $cmStmt = $pdo->prepare("SELECT user_id FROM committee_members WHERE order_id = ?");
                $cmStmt->execute([$data['committee_order_id']]);
                foreach ($cmStmt->fetchAll(PDO::FETCH_COLUMN) as $memberId) {
                    sendNotification(
                        $memberId,
                        'โครงการตรวจสอบครุภัณฑ์ใหม่',
                        "โครงการ \"{$data['name']}\" พร้อมสำหรับการตรวจสอบแล้ว",
                        "inspection_check.php?project={$projectId}",
                        'info'
                    );
                }
            }

            $pdo->commit();
            logActivity($_SESSION['user_id'], 'inspection_create', "สร้างโครงการตรวจสอบ: {$data['name']} (นำเข้า {$importedCount} รายการ)");
            echo json_encode(['success' => true, 'id' => $projectId, 'imported_count' => $importedCount]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // Update inspection project info
    if ($action === 'update_project') {
        requireRole(['admin', 'supply_officer']);
        try {
            $stmt = $pdo->prepare("UPDATE inspection_projects SET name = ?, fiscal_year = ?, committee_order_id = ?, start_date = ?, end_date = ? WHERE id = ?");
            $stmt->execute([
                $data['name'],
                $data['fiscal_year'],
                $data['committee_order_id'] ?: null,
                $data['start_date'] ?: null,
                $data['end_date'] ?: null,
                $data['project_id']
            ]);
            logActivity($_SESSION['user_id'], 'inspection_update', "แก้ไขโครงการตรวจสอบ ID: {$data['project_id']}");
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // Delete inspection project
    if ($action === 'delete_project') {
        requireRole(['admin', 'supply_officer']);
        try {
            $projectId = $data['project_id'];
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM inspection_group_members WHERE group_id IN (SELECT id FROM inspection_groups WHERE project_id = ?)")->execute([$projectId]);
            $pdo->prepare("DELETE FROM inspection_groups WHERE project_id = ?")->execute([$projectId]);
            $pdo->prepare("DELETE FROM inspection_items WHERE project_id = ?")->execute([$projectId]);
            $pdo->prepare("DELETE FROM inspection_projects WHERE id = ?")->execute([$projectId]);
            $pdo->commit();
            logActivity($_SESSION['user_id'], 'inspection_delete', "ลบโครงการตรวจสอบ ID: {$projectId}");
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // Import assets to inspection
    if ($action === 'import_assets') {
        requireRole(['admin', 'supply_officer']);
        try {
            $projectId = $data['project_id'];
            if (!empty($data['import_all_active'])) {
                $stmt = $pdo->prepare("INSERT INTO inspection_items (project_id, asset_id)
                                       SELECT ?, a.id FROM assets a
                                       WHERE a.status = 'active'
                                       AND NOT EXISTS (SELECT 1 FROM inspection_items ii WHERE ii.project_id = ? AND ii.asset_id = a.id)");
                $stmt->execute([$projectId, $projectId]);
                $count = $stmt->rowCount();
            } else {
                $checkStmt = $pdo->prepare("SELECT id FROM inspection_items WHERE project_id = ? AND asset_id = ? LIMIT 1");
                $insertStmt = $pdo->prepare("INSERT INTO inspection_items (project_id, asset_id) VALUES (?, ?)");
                $count = 0;
                foreach (($data['asset_ids'] ?? []) as $assetId) {
                    $checkStmt->execute([$projectId, $assetId]);
                    if (!$checkStmt->fetch()) {
                        $insertStmt->execute([$projectId, $assetId]);
                        $count++;
                    }
                }
            }
            logActivity($_SESSION['user_id'], 'inspection_import', "Import {$count} รายการ เข้าโครงการ ID: {$projectId}");
            echo json_encode(['success' => true, 'count' => $count]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // Update project status (with optional auto-complete pending & sync to assets table)
    if ($action === 'update_status') {
        requireRole(['admin', 'supply_officer']);
        try {
            $pdo->beginTransaction();
            $projectId = $data['project_id'];
            $newStatus = $data['status'];

            if ($newStatus === 'completed') {
                if (!empty($data['mark_pending_normal'])) {
                    $pdo->prepare("UPDATE inspection_items SET status = 'normal', inspected_at = NOW(), inspected_by = ? WHERE project_id = ? AND status = 'pending'")
                        ->execute([$_SESSION['user_id'], $projectId]);
                }
                if (!empty($data['sync_asset_status'])) {
                    // Sync damaged / deteriorated -> damaged
                    $pdo->prepare("UPDATE assets
                                   SET status = 'damaged'
                                   WHERE id IN (SELECT asset_id FROM inspection_items WHERE project_id = ? AND status IN ('damaged', 'deteriorated'))")
                        ->execute([$projectId]);
                    // Sync not_found -> lost
                    $pdo->prepare("UPDATE assets
                                   SET status = 'lost'
                                   WHERE id IN (SELECT asset_id FROM inspection_items WHERE project_id = ? AND status = 'not_found')")
                        ->execute([$projectId]);
                }
            }

            $stmt = $pdo->prepare("UPDATE inspection_projects SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $projectId]);
            $pdo->commit();
            logActivity($_SESSION['user_id'], 'inspection_status', "เปลี่ยนสถานะโครงการ ID: {$projectId} เป็น {$newStatus}");
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // Create group
    if ($action === 'create_group') {
        requireRole(['admin', 'supply_officer']);
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO inspection_groups (project_id, name) VALUES (?, ?)");
            $stmt->execute([$data['project_id'], $data['name']]);
            $groupId = $pdo->lastInsertId();

            if (!empty($data['members'])) {
                $stmtM = $pdo->prepare("INSERT INTO inspection_group_members (group_id, user_id) VALUES (?, ?)");
                foreach ($data['members'] as $userId) {
                    $stmtM->execute([$groupId, $userId]);
                }
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'id' => $groupId]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // Assign items to member
    if ($action === 'assign_items') {
        requireRole(['admin', 'supply_officer']);
        try {
            $where = "project_id = ?";
            $params = [$data['project_id']];

            if (!empty($data['group_id'])) {
                $where .= " AND group_id = ?";
                $params[] = $data['group_id'];
            }

            if ($data['mode'] === 'unassigned') {
                $where .= " AND assigned_to IS NULL";
            }

            $stmt = $pdo->prepare("UPDATE inspection_items SET assigned_to = ? WHERE {$where}");
            $stmt->execute(array_merge([$data['user_id']], $params));
            $count = $stmt->rowCount();

            // Notify assigned member
            sendNotification(
                $data['user_id'],
                'มอบหมายการตรวจสอบ',
                "คุณได้รับมอบหมายให้ตรวจสอบครุภัณฑ์ {$count} รายการ",
                'inspection.php',
                'info'
            );

            echo json_encode(['success' => true, 'count' => $count]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // Update inspection item status
    if ($action === 'update_item') {
        try {
            $chk = $pdo->prepare("SELECT ip.status FROM inspection_items ii JOIN inspection_projects ip ON ii.project_id = ip.id WHERE ii.id = ?");
            $chk->execute([$data['item_id']]);
            $projStatus = $chk->fetchColumn();
            if (in_array($projStatus, ['completed', 'cancelled'])) {
                throw new Exception('โครงการนี้สรุปผลหรือยกเลิกแล้ว ไม่สามารถแก้ไขผลการตรวจสอบได้');
            }

            if (array_key_exists('note', $data)) {
                $stmt = $pdo->prepare("UPDATE inspection_items SET status = ?, note = ?, inspected_at = NOW(), inspected_by = ? WHERE id = ?");
                $stmt->execute([$data['status'], $data['note'], $_SESSION['user_id'], $data['item_id']]);
            } else {
                $stmt = $pdo->prepare("UPDATE inspection_items SET status = ?, inspected_at = NOW(), inspected_by = ? WHERE id = ?");
                $stmt->execute([$data['status'], $_SESSION['user_id'], $data['item_id']]);
            }
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // Bulk update inspection items status (e.g., mark all visible pending items in a location as normal)
    if ($action === 'bulk_update_items') {
        try {
            $itemIds = $data['item_ids'] ?? [];
            $status = $data['status'] ?? 'normal';
            if (empty($itemIds) || !is_array($itemIds)) {
                throw new Exception('ไม่มีรายการที่เลือก');
            }
            $chk = $pdo->prepare("SELECT ip.status FROM inspection_items ii JOIN inspection_projects ip ON ii.project_id = ip.id WHERE ii.id = ?");
            $chk->execute([$itemIds[0]]);
            $projStatus = $chk->fetchColumn();
            if (in_array($projStatus, ['completed', 'cancelled'])) {
                throw new Exception('โครงการนี้สรุปผลหรือยกเลิกแล้ว ไม่สามารถแก้ไขผลการตรวจสอบได้');
            }

            $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
            $stmt = $pdo->prepare("UPDATE inspection_items SET status = ?, inspected_at = NOW(), inspected_by = ? WHERE id IN ($placeholders)");
            $stmt->execute(array_merge([$status, $_SESSION['user_id']], $itemIds));
            echo json_encode(['success' => true, 'count' => $stmt->rowCount()]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // Update inspection item note
    if ($action === 'update_item_note') {
        try {
            $chk = $pdo->prepare("SELECT ip.status FROM inspection_items ii JOIN inspection_projects ip ON ii.project_id = ip.id WHERE ii.id = ?");
            $chk->execute([$data['item_id']]);
            $projStatus = $chk->fetchColumn();
            if (in_array($projStatus, ['completed', 'cancelled'])) {
                throw new Exception('โครงการนี้สรุปผลหรือยกเลิกแล้ว ไม่สามารถแก้ไขหมายเหตุได้');
            }

            $stmt = $pdo->prepare("UPDATE inspection_items SET note = ? WHERE id = ?");
            $stmt->execute([$data['note'] ?? '', $data['item_id']]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // Upload inspection photo
    if ($action === 'upload_photo') {
        try {
            $chk = $pdo->prepare("SELECT ip.status FROM inspection_items ii JOIN inspection_projects ip ON ii.project_id = ip.id WHERE ii.id = ?");
            $chk->execute([$_POST['item_id'] ?? 0]);
            $projStatus = $chk->fetchColumn();
            if (in_array($projStatus, ['completed', 'cancelled'])) {
                throw new Exception('โครงการนี้สรุปผลหรือยกเลิกแล้ว ไม่สามารถแนบรูปภาพได้');
            }

            if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('ไม่พบไฟล์รูปภาพ');
            }

            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                throw new Exception('รองรับเฉพาะ jpg, png, webp');
            }

            $filename = 'inspect_' . time() . '_' . uniqid() . '.' . $ext;
            $uploadDir = __DIR__ . '/../uploads/inspection_photos/';
            if (!is_dir($uploadDir))
                mkdir($uploadDir, 0755, true);
            move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $filename);

            $photoPath = 'uploads/inspection_photos/' . $filename;
            $stmt = $pdo->prepare("UPDATE inspection_items SET photo = ? WHERE id = ?");
            $stmt->execute([$photoPath, $_POST['item_id']]);

            echo json_encode(['success' => true, 'path' => $photoPath]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // Delete Group
    if ($action === 'delete_group') {
        requireRole(['admin', 'supply_officer']);
        try {
            $groupId = $data['group_id'];
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE inspection_items SET group_id = NULL WHERE group_id = ?")->execute([$groupId]);
            $pdo->prepare("DELETE FROM inspection_group_members WHERE group_id = ?")->execute([$groupId]);
            $pdo->prepare("DELETE FROM inspection_groups WHERE id = ?")->execute([$groupId]);
            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // Auto Group by Location & Distribute Committee Members
    if ($action === 'auto_group_by_location') {
        requireRole(['admin', 'supply_officer']);
        try {
            $projectId = $data['project_id'];
            $pdo->beginTransaction();

            // Get distinct locations in this project that aren't assigned to a group yet
            $locStmt = $pdo->prepare("SELECT DISTINCT TRIM(a.location) as loc
                                      FROM inspection_items ii
                                      JOIN assets a ON ii.asset_id = a.id
                                      WHERE ii.project_id = ? AND ii.group_id IS NULL
                                      AND a.location IS NOT NULL AND TRIM(a.location) != '' AND TRIM(a.location) != '-'
                                      ORDER BY loc");
            $locStmt->execute([$projectId]);
            $locations = $locStmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($locations)) {
                throw new Exception('ไม่พบที่ตั้งของครุภัณฑ์ที่ยังไม่ได้จัดกลุ่ม');
            }

            // Get committee members for this project
            $projStmt = $pdo->prepare("SELECT committee_order_id FROM inspection_projects WHERE id = ?");
            $projStmt->execute([$projectId]);
            $coId = $projStmt->fetchColumn();
            $members = [];
            if ($coId) {
                $mStmt = $pdo->prepare("SELECT user_id FROM committee_members WHERE order_id = ? ORDER BY id");
                $mStmt->execute([$coId]);
                $members = $mStmt->fetchAll(PDO::FETCH_COLUMN);
            }

            $createdGroups = 0;
            $assignedItems = 0;
            $insGroup = $pdo->prepare("INSERT INTO inspection_groups (project_id, name, description) VALUES (?, ?, ?)");
            $insMem = $pdo->prepare("INSERT INTO inspection_group_members (group_id, user_id) VALUES (?, ?)");
            $updItems = $pdo->prepare("UPDATE inspection_items SET group_id = ? WHERE project_id = ? AND group_id IS NULL AND asset_id IN (SELECT id FROM assets WHERE TRIM(location) = ?)");

            foreach ($locations as $idx => $loc) {
                $insGroup->execute([$projectId, $loc, "สร้างอัตโนมัติตามที่ตั้ง: {$loc}"]);
                $gId = $pdo->lastInsertId();
                $createdGroups++;

                if (!empty($members)) {
                    // Assign all committee members or round-robin depending on mode
                    if (($data['member_mode'] ?? 'all') === 'round_robin') {
                        $mId = $members[$idx % count($members)];
                        $insMem->execute([$gId, $mId]);
                    } else {
                        foreach ($members as $mId) {
                            $insMem->execute([$gId, $mId]);
                        }
                    }
                }

                $updItems->execute([$gId, $projectId, $loc]);
                $assignedItems += $updItems->rowCount();
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'groups_created' => $createdGroups, 'items_assigned' => $assignedItems]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // Manage Group (Create/Edit)
    if ($action === 'manage_group') {
        requireRole(['admin', 'supply_officer']);
        try {
        $pdo->beginTransaction();

        $groupId = $data['id'] ?? null;
        $name = $data['name'];
        $projectId = $data['project_id'];
        $description = $data['description'] ?? '';

        if ($groupId) {
            // Update
            $stmt = $pdo->prepare("UPDATE inspection_groups SET name = ?, description = ? WHERE id = ?");
            $stmt->execute([$name, $description, $groupId]);

            // Clear existing members
            $pdo->prepare("DELETE FROM inspection_group_members WHERE group_id = ?")->execute([$groupId]);
        } else {
            // Create
            $stmt = $pdo->prepare("INSERT INTO inspection_groups (project_id, name, description) VALUES (?, ?, ?)");
            $stmt->execute([$projectId, $name, $description]);
            $groupId = $pdo->lastInsertId();
        }

        // Insert members
        if (!empty($data['members'])) {
            $stmtM = $pdo->prepare("INSERT INTO inspection_group_members (group_id, user_id) VALUES (?, ?)");
            foreach ($data['members'] as $userId) {
                $stmtM->execute([$groupId, $userId]);
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'id' => $groupId]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Assign Scope (Assets) to Group
if ($action === 'assign_scope') {
    requireRole(['admin', 'supply_officer']);
    try {
        $groupId = $data['group_id'];
        $projectId = $data['project_id'];
        $type = $data['type']; // 'location' or 'assets'

        if ($type === 'location') {
            // Assign by location field (exact match, multiple support)
            $locations = $data['locations'] ?? [];

            // Reset existing locations assigned to this group in this project first
            $resetStmt = $pdo->prepare("UPDATE inspection_items SET group_id = NULL WHERE project_id = ? AND group_id = ?");
            $resetStmt->execute([$projectId, $groupId]);

            if (empty($locations)) {
                echo json_encode(['success' => true, 'count' => 0]);
                exit;
            }

            $sql = "UPDATE inspection_items ii
                        JOIN assets a ON ii.asset_id = a.id
                        SET ii.group_id = ?
                        WHERE ii.project_id = ?";
            $params = [$groupId, $projectId];

            // Add IN clause for locations (using TRIM to ensure match)
            $placeholders = implode(',', array_fill(0, count($locations), '?'));
            $sql .= " AND TRIM(a.location) IN ($placeholders)";
            $params = array_merge($params, $locations);

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $count = $stmt->rowCount();

        } elseif ($type === 'assets') {
            // Assign specific assets
            if (!empty($data['asset_ids'])) {
                $placeholders = implode(',', array_fill(0, count($data['asset_ids']), '?'));
                $sql = "UPDATE inspection_items SET group_id = ? WHERE project_id = ? AND asset_id IN ($placeholders)";
                $params = array_merge([$groupId, $projectId], $data['asset_ids']);
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $count = $stmt->rowCount();
            } else {
                $count = 0;
            }
        }

        echo json_encode(['success' => true, 'count' => $count]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Upload Inspection Report
if ($action === 'upload_report') {
    try {
        // Check/Create Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS inspection_reports (
                id INT AUTO_INCREMENT PRIMARY KEY,
                project_id INT NOT NULL,
                user_id INT NOT NULL,
                file_path VARCHAR(255) NOT NULL,
                comment TEXT DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (project_id) REFERENCES inspection_projects(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB");

        if (!isset($_FILES['report_file']) || $_FILES['report_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('ไม่พบไฟล์เอกสาร');
        }

        $ext = strtolower(pathinfo($_FILES['report_file']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            throw new Exception('รองรับเฉพาะไฟล์ PDF เท่านั้น');
        }

        $filename = 'report_' . time() . '_' . uniqid() . '.pdf';
        $uploadDir = __DIR__ . '/../uploads/inspection_reports/';
        if (!is_dir($uploadDir))
            mkdir($uploadDir, 0755, true);

        $dbPath = 'uploads/inspection_reports/' . $filename;
        move_uploaded_file($_FILES['report_file']['tmp_name'], $uploadDir . $filename);

        $stmt = $pdo->prepare("INSERT INTO inspection_reports (project_id, user_id, file_path, comment) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $_POST['project_id'],
            $_SESSION['user_id'],
            $dbPath,
            $_POST['comment'] ?? ''
        ]);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);

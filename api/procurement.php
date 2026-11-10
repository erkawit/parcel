<?php
/**
 * Procurement API - ระบบจัดซื้อจัดจ้างภาครัฐ
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

/**
 * Ensure procurements and procurement_items have all columns needed for Thai Government Procurement
 */
function ensureProcurementSchema(PDO $pdo): void
{
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM procurements")->fetchAll(PDO::FETCH_COLUMN);
        $alterations = [
            'vendor_address' => "ALTER TABLE procurements ADD COLUMN vendor_address VARCHAR(255) NULL DEFAULT NULL COMMENT 'ที่อยู่ร้านค้า/ผู้รับจ้าง' AFTER vendor_name",
            'vendor_tax_id' => "ALTER TABLE procurements ADD COLUMN vendor_tax_id VARCHAR(30) NULL DEFAULT NULL COMMENT 'เลขประจำตัวผู้เสียภาษี' AFTER vendor_address",
            'budget_source' => "ALTER TABLE procurements ADD COLUMN budget_source VARCHAR(255) NULL DEFAULT NULL COMMENT 'แหล่งเงินงบประมาณ/แผนงาน' AFTER buy_reason",
            'head_director_name' => "ALTER TABLE procurements ADD COLUMN head_director_name VARCHAR(150) NULL DEFAULT NULL COMMENT 'ชื่อผู้พิพากษาหัวหน้าศาล/หัวหน้าส่วนราชการสูงสุด' AFTER head_officer_id",
            'head_director_position' => "ALTER TABLE procurements ADD COLUMN head_director_position VARCHAR(150) NULL DEFAULT NULL COMMENT 'ตำแหน่งหัวหน้าส่วนราชการสูงสุด' AFTER head_director_name",
            'inspect_date' => "ALTER TABLE procurements ADD COLUMN inspect_date DATE NULL DEFAULT NULL COMMENT 'วันที่ตรวจรับพัสดุ/วันที่ใบส่งของ' AFTER delivery_date",
            'service_request_id' => "ALTER TABLE procurements ADD COLUMN service_request_id INT NULL DEFAULT NULL COMMENT 'อ้างอิงคำขอซื้อ/ขอซ่อมจากระบบแจ้งซ่อม' AFTER requester_id"
        ];
        foreach ($alterations as $col => $sql) {
            if (!in_array($col, $cols)) {
                $pdo->exec($sql);
            }
        }

        $itemCols = $pdo->query("SHOW COLUMNS FROM procurement_items")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('unit', $itemCols)) {
            $pdo->exec("ALTER TABLE procurement_items ADD COLUMN unit VARCHAR(50) NULL DEFAULT 'รายการ' COMMENT 'หน่วยนับ' AFTER quantity");
        }
        if (!in_array('spec_detail', $itemCols)) {
            $pdo->exec("ALTER TABLE procurement_items ADD COLUMN spec_detail TEXT NULL DEFAULT NULL COMMENT 'รายละเอียดคุณลักษณะเฉพาะ' AFTER item_name");
        }
    } catch (Exception $e) {
        // Ignore schema check errors if permissions are restricted
    }
}

ensureProcurementSchema($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'get') {
        $id = intval($_GET['id'] ?? 0);

        $stmt = $pdo->prepare("SELECT p.*,
                               u_req.full_name as requester_name, u_req.position as requester_position,
                               u_insp.full_name as inspector_name, u_insp.position as inspector_position,
                               u_supp.full_name as supply_officer_name, u_supp.position as supply_officer_position,
                               u_dir.full_name as director_name, u_dir.position as director_position,
                               u_head.full_name as head_officer_name, u_head.position as head_officer_position
                               FROM procurements p
                               LEFT JOIN users u_req ON p.requester_id = u_req.id
                               LEFT JOIN users u_insp ON p.inspector_id = u_insp.id
                               LEFT JOIN users u_supp ON p.supply_officer_id = u_supp.id
                               LEFT JOIN users u_dir ON p.director_id = u_dir.id
                               LEFT JOIN users u_head ON p.head_officer_id = u_head.id
                               WHERE p.id = ?");
        $stmt->execute([$id]);
        $procurement = $stmt->fetch();

        if (!$procurement) {
            echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูล']);
            exit;
        }

        if (!hasRole(['admin', 'supply_officer', 'department_head', 'director']) && $procurement['requester_id'] != $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'ไม่มีสิทธิ์เข้าถึง']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT pi.*, a.asset_code 
                               FROM procurement_items pi
                               LEFT JOIN assets a ON pi.asset_id = a.id
                               WHERE pi.procurement_id = ?
                               ORDER BY pi.id ASC");
        $stmt->execute([$id]);
        $procurement['items'] = $stmt->fetchAll();

        echo json_encode(['success' => true, 'data' => $procurement]);
        exit;
    }

    if ($action === 'next_doc_no') {
        $year = $_GET['year'] ?? (date('Y') + 543);
        $stmt = $pdo->prepare("SELECT doc_no FROM procurements WHERE fiscal_year = ? ORDER BY id DESC LIMIT 50");
        $stmt->execute([$year]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $maxNum = 0;
        foreach ($rows as $docNo) {
            if (preg_match('/^(\d+)\//', trim($docNo), $m)) {
                $num = intval($m[1]);
                if ($num > $maxNum) {
                    $maxNum = $num;
                }
            }
        }
        $nextNo = ($maxNum + 1) . '/' . $year;
        echo json_encode(['success' => true, 'next_doc_no' => $nextNo, 'year' => $year]);
        exit;
    }

    if ($action === 'pending_requests') {
        try {
            $stmt = $pdo->query("SELECT sr.*, u.full_name as user_name, u.department, u.position,
                                 tv.type_volume as unit_name
                                 FROM service_requests sr
                                 LEFT JOIN users u ON sr.user_id = u.id
                                 LEFT JOIN type_volume tv ON sr.type_asset = tv.id
                                 WHERE sr.status IN ('pending', 'approved', 'in_progress')
                                 ORDER BY sr.created_at DESC
                                 LIMIT 30");
            $requests = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $requests]);
        } catch (Exception $e) {
            echo json_encode(['success' => true, 'data' => []]);
        }
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $data['action'] ?? '';

    if ($action === 'create') {
        try {
            $pdo->beginTransaction();

            $items = $data['items'] ?? [];
            if (empty($items)) {
                throw new Exception('กรุณาระบุรายการพัสดุ/ครุภัณฑ์อย่างน้อย 1 รายการ');
            }

            $totalAmount = 0;
            foreach ($items as $item) {
                $qty = floatval($item['qty'] ?? 1);
                $price = floatval($item['price'] ?? 0);
                $totalAmount += ($qty * $price);
            }

            $year = !empty($data['fiscal_year']) ? $data['fiscal_year'] : (date('Y') + 543);
            $requesterId = !empty($data['requester_id']) ? intval($data['requester_id']) : $_SESSION['user_id'];
            $docDate = !empty($data['doc_date']) ? $data['doc_date'] : date('Y-m-d');
            $deliveryDate = !empty($data['delivery_date']) ? $data['delivery_date'] : $docDate;
            $inspectDate = !empty($data['inspect_date']) ? $data['inspect_date'] : $deliveryDate;

            $stmt = $pdo->prepare("INSERT INTO procurements (
                type, fiscal_year, doc_no, doc_date, subject, requester_id, service_request_id,
                repair_reason, repair_detail, buy_reason, budget_source,
                receipt_no, vendor_name, vendor_address, vendor_tax_id,
                delivery_date, inspect_date,
                inspector_id, supply_officer_id, director_id, head_officer_id,
                head_director_name, head_director_position,
                total_amount, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $stmt->execute([
                $data['type'],
                $year,
                trim($data['doc_no']),
                $docDate,
                trim($data['subject']),
                $requesterId,
                !empty($data['service_request_id']) ? intval($data['service_request_id']) : null,
                $data['repair_reason'] ?? null,
                $data['repair_detail'] ?? null,
                $data['buy_reason'] ?? null,
                $data['budget_source'] ?? null,
                $data['receipt_no'] ?? null,
                $data['vendor_name'] ?? null,
                $data['vendor_address'] ?? null,
                $data['vendor_tax_id'] ?? null,
                $deliveryDate,
                $inspectDate,
                !empty($data['inspector_id']) ? intval($data['inspector_id']) : null,
                !empty($data['supply_officer_id']) ? intval($data['supply_officer_id']) : null,
                !empty($data['director_id']) ? intval($data['director_id']) : null,
                !empty($data['head_officer_id']) ? intval($data['head_officer_id']) : null,
                !empty($data['head_director_name']) ? trim($data['head_director_name']) : null,
                !empty($data['head_director_position']) ? trim($data['head_director_position']) : null,
                $totalAmount,
                !empty($data['status']) ? $data['status'] : 'pending'
            ]);
            $procurementId = $pdo->lastInsertId();

            $stmtItem = $pdo->prepare("INSERT INTO procurement_items (
                procurement_id, asset_id, item_name, spec_detail, quantity, unit, unit_price, total_price
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

            foreach ($items as $item) {
                $qty = floatval($item['qty'] ?? 1);
                $price = floatval($item['price'] ?? 0);
                $total = $qty * $price;
                $unit = !empty($item['unit']) ? trim($item['unit']) : ($data['type'] === 'repair' ? 'งาน' : 'รายการ');
                $spec = !empty($item['spec_detail']) ? trim($item['spec_detail']) : null;
                $assetId = !empty($item['asset_id']) ? intval($item['asset_id']) : null;

                $stmtItem->execute([
                    $procurementId,
                    $assetId,
                    trim($item['name']),
                    $spec,
                    $qty,
                    $unit,
                    $price,
                    $total
                ]);

                if ($data['type'] === 'repair' && $assetId) {
                    $upd = $pdo->prepare("UPDATE assets SET status = 'damaged' WHERE id = ?");
                    $upd->execute([$assetId]);
                }
            }

            if (!empty($data['service_request_id'])) {
                try {
                    $updReq = $pdo->prepare("UPDATE service_requests SET status = 'approved', admin_note = ? WHERE id = ?");
                    $updReq->execute(["ออกเอกสารจัดซื้อ/จัดจ้างเลขที่ " . trim($data['doc_no']), intval($data['service_request_id'])]);
                } catch (Exception $e) {
                    // Ignore if service_requests table is missing
                }
            }

            $pdo->commit();
            logActivity($_SESSION['user_id'], 'procurement_create', "สร้างรายการจัดซื้อ/จ้าง {$data['doc_no']} ({$data['subject']})");
            echo json_encode(['success' => true, 'id' => $procurementId]);

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['success' => false, 'message' => 'บันทึกไม่สำเร็จ: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'update') {
        requireRole(['admin', 'supply_officer']);
        try {
            $pdo->beginTransaction();

            $id = intval($data['id'] ?? 0);
            $stmtCheck = $pdo->prepare("SELECT * FROM procurements WHERE id = ?");
            $stmtCheck->execute([$id]);
            $existing = $stmtCheck->fetch();
            if (!$existing) {
                throw new Exception('ไม่พบรายการที่ต้องการแก้ไข');
            }

            $items = $data['items'] ?? [];
            if (empty($items)) {
                throw new Exception('กรุณาระบุรายการพัสดุ/ครุภัณฑ์อย่างน้อย 1 รายการ');
            }

            $totalAmount = 0;
            foreach ($items as $item) {
                $qty = floatval($item['qty'] ?? 1);
                $price = floatval($item['price'] ?? 0);
                $totalAmount += ($qty * $price);
            }

            $year = !empty($data['fiscal_year']) ? $data['fiscal_year'] : $existing['fiscal_year'];
            $requesterId = !empty($data['requester_id']) ? intval($data['requester_id']) : $existing['requester_id'];
            $docDate = !empty($data['doc_date']) ? $data['doc_date'] : $existing['doc_date'];
            $deliveryDate = !empty($data['delivery_date']) ? $data['delivery_date'] : $docDate;
            $inspectDate = !empty($data['inspect_date']) ? $data['inspect_date'] : $deliveryDate;

            $stmt = $pdo->prepare("UPDATE procurements SET
                type = ?, fiscal_year = ?, doc_no = ?, doc_date = ?, subject = ?, requester_id = ?,
                repair_reason = ?, repair_detail = ?, buy_reason = ?, budget_source = ?,
                receipt_no = ?, vendor_name = ?, vendor_address = ?, vendor_tax_id = ?,
                delivery_date = ?, inspect_date = ?,
                inspector_id = ?, supply_officer_id = ?, director_id = ?, head_officer_id = ?,
                head_director_name = ?, head_director_position = ?,
                total_amount = ?, status = ?
                WHERE id = ?");

            $stmt->execute([
                $data['type'],
                $year,
                trim($data['doc_no']),
                $docDate,
                trim($data['subject']),
                $requesterId,
                $data['repair_reason'] ?? null,
                $data['repair_detail'] ?? null,
                $data['buy_reason'] ?? null,
                $data['budget_source'] ?? null,
                $data['receipt_no'] ?? null,
                $data['vendor_name'] ?? null,
                $data['vendor_address'] ?? null,
                $data['vendor_tax_id'] ?? null,
                $deliveryDate,
                $inspectDate,
                !empty($data['inspector_id']) ? intval($data['inspector_id']) : null,
                !empty($data['supply_officer_id']) ? intval($data['supply_officer_id']) : null,
                !empty($data['director_id']) ? intval($data['director_id']) : null,
                !empty($data['head_officer_id']) ? intval($data['head_officer_id']) : null,
                !empty($data['head_director_name']) ? trim($data['head_director_name']) : null,
                !empty($data['head_director_position']) ? trim($data['head_director_position']) : null,
                $totalAmount,
                !empty($data['status']) ? $data['status'] : $existing['status'],
                $id
            ]);

            // Replace items
            $pdo->prepare("DELETE FROM procurement_items WHERE procurement_id = ?")->execute([$id]);

            $stmtItem = $pdo->prepare("INSERT INTO procurement_items (
                procurement_id, asset_id, item_name, spec_detail, quantity, unit, unit_price, total_price
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

            foreach ($items as $item) {
                $qty = floatval($item['qty'] ?? 1);
                $price = floatval($item['price'] ?? 0);
                $total = $qty * $price;
                $unit = !empty($item['unit']) ? trim($item['unit']) : ($data['type'] === 'repair' ? 'งาน' : 'รายการ');
                $spec = !empty($item['spec_detail']) ? trim($item['spec_detail']) : null;
                $assetId = !empty($item['asset_id']) ? intval($item['asset_id']) : null;

                $stmtItem->execute([
                    $id,
                    $assetId,
                    trim($item['name']),
                    $spec,
                    $qty,
                    $unit,
                    $price,
                    $total
                ]);
            }

            $pdo->commit();
            logActivity($_SESSION['user_id'], 'procurement_update', "แก้ไขรายการจัดซื้อ/จ้าง {$data['doc_no']}");
            echo json_encode(['success' => true, 'id' => $id]);

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['success' => false, 'message' => 'แก้ไขไม่สำเร็จ: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'update_status') {
        requireRole(['admin', 'supply_officer']);
        try {
            $id = intval($data['id'] ?? 0);
            $newStatus = $data['status'] ?? 'pending';
            $note = $data['note'] ?? null;
            $receipt = $data['receipt'] ?? null;
            $date = $data['date'] ?? null;
            $vendorName = $data['vendor_name'] ?? null;

            $stmt = $pdo->prepare("SELECT * FROM procurements WHERE id = ?");
            $stmt->execute([$id]);
            $proc = $stmt->fetch();

            if (!$proc) {
                throw new Exception('ไม่พบรายการ');
            }

            $sql = "UPDATE procurements SET status = ?";
            $params = [$newStatus];

            if ($note !== null && $note !== '') {
                $sql .= ", repair_detail = ?";
                $params[] = $note;
            }
            if ($receipt !== null && $receipt !== '') {
                $sql .= ", receipt_no = ?";
                $params[] = $receipt;
            }
            if ($vendorName !== null && $vendorName !== '') {
                $sql .= ", vendor_name = ?";
                $params[] = $vendorName;
            }
            if ($date) {
                $sql .= ", inspect_date = ?, delivery_date = COALESCE(delivery_date, ?)";
                $params[] = $date;
                $params[] = $date;
            }

            $sql .= " WHERE id = ?";
            $params[] = $id;

            $pdo->prepare($sql)->execute($params);

            if ($proc['type'] === 'repair') {
                if ($newStatus === 'completed' || $newStatus === 'cancelled') {
                    $items = $pdo->prepare("SELECT asset_id FROM procurement_items WHERE procurement_id = ? AND asset_id IS NOT NULL");
                    $items->execute([$id]);
                    while ($it = $items->fetch()) {
                        $pdo->prepare("UPDATE assets SET status = 'active' WHERE id = ?")->execute([$it['asset_id']]);
                    }
                }
            }

            if (!empty($proc['service_request_id']) && ($newStatus === 'completed' || $newStatus === 'cancelled')) {
                try {
                    $srStatus = $newStatus === 'completed' ? 'completed' : 'rejected';
                    $pdo->prepare("UPDATE service_requests SET status = ? WHERE id = ?")->execute([$srStatus, $proc['service_request_id']]);
                } catch (Exception $e) {
                }
            }

            logActivity($_SESSION['user_id'], 'procurement_update', "อัปเดตสถานะรายการ {$proc['doc_no']} -> $newStatus");
            echo json_encode(['success' => true]);

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'อัปเดตไม่สำเร็จ: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'delete') {
        requireRole(['admin', 'supply_officer']);
        try {
            $id = intval($data['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM procurements WHERE id = ?");
            $stmt->execute([$id]);
            $proc = $stmt->fetch();
            if (!$proc) {
                throw new Exception('ไม่พบรายการที่ต้องการลบ');
            }

            $pdo->beginTransaction();

            if ($proc['type'] === 'repair' && $proc['status'] !== 'completed') {
                $items = $pdo->prepare("SELECT asset_id FROM procurement_items WHERE procurement_id = ? AND asset_id IS NOT NULL");
                $items->execute([$id]);
                while ($it = $items->fetch()) {
                    $pdo->prepare("UPDATE assets SET status = 'active' WHERE id = ?")->execute([$it['asset_id']]);
                }
            }

            $pdo->prepare("DELETE FROM procurement_items WHERE procurement_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM procurements WHERE id = ?")->execute([$id]);

            $pdo->commit();
            logActivity($_SESSION['user_id'], 'procurement_delete', "ลบรายการจัดซื้อ/จ้าง {$proc['doc_no']}");
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['success' => false, 'message' => 'ลบไม่สำเร็จ: ' . $e->getMessage()]);
        }
        exit;
    }
}

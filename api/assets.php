<?php
/**
 * Assets API
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

// GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'get') {
        $id = $_GET['id'] ?? 0;

        if (!canAccessAsset($id)) {
            echo json_encode(['success' => false, 'message' => 'ไม่มีสิทธิ์เข้าถึงข้อมูลนี้']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT a.*, c.name as category_name, u.full_name as holder_name 
                               FROM assets a 
                               LEFT JOIN asset_categories c ON a.category_id = c.id 
                               LEFT JOIN users u ON a.holder_id = u.id 
                               WHERE a.id = ?");
        $stmt->execute([$id]);
        $asset = $stmt->fetch();

        if ($asset) {
            echo json_encode(['success' => true, 'asset' => $asset]);
        } else {
            echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลครุภัณฑ์']);
        }
        exit;
    }

    if ($action === 'list') {
        list($whereSQL, $params) = getAssetFilterByRole(); // Get role-based filter

        $stmt = $pdo->prepare("SELECT a.*, c.name as category_name, u.full_name as holder_name 
                            FROM assets a 
                            LEFT JOIN asset_categories c ON a.category_id = c.id 
                            LEFT JOIN users u ON a.holder_id = u.id 
                            WHERE $whereSQL
                            ORDER BY a.asset_code");
        $stmt->execute($params);
        echo json_encode(['success' => true, 'assets' => $stmt->fetchAll()]);
        exit;
    }

    if ($action === 'list_locations') {
        $stmt = $pdo->query("SELECT DISTINCT location FROM assets WHERE location IS NOT NULL AND location != '' ORDER BY location");
        echo json_encode(['success' => true, 'locations' => $stmt->fetchAll(PDO::FETCH_COLUMN)]);
        exit;
    }

    if ($action === 'search') {
        $q = $_GET['q'] ?? '';
        if (strlen($q) < 2) {
            echo json_encode([]);
            exit;
        }

        list($whereSQL, $params) = getAssetFilterByRole();

        $whereSQL .= " AND (a.asset_code LIKE ? OR a.name LIKE ?)";
        $params[] = "%$q%";
        $params[] = "%$q%";

        $stmt = $pdo->prepare("SELECT a.id, a.asset_code, a.name, a.purchase_price as unit_price FROM assets a 
                               LEFT JOIN users u ON a.holder_id = u.id 
                               WHERE $whereSQL 
                               ORDER BY a.asset_code
                               LIMIT 20");
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll());
        exit;
    }
}

// POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $data['action'] ?? '';

    if ($action === 'create') {
        requireRole(['admin', 'supply_officer', 'director', 'department_head']);

        // For department_head, validate that holder belongs to their department (optional logic, but basic create is allowed)

        try {
            $stmt = $pdo->prepare("INSERT INTO assets (asset_code, name, category_id, brand, model, purchase_price, purchase_date, location, holder_id) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['asset_code'],
                $data['name'],
                $data['category_id'] ?: null,
                $data['brand'] ?: null,
                $data['model'] ?: null,
                $data['purchase_price'] ?: 0,
                $data['purchase_date'] ?: null,
                $data['location'] ?: null,
                $data['holder_id'] ?: null
            ]);

            logActivity($_SESSION['user_id'], 'asset_create', "เพิ่มครุภัณฑ์: {$data['name']}");
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'รหัสครุภัณฑ์ซ้ำหรือข้อมูลไม่ถูกต้อง']);
        }
        exit;
    }

    if ($action === 'update') {
        requireRole(['admin', 'supply_officer', 'director', 'department_head']);

        $id = $data['id'];

        // Specific check for department_head
        if ($_SESSION['user_role'] === 'department_head') {
            if (!canAccessAsset($id)) {
                echo json_encode(['success' => false, 'message' => 'ไม่มีสิทธิ์แก้ไขข้อมูลนี้ (ไม่อยู่ในแผนกของคุณ)']);
                exit;
            }
        }

        try {
            $stmt = $pdo->prepare("UPDATE assets SET asset_code=?, name=?, category_id=?, brand=?, model=?, 
                                  purchase_price=?, status=?, location=?, holder_id=? WHERE id=?");
            $stmt->execute([
                $data['asset_code'],
                $data['name'],
                $data['category_id'] ?: null,
                $data['brand'] ?: null,
                $data['model'] ?: null,
                $data['purchase_price'] ?: 0,
                $data['status'] ?? 'active',
                $data['location'] ?: null,
                $data['holder_id'] ?: null,
                $data['id']
            ]);

            logActivity($_SESSION['user_id'], 'asset_update', "แก้ไขครุภัณฑ์ ID: {$data['id']}");
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'ไม่สามารถแก้ไขได้']);
        }
        exit;
    }

    if ($action === 'delete') {
        requireRole(['admin', 'supply_officer', 'director', 'department_head']);

        $id = $data['id'];

        // Specific check for department_head
        if ($_SESSION['user_role'] === 'department_head') {
            if (!canAccessAsset($id)) {
                echo json_encode(['success' => false, 'message' => 'ไม่มีสิทธิ์ลบข้อมูลนี้ (ไม่อยู่ในแผนกของคุณ)']);
                exit;
            }
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM assets WHERE id = ?");
            $stmt->execute([$data['id']]);
            logActivity($_SESSION['user_id'], 'asset_delete', "ลบครุภัณฑ์ ID: {$data['id']}");
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'ไม่สามารถลบได้ มีข้อมูลที่เกี่ยวข้อง']);
        }
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);

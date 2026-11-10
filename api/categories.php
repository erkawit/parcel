<?php
/**
 * Categories API
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

// Only authorized roles can manage categories
// Assuming same roles as asset management
$canManage = hasManagementPrivileges(); // admin, supply_officer, director, department_head

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // List categories
    $stmt = $pdo->query("SELECT * FROM asset_categories ORDER BY name");
    $categories = $stmt->fetchAll();
    echo json_encode(['success' => true, 'categories' => $categories]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canManage) {
        echo json_encode(['success' => false, 'message' => 'ไม่มีสิทธิ์จัดการหมวดหมู่']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $data['action'] ?? '';

    if ($action === 'create') {
        $name = trim($data['name'] ?? '');
        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'กรุณาระบุชื่อหมวดหมู่']);
            exit;
        }

        // Check duplicate
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM asset_categories WHERE name = ?");
        $stmt->execute([$name]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'หมวดหมู่นี้มีอยู่แล้ว']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO asset_categories (name) VALUES (?)");
            $stmt->execute([$name]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId(), 'name' => $name]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'ไม่สามารถเพิ่มหมวดหมู่ได้']);
        }
        exit;
    }

    // Optional: Delete category
    // Not explicitly requested but useful for "list and add" - usually implies management.
    // However, user only said "list" and "form to add". I will stick to that to avoid deleting used categories.
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>
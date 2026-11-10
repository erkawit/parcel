<?php
/**
 * Users API
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

// GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'list') {
        // Get all active users
        $stmt = $pdo->query("SELECT id, full_name, department, position, role FROM users WHERE status = 1 ORDER BY full_name");
        $users = $stmt->fetchAll();
        echo json_encode(['success' => true, 'users' => $users]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $data['action'] ?? '';

    // Create user (Admin only)
    if ($action === 'create') {
        requireRole(['admin']);
        try {
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, phone, department, position, position_level, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['username'],
                $hashedPassword,
                $data['full_name'],
                $data['email'] ?: null,
                $data['phone'] ?: null,
                $data['department'] ?: null,
                $data['position'] ?: null,
                $data['position_level'] ?: null,
                $data['role'] ?? 'user'
            ]);
            logActivity($_SESSION['user_id'], 'user_create', "เพิ่มผู้ใช้: {$data['full_name']}");
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'ชื่อผู้ใช้ซ้ำหรือข้อมูลไม่ถูกต้อง']);
        }
        exit;
    }

    // Update user (Admin only)
    if ($action === 'update') {
        requireRole(['admin']);
        try {
            $stmt = $pdo->prepare("UPDATE users SET full_name=?, email=?, phone=?, department=?, position=?, position_level=?, role=? WHERE id=?");
            $stmt->execute([
                $data['full_name'],
                $data['email'] ?: null,
                $data['phone'] ?: null,
                $data['department'] ?: null,
                $data['position'] ?: null,
                $data['position_level'] ?: null,
                $data['role'],
                $data['id']
            ]);
            logActivity($_SESSION['user_id'], 'user_update', "แก้ไขผู้ใช้ ID: {$data['id']}");
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'ไม่สามารถแก้ไขได้']);
        }
        exit;
    }

    // Toggle user status (Admin only)
    if ($action === 'toggle_status') {
        requireRole(['admin']);
        try {
            $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->execute([$data['status'], $data['id']]);
            logActivity($_SESSION['user_id'], 'user_status', "เปลี่ยนสถานะผู้ใช้ ID: {$data['id']}");
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'ไม่สามารถเปลี่ยนสถานะได้']);
        }
        exit;
    }

    // Update own profile
    if ($action === 'update_profile') {
        try {
            $stmt = $pdo->prepare("UPDATE users SET full_name=?, email=?, phone=? WHERE id=?");
            $stmt->execute([
                $data['full_name'],
                $data['email'] ?: null,
                $data['phone'] ?: null,
                $_SESSION['user_id']
            ]);
            $_SESSION['user_name'] = $data['full_name'];
            logActivity($_SESSION['user_id'], 'profile_update', 'แก้ไขข้อมูลส่วนตัว');
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'ไม่สามารถแก้ไขได้']);
        }
        exit;
    }

    // Change password
    if ($action === 'change_password') {
        try {
            $user = getCurrentUser();
            if (!password_verify($data['old_password'], $user['password'])) {
                echo json_encode(['success' => false, 'message' => 'รหัสผ่านปัจจุบันไม่ถูกต้อง']);
                exit;
            }

            $newHash = password_hash($data['new_password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$newHash, $_SESSION['user_id']]);
            logActivity($_SESSION['user_id'], 'password_change', 'เปลี่ยนรหัสผ่าน');
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'ไม่สามารถเปลี่ยนรหัสผ่านได้']);
        }
        exit;
    }

    // Reset Password (Admin only)
    if ($action === 'reset_password') {
        requireRole(['admin']);
        try {
            $id = $data['id'];
            // Generate random password (8 chars)
            $newPassword = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $id]);

            // Log manually if function not available in context, but based on line 120 it is available
            if (function_exists('logActivity')) {
                logActivity($_SESSION['user_id'] ?? 0, 'user_reset_password', "รีเซ็ตรหัสผ่าน User ID: {$id}");
            }

            echo json_encode(['success' => true, 'new_password' => $newPassword]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'ไม่สามารถรีเซ็ตรหัสผ่านได้']);
        }
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);

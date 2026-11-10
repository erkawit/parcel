<?php
/**
 * Notifications API
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'list') {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
    $stmt->execute([$_SESSION['user_id']]);
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($action === 'mark_all_read') {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$_SESSION['user_id']]);
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
    exit;
}

if ($action === 'mark_read') {
    $id = $_GET['id'] ?? $_POST['id'] ?? 0;
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")->execute([$id, $_SESSION['user_id']]);
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode([]);

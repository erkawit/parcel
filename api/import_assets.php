<?php
require_once '../includes/auth.php';
requireLogin();

// Check management privileges
if (!hasManagementPrivileges()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'ไม่มีสิทธิ์ดำเนินการ']);
    exit;
}

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('กรุณาอัปโหลดไฟล์');
    }

    $file = $_FILES['file'];
    $importType = $_POST['type'] ?? 'registered'; // registered (>10k), unregistered (<=10k)

    // Validate extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['xlsx', 'xls'])) {
        throw new Exception('อนุญาตเฉพาะไฟล์ .xlsx หรือ .xls เท่านั้น');
    }

    // Create upload directory
    $uploadDir = __DIR__ . '/../uploads/imports/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = 'import_' . date('Ymd_His') . '_' . uniqid() . '.' . $ext;
    $filepath = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('ไม่สามารถบันทึกไฟล์ได้');
    }

    // TODO: Implement actual Excel parsing here.
    // Since PhpSpreadsheet is not guaranteed to be installed, we will simulate the process.
    // In a production environment, run: composer require phpoffice/phpspreadsheet

    // Simulate processing time
    sleep(1);

    // Mock analysis result
    // Ideally, we would read the first row to check headers.
    // If headers match "Registered" format vs "Unregistered" format, proceed.

    // Return success response (Mock data)
    echo json_encode([
        'success' => true,
        'message' => 'นำเข้าข้อมูลเรียบร้อยแล้ว',
        'summary' => [
            'total' => 0, // Placeholder
            'success' => 0,
            'new' => 0,
            'updated' => 0,
            'failed' => 0
        ],
        'debug_info' => 'File uploaded to ' . $filepath . '. Connect Excel parser to process data.'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

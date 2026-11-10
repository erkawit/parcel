<?php
/**
 * API to fetch specific tracking data for DataTables
 */
ob_start(); // Start buffering to catch any unwanted output
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

try {
    // Join users to get requester name
    // Fetch all needed columns
    // Join users to get requester name
    // Fetch all needed columns
    $stmt = $pdo->query("SELECT 
        r.*, 
        u.full_name 
    FROM service_requests r 
    LEFT JOIN users u ON r.user_id = u.id 
    ORDER BY r.created_at DESC");

    $requests = $stmt->fetchAll();

    // Clear buffer before output
    ob_end_clean();
    echo json_encode(['data' => $requests]);

} catch (PDOException $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

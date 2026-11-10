<?php
require_once 'includes/db.php';
try {
    echo "<h2>Table Structure</h2>";
    $stmt = $pdo->query("DESCRIBE system_settings");
    echo "<pre>";
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    echo "</pre>";

    echo "<h2>Table Content</h2>";
    $stmt = $pdo->query("SELECT * FROM system_settings");
    echo "<pre>";
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    echo "</pre>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

<?php
require_once __DIR__ . '/../includes/db.php';

try {
    // Create procurements table
    $sql = "CREATE TABLE IF NOT EXISTS procurements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type ENUM('repair', 'credit', 'cash') NOT NULL,
        fiscal_year VARCHAR(4) NOT NULL,
        doc_no VARCHAR(50) NOT NULL COMMENT 'เลขที่หนังสือ',
        doc_date DATE NOT NULL COMMENT 'วันที่เอกสาร',
        subject VARCHAR(255) DEFAULT NULL COMMENT 'เรื่อง',
        requester_id INT NOT NULL COMMENT 'ผู้ขอ (User ID)',
        
        repair_reason TEXT DEFAULT NULL COMMENT 'สาเหตุ/อาการ (repair)',
        repair_detail TEXT DEFAULT NULL COMMENT 'รายละเอียดการซ่อม (repair)',
        buy_reason TEXT DEFAULT NULL COMMENT 'เหตุผลความจำเป็น (credit)',
        receipt_no VARCHAR(50) DEFAULT NULL COMMENT 'เลขที่ใบเสร็จ (cash)',
        vendor_name VARCHAR(255) DEFAULT NULL COMMENT 'ร้านค้า',
        delivery_date DATE DEFAULT NULL COMMENT 'กำหนดส่งมอบ',
        
        total_amount DECIMAL(15,2) DEFAULT 0.00,
        
        inspector_id INT DEFAULT NULL COMMENT 'ผู้ตรวจรับ',
        supply_officer_id INT DEFAULT NULL COMMENT 'เจ้าหน้าที่พัสดุ',
        director_id INT DEFAULT NULL COMMENT 'ผู้อำนวยการ',
        head_officer_id INT DEFAULT NULL COMMENT 'หัวหน้าส่วนราชการ (for credit)',
        
        status ENUM('draft', 'pending', 'approved', 'completed', 'cancelled') DEFAULT 'draft',
        
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $pdo->exec($sql);
    echo "Table 'procurements' created or already exists.\n";

    // Create procurement_items table
    $sql = "CREATE TABLE IF NOT EXISTS procurement_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        procurement_id INT NOT NULL,
        asset_id INT DEFAULT NULL COMMENT 'For repairs linking to existing asset',
        item_name VARCHAR(255) NOT NULL,
        quantity INT DEFAULT 1,
        unit_price DECIMAL(15,2) DEFAULT 0.00,
        total_price DECIMAL(15,2) DEFAULT 0.00,
        
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        FOREIGN KEY (procurement_id) REFERENCES procurements(id) ON DELETE CASCADE,
        FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $pdo->exec($sql);
    echo "Table 'procurement_items' created or already exists.\n";

} catch (PDOException $e) {
    die("DB Setup Error: " . $e->getMessage());
}

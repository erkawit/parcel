-- ============================================
-- ระบบจัดการครุภัณฑ์สำนักงาน
-- Office Asset Management System
-- Database Schema
-- ============================================

CREATE DATABASE IF NOT EXISTS `asset_management_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `asset_management_db`;

-- ============================================
-- 1. Users Table
-- ============================================
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(100) DEFAULT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `department` VARCHAR(100) DEFAULT NULL,
    `position` VARCHAR(100) DEFAULT NULL,
    `role` ENUM('admin', 'supply_officer', 'user') NOT NULL DEFAULT 'user',
    `avatar` VARCHAR(255) DEFAULT NULL,
    `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=active, 0=inactive',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================
-- 2. Asset Categories
-- ============================================
CREATE TABLE IF NOT EXISTS `asset_categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================
-- 3. Assets Table
-- ============================================
CREATE TABLE IF NOT EXISTS `assets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `asset_code` VARCHAR(50) NOT NULL UNIQUE COMMENT 'หมายเลขครุภัณฑ์',
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `category_id` INT DEFAULT NULL,
    `serial_number` VARCHAR(100) DEFAULT NULL,
    `brand` VARCHAR(100) DEFAULT NULL,
    `model` VARCHAR(100) DEFAULT NULL,
    `purchase_date` DATE DEFAULT NULL,
    `purchase_price` DECIMAL(12,2) DEFAULT 0.00,
    `warranty_expire` DATE DEFAULT NULL,
    `location` VARCHAR(255) DEFAULT NULL,
    `building` VARCHAR(100) DEFAULT NULL,
    `floor` VARCHAR(10) DEFAULT NULL,
    `room` VARCHAR(50) DEFAULT NULL,
    `holder_id` INT DEFAULT NULL COMMENT 'ผู้ถือครอง',
    `status` ENUM('active','damaged','lost','retired','transferred') NOT NULL DEFAULT 'active',
    `qr_code` VARCHAR(255) DEFAULT NULL,
    `image` VARCHAR(255) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`category_id`) REFERENCES `asset_categories`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`holder_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================
-- 4. Transfer Requests
-- ============================================
CREATE TABLE IF NOT EXISTS `transfer_requests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `asset_id` INT NOT NULL,
    `from_user_id` INT NOT NULL,
    `to_user_id` INT DEFAULT NULL,
    `transfer_type` ENUM('user','supply','it') NOT NULL COMMENT 'โอนให้ผู้ใช้/คืนพัสดุ/ส่งIT',
    `reason` TEXT DEFAULT NULL,
    `status` ENUM('pending','approved','rejected','completed') NOT NULL DEFAULT 'pending',
    `approved_by` INT DEFAULT NULL,
    `approved_at` DATETIME DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`asset_id`) REFERENCES `assets`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`from_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`to_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================
-- 5. Committee Orders (คำสั่งแต่งตั้งคณะกรรมการ)
-- ============================================
CREATE TABLE IF NOT EXISTS `committee_orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `fiscal_year` INT NOT NULL COMMENT 'ปีงบประมาณ พ.ศ.',
    `order_number` VARCHAR(100) DEFAULT NULL COMMENT 'เลขที่คำสั่ง',
    `order_date` DATE DEFAULT NULL,
    `pdf_file` VARCHAR(255) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_by` INT NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- 6. Committee Members
-- ============================================
CREATE TABLE IF NOT EXISTS `committee_members` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `role` ENUM('chairman','member','secretary') NOT NULL DEFAULT 'member',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`order_id`) REFERENCES `committee_orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- 7. Inspection Projects (โครงการตรวจสอบ)
-- ============================================
CREATE TABLE IF NOT EXISTS `inspection_projects` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `fiscal_year` INT NOT NULL,
    `committee_order_id` INT DEFAULT NULL,
    `start_date` DATE DEFAULT NULL,
    `end_date` DATE DEFAULT NULL,
    `status` ENUM('draft','in_progress','completed','cancelled') NOT NULL DEFAULT 'draft',
    `summary` TEXT DEFAULT NULL,
    `created_by` INT NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`committee_order_id`) REFERENCES `committee_orders`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- 8. Inspection Groups (กลุ่มการตรวจสอบ)
-- ============================================
CREATE TABLE IF NOT EXISTS `inspection_groups` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `project_id` INT NOT NULL,
    `name` VARCHAR(255) NOT NULL COMMENT 'เช่น กลุ่มตึก A, กลุ่มชั้น 2',
    `description` TEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`project_id`) REFERENCES `inspection_projects`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- 9. Inspection Group Members
-- ============================================
CREATE TABLE IF NOT EXISTS `inspection_group_members` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `group_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`group_id`) REFERENCES `inspection_groups`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- 10. Inspection Items (รายการตรวจสอบ)
-- ============================================
CREATE TABLE IF NOT EXISTS `inspection_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `project_id` INT NOT NULL,
    `asset_id` INT NOT NULL,
    `group_id` INT DEFAULT NULL,
    `assigned_to` INT DEFAULT NULL COMMENT 'กรรมการที่ได้รับมอบหมาย',
    `status` ENUM('pending','normal','damaged','deteriorated','not_found') NOT NULL DEFAULT 'pending',
    `note` TEXT DEFAULT NULL,
    `photo` VARCHAR(255) DEFAULT NULL,
    `inspected_at` DATETIME DEFAULT NULL,
    `inspected_by` INT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`project_id`) REFERENCES `inspection_projects`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`asset_id`) REFERENCES `assets`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`group_id`) REFERENCES `inspection_groups`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`inspected_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================
-- 11. Notifications
-- ============================================
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `link` VARCHAR(255) DEFAULT NULL,
    `type` ENUM('info','success','warning','danger') NOT NULL DEFAULT 'info',
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- 12. Activity Logs
-- ============================================
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================
-- Default Data
-- ============================================

-- Default Admin User (password: admin123)
INSERT INTO `users` (`username`, `password`, `full_name`, `email`, `role`, `department`, `position`) VALUES
('admin', '$2y$12$9Ph1yraAtnEvkO2K5No8OeroFlb9ymnRPvXOKjo0GNiaeSDfYxit.', 'ผู้ดูแลระบบ', 'admin@office.go.th', 'admin', 'ฝ่ายบริหาร', 'ผู้ดูแลระบบ'),
('supply01', '$2y$12$9Ph1yraAtnEvkO2K5No8OeroFlb9ymnRPvXOKjo0GNiaeSDfYxit.', 'สมชาย พัสดุดี', 'supply@office.go.th', 'supply_officer', 'ฝ่ายพัสดุ', 'เจ้าหน้าที่พัสดุ'),
('user01', '$2y$12$9Ph1yraAtnEvkO2K5No8OeroFlb9ymnRPvXOKjo0GNiaeSDfYxit.', 'สมหญิง ใจดี', 'user01@office.go.th', 'user', 'ฝ่ายการเงิน', 'นักวิชาการเงิน'),
('user02', '$2y$12$9Ph1yraAtnEvkO2K5No8OeroFlb9ymnRPvXOKjo0GNiaeSDfYxit.', 'วิภาวดี สว่างดี', 'user02@office.go.th', 'user', 'ฝ่ายธุรการ', 'นักจัดการงานทั่วไป'),
('user03', '$2y$12$9Ph1yraAtnEvkO2K5No8OeroFlb9ymnRPvXOKjo0GNiaeSDfYxit.', 'ประเสริฐ มั่นคง', 'user03@office.go.th', 'user', 'ฝ่ายสารสนเทศ', 'นักวิชาการคอมพิวเตอร์');

-- Default Categories
INSERT INTO `asset_categories` (`name`, `description`) VALUES
('คอมพิวเตอร์และอุปกรณ์IT', 'คอมพิวเตอร์ โน้ตบุ๊ก เครื่องพิมพ์ อุปกรณ์เครือข่าย'),
('เครื่องใช้สำนักงาน', 'โต๊ะ เก้าอี้ ตู้เอกสาร ชั้นวาง'),
('เครื่องปรับอากาศ', 'แอร์ พัดลม เครื่องฟอกอากาศ'),
('ยานพาหนะ', 'รถยนต์ รถจักรยานยนต์'),
('เครื่องมือและอุปกรณ์', 'เครื่องมือช่าง อุปกรณ์ซ่อมบำรุง'),
('อื่นๆ', 'ครุภัณฑ์อื่นๆ ที่ไม่จัดอยู่ในหมวดหมู่ข้างต้น');

-- Sample Assets
INSERT INTO `assets` (`asset_code`, `name`, `category_id`, `brand`, `model`, `purchase_date`, `purchase_price`, `location`, `building`, `floor`, `room`, `holder_id`, `status`) VALUES
('IT-2567-001', 'คอมพิวเตอร์ตั้งโต๊ะ Dell Optiplex', 1, 'Dell', 'Optiplex 7090', '2024-03-15', 32000.00, 'อาคาร A ชั้น 2 ห้อง 201', 'อาคาร A', '2', '201', 3, 'active'),
('IT-2567-002', 'โน้ตบุ๊ก Lenovo ThinkPad', 1, 'Lenovo', 'ThinkPad T14', '2024-03-15', 45000.00, 'อาคาร A ชั้น 2 ห้อง 202', 'อาคาร A', '2', '202', 4, 'active'),
('IT-2567-003', 'เครื่องพิมพ์ Brother', 1, 'Brother', 'HL-L2370DN', '2024-05-20', 8500.00, 'อาคาร A ชั้น 1 ห้องพัสดุ', 'อาคาร A', '1', '101', 2, 'active'),
('OF-2567-001', 'โต๊ะทำงานเหล็ก', 2, 'Modernform', 'MD-120', '2024-01-10', 5500.00, 'อาคาร A ชั้น 2 ห้อง 201', 'อาคาร A', '2', '201', 3, 'active'),
('OF-2567-002', 'เก้าอี้สำนักงาน', 2, 'Modernform', 'EG-200', '2024-01-10', 4200.00, 'อาคาร A ชั้น 2 ห้อง 202', 'อาคาร A', '2', '202', 4, 'active'),
('AC-2567-001', 'เครื่องปรับอากาศ Daikin', 3, 'Daikin', 'FTKM18TV2S', '2023-11-01', 28000.00, 'อาคาร A ชั้น 2 ห้อง 201', 'อาคาร A', '2', '201', NULL, 'active'),
('IT-2567-004', 'จอมอนิเตอร์ LG', 1, 'LG', '27UK850-W', '2024-06-01', 15000.00, 'อาคาร B ชั้น 1 ห้อง 101', 'อาคาร B', '1', '101', 5, 'active'),
('OF-2567-003', 'ตู้เอกสาร 4 ลิ้นชัก', 2, 'Lucky', 'LK-4D', '2023-08-15', 6800.00, 'อาคาร B ชั้น 1 ห้อง 102', 'อาคาร B', '1', '102', NULL, 'active'),
('IT-2567-005', 'เครื่องสำรองไฟ APC', 1, 'APC', 'BX1100LI-MS', '2024-03-15', 3200.00, 'อาคาร A ชั้น 2 ห้อง 201', 'อาคาร A', '2', '201', 3, 'active'),
('VH-2567-001', 'รถยนต์ Toyota Hilux', 4, 'Toyota', 'Hilux Revo', '2023-06-01', 850000.00, 'ลานจอดรถ', 'ลานจอดรถ', '-', '-', NULL, 'active');

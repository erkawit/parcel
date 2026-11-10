-- เพิ่มคอลัมน์สำหรับเก็บไฟล์รายงานสรุปการประชุม
ALTER TABLE `committee_orders` 
ADD COLUMN `meeting_report_file` VARCHAR(255) DEFAULT NULL COMMENT 'ไฟล์รายงานสรุปการประชุม' AFTER `pdf_file`,
ADD COLUMN `meeting_report_uploaded_at` DATETIME DEFAULT NULL COMMENT 'วันที่อัปโหลดรายงาน' AFTER `meeting_report_file`,
ADD COLUMN `meeting_report_uploaded_by` INT DEFAULT NULL COMMENT 'ผู้อัปโหลดรายงาน' AFTER `meeting_report_uploaded_at`,
ADD FOREIGN KEY (`meeting_report_uploaded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL;

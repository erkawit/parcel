-- เพิ่มสิทธิ์ใหม่: department_head และ director
ALTER TABLE `users` 
MODIFY COLUMN `role` ENUM('admin', 'supply_officer', 'department_head', 'director', 'user') NOT NULL DEFAULT 'user';

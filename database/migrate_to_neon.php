<?php
/**
 * Migrate / Seed Schema & Data into Neon PostgreSQL
 */
require_once __DIR__ . '/../includes/db.php';

echo "=== GPAMS Neon PostgreSQL Migration Tool ===\n";

$neon = getNeonServicesConfig();
echo "Database Mode: " . (isPostgresDb() ? "Neon PostgreSQL" : "MySQL (Fallback)") . "\n";

if (!isPostgresDb()) {
    echo "NOTE: DATABASE_URL is not set to a PostgreSQL connection string yet.\n";
    echo "Once `neon deploy` writes DATABASE_URL into .env.local or .env, re-run `npm run db:migrate`.\n";
    exit(0);
}

$schemaFile = __DIR__ . '/schema_postgres.sql';
if (!is_file($schemaFile)) {
    fwrite(STDERR, "ERROR: Missing {$schemaFile}\n");
    exit(1);
}

echo "Applying PostgreSQL schema from database/schema_postgres.sql...\n";
$sql = file_get_contents($schemaFile);
$pdo->exec($sql);

// Ensure default rows exist in system_settings, line_settings, users, asset_categories, type_volume
$pdo->exec("
    INSERT INTO system_settings (id, org_name, org_name_en, agency_name, theme_color, logo_path)
    VALUES (1, 'ระบบบริหารจัดการครุภัณฑ์และจัดซื้อจัดจ้าง', 'Asset Management and Procurement System', 'ศาลจังหวัดอุดรธานี', 'blue', 'img/default_logo.png')
    ON CONFLICT (id) DO NOTHING
");

$pdo->exec("
    INSERT INTO line_settings (id, notify_enabled)
    VALUES (1, 0)
    ON CONFLICT (id) DO NOTHING
");

$userCount = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
if ($userCount === 0) {
    echo "Seeding default users, categories, and lookup tables...\n";
    $pdo->exec("
        INSERT INTO users (username, password, full_name, email, phone, department, position, position_level, role, status) VALUES
        ('admin', '\$2y\$10\$EA4g7CzkXJZPM.3Cs1MkXO2DDByvfZwXNb6UT7CmnRzEJcf2m.iJy', 'นายเอกวิทย์ ลาดล้าย', 'admin@office.go.th', '0643026465', 'ส่วนเทคโนโลยีสารสนเทศ', 'นักวิชาการคอมพิวเตอร์', 'ปฏิบัติการ', 'admin', 1),
        ('supply01', '\$2y\$12\$9Ph1yraAtnEvkO2K5No8OeroFlb9ymnRPvXOKjo0GNiaeSDfYxit.', 'นางณัฐรินันท์ กุบแก้ว', 'supply@office.go.th', '0953643361', 'ส่วนคลัง(งานพัสดุ)', 'นักวิชาการพัสดุ', 'ปฏิบัติการ', 'supply_officer', 1)
        ON CONFLICT (username) DO NOTHING
    ");

    $pdo->exec("
        INSERT INTO asset_categories (id, name, description) VALUES
        (1, 'คอมพิวเตอร์และอุปกรณ์IT', 'คอมพิวเตอร์ โน้ตบุ๊ก เครื่องพิมพ์ อุปกรณ์เครือข่าย'),
        (2, 'เครื่องใช้สำนักงาน', 'โต๊ะ เก้าอี้ ตู้เอกสาร ชั้นวาง'),
        (3, 'เครื่องปรับอากาศ', 'แอร์ พัดลม เครื่องฟอกอากาศ'),
        (4, 'ยานพาหนะ', 'รถยนต์ รถจักรยานยนต์'),
        (5, 'เครื่องมือและอุปกรณ์', 'เครื่องมือช่าง อุปกรณ์ซ่อมบำรุง'),
        (6, 'อื่นๆ', 'ครุภัณฑ์อื่นๆ ที่ไม่จัดอยู่ในหมวดหมู่ข้างต้น')
        ON CONFLICT (id) DO NOTHING
    ");
    $pdo->exec("SELECT setval(pg_get_serial_sequence('asset_categories', 'id'), COALESCE((SELECT MAX(id) FROM asset_categories), 1))");

    $pdo->exec("
        INSERT INTO type_volume (id, type_volume) VALUES
        (1, 'ชิ้น'), (2, 'แท่ง'), (3, 'ด้าม'), (4, 'แพ็ก'), (5, 'ห่อ'),
        (6, 'รีม'), (7, 'อัน'), (8, 'เล่ม'), (9, 'กล่อง'), (10, 'หน่วย'),
        (11, 'ถุง'), (12, 'ก้อน'), (13, 'ขวด'), (14, 'ตลับ'), (15, 'หลอด'),
        (16, 'ตัว'), (17, 'ม้วน')
        ON CONFLICT (id) DO NOTHING
    ");
    $pdo->exec("SELECT setval(pg_get_serial_sequence('type_volume', 'id'), COALESCE((SELECT MAX(id) FROM type_volume), 1))");
}

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "Migration complete! Total tables in Neon PostgreSQL: " . count($tables) . "\n";

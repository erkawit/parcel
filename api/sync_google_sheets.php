<?php
/**
 * Google Sheets Sync API
 * ดึงข้อมูลครุภัณฑ์จาก Google Sheets และนำเข้าสู่ระบบ
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireRole(['admin', 'supply_officer']);

header('Content-Type: application/json; charset=utf-8');

// Google Sheets Configuration
const GOOGLE_SHEET_ID = '1YEjb5Jmt5Y0T5nV51dyZVa8fogeyNrLqrjgs-SheBJ4';
const GOOGLE_SHEET_URL = 'https://docs.google.com/spreadsheets/d/' . GOOGLE_SHEET_ID . '/export?format=csv';

/**
 * แปลงวันที่ไทยเป็นรูปแบบ Y-m-d
 */
function parseThaiDate($thaiDate)
{
    if (empty($thaiDate))
        return null;

    // แปลงเดือนไทยเป็นตัวเลข
    $thaiMonths = [
        'ม.ค.' => '01',
        'ก.พ.' => '02',
        'มี.ค.' => '03',
        'เม.ย.' => '04',
        'พ.ค.' => '05',
        'มิ.ย.' => '06',
        'ก.ค.' => '07',
        'ส.ค.' => '08',
        'ก.ย.' => '09',
        'ต.ค.' => '10',
        'พ.ย.' => '11',
        'ธ.ค.' => '12'
    ];

    // รูปแบบ: "1 ก.ย. 59" หรือ "10 ก.ค. 67"
    if (preg_match('/(\d+)\s+([^\s]+)\s+(\d+)/', $thaiDate, $matches)) {
        $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
        $month = $thaiMonths[$matches[2]] ?? '01';
        $year = $matches[3];

        // แปลงปี พ.ศ. 2 หลักเป็น 4 หลัก
        if (strlen($year) == 2) {
            $year = (int) $year;
            // ถ้าเป็น 00-50 ถือว่าเป็น 2500-2550
            // ถ้าเป็น 51-99 ถือว่าเป็น 2551-2599
            $year = ($year <= 50) ? 2500 + $year : 2500 + $year;
        }

        // แปลง พ.ศ. เป็น ค.ศ.
        $year = (int) $year - 543;

        return sprintf('%04d-%s-%s', $year, $month, $day);
    }

    return null;
}

/**
 * ดึงข้อมูลจาก Google Sheets
 */
function fetchGoogleSheetData()
{
    $csvData = @file_get_contents(GOOGLE_SHEET_URL);

    if ($csvData === false) {
        return ['success' => false, 'message' => 'ไม่สามารถเชื่อมต่อกับ Google Sheets ได้'];
    }

    $lines = explode("\n", $csvData);
    $data = [];
    $headers = null;

    foreach ($lines as $line) {
        $row = str_getcsv($line);

        if ($headers === null) {
            $headers = $row;
            continue;
        }

        if (count($row) >= 7 && !empty($row[0])) {
            $data[] = [
                'id' => $row[0],
                'durable_no' => $row[1],
                'durable_name' => $row[2],
                'durable_description' => $row[3],
                'date' => $row[4],
                'location' => $row[5],
                'status' => $row[6]
            ];
        }
    }

    return ['success' => true, 'data' => $data];
}

/**
 * หาหรือสร้างหมวดหมู่จากชื่อครุภัณฑ์
 */
function getCategoryId($pdo, $assetName)
{
    // กำหนดคีย์เวิร์ดสำหรับแต่ละหมวดหมู่
    $categoryKeywords = [
        'คอมพิวเตอร์และอุปกรณ์IT' => ['กล้อง', 'คอมพิวเตอร์', 'โน้ตบุ๊ก', 'เครื่องพิมพ์', 'IP', 'CAMERA', 'Webcam', 'Hard Disk', 'NVR', 'กระดานอิเล็กทรอนิกส์'],
        'เครื่องใช้สำนักงาน' => ['เก้าอี้', 'โต๊ะ', 'ตู้', 'ชั้น'],
        'เครื่องปรับอากาศ' => ['แอร์', 'ปรับอากาศ', 'พัดลม'],
        'ยานพาหนะ' => ['รถ', 'จักรยาน'],
        'เครื่องมือและอุปกรณ์' => ['กระบอง', 'เครื่องมือ']
    ];

    foreach ($categoryKeywords as $categoryName => $keywords) {
        foreach ($keywords as $keyword) {
            if (stripos($assetName, $keyword) !== false) {
                $stmt = $pdo->prepare("SELECT id FROM asset_categories WHERE name = ?");
                $stmt->execute([$categoryName]);
                $result = $stmt->fetch();
                if ($result) {
                    return $result['id'];
                }
            }
        }
    }

    // ถ้าไม่เจอ ใช้หมวด "อื่นๆ"
    $stmt = $pdo->prepare("SELECT id FROM asset_categories WHERE name = 'อื่นๆ'");
    $stmt->execute();
    $result = $stmt->fetch();
    return $result ? $result['id'] : null;
}

// POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $data['action'] ?? '';

    if ($action === 'sync') {
        try {
            // ดึงข้อมูลจาก Google Sheets
            $result = fetchGoogleSheetData();

            if (!$result['success']) {
                echo json_encode($result);
                exit;
            }

            $sheetData = $result['data'];
            $imported = 0;
            $updated = 0;
            $skipped = 0;
            $errors = [];

            foreach ($sheetData as $row) {
                try {
                    $assetCode = trim($row['durable_no']);
                    $assetName = trim($row['durable_name']);
                    $description = trim($row['durable_description']);
                    $location = trim($row['location']);
                    $purchaseDate = parseThaiDate($row['date']);
                    $status = ($row['status'] == '1') ? 'active' : 'retired';

                    if (empty($assetCode) || empty($assetName)) {
                        $skipped++;
                        continue;
                    }

                    // หาหมวดหมู่
                    $categoryId = getCategoryId($pdo, $assetName);

                    // แยก brand และ model จาก description
                    $brand = null;
                    $model = null;
                    if (preg_match('/ยี่ห้อ\s+([^\s]+)/', $description, $matches)) {
                        $brand = $matches[1];
                    }
                    if (preg_match('/รุ่น\s+([^\s,]+)/', $description, $matches)) {
                        $model = $matches[1];
                    }

                    // ตรวจสอบว่ามีครุภัณฑ์นี้อยู่แล้วหรือไม่
                    $stmt = $pdo->prepare("SELECT id FROM assets WHERE asset_code = ?");
                    $stmt->execute([$assetCode]);
                    $existing = $stmt->fetch();

                    if ($existing) {
                        // อัปเดตข้อมูล
                        $stmt = $pdo->prepare("UPDATE assets SET 
                            name = ?, 
                            description = ?, 
                            category_id = ?, 
                            brand = ?, 
                            model = ?, 
                            purchase_date = ?, 
                            location = ?, 
                            status = ?,
                            updated_at = NOW()
                            WHERE asset_code = ?");

                        $stmt->execute([
                            $assetName,
                            $description,
                            $categoryId,
                            $brand,
                            $model,
                            $purchaseDate,
                            $location,
                            $status,
                            $assetCode
                        ]);
                        $updated++;
                    } else {
                        // เพิ่มใหม่
                        $stmt = $pdo->prepare("INSERT INTO assets 
                            (asset_code, name, description, category_id, brand, model, purchase_date, location, status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

                        $stmt->execute([
                            $assetCode,
                            $assetName,
                            $description,
                            $categoryId,
                            $brand,
                            $model,
                            $purchaseDate,
                            $location,
                            $status
                        ]);
                        $imported++;
                    }

                } catch (Exception $e) {
                    $errors[] = "รหัส {$assetCode}: " . $e->getMessage();
                }
            }

            // บันทึก Activity Log
            logActivity(
                $_SESSION['user_id'],
                'google_sheets_sync',
                "ซิงค์ข้อมูลจาก Google Sheets: นำเข้า {$imported} รายการ, อัปเดต {$updated} รายการ"
            );

            echo json_encode([
                'success' => true,
                'imported' => $imported,
                'updated' => $updated,
                'skipped' => $skipped,
                'total' => count($sheetData),
                'errors' => $errors
            ]);

        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
            ]);
        }
        exit;
    }

    if ($action === 'preview') {
        // แสดงตัวอย่างข้อมูลจาก Google Sheets
        $result = fetchGoogleSheetData();

        if (!$result['success']) {
            echo json_encode($result);
            exit;
        }

        // แสดงเฉพาะ 10 รายการแรก
        $preview = array_slice($result['data'], 0, 10);

        echo json_encode([
            'success' => true,
            'preview' => $preview,
            'total' => count($result['data'])
        ]);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);

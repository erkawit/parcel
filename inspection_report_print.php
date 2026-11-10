<?php
/**
 * Printable Inspection Summary Report - รายงานสรุปผลการตรวจสอบครุภัณฑ์ประจำปี (พิมพ์ / PDF)
 */
require_once 'includes/auth.php';
requireLogin();

$projectId = intval($_GET['project'] ?? ($_GET['id'] ?? 0));
if (!$projectId) {
    header('Location: inspection.php');
    exit;
}

// Get system settings
$settings = $pdo->query("SELECT * FROM system_settings WHERE id = 1")->fetch();
$orgName = $settings['org_name'] ?? 'ระบบบริหารจัดการครุภัณฑ์';

// Get project & committee order
$stmt = $pdo->prepare("SELECT ip.*, co.order_number, co.order_date, co.fiscal_year as order_year, u.full_name as creator_name
                       FROM inspection_projects ip
                       LEFT JOIN committee_orders co ON ip.committee_order_id = co.id
                       LEFT JOIN users u ON ip.created_by = u.id
                       WHERE ip.id = ?");
$stmt->execute([$projectId]);
$project = $stmt->fetch();

if (!$project) {
    header('Location: inspection.php');
    exit;
}

// Get committee members
$members = [];
if ($project['committee_order_id']) {
    $mStmt = $pdo->prepare("SELECT cm.role, u.full_name, u.position, u.department
                            FROM committee_members cm
                            JOIN users u ON cm.user_id = u.id
                            WHERE cm.order_id = ?
                            ORDER BY FIELD(cm.role, 'chairman', 'member', 'secretary'), cm.id");
    $mStmt->execute([$project['committee_order_id']]);
    $members = $mStmt->fetchAll();
}

// Get all inspection items
$iStmt = $pdo->prepare("SELECT ii.*, a.asset_code, a.name as asset_name, a.brand, a.model, a.location, a.purchase_price,
                        c.name as category_name, u.full_name as holder_name, ui.full_name as inspector_name
                        FROM inspection_items ii
                        JOIN assets a ON ii.asset_id = a.id
                        LEFT JOIN asset_categories c ON a.category_id = c.id
                        LEFT JOIN users u ON a.holder_id = u.id
                        LEFT JOIN users ui ON ii.inspected_by = ui.id
                        WHERE ii.project_id = ?
                        ORDER BY a.asset_code");
$iStmt->execute([$projectId]);
$items = $iStmt->fetchAll();

$counts = ['total' => count($items), 'normal' => 0, 'damaged' => 0, 'deteriorated' => 0, 'not_found' => 0, 'pending' => 0];
$totalValue = 0;
$issueItems = [];

foreach ($items as $it) {
    $st = $it['status'] ?? 'pending';
    if (isset($counts[$st])) {
        $counts[$st]++;
    }
    $totalValue += floatval($it['purchase_price'] ?? 0);
    if (in_array($st, ['damaged', 'deteriorated', 'not_found'])) {
        $issueItems[] = $it;
    }
}

$statusLabels = [
    'pending' => 'รอตรวจ',
    'normal' => 'ปกติ',
    'damaged' => 'ชำรุด',
    'deteriorated' => 'เสื่อมสภาพ',
    'not_found' => 'ไม่พบ/สูญหาย'
];
$roleLabels = [
    'chairman' => 'ประธานกรรมการ',
    'member' => 'กรรมการ',
    'secretary' => 'กรรมการและเลขานุการ'
];
$showMode = $_GET['mode'] ?? 'issues'; // 'issues' or 'all'
$displayItems = $showMode === 'all' ? $items : $issueItems;
$faviconPath = (!empty($settings['logo_path']) && file_exists(__DIR__ . '/' . $settings['logo_path']))
    ? $settings['logo_path']
    : 'img/default_logo.png';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายงานสรุปผลการตรวจสอบครุภัณฑ์ - <?= htmlspecialchars($project['name']) ?></title>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($faviconPath) ?>">
    <link rel="shortcut icon" type="image/png" href="<?= htmlspecialchars($faviconPath) ?>">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($faviconPath) ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Sarabun', sans-serif; }
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; padding: 0 !important; }
            .print-sheet { box-shadow: none !important; border: none !important; margin: 0 !important; max-width: 100% !important; padding: 10mm !important; }
        }
    </style>
</head>
<body class="bg-gray-100 py-6 px-4">
    <!-- Top Action Bar -->
    <div class="no-print max-w-5xl mx-auto mb-4 bg-white p-4 rounded-xl shadow-sm border border-gray-200 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <a href="inspection.php" class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm font-semibold transition">
                <i class="fas fa-arrow-left mr-1"></i> กลับหน้าตรวจสอบ
            </a>
            <div class="flex bg-gray-100 p-1 rounded-lg text-xs font-semibold">
                <a href="?project=<?= $projectId ?>&mode=issues" class="px-3 py-1.5 rounded-md <?= $showMode === 'issues' ? 'bg-blue-900 text-white' : 'text-gray-600' ?>">
                    แสดงเฉพาะรายการพบปัญหา (<?= count($issueItems) ?>)
                </a>
                <a href="?project=<?= $projectId ?>&mode=all" class="px-3 py-1.5 rounded-md <?= $showMode === 'all' ? 'bg-blue-900 text-white' : 'text-gray-600' ?>">
                    แสดงครุภัณฑ์ทั้งหมด (<?= count($items) ?>)
                </a>
            </div>
        </div>
        <button onclick="window.print()" class="px-5 py-2.5 bg-blue-900 hover:bg-blue-800 text-white rounded-xl text-sm font-bold shadow flex items-center gap-2">
            <i class="fas fa-print"></i> พิมพ์รายงาน / บันทึกเป็น PDF
        </button>
    </div>

    <!-- Printable Sheet -->
    <div class="print-sheet max-w-5xl mx-auto bg-white p-8 md:p-12 rounded-xl shadow-md border border-gray-200 text-gray-900">
        <div class="text-center mb-6">
            <h1 class="text-xl font-bold"><?= htmlspecialchars($orgName) ?></h1>
            <h2 class="text-lg font-bold mt-1">รายงานสรุปผลการตรวจสอบครุภัณฑ์ประจำปีงบประมาณ พ.ศ. <?= htmlspecialchars($project['fiscal_year']) ?></h2>
            <p class="text-sm text-gray-600 mt-1">
                โครงการ: <strong><?= htmlspecialchars($project['name']) ?></strong>
                <?php if (!empty($project['order_number'])): ?>
                    | ตามคำสั่งแต่งตั้งเลขที่ <strong><?= htmlspecialchars($project['order_number']) ?></strong>
                <?php endif; ?>
                <?php if (!empty($project['order_date'])): ?>
                    ลงวันที่ <?= formatThaiDate($project['order_date']) ?>
                <?php endif; ?>
            </p>
            <p class="text-xs text-gray-500 mt-1">วันที่พิมพ์รายงาน: <?= formatThaiDate(date('Y-m-d')) ?></p>
        </div>

        <!-- Summary Box -->
        <div class="mb-6">
            <h3 class="font-bold text-sm mb-2 border-b pb-1">1. สรุปผลการตรวจสอบครุภัณฑ์</h3>
            <table class="w-full border-collapse border border-gray-400 text-sm text-center">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="border border-gray-400 p-2">รายการทั้งหมด</th>
                        <th class="border border-gray-400 p-2 text-green-800">ใช้งานปกติ</th>
                        <th class="border border-gray-400 p-2 text-orange-800">ชำรุด</th>
                        <th class="border border-gray-400 p-2 text-red-800">เสื่อมสภาพ</th>
                        <th class="border border-gray-400 p-2 text-gray-800">ไม่พบ/สูญหาย</th>
                        <th class="border border-gray-400 p-2 text-gray-500">รอตรวจสอบ</th>
                        <th class="border border-gray-400 p-2">มูลค่ารวมทั้งสิ้น (บาท)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="font-bold text-base">
                        <td class="border border-gray-400 p-2"><?= number_format($counts['total']) ?></td>
                        <td class="border border-gray-400 p-2 text-green-700"><?= number_format($counts['normal']) ?></td>
                        <td class="border border-gray-400 p-2 text-orange-700"><?= number_format($counts['damaged']) ?></td>
                        <td class="border border-gray-400 p-2 text-red-700"><?= number_format($counts['deteriorated']) ?></td>
                        <td class="border border-gray-400 p-2 text-gray-800"><?= number_format($counts['not_found']) ?></td>
                        <td class="border border-gray-400 p-2 text-gray-500"><?= number_format($counts['pending']) ?></td>
                        <td class="border border-gray-400 p-2"><?= number_format($totalValue, 2) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Table of Items -->
        <div class="mb-8">
            <h3 class="font-bold text-sm mb-2 border-b pb-1">
                2. <?= $showMode === 'all' ? 'บัญชีรายการครุภัณฑ์ที่ตรวจสอบทั้งหมด' : 'รายการครุภัณฑ์ที่ตรวจพบความผิดปกติ (ชำรุด / เสื่อมสภาพ / ไม่พบ)' ?>
                (<?= number_format(count($displayItems)) ?> รายการ)
            </h3>
            <?php if (empty($displayItems)): ?>
                <div class="border border-gray-300 rounded p-4 text-center text-sm text-gray-600">
                    ไม่พบรายการครุภัณฑ์ที่ชำรุด เสื่อมสภาพ หรือสูญหาย (ครุภัณฑ์อยู่ในสภาพปกติทุกรายการ)
                </div>
            <?php else: ?>
                <table class="w-full border-collapse border border-gray-400 text-xs">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="border border-gray-400 p-1.5 w-10 text-center">ลำดับ</th>
                            <th class="border border-gray-400 p-1.5 text-left">รหัสครุภัณฑ์</th>
                            <th class="border border-gray-400 p-1.5 text-left">รายการ / ชื่อครุภัณฑ์</th>
                            <th class="border border-gray-400 p-1.5 text-left">ที่ตั้ง</th>
                            <th class="border border-gray-400 p-1.5 text-left">ผู้ถือครอง</th>
                            <th class="border border-gray-400 p-1.5 text-center w-24">ผลการตรวจ</th>
                            <th class="border border-gray-400 p-1.5 text-left">หมายเหตุ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($displayItems as $idx => $item): ?>
                            <tr>
                                <td class="border border-gray-400 p-1.5 text-center"><?= $idx + 1 ?></td>
                                <td class="border border-gray-400 p-1.5 font-mono"><?= htmlspecialchars($item['asset_code']) ?></td>
                                <td class="border border-gray-400 p-1.5"><?= htmlspecialchars($item['asset_name']) ?></td>
                                <td class="border border-gray-400 p-1.5"><?= htmlspecialchars($item['location'] ?? '-') ?></td>
                                <td class="border border-gray-400 p-1.5"><?= htmlspecialchars($item['holder_name'] ?? '-') ?></td>
                                <td class="border border-gray-400 p-1.5 text-center font-semibold"><?= htmlspecialchars($statusLabels[$item['status']] ?? $item['status']) ?></td>
                                <td class="border border-gray-400 p-1.5"><?= htmlspecialchars($item['note'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Signatures Section -->
        <div class="mt-10 page-break-inside-avoid">
            <h3 class="font-bold text-sm mb-6 border-b pb-1">3. คณะกรรมการตรวจสอบครุภัณฑ์ได้ตรวจสอบถูกต้องแล้ว จึงลงลายมือชื่อไว้เป็นหลักฐาน</h3>
            <?php if (empty($members)): ?>
                <div class="grid grid-cols-2 gap-8 text-center text-sm mt-8">
                    <div>
                        <p>ลงชื่อ ....................................................... ประธานกรรมการ</p>
                        <p class="mt-1">( ....................................................... )</p>
                    </div>
                    <div>
                        <p>ลงชื่อ ....................................................... กรรมการและเลขานุการ</p>
                        <p class="mt-1">( ....................................................... )</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-y-10 gap-x-8 text-center text-sm mt-6">
                    <?php foreach ($members as $m): ?>
                        <div>
                            <p>ลงชื่อ .......................................................</p>
                            <p class="font-semibold mt-1">( <?= htmlspecialchars($m['full_name']) ?> )</p>
                            <?php if (!empty($m['position'])): ?>
                                <p class="text-xs text-gray-600"><?= htmlspecialchars($m['position']) ?></p>
                            <?php endif; ?>
                            <p class="text-xs font-bold mt-0.5"><?= htmlspecialchars($roleLabels[$m['role']] ?? $m['role']) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

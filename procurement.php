<?php
/**
 * Procurement Dashboard - ระบบจัดซื้อจัดจ้างภาครัฐ และจัดทำเอกสารราชการ Word (.docx)
 */
require_once 'includes/auth.php';
requireLogin();

$pageTitle = 'ระบบจัดซื้อ/จัดจ้าง';
$currentUser = getCurrentUser();
$isManager = hasRole(['admin', 'supply_officer']);

// Ensure schema columns exist
try {
    $cols = $pdo->query("SHOW COLUMNS FROM procurements")->fetchAll(PDO::FETCH_COLUMN);
    $alterations = [
        'vendor_address' => "ALTER TABLE procurements ADD COLUMN vendor_address VARCHAR(255) NULL DEFAULT NULL AFTER vendor_name",
        'vendor_tax_id' => "ALTER TABLE procurements ADD COLUMN vendor_tax_id VARCHAR(30) NULL DEFAULT NULL AFTER vendor_address",
        'budget_source' => "ALTER TABLE procurements ADD COLUMN budget_source VARCHAR(255) NULL DEFAULT NULL AFTER buy_reason",
        'head_director_name' => "ALTER TABLE procurements ADD COLUMN head_director_name VARCHAR(150) NULL DEFAULT NULL AFTER head_officer_id",
        'head_director_position' => "ALTER TABLE procurements ADD COLUMN head_director_position VARCHAR(150) NULL DEFAULT NULL AFTER head_director_name",
        'inspect_date' => "ALTER TABLE procurements ADD COLUMN inspect_date DATE NULL DEFAULT NULL AFTER delivery_date",
        'service_request_id' => "ALTER TABLE procurements ADD COLUMN service_request_id INT NULL DEFAULT NULL AFTER requester_id"
    ];
    foreach ($alterations as $col => $sql) {
        if (!in_array($col, $cols)) {
            $pdo->exec($sql);
        }
    }
    $itemCols = $pdo->query("SHOW COLUMNS FROM procurement_items")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('unit', $itemCols)) {
        $pdo->exec("ALTER TABLE procurement_items ADD COLUMN unit VARCHAR(50) NULL DEFAULT 'รายการ' AFTER quantity");
    }
    if (!in_array('spec_detail', $itemCols)) {
        $pdo->exec("ALTER TABLE procurement_items ADD COLUMN spec_detail TEXT NULL DEFAULT NULL AFTER item_name");
    }
} catch (Exception $e) {
}

// Filters
$year = $_GET['year'] ?? (date('Y') + 543);
$tab = $_GET['tab'] ?? 'all'; // all, repair, credit, cash
$statusFilter = $_GET['status'] ?? 'all';
$search = trim($_GET['q'] ?? '');

// Build Query
$where = ["p.fiscal_year = ?"];
$params = [$year];

if ($tab !== 'all') {
    $where[] = "p.type = ?";
    $params[] = $tab;
}

if ($statusFilter !== 'all') {
    $where[] = "p.status = ?";
    $params[] = $statusFilter;
}

if ($search !== '') {
    $where[] = "(p.doc_no LIKE ? OR p.subject LIKE ? OR p.vendor_name LIKE ? OR p.receipt_no LIKE ? OR u.full_name LIKE ?)";
    $like = "%{$search}%";
    array_push($params, $like, $like, $like, $like, $like);
}

if (!$isManager) {
    $where[] = "p.requester_id = ?";
    $params[] = $currentUser['id'];
}

$whereSQL = implode(' AND ', $where);

$stmt = $pdo->prepare("SELECT p.*, 
                       u.full_name as requester_name,
                       u_insp.full_name as inspector_name,
                       (SELECT COUNT(*) FROM procurement_items pi WHERE pi.procurement_id = p.id) as item_count
                       FROM procurements p 
                       LEFT JOIN users u ON p.requester_id = u.id 
                       LEFT JOIN users u_insp ON p.inspector_id = u_insp.id
                       WHERE $whereSQL 
                       ORDER BY p.doc_date DESC, p.id DESC");
$stmt->execute($params);
$items = $stmt->fetchAll();

// Summary KPIs for the selected year
$kpiStmt = $pdo->prepare("SELECT 
    COUNT(*) as total_count,
    COALESCE(SUM(CASE WHEN status != 'cancelled' THEN total_amount ELSE 0 END), 0) as total_budget,
    SUM(CASE WHEN type = 'repair' AND status != 'cancelled' THEN 1 ELSE 0 END) as repair_count,
    SUM(CASE WHEN type = 'credit' AND status != 'cancelled' THEN 1 ELSE 0 END) as credit_count,
    SUM(CASE WHEN type = 'cash' AND status != 'cancelled' THEN 1 ELSE 0 END) as cash_count,
    SUM(CASE WHEN status IN ('pending', 'approved') THEN 1 ELSE 0 END) as active_count,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count
    FROM procurements WHERE fiscal_year = ?");
$kpiStmt->execute([$year]);
$kpi = $kpiStmt->fetch();

// Pending service requests from users (for supply officer quick import)
$pendingRequests = [];
if ($isManager) {
    try {
        $srStmt = $pdo->query("SELECT sr.*, u.full_name as user_name, u.department
                               FROM service_requests sr
                               LEFT JOIN users u ON sr.user_id = u.id
                               WHERE sr.status = 'pending'
                               ORDER BY sr.created_at DESC LIMIT 10");
        $pendingRequests = $srStmt->fetchAll();
    } catch (Exception $e) {
    }
}

// Get fiscal years for filter
$years = $pdo->query("SELECT DISTINCT fiscal_year FROM procurements ORDER BY fiscal_year DESC")->fetchAll(PDO::FETCH_COLUMN);
if (empty($years)) {
    $years = [date('Y') + 543];
}
if (!in_array(date('Y') + 543, $years)) {
    array_unshift($years, date('Y') + 543);
}

include 'includes/header.php';
include 'includes/sidebar.php';

$statusBadges = [
    'draft' => 'bg-gray-100 text-gray-600 border-gray-200',
    'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
    'approved' => 'bg-blue-100 text-blue-800 border-blue-200',
    'completed' => 'bg-green-100 text-green-800 border-green-200',
    'cancelled' => 'bg-red-100 text-red-800 border-red-200'
];
$statusLabels = [
    'draft' => 'ร่างเอกสาร',
    'pending' => 'รออนุมัติ/ดำเนินการ',
    'approved' => 'อนุมัติสั่งซื้อ/จ้างแล้ว',
    'completed' => 'ตรวจรับ/เบิกจ่ายแล้ว',
    'cancelled' => 'ยกเลิก'
];
$typeLabels = [
    'repair' => '<span class="px-2.5 py-1 rounded-full text-xs font-bold bg-orange-100 text-orange-800 border border-orange-200 inline-flex items-center gap-1"><i class="fas fa-tools text-[10px]"></i> จ้างซ่อม</span>',
    'credit' => '<span class="px-2.5 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-800 border border-blue-200 inline-flex items-center gap-1"><i class="fas fa-file-invoice text-[10px]"></i> ซื้อ (เครดิต)</span>',
    'cash' => '<span class="px-2.5 py-1 rounded-full text-xs font-bold bg-green-100 text-green-800 border border-green-200 inline-flex items-center gap-1"><i class="fas fa-money-bill-wave text-[10px]"></i> ซื้อ (เงินสด)</span>'
];
?>

<!-- Header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-extrabold text-gray-800 flex items-center gap-2">
            <i class="fas fa-shopping-cart text-blue-900"></i> ระบบจัดซื้อจัดจ้างภาครัฐ และจัดทำหนังสือราชการ
        </h1>
        <p class="text-gray-500 text-sm mt-1">
            รองรับการจัดทำบันทึกข้อความ รายงานขอซื้อ/ขอจ้าง ตาราง ป.ป.ช. ใบตรวจรับ และขออนุมัติเบิกจ่ายในรูปแบบไฟล์ Word (.docx)
        </p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="procurement_form.php?type=<?= $tab !== 'all' ? htmlspecialchars($tab) : 'credit' ?>"
            class="btn-press bg-blue-900 text-white px-5 py-2.5 rounded-xl font-bold border-b-4 border-yellow-600 hover:bg-blue-800 flex items-center gap-2 shadow-lg text-sm">
            <i class="fas fa-plus"></i> สร้างชุดเอกสารจัดซื้อ/จัดจ้าง
        </a>
    </div>
</div>

<!-- KPI Summary Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-blue-100 text-blue-900 flex items-center justify-center text-xl">
            <i class="fas fa-coins"></i>
        </div>
        <div>
            <p class="text-xs text-gray-500 font-semibold">วงเงินจัดซื้อจัดจ้างรวม (ปี <?= htmlspecialchars($year) ?>)</p>
            <p class="text-xl font-extrabold text-blue-900 font-mono"><?= number_format(floatval($kpi['total_budget'] ?? 0), 2) ?> <span class="text-xs font-sans font-normal text-gray-500">บาท</span></p>
        </div>
    </div>

    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-indigo-100 text-indigo-800 flex items-center justify-center text-xl">
            <i class="fas fa-folder-open"></i>
        </div>
        <div>
            <p class="text-xs text-gray-500 font-semibold">จำนวนโครงการทั้งหมด</p>
            <p class="text-xl font-extrabold text-gray-800"><?= intval($kpi['total_count'] ?? 0) ?> <span class="text-xs font-normal text-gray-500">รายการ</span></p>
            <p class="text-[11px] text-gray-400">ซื้อเครดิต <?= intval($kpi['credit_count'] ?? 0) ?> | ซ่อม <?= intval($kpi['repair_count'] ?? 0) ?> | เงินสด <?= intval($kpi['cash_count'] ?? 0) ?></p>
        </div>
    </div>

    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center text-xl">
            <i class="fas fa-clock"></i>
        </div>
        <div>
            <p class="text-xs text-gray-500 font-semibold">อยู่ระหว่างดำเนินการ/รอตรวจรับ</p>
            <p class="text-xl font-extrabold text-amber-700"><?= intval($kpi['active_count'] ?? 0) ?> <span class="text-xs font-normal text-gray-500">รายการ</span></p>
        </div>
    </div>

    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-green-100 text-green-700 flex items-center justify-center text-xl">
            <i class="fas fa-check-double"></i>
        </div>
        <div>
            <p class="text-xs text-gray-500 font-semibold">ตรวจรับและเบิกจ่ายเสร็จสิ้น</p>
            <p class="text-xl font-extrabold text-green-700"><?= intval($kpi['completed_count'] ?? 0) ?> <span class="text-xs font-normal text-gray-500">รายการ</span></p>
        </div>
    </div>
</div>

<!-- Pending Service Requests Alert for Supply Officer -->
<?php if ($isManager && !empty($pendingRequests)): ?>
    <div class="bg-amber-50/90 border border-amber-200 rounded-xl p-4 mb-6 shadow-sm">
        <div class="flex items-center justify-between mb-2">
            <h3 class="text-sm font-bold text-amber-900 flex items-center gap-2">
                <span class="w-6 h-6 rounded-full bg-amber-500 text-white inline-flex items-center justify-center text-xs"><?= count($pendingRequests) ?></span>
                คำขอแจ้งซ่อม / ขอจัดซื้อจากผู้ใช้งานที่รอดำเนินการออกเลขที่หนังสือพัสดุ
            </h3>
            <span class="text-xs text-amber-700">คลิก "ออกชุดเอกสารพัสดุ" เพื่อดึงข้อมูลไปสร้างบันทึกข้อความอัตโนมัติ</span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5 mt-2">
            <?php foreach (array_slice($pendingRequests, 0, 4) as $req): ?>
                <div class="bg-white p-3 rounded-lg border border-amber-200 flex items-center justify-between gap-2">
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= $req['request_type'] === 'repair' ? 'bg-orange-100 text-orange-800' : 'bg-blue-100 text-blue-800' ?>">
                                <?= $req['request_type'] === 'repair' ? 'แจ้งซ่อม' : 'ขอซื้อ' ?>
                            </span>
                            <p class="text-xs font-bold text-gray-800 truncate"><?= htmlspecialchars($req['asset_name'] ?? '') ?> <?= htmlspecialchars($req['asset_description'] ?? '') ?></p>
                        </div>
                        <p class="text-[11px] text-gray-500 mt-0.5 truncate">
                            ผู้แจ้ง: <?= htmlspecialchars($req['user_name'] ?? '-') ?> (<?= htmlspecialchars($req['department'] ?? '-') ?>)
                        </p>
                    </div>
                    <div class="flex items-center gap-1.5 shrink-0">
                        <?php if (!empty($req['file_path']) && file_exists(__DIR__ . '/' . $req['file_path'])): ?>
                            <a href="<?= htmlspecialchars($req['file_path']) ?>" target="_blank" title="ดาวน์โหลดบันทึกแจ้งซ่อมต้นเรื่อง (.docx)"
                                class="px-2.5 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-xs font-bold">
                                <i class="fas fa-file-word text-blue-700"></i>
                            </a>
                        <?php endif; ?>
                        <a href="procurement_form.php?from_request=<?= $req['id'] ?>"
                            class="px-3 py-1.5 bg-blue-900 hover:bg-blue-800 text-white rounded-lg text-xs font-bold whitespace-nowrap">
                            <i class="fas fa-magic mr-1"></i>ออกชุดเอกสารพัสดุ
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Filters & Search -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
    <div class="flex flex-col lg:flex-row gap-4 items-center justify-between">
        <div class="flex flex-wrap gap-1.5 bg-gray-100 p-1 rounded-lg w-full lg:w-auto">
            <?php
            $tabs = [
                'all' => 'ทั้งหมด',
                'credit' => 'ซื้อ (เครดิต)',
                'repair' => 'จ้างซ่อม',
                'cash' => 'ซื้อ (เงินสด)'
            ];
            foreach ($tabs as $k => $v):
                $active = $tab === $k ? 'bg-white text-blue-900 shadow-sm font-bold' : 'text-gray-500 hover:text-gray-700';
            ?>
                <a href="?year=<?= urlencode($year) ?>&tab=<?= $k ?>&status=<?= urlencode($statusFilter) ?>&q=<?= urlencode($search) ?>"
                    class="px-3.5 py-2 rounded-md text-xs sm:text-sm transition-all <?= $active ?>">
                    <?= $v ?>
                </a>
            <?php endforeach; ?>
        </div>

        <form method="GET" class="flex flex-wrap items-center gap-2 w-full lg:w-auto justify-end">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">

            <div class="relative flex-1 sm:w-56">
                <i class="fas fa-search absolute left-3 top-2.5 text-gray-400 text-xs"></i>
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                    placeholder="ค้นหาเลขที่, เรื่อง, ร้านค้า..."
                    class="w-full pl-8 pr-3 py-1.5 border border-gray-200 rounded-lg text-sm focus:border-blue-900 focus:outline-none">
            </div>

            <select name="status" onchange="this.form.submit()"
                class="border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:border-blue-900 focus:outline-none bg-white">
                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>ทุกสถานะ</option>
                <?php foreach ($statusLabels as $stKey => $stName): ?>
                    <option value="<?= $stKey ?>" <?= $statusFilter === $stKey ? 'selected' : '' ?>><?= $stName ?></option>
                <?php endforeach; ?>
            </select>

            <div class="flex items-center gap-1.5">
                <span class="text-xs font-semibold text-gray-600">ปีงบ:</span>
                <select name="year" onchange="this.form.submit()"
                    class="border border-gray-200 rounded-lg px-3 py-1.5 text-sm font-bold text-blue-900 focus:border-blue-900 focus:outline-none bg-white">
                    <?php foreach ($years as $y): ?>
                        <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="px-3 py-1.5 bg-blue-900 text-white rounded-lg text-sm font-bold hover:bg-blue-800">
                ค้นหา
            </button>
        </form>
    </div>
</div>

<!-- DATA TABLE -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="data-table w-full">
            <thead>
                <tr>
                    <th class="w-28">วันที่</th>
                    <th class="w-28">เลขที่หนังสือ</th>
                    <th class="w-32">ประเภท</th>
                    <th>เรื่อง / ร้านค้าผู้รับจ้าง</th>
                    <th class="text-right w-36">วงเงิน (บาท)</th>
                    <th class="w-40">ผู้ขอ / ผู้ตรวจรับ</th>
                    <th class="text-center w-36">สถานะ</th>
                    <th class="text-center w-52">เอกสาร Word / จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-10 text-gray-400">
                            <i class="fas fa-file-word text-4xl mb-2 text-gray-300"></i>
                            <p class="font-semibold text-gray-500">ไม่พบรายการจัดซื้อจัดจ้างตามเงื่อนไขที่เลือก</p>
                            <a href="procurement_form.php" class="inline-block mt-3 text-xs font-bold text-blue-800 hover:underline">
                                + คลิกที่นี่เพื่อสร้างชุดเอกสารจัดซื้อ/จัดจ้างใหม่
                            </a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="text-xs text-gray-600 whitespace-nowrap">
                                <div class="font-semibold text-gray-800"><?= formatThaiDate($item['doc_date']) ?></div>
                                <?php if (!empty($item['inspect_date']) && $item['inspect_date'] !== $item['doc_date']): ?>
                                    <div class="text-[11px] text-green-700 mt-0.5" title="วันที่ตรวจรับพัสดุ">
                                        <i class="fas fa-clipboard-check"></i> <?= formatThaiDate($item['inspect_date']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="font-mono text-sm font-bold text-blue-900 whitespace-nowrap">
                                <?= htmlspecialchars($item['doc_no']) ?>
                                <div class="text-[11px] font-sans font-normal text-gray-400"><?= intval($item['item_count']) ?> รายการ</div>
                            </td>
                            <td class="whitespace-nowrap">
                                <?= $typeLabels[$item['type']] ?? $item['type'] ?>
                            </td>
                            <td class="font-medium text-gray-800">
                                <div class="font-bold text-sm text-gray-900"><?= htmlspecialchars($item['subject']) ?></div>
                                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500 mt-1">
                                    <?php if (!empty($item['vendor_name'])): ?>
                                        <span><i class="fas fa-store text-purple-600 mr-1"></i><?= htmlspecialchars($item['vendor_name']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($item['receipt_no'])): ?>
                                        <span><i class="fas fa-receipt text-green-600 mr-1"></i>เลขที่ใบส่งของ: <?= htmlspecialchars($item['receipt_no']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-right font-mono font-bold text-blue-900 whitespace-nowrap">
                                <?= number_format(floatval($item['total_amount']), 2) ?>
                            </td>
                            <td class="text-xs text-gray-600">
                                <div><span class="text-gray-400">ผู้ขอ:</span> <?= htmlspecialchars($item['requester_name'] ?? '-') ?></div>
                                <?php if (!empty($item['inspector_name'])): ?>
                                    <div class="text-gray-500 mt-0.5"><span class="text-gray-400">ผู้ตรวจรับ:</span> <?= htmlspecialchars($item['inspector_name']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center whitespace-nowrap">
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold border <?= $statusBadges[$item['status']] ?? 'bg-gray-100 text-gray-600' ?>">
                                    <?= $statusLabels[$item['status']] ?? $item['status'] ?>
                                </span>
                            </td>
                            <td class="text-center whitespace-nowrap">
                                <div class="flex items-center justify-center gap-1.5">
                                    <!-- Word Document Download Modal Button -->
                                    <button type="button"
                                        onclick="openWordModal(<?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['doc_no'])) ?>', '<?= $item['type'] ?>', '<?= htmlspecialchars(addslashes($item['subject'])) ?>')"
                                        class="px-2.5 py-1.5 bg-blue-50 hover:bg-blue-900 text-blue-800 hover:text-white border border-blue-200 rounded-lg text-xs font-bold transition flex items-center gap-1 shadow-sm"
                                        title="ดาวน์โหลดชุดเอกสารราชการ Word (.docx)">
                                        <i class="fas fa-file-word"></i> Word
                                    </button>

                                    <?php if ($isManager): ?>
                                        <button type="button" onclick="updateStatus(<?= $item['id'] ?>)"
                                            class="p-1.5 text-amber-600 hover:bg-amber-50 border border-transparent hover:border-amber-200 rounded-lg transition"
                                            title="บันทึกผลตรวจรับ / อัปเดตสถานะ">
                                            <i class="fas fa-clipboard-check"></i>
                                        </button>

                                        <a href="procurement_form.php?id=<?= $item['id'] ?>"
                                            class="p-1.5 text-indigo-600 hover:bg-indigo-50 border border-transparent hover:border-indigo-200 rounded-lg transition"
                                            title="แก้ไขข้อมูลและรายการพัสดุ">
                                            <i class="fas fa-edit"></i>
                                        </a>

                                        <button type="button" onclick="deleteProcurement(<?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['doc_no'])) ?>')"
                                            class="p-1.5 text-red-500 hover:bg-red-50 border border-transparent hover:border-red-200 rounded-lg transition"
                                            title="ลบรายการ">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Scripts -->
<script>
    /**
     * Open Modal to choose Word (.docx) government procurement document sets
     */
    function openWordModal(id, docNo, type, subject) {
        const isCash = (type === 'cash');
        const actionWord = (type === 'repair') ? 'จ้าง' : 'ซื้อ';

        const stageButtons = `
            <div class="text-left space-y-3">
                <div class="bg-blue-50/70 p-3 rounded-xl border border-blue-100">
                    <p class="text-xs font-bold text-blue-900">เลขที่หนังสือ: <span class="font-mono">${docNo}</span></p>
                    <p class="text-xs text-gray-600 mt-0.5">${subject}</p>
                </div>

                <div class="space-y-2">
                    <p class="text-xs font-extrabold text-gray-500 uppercase tracking-wider">1. ดาวน์โหลดชุดเอกสารครบทุกขั้นตอนในไฟล์เดียว (.docx)</p>
                    
                    <a href="api/procurement_print.php?id=${id}&doc=template" target="_blank"
                        class="p-3 rounded-xl border-2 border-blue-600 bg-blue-50/40 hover:bg-blue-100/70 flex items-center justify-between transition group">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-blue-900 text-white flex items-center justify-center text-lg shrink-0">
                                <i class="fas fa-file-word"></i>
                            </div>
                            <div>
                                <p class="font-bold text-sm text-blue-900">ชุดเอกสารครบชุดตามแม่แบบหน่วยงาน (.docx)</p>
                                <p class="text-xs text-gray-500">ใช้ฟอร์แมตต้นฉบับของศาลจังหวัดอุดรธานี พร้อมตราครุฑและกล่องเกษียนหนังสือครบทุกหน้า</p>
                            </div>
                        </div>
                        <span class="px-3 py-1 bg-blue-900 text-white rounded-lg text-xs font-bold shrink-0">ดาวน์โหลด</span>
                    </a>

                    <a href="api/procurement_print.php?id=${id}&doc=full_dynamic" target="_blank"
                        class="p-3 rounded-xl border border-indigo-200 bg-indigo-50/30 hover:bg-indigo-100/60 flex items-center justify-between transition group">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-indigo-700 text-white flex items-center justify-center text-lg shrink-0">
                                <i class="fas fa-table"></i>
                            </div>
                            <div>
                                <p class="font-bold text-sm text-indigo-900">ชุดเอกสารครบชุด + ตารางรายการพัสดุครบทุกแถว (.docx)</p>
                                <p class="text-xs text-gray-500">สร้างตารางรายการพัสดุ ราคาต่อหน่วย และราคารวมทุกแถวให้อัตโนมัติ แก้ไขใน Word ได้ทันที</p>
                            </div>
                        </div>
                        <span class="px-3 py-1 bg-indigo-700 text-white rounded-lg text-xs font-bold shrink-0">ดาวน์โหลด</span>
                    </a>
                </div>

                <div class="pt-2 space-y-1.5">
                    <p class="text-xs font-extrabold text-gray-500 uppercase tracking-wider">2. แยกดาวน์โหลดบันทึกข้อความรายขั้นตอน (.docx)</p>

                    <a href="api/procurement_print.php?id=${id}&doc=request_memo" target="_blank"
                        class="p-2.5 rounded-lg border border-gray-200 hover:bg-gray-50 flex items-center justify-between text-xs font-semibold text-gray-700">
                        <span><i class="fas fa-file-alt text-gray-500 mr-2"></i>0. บันทึกข้อความ แจ้งความชำรุด/ขออนุมัติ${actionWord}เบื้องต้น (สำหรับผู้ขอเบิก)</span>
                        <i class="fas fa-download text-blue-700"></i>
                    </a>

                    <a href="api/procurement_print.php?id=${id}&doc=report_request" target="_blank"
                        class="p-2.5 rounded-lg border border-gray-200 hover:bg-gray-50 flex items-center justify-between text-xs font-semibold text-gray-700">
                        <span><i class="fas fa-file-signature text-blue-600 mr-2"></i>1. บันทึกข้อความ รายงานขอ${actionWord} (ระเบียบฯ ข้อ 22) + แต่งตั้งผู้ตรวจรับ</span>
                        <i class="fas fa-download text-blue-700"></i>
                    </a>

                    <a href="api/procurement_print.php?id=${id}&doc=price_spec" target="_blank"
                        class="p-2.5 rounded-lg border border-gray-200 hover:bg-gray-50 flex items-center justify-between text-xs font-semibold text-gray-700">
                        <span><i class="fas fa-list-ol text-amber-600 mr-2"></i>2. ตารางเปิดเผยราคากลาง (แบบ ป.ป.ช.) + ตารางรายละเอียดรายการพัสดุ</span>
                        <i class="fas fa-download text-blue-700"></i>
                    </a>

                    ${!isCash ? `
                    <a href="api/procurement_print.php?id=${id}&doc=consider" target="_blank"
                        class="p-2.5 rounded-lg border border-gray-200 hover:bg-gray-50 flex items-center justify-between text-xs font-semibold text-gray-700">
                        <span><i class="fas fa-check-circle text-purple-600 mr-2"></i>3. บันทึกข้อความ รายงานผลการพิจารณาและขออนุมัติสั่ง${actionWord} (ข้อ 79)</span>
                        <i class="fas fa-download text-blue-700"></i>
                    </a>

                    <a href="api/procurement_print.php?id=${id}&doc=inspect" target="_blank"
                        class="p-2.5 rounded-lg border border-gray-200 hover:bg-gray-50 flex items-center justify-between text-xs font-semibold text-gray-700">
                        <span><i class="fas fa-clipboard-check text-green-600 mr-2"></i>4. ใบตรวจรับการจัด${actionWord} + บันทึกข้อความ รายงานผลการตรวจรับพัสดุ (ข้อ 175)</span>
                        <i class="fas fa-download text-blue-700"></i>
                    </a>
                    ` : ''}

                    <a href="api/procurement_print.php?id=${id}&doc=payment" target="_blank"
                        class="p-2.5 rounded-lg border border-gray-200 hover:bg-gray-50 flex items-center justify-between text-xs font-semibold text-gray-700">
                        <span><i class="fas fa-hand-holding-usd text-emerald-600 mr-2"></i>5. บันทึกข้อความ รายงานผลการพิจารณาและขออนุมัติเบิกจ่ายเงิน</span>
                        <i class="fas fa-download text-blue-700"></i>
                    </a>
                </div>
            </div>
        `;

        Swal.fire({
            title: 'จัดทำเอกสารราชการ Word (.docx)',
            html: stageButtons,
            width: '620px',
            showConfirmButton: false,
            showCloseButton: true
        });
    }

    function updateStatus(id) {
        fetch(`api/procurement.php?action=get&id=${id}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                const item = data.data;
                const today = new Date().toISOString().split('T')[0];

                SwalThemed.fire({
                    title: `บันทึกผลตรวจรับ / อัปเดตสถานะ (${item.doc_no})`,
                    html: `
                        <div class="text-left space-y-3">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1">สถานะการดำเนินการ</label>
                                <select id="update_status" class="w-full border rounded-lg px-3 py-2 text-sm font-bold text-gray-800">
                                    <option value="draft" ${item.status === 'draft' ? 'selected' : ''}>ร่างเอกสาร (Draft)</option>
                                    <option value="pending" ${item.status === 'pending' ? 'selected' : ''}>รออนุมัติ / กำลังดำเนินการ</option>
                                    <option value="approved" ${item.status === 'approved' ? 'selected' : ''}>อนุมัติสั่งซื้อ/สั่งจ้างแล้ว</option>
                                    <option value="completed" ${item.status === 'completed' ? 'selected' : ''}>เสร็จสิ้น (ตรวจรับพัสดุและเบิกจ่ายแล้ว)</option>
                                    <option value="cancelled" ${item.status === 'cancelled' ? 'selected' : ''}>ยกเลิก</option>
                                </select>
                            </div>
                            <div class="bg-blue-50/70 p-3.5 rounded-xl border border-blue-200 space-y-2.5">
                                <h4 class="font-bold text-xs text-blue-900"><i class="fas fa-file-invoice mr-1"></i> ข้อมูลการส่งมอบและตรวจรับพัสดุ (แสดงในใบตรวจรับและเบิกจ่าย)</h4>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-0.5">ชื่อร้านค้า / ผู้รับจ้าง</label>
                                    <input id="update_vendor" class="w-full border rounded-lg p-2 text-sm bg-white" value="${item.vendor_name || ''}" placeholder="ระบุชื่อร้านค้า/บริษัท...">
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-xs text-gray-600 mb-0.5">เลขที่ใบส่งของ/ใบกำกับภาษี/ใบเสร็จ</label>
                                        <input id="update_receipt" class="w-full border rounded-lg p-2 text-sm bg-white" value="${item.receipt_no || ''}" placeholder="เช่น IC-ST2-69-...">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-600 mb-0.5">วันที่ตรวจรับ / วันที่ใบส่งของ</label>
                                        <input type="date" id="update_date" class="w-full border rounded-lg p-2 text-sm bg-white" value="${item.inspect_date || item.delivery_date || today}">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-0.5">หมายเหตุ / บันทึกผลการซ่อมหรือตรวจรับ</label>
                                    <textarea id="update_note" rows="2" class="w-full border rounded-lg p-2 text-sm bg-white" placeholder="รายละเอียดเพิ่มเติม (ถ้ามี)...">${item.repair_detail || ''}</textarea>
                                </div>
                            </div>
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: 'บันทึกข้อมูล',
                    cancelButtonText: 'ยกเลิก',
                    preConfirm: () => {
                        return {
                            id: id,
                            status: document.getElementById('update_status').value,
                            vendor_name: document.getElementById('update_vendor').value,
                            receipt: document.getElementById('update_receipt').value,
                            date: document.getElementById('update_date').value,
                            note: document.getElementById('update_note').value
                        };
                    }
                }).then(r => {
                    if (r.isConfirmed) {
                        fetch('api/procurement.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ action: 'update_status', ...r.value })
                        }).then(res => res.json()).then(res => {
                            if (res.success) {
                                Swal.fire('สำเร็จ', 'อัปเดตข้อมูลเรียบร้อยแล้ว', 'success').then(() => location.reload());
                            } else {
                                Swal.fire('ผิดพลาด', res.message, 'error');
                            }
                        });
                    }
                });
            });
    }

    function deleteProcurement(id, docNo) {
        Swal.fire({
            title: `ยืนยันการลบรายการ ${docNo}?`,
            text: 'ข้อมูลรายการพัสดุในชุดเอกสารนี้จะถูกลบออกจากระบบ',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            confirmButtonText: 'ลบรายการ',
            cancelButtonText: 'ยกเลิก'
        }).then(result => {
            if (result.isConfirmed) {
                fetch('api/procurement.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete', id: id })
                })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        Swal.fire('ลบสำเร็จ', 'ลบรายการเรียบร้อยแล้ว', 'success').then(() => location.reload());
                    } else {
                        Swal.fire('ผิดพลาด', res.message, 'error');
                    }
                });
            }
        });
    }
</script>

<?php include 'includes/footer.php'; ?>

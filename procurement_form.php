<?php
/**
 * Procurement Form - สร้าง/แก้ไขรายการจัดซื้อจัดจ้างภาครัฐ และจัดทำเอกสารราชการ Word (.docx)
 */
require_once 'includes/auth.php';
requireLogin();

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

$editId = intval($_GET['id'] ?? 0);
$fromRequestId = intval($_GET['from_request'] ?? 0);
$editData = null;
$editItems = [];

if ($editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM procurements WHERE id = ?");
    $stmt->execute([$editId]);
    $editData = $stmt->fetch();
    if ($editData) {
        $stmtIt = $pdo->prepare("SELECT pi.*, a.asset_code FROM procurement_items pi LEFT JOIN assets a ON pi.asset_id = a.id WHERE pi.procurement_id = ? ORDER BY pi.id ASC");
        $stmtIt->execute([$editId]);
        $editItems = $stmtIt->fetchAll();
    }
}

$fromRequest = null;
if (!$editData && $fromRequestId > 0) {
    try {
        $stmtReq = $pdo->prepare("SELECT sr.*, u.full_name as user_name, u.department, tv.type_volume as unit_name
                                  FROM service_requests sr
                                  LEFT JOIN users u ON sr.user_id = u.id
                                  LEFT JOIN type_volume tv ON sr.type_asset = tv.id
                                  WHERE sr.id = ?");
        $stmtReq->execute([$fromRequestId]);
        $fromRequest = $stmtReq->fetch();
    } catch (Exception $e) {
    }
}

$type = $editData['type'] ?? ($fromRequest ? ($fromRequest['request_type'] === 'repair' ? 'repair' : 'credit') : ($_GET['type'] ?? 'repair'));
$pageTitle = $editData ? 'แก้ไขรายการจัดซื้อ/จัดจ้าง' : 'สร้างรายการจัดซื้อ/จัดจ้าง';

// Agency name
$courtName = 'ศาลจังหวัดอุดรธานี';
try {
    $st = $pdo->query("SELECT agency_name FROM system_settings WHERE id = 1")->fetch();
    if (!empty($st['agency_name'])) {
        $courtName = trim($st['agency_name']);
    }
} catch (Exception $e) {
}

// Get active users for officer roles
$users = $pdo->query("SELECT id, full_name, position, position_level, department, role FROM users WHERE status = 1 ORDER BY full_name")->fetchAll();

// Determine default officer IDs when creating a new record
$defaultSupplyId = null;
$defaultHeadOfficerId = null;
$defaultDirectorId = null;
foreach ($users as $u) {
    if ($defaultSupplyId === null && $u['role'] === 'supply_officer') {
        $defaultSupplyId = $u['id'];
    }
    if ($defaultDirectorId === null && $u['role'] === 'director') {
        $defaultDirectorId = $u['id'];
    }
    if ($defaultHeadOfficerId === null && $u['role'] === 'department_head' && strpos($u['department'] ?? '', 'คลัง') !== false) {
        $defaultHeadOfficerId = $u['id'];
    }
}

// Suggest next doc_no for new record
$currentFiscalYear = $editData['fiscal_year'] ?? (date('Y') + 543);
$suggestedDocNo = '';
if (!$editData) {
    $stmtNos = $pdo->prepare("SELECT doc_no FROM procurements WHERE fiscal_year = ? ORDER BY id DESC LIMIT 50");
    $stmtNos->execute([$currentFiscalYear]);
    $maxNum = 0;
    foreach ($stmtNos->fetchAll(PDO::FETCH_COLUMN) as $dNo) {
        if (preg_match('/^(\d+)\//', trim($dNo), $m)) {
            $n = intval($m[1]);
            if ($n > $maxNum) {
                $maxNum = $n;
            }
        }
    }
    $suggestedDocNo = ($maxNum + 1) . '/' . $currentFiscalYear;
}

// Units list from type_volume
$unitList = ['รายการ', 'งาน', 'ชิ้น', 'อัน', 'เครื่อง', 'ชุด', 'กล่อง', 'รีม', 'ตลับ', 'หลอด', 'แผ่น', 'เล่ม', 'แฟ้ม', 'แพ็ก', 'ถุง', 'ขวด', 'ม้วน'];
try {
    $dbUnits = $pdo->query("SELECT DISTINCT type_volume FROM type_volume ORDER BY type_volume")->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($dbUnits)) {
        $unitList = array_unique(array_merge(['รายการ', 'งาน'], $dbUnits));
    }
} catch (Exception $e) {
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!-- Breadcrumb -->
<nav class="flex mb-4" aria-label="Breadcrumb">
    <ol class="inline-flex items-center space-x-1 md:space-x-3">
        <li class="inline-flex items-center">
            <a href="procurement.php" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-900">
                <i class="fas fa-shopping-cart mr-2"></i> ระบบจัดซื้อ/จัดจ้าง
            </a>
        </li>
        <li>
            <div class="flex items-center">
                <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                <span class="text-sm font-medium text-gray-500"><?= $editData ? 'แก้ไขรายการ #' . htmlspecialchars($editData['doc_no']) : 'สร้างเอกสารจัดซื้อ/จัดจ้างใหม่' ?></span>
            </div>
        </li>
    </ol>
</nav>

<div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl md:text-3xl font-extrabold text-gray-800 flex items-center gap-3">
            <span class="w-11 h-11 rounded-xl bg-gradient-to-br from-blue-900 to-blue-700 flex items-center justify-center text-white shadow-lg">
                <i class="fas fa-file-word text-xl"></i>
            </span>
            <?= $editData ? 'แก้ไขข้อมูลการจัดซื้อ/จัดจ้าง' : 'สร้างชุดเอกสารจัดซื้อจัดจ้างภาครัฐ' ?>
        </h1>
        <p class="text-gray-500 text-sm mt-1 ml-14">
            รองรับระเบียบกระทรวงการคลังว่าด้วยการจัดซื้อจัดจ้างและการบริหารพัสดุภาครัฐ พ.ศ. 2560 พร้อมส่งออกไฟล์ Word (.docx)
        </p>
    </div>
    <?php if (!$editData): ?>
        <div>
            <button type="button" onclick="openImportRequestModal()"
                class="bg-amber-50 text-amber-800 border border-amber-300 hover:bg-amber-100 px-4 py-2.5 rounded-xl font-bold text-sm flex items-center gap-2 shadow-sm transition">
                <i class="fas fa-file-import text-amber-600"></i> ดึงข้อมูลจากใบแจ้งซ่อม/คำขอซื้อ
            </button>
        </div>
    <?php endif; ?>
</div>

<?php if ($fromRequest): ?>
    <div class="bg-amber-50 border-l-4 border-amber-500 p-4 rounded-r-xl mb-6 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <i class="fas fa-link text-amber-600 text-lg"></i>
            <div>
                <p class="text-sm font-bold text-amber-900">ดึงข้อมูลจากคำขอแจ้งซ่อม/ขอซื้อ #<?= $fromRequest['id'] ?> ของ <?= htmlspecialchars($fromRequest['user_name'] ?? '') ?></p>
                <p class="text-xs text-amber-700">รายการ: <?= htmlspecialchars($fromRequest['asset_name'] ?? '') ?> <?= htmlspecialchars($fromRequest['asset_description'] ?? '') ?></p>
            </div>
        </div>
        <a href="procurement_form.php?type=<?= $type ?>" class="text-xs text-amber-800 underline hover:font-bold">ยกเลิกการเชื่อมโยง</a>
    </div>
<?php endif; ?>

<!-- Type Selector -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <?php
    $typeCards = [
        'repair' => [
            'title' => 'จ้างซ่อมแซม (วิธีเฉพาะเจาะจง)',
            'desc' => 'รายงานขอจ้างซ่อมครุภัณฑ์ + ตาราง ป.ป.ช. + ผลพิจารณา + ใบตรวจรับ + เบิกจ่าย',
            'icon' => 'fas fa-tools',
            'border' => 'border-orange-500',
            'bgIcon' => 'bg-orange-100 text-orange-600'
        ],
        'credit' => [
            'title' => 'จัดซื้อพัสดุ (เครดิต / เฉพาะเจาะจง)',
            'desc' => 'รายงานขอซื้อ (ข้อ 22) + ตาราง ป.ป.ช. + ผลพิจารณา (ข้อ 79) + ใบตรวจรับ + เบิกจ่าย',
            'icon' => 'fas fa-file-invoice',
            'border' => 'border-blue-600',
            'bgIcon' => 'bg-blue-100 text-blue-600'
        ],
        'cash' => [
            'title' => 'จัดซื้อด้วยเงินสด / เงินทดรองราชการ',
            'desc' => 'ขออนุมัติจัดซื้อและจ่ายเงินทดรองราชการ + ขอบเขตงาน + ขออนุมัติเบิกเงินคืนทดรองฯ',
            'icon' => 'fas fa-money-bill-wave',
            'border' => 'border-green-600',
            'bgIcon' => 'bg-green-100 text-green-600'
        ]
    ];
    foreach ($typeCards as $k => $card):
        $isActive = ($type === $k);
        $href = $editData ? "javascript:switchType('{$k}')" : "?type={$k}";
    ?>
        <a href="<?= $href ?>" id="typeCard_<?= $k ?>" onclick="switchType('<?= $k ?>'); return false;"
            class="type-card relative group p-5 rounded-2xl border-2 transition-all duration-200 cursor-pointer <?= $isActive ? $card['border'] . ' bg-white shadow-lg scale-[1.01]' : 'border-gray-200 bg-gray-50 hover:bg-white hover:shadow-md' ?>">
            <div class="type-check absolute top-4 right-4 items-center justify-center w-6 h-6 rounded-full bg-blue-900 text-white <?= $isActive ? 'flex' : 'hidden' ?>">
                <i class="fas fa-check text-xs"></i>
            </div>
            <div class="flex items-center gap-3 mb-2">
                <div class="w-11 h-11 rounded-xl flex items-center justify-center <?= $card['bgIcon'] ?>">
                    <i class="<?= $card['icon'] ?> text-lg"></i>
                </div>
                <h3 class="font-bold text-base text-gray-800"><?= $card['title'] ?></h3>
            </div>
            <p class="text-xs text-gray-500 leading-relaxed"><?= $card['desc'] ?></p>
        </a>
    <?php endforeach; ?>
</div>

<form id="procurementForm" class="space-y-6 pb-20">
    <input type="hidden" name="id" id="proc_id" value="<?= $editId ?>">
    <input type="hidden" name="type" id="proc_type" value="<?= htmlspecialchars($type) ?>">
    <input type="hidden" name="service_request_id" id="service_request_id" value="<?= $editData['service_request_id'] ?? ($fromRequest['id'] ?? '') ?>">

    <!-- Section 1: ข้อมูลหนังสือราชการและเหตุผลความจำเป็น -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-2 bg-gray-50/60">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-blue-100 flex items-center justify-center text-blue-900">
                    <i class="fas fa-book-open"></i>
                </div>
                <div>
                    <h2 class="font-bold text-base text-gray-800">1. ข้อมูลหนังสือราชการ (บันทึกข้อความ)</h2>
                    <p class="text-xs text-gray-500">ระบุเลขที่หนังสือ วันที่ เรื่อง เหตุผลความจำเป็น และแหล่งเงินงบประมาณ</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs font-semibold text-gray-500">สถานะรายการ:</span>
                <select name="status" id="proc_status" class="border border-gray-300 rounded-lg px-3 py-1.5 text-xs font-bold text-gray-700 bg-white">
                    <?php
                    $curStatus = $editData['status'] ?? 'pending';
                    $statuses = [
                        'draft' => 'ร่างเอกสาร (Draft)',
                        'pending' => 'รออนุมัติ / กำลังดำเนินการ',
                        'approved' => 'อนุมัติสั่งซื้อ/สั่งจ้างแล้ว',
                        'completed' => 'ตรวจรับและเบิกจ่ายเสร็จสิ้น',
                        'cancelled' => 'ยกเลิก'
                    ];
                    foreach ($statuses as $stKey => $stLabel):
                    ?>
                        <option value="<?= $stKey ?>" <?= $curStatus === $stKey ? 'selected' : '' ?>><?= $stLabel ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1">เลขที่หนังสือ (ที่) <span class="text-red-500">*</span></label>
                <div class="flex gap-1.5">
                    <input type="text" name="doc_no" id="doc_no" required
                        value="<?= htmlspecialchars($editData['doc_no'] ?? $suggestedDocNo) ?>"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono font-bold text-blue-900 focus:border-blue-900 focus:outline-none"
                        placeholder="เช่น 71/2569">
                    <button type="button" onclick="fetchNextDocNo()" title="รันเลขที่หนังสือถัดไป"
                        class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-xs font-bold whitespace-nowrap">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1">ปีงบประมาณ (พ.ศ.) <span class="text-red-500">*</span></label>
                <input type="text" name="fiscal_year" id="fiscal_year" required
                    value="<?= htmlspecialchars($currentFiscalYear) ?>"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-bold text-gray-800 focus:border-blue-900 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1">วันที่รายงานขอซื้อ/ขอจ้าง <span class="text-red-500">*</span></label>
                <input type="text" name="doc_date" id="doc_date" required
                    value="<?= htmlspecialchars($editData['doc_date'] ?? date('Y-m-d')) ?>"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800 bg-white focus:border-blue-900 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1">กำหนดส่งมอบ / วันที่ตรวจรับ</label>
                <input type="text" name="delivery_date" id="delivery_date"
                    value="<?= htmlspecialchars($editData['delivery_date'] ?? ($editData['doc_date'] ?? date('Y-m-d'))) ?>"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800 bg-white focus:border-blue-900 focus:outline-none">
            </div>

            <div class="md:col-span-2 lg:col-span-4">
                <div class="flex items-center justify-between mb-1">
                    <label class="block text-xs font-bold text-gray-700">เรื่อง (Subject) <span class="text-red-500">*</span></label>
                    <div class="flex flex-wrap gap-1 text-xs">
                        <button type="button" onclick="setSubjectPrefix('ขออนุมัติจ้างซ่อมแซม')" class="px-2 py-0.5 bg-orange-50 text-orange-700 rounded hover:bg-orange-100">+ ขออนุมัติจ้างซ่อมแซม...</button>
                        <button type="button" onclick="setSubjectPrefix('รายงานขออนุมัติจัดซื้อวัสดุสำนักงานและแบบพิมพ์')" class="px-2 py-0.5 bg-blue-50 text-blue-700 rounded hover:bg-blue-100">+ รายงานขออนุมัติจัดซื้อวัสดุ...</button>
                        <button type="button" onclick="setSubjectPrefix('ขออนุมัติจัดซื้อและขออนุมัติจ่ายเงินทดรองราชการ')" class="px-2 py-0.5 bg-green-50 text-green-700 rounded hover:bg-green-100">+ ขออนุมัติจัดซื้อ (เงินทดรองฯ)...</button>
                    </div>
                </div>
                <?php
                $defaultSubj = $editData['subject'] ?? '';
                if ($defaultSubj === '') {
                    if ($fromRequest) {
                        $defaultSubj = ($type === 'repair' ? 'ขออนุมัติจ้างซ่อมแซม' : 'รายงานขออนุมัติจัดซื้อ') . trim($fromRequest['asset_name'] ?? '');
                    } else {
                        $defaultSubj = ($type === 'repair')
                            ? 'ขออนุมัติจ้างซ่อมแซมครุภัณฑ์'
                            : ($type === 'credit' ? 'รายงานขออนุมัติจัดซื้อวัสดุสำนักงานและแบบพิมพ์' : 'ขออนุมัติจัดซื้อและขออนุมัติจ่ายเงินทดรองราชการ');
                    }
                }
                ?>
                <input type="text" name="subject" id="subject" required
                    value="<?= htmlspecialchars($defaultSubj) ?>"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-medium text-gray-800 focus:border-blue-900 focus:outline-none"
                    placeholder="ระบุชื่อเรื่องในบันทึกข้อความ...">
            </div>

            <!-- เหตุผลและความจำเป็น -->
            <div class="md:col-span-2 lg:col-span-4">
                <div class="flex items-center justify-between mb-1">
                    <label class="block text-xs font-bold text-gray-700" id="reasonLabel">
                        <?= $type === 'repair' ? '1. เหตุผลและความจำเป็น / สาเหตุอาการชำรุดที่ต้องจ้างซ่อม' : '1. เหตุผลและความจำเป็นในการจัดซื้อ' ?>
                        <span class="text-red-500">*</span>
                    </label>
                    <div class="flex flex-wrap gap-1 text-xs">
                        <button type="button" onclick="insertReasonTemplate('supply')" class="px-2 py-0.5 bg-blue-50 text-blue-700 rounded hover:bg-blue-100">
                            <i class="fas fa-magic mr-1"></i>เทมเพลตวัสดุสำนักงานไม่เพียงพอ
                        </button>
                        <button type="button" onclick="insertReasonTemplate('repair')" class="px-2 py-0.5 bg-orange-50 text-orange-700 rounded hover:bg-orange-100">
                            <i class="fas fa-magic mr-1"></i>เทมเพลตครุภัณฑ์ชำรุดเสียหาย
                        </button>
                        <button type="button" onclick="insertReasonTemplate('building')" class="px-2 py-0.5 bg-green-50 text-green-700 rounded hover:bg-green-100">
                            <i class="fas fa-magic mr-1"></i>เทมเพลตซ่อมบำรุงอาคารสถานที่
                        </button>
                    </div>
                </div>
                <?php
                $reasonVal = '';
                if ($editData) {
                    $reasonVal = ($editData['type'] === 'repair') ? ($editData['repair_reason'] ?: $editData['buy_reason']) : ($editData['buy_reason'] ?: $editData['repair_reason']);
                } elseif ($fromRequest) {
                    $reasonVal = trim(($fromRequest['details'] ?? '') . ' ' . ($fromRequest['details_buy'] ?? ''));
                }
                ?>
                <textarea name="reason_text" id="reason_text" rows="3" required
                    class="w-full border border-gray-300 rounded-lg p-3 text-sm text-gray-800 focus:border-blue-900 focus:outline-none leading-relaxed"
                    placeholder="ระบุเหตุผลความจำเป็น หรืออาการชำรุด เพื่อนำไปแสดงในรายงานขอซื้อ/ขอจ้าง ข้อ 1..."><?= htmlspecialchars($reasonVal ?? '') ?></textarea>
            </div>

            <!-- แหล่งเงินงบประมาณ -->
            <div class="md:col-span-2 lg:col-span-4">
                <div class="flex items-center justify-between mb-1">
                    <label class="block text-xs font-bold text-gray-700">แหล่งเงินงบประมาณ / แผนงาน (แสดงในรายงานขอซื้อ/ขอจ้าง ข้อ 4)</label>
                    <button type="button" onclick="document.getElementById('budget_source').value = 'เงินงบประมาณประจำปีงบประมาณ พ.ศ. ' + document.getElementById('fiscal_year').value + ' ค่าใช้จ่ายด้านบริหารจัดการกิจกรรมสนับสนุนการพิจารณาพิพากษา'"
                        class="text-xs text-blue-700 hover:underline">ใช้ข้อความมาตรฐานศาล</button>
                </div>
                <input type="text" name="budget_source" id="budget_source"
                    value="<?= htmlspecialchars($editData['budget_source'] ?? "เงินงบประมาณประจำปีงบประมาณ พ.ศ. {$currentFiscalYear} ค่าใช้จ่ายด้านบริหารจัดการกิจกรรมสนับสนุนการพิจารณาพิพากษา") ?>"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800 focus:border-blue-900 focus:outline-none">
            </div>
        </div>
    </div>

    <!-- Section 2: ข้อมูลผู้ขาย/ผู้รับจ้าง และใบส่งของ/ใบกำกับภาษี -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center gap-3 bg-gray-50/60">
            <div class="w-9 h-9 rounded-lg bg-purple-100 flex items-center justify-center text-purple-800">
                <i class="fas fa-store"></i>
            </div>
            <div>
                <h2 class="font-bold text-base text-gray-800">2. ข้อมูลผู้ขาย / ผู้รับจ้าง และหลักฐานการส่งมอบงาน</h2>
                <p class="text-xs text-gray-500">ใช้แสดงในตาราง ป.ป.ช. (แหล่งที่มาของราคากลาง), รายงานผลการพิจารณา (ข้อ 79), ใบตรวจรับ และขออนุมัติเบิกจ่าย</p>
            </div>
        </div>

        <div class="p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
            <div class="lg:col-span-2">
                <label class="block text-xs font-bold text-gray-700 mb-1">ชื่อร้านค้า / บริษัท / ผู้รับจ้าง (แหล่งที่มาของราคากลาง)</label>
                <input type="text" name="vendor_name" id="vendor_name"
                    value="<?= htmlspecialchars($editData['vendor_name'] ?? '') ?>"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800 focus:border-blue-900 focus:outline-none"
                    placeholder="เช่น บริษัท นิวง่วนแสงไทย 2003 จำกัด หรือ หจก. ...">
            </div>

            <div class="lg:col-span-2">
                <label class="block text-xs font-bold text-gray-700 mb-1">ที่อยู่ร้านค้า/ผู้รับจ้าง (แสดงในรายงานผลการพิจารณา ข้อ 79)</label>
                <input type="text" name="vendor_address" id="vendor_address"
                    value="<?= htmlspecialchars($editData['vendor_address'] ?? '') ?>"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800 focus:border-blue-900 focus:outline-none"
                    placeholder="เช่น 88/8 หมู่ 6 ตำบลหมากแข้ง อำเภอเมือง จังหวัดอุดรธานี">
            </div>

            <div class="lg:col-span-2">
                <label class="block text-xs font-bold text-gray-700 mb-1">เลขที่ใบส่งของ / ใบกำกับภาษี / ใบแจ้งหนี้ / ใบเสร็จรับเงิน</label>
                <input type="text" name="receipt_no" id="receipt_no"
                    value="<?= htmlspecialchars($editData['receipt_no'] ?? '') ?>"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800 focus:border-blue-900 focus:outline-none"
                    placeholder="เช่น IC-ST2-69-0000279 หรือ 69/0011 (เว้นว่างไว้กรอกภายหลังเมื่อตรวจรับได้)">
            </div>

            <div class="lg:col-span-2">
                <label class="block text-xs font-bold text-gray-700 mb-1">วันที่ใบส่งของ / วันที่ตรวจรับพัสดุ</label>
                <input type="text" name="inspect_date" id="inspect_date"
                    value="<?= htmlspecialchars($editData['inspect_date'] ?? ($editData['delivery_date'] ?? date('Y-m-d'))) ?>"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800 bg-white focus:border-blue-900 focus:outline-none">
            </div>
        </div>
    </div>

    <!-- Section 3: รายการพัสดุ/ครุภัณฑ์/งานจ้าง -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-2 bg-gray-50/60">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-yellow-100 flex items-center justify-center text-yellow-700">
                    <i class="fas fa-boxes"></i>
                </div>
                <div>
                    <h2 class="font-bold text-base text-gray-800">3. รายการพัสดุ / ครุภัณฑ์ / งานจ้าง และราคากลาง</h2>
                    <p class="text-xs text-gray-500">ค้นหาครุภัณฑ์ในระบบเพื่อดึงชื่อและรหัสอัตโนมัติ หรือเพิ่มรายการพัสดุได้ไม่จำกัด</p>
                </div>
            </div>

            <button type="button" onclick="addItem()"
                class="bg-blue-900 text-white px-4 py-2 rounded-lg hover:bg-blue-800 transition font-bold text-xs flex items-center gap-1.5 shadow-sm">
                <i class="fas fa-plus"></i> เพิ่มแถวรายการ
            </button>
        </div>

        <div class="p-6">
            <!-- Asset Search Helper -->
            <div class="mb-5 relative bg-blue-50/60 p-4 rounded-xl border border-blue-100">
                <label class="block text-xs font-bold text-blue-900 mb-1.5">
                    <i class="fas fa-search mr-1"></i> ค้นหาครุภัณฑ์จากทะเบียนเพื่อดึงเข้าตาราง (กรณีแจ้งซ่อมหรืออ้างอิงครุภัณฑ์เดิม)
                </label>
                <div class="relative">
                    <input type="text" id="assetSearch"
                        class="w-full pl-4 pr-10 py-2.5 border border-blue-200 rounded-lg focus:border-blue-900 focus:outline-none text-sm bg-white"
                        placeholder="พิมพ์ชื่อครุภัณฑ์ หรือ หมายเลขครุภัณฑ์ เช่น 6350-003...">
                    <div class="absolute right-3 top-2.5 text-gray-400"><i class="fas fa-barcode"></i></div>
                </div>
                <div id="searchResults" class="absolute z-30 w-full left-0 bg-white shadow-xl rounded-xl mt-1 border border-gray-200 max-h-60 overflow-y-auto hidden divide-y divide-gray-100"></div>
            </div>

            <datalist id="unitOptions">
                <?php foreach ($unitList as $uName): ?>
                    <option value="<?= htmlspecialchars($uName) ?>">
                <?php endforeach; ?>
            </datalist>

            <div class="overflow-x-auto rounded-xl border border-gray-200">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-100 text-gray-700 text-xs font-bold">
                            <th class="py-3 px-3 text-center w-12">#</th>
                            <th class="py-3 px-3 text-left">ชื่อรายการพัสดุ / งานจ้าง <span class="text-red-500">*</span></th>
                            <th class="py-3 px-3 text-left w-56">รายละเอียด / รุ่น / หมายเหตุ</th>
                            <th class="py-3 px-3 text-center w-24">จำนวน</th>
                            <th class="py-3 px-3 text-center w-28">หน่วยนับ</th>
                            <th class="py-3 px-3 text-right w-36">ราคา/หน่วย</th>
                            <th class="py-3 px-3 text-right w-36">รวม (บาท)</th>
                            <th class="py-3 px-2 text-center w-12">ลบ</th>
                        </tr>
                    </thead>
                    <tbody id="itemTableBody" class="divide-y divide-gray-100 bg-white">
                    </tbody>
                    <tfoot class="bg-gray-50 border-t border-gray-200">
                        <tr>
                            <td colspan="5" class="py-3 px-4 text-left text-xs text-gray-600">
                                <span class="font-bold text-gray-700">ตัวอักษร:</span>
                                <span id="thaiBahtText" class="font-bold text-blue-900 ml-1">(ศูนย์บาทถ้วน)</span>
                            </td>
                            <td class="text-right py-3 px-3 text-xs font-bold text-gray-600">รวมทั้งสิ้นสุทธิ</td>
                            <td class="text-right py-3 px-3">
                                <span class="text-lg font-extrabold text-blue-900 font-mono" id="grandTotal">0.00</span>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Section 4: ผู้เกี่ยวข้องและผู้มีอำนาจลงนามตามระเบียบพัสดุฯ -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center gap-3 bg-gray-50/60">
            <div class="w-9 h-9 rounded-lg bg-green-100 flex items-center justify-center text-green-700">
                <i class="fas fa-user-check"></i>
            </div>
            <div>
                <h2 class="font-bold text-base text-gray-800">4. คณะกรรมการ / เจ้าหน้าที่ผู้เกี่ยวข้องในการลงนามหนังสือราชการ</h2>
                <p class="text-xs text-gray-500">ระบบเลือกเจ้าหน้าที่พัสดุ หัวหน้าส่วนคลัง และผู้อำนวยการฯ ให้อัตโนมัติ สามารถปรับเปลี่ยนได้ตามจริง</p>
            </div>
        </div>

        <div class="p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            <?php
            $selRequester = $editData['requester_id'] ?? ($fromRequest['user_id'] ?? $currentUser['id']);
            $selInspector = $editData['inspector_id'] ?? ($fromRequest['user_id'] ?? '');
            $selSupply = $editData['supply_officer_id'] ?? $defaultSupplyId;
            $selHeadOfficer = $editData['head_officer_id'] ?? $defaultHeadOfficerId;
            $selDirector = $editData['director_id'] ?? $defaultDirectorId;

            $officerSelects = [
                'requester_id' => [
                    'label' => 'ผู้ขอเบิก / ผู้แจ้งซ่อม / ผู้รับผิดชอบเงินทดรองฯ',
                    'icon' => 'fas fa-user-edit text-blue-600',
                    'selected' => $selRequester
                ],
                'inspector_id' => [
                    'label' => 'ผู้ตรวจรับพัสดุ / งานจ้าง (ข้อ 175)',
                    'icon' => 'fas fa-clipboard-check text-green-600',
                    'selected' => $selInspector
                ],
                'supply_officer_id' => [
                    'label' => 'เจ้าหน้าที่พัสดุ (ผู้จัดทำรายงานขอซื้อ/ขอจ้าง)',
                    'icon' => 'fas fa-box text-amber-600',
                    'selected' => $selSupply
                ],
                'head_officer_id' => [
                    'label' => 'หัวหน้าเจ้าหน้าที่พัสดุ / หัวหน้าส่วนคลัง',
                    'icon' => 'fas fa-user-tag text-purple-600',
                    'selected' => $selHeadOfficer
                ],
                'director_id' => [
                    'label' => 'ผู้อำนวยการสำนักอำนวยการฯ (ผู้มีอำนาจอนุมัติ)',
                    'icon' => 'fas fa-user-tie text-indigo-600',
                    'selected' => $selDirector
                ]
            ];

            foreach ($officerSelects as $field => $meta):
            ?>
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">
                        <i class="<?= $meta['icon'] ?> mr-1"></i> <?= $meta['label'] ?>
                    </label>
                    <select name="<?= $field ?>" id="<?= $field ?>"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800 bg-white focus:border-blue-900 focus:outline-none">
                        <option value="">-- กรุณาเลือก --</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= ($meta['selected'] == $u['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['full_name']) ?> (<?= htmlspecialchars( trim(($u['position'] ?? '') . ($u['position_level'] ?? '')) ?: $u['role']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endforeach; ?>

            <!-- ผู้พิพากษาหัวหน้าศาล / หัวหน้าส่วนราชการสูงสุด -->
            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1">
                    <i class="fas fa-gavel text-red-700 mr-1"></i> ผู้พิพากษาหัวหน้าศาล / หัวหน้าส่วนราชการ (ผู้ให้ความเห็นชอบ)
                </label>
                <div class="grid grid-cols-2 gap-2">
                    <input type="text" name="head_director_name" id="head_director_name"
                        value="<?= htmlspecialchars($editData['head_director_name'] ?? 'นายเลอภพอักขรา พรชัย') ?>"
                        class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-xs text-gray-800 focus:border-blue-900 focus:outline-none"
                        placeholder="ชื่อ-นามสกุล">
                    <input type="text" name="head_director_position" id="head_director_position"
                        value="<?= htmlspecialchars($editData['head_director_position'] ?? ('ผู้พิพากษาหัวหน้า' . $courtName)) ?>"
                        class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-xs text-gray-800 focus:border-blue-900 focus:outline-none"
                        placeholder="ตำแหน่ง">
                </div>
            </div>
        </div>
    </div>

    <!-- Sticky Bottom Action Bar -->
    <div class="fixed bottom-0 left-0 right-0 bg-white/95 backdrop-blur border-t border-gray-200 p-4 shadow-2xl z-40 md:pl-64 flex flex-wrap justify-between items-center gap-3">
        <a href="procurement.php" class="px-4 py-2.5 rounded-lg font-bold bg-gray-100 text-gray-700 hover:bg-gray-200 text-sm transition">
            <i class="fas fa-arrow-left mr-1"></i> กลับหน้ารายการ
        </a>
        <div class="flex items-center gap-2">
            <?php if ($editId > 0): ?>
                <button type="button" onclick="window.open('api/procurement_print.php?id=<?= $editId ?>&doc=template', '_blank')"
                    class="px-4 py-2.5 rounded-lg font-bold bg-indigo-50 text-indigo-800 border border-indigo-200 hover:bg-indigo-100 text-sm transition flex items-center gap-1.5">
                    <i class="fas fa-file-word"></i> ดาวน์โหลดชุดเอกสาร Word (.docx)
                </button>
            <?php endif; ?>
            <button type="submit" id="btnSubmit"
                class="px-6 py-2.5 rounded-lg font-bold bg-blue-900 text-white shadow-lg hover:bg-blue-800 transition text-sm flex items-center gap-2">
                <i class="fas fa-save"></i> <?= $editId > 0 ? 'บันทึกการแก้ไข' : 'บันทึกรายการและสร้างเอกสาร Word' ?>
            </button>
        </div>
    </div>
</form>

<script>
    const existingItems = <?= json_encode($editItems, JSON_UNESCAPED_UNICODE) ?>;
    const fromRequestData = <?= json_encode($fromRequest, JSON_UNESCAPED_UNICODE) ?>;

    document.addEventListener('DOMContentLoaded', function() {
        const fpConfig = {
            locale: "th",
            dateFormat: "Y-m-d",
            altInput: true,
            altFormat: "j F Y"
        };
        flatpickr("#doc_date", fpConfig);
        flatpickr("#delivery_date", fpConfig);
        flatpickr("#inspect_date", fpConfig);

        if (existingItems && existingItems.length > 0) {
            existingItems.forEach(it => {
                addItem(
                    it.item_name || '',
                    it.spec_detail || (it.asset_code ? `หมายเลขครุภัณฑ์ ${it.asset_code}` : ''),
                    parseFloat(it.quantity || 1),
                    it.unit || 'รายการ',
                    parseFloat(it.unit_price || 0),
                    it.asset_id || ''
                );
            });
        } else if (fromRequestData) {
            const defaultUnit = fromRequestData.unit_name || (fromRequestData.request_type === 'repair' ? 'งาน' : 'รายการ');
            addItem(
                fromRequestData.asset_name || '',
                fromRequestData.asset_description || '',
                parseFloat(fromRequestData.asset_volume || 1),
                defaultUnit,
                0,
                fromRequestData.asset_id || ''
            );
        } else {
            addItem('', '', 1, document.getElementById('proc_type').value === 'repair' ? 'งาน' : 'รายการ', 0, '');
        }
    });

    function switchType(newType) {
        document.getElementById('proc_type').value = newType;
        document.querySelectorAll('.type-card').forEach(card => {
            card.className = 'type-card relative group p-5 rounded-2xl border-2 transition-all duration-200 cursor-pointer border-gray-200 bg-gray-50 hover:bg-white hover:shadow-md';
            card.querySelector('.type-check').classList.add('hidden');
            card.querySelector('.type-check').classList.remove('flex');
        });
        const activeCard = document.getElementById('typeCard_' + newType);
        if (activeCard) {
            const borderMap = { repair: 'border-orange-500', credit: 'border-blue-600', cash: 'border-green-600' };
            activeCard.className = `type-card relative group p-5 rounded-2xl border-2 transition-all duration-200 cursor-pointer ${borderMap[newType]} bg-white shadow-lg scale-[1.01]`;
            activeCard.querySelector('.type-check').classList.remove('hidden');
            activeCard.querySelector('.type-check').classList.add('flex');
        }

        const reasonLabel = document.getElementById('reasonLabel');
        if (newType === 'repair') {
            reasonLabel.innerHTML = '1. เหตุผลและความจำเป็น / สาเหตุอาการชำรุดที่ต้องจ้างซ่อม <span class="text-red-500">*</span>';
        } else {
            reasonLabel.innerHTML = '1. เหตุผลและความจำเป็นในการจัดซื้อ <span class="text-red-500">*</span>';
        }
    }

    function setSubjectPrefix(prefix) {
        document.getElementById('subject').value = prefix;
        document.getElementById('subject').focus();
    }

    function insertReasonTemplate(tpl) {
        const el = document.getElementById('reason_text');
        if (tpl === 'supply') {
            el.value = 'ด้วยส่วนคลัง ส่วนช่วยอำนวยการ ส่วนบริหารจัดการคดี ส่วนไกล่เกลี่ยและประนอมข้อพิพาท ส่วนบริการประชาชนและประชาสัมพันธ์ ได้ขออนุมัติเบิกใช้วัสดุสำนักงานและแบบพิมพ์สำหรับใช้ในการปฏิบัติราชการของส่วนงานต่างๆ แต่ปรากฏว่าวัสดุสำนักงานและแบบพิมพ์ดังกล่าวมีไม่เพียงพอให้เบิกใช้ เพื่อให้การปฏิบัติราชการเป็นไปด้วยความสะดวกเรียบร้อยและไม่เกิดความเสียหายต่อทางราชการ จึงจำเป็นต้องจัดซื้อวัสดุสำนักงานและแบบพิมพ์ดังกล่าว';
        } else if (tpl === 'repair') {
            el.value = 'ด้วยได้รับแจ้งความชำรุดบกพร่องของครุภัณฑ์ ซึ่งไม่สามารถใช้งานได้ตามปกติ จำเป็นต้องดำเนินการตรวจเช็คและซ่อมแซมแก้ไขหรือเปลี่ยนอะไหล่อุปกรณ์ที่ชำรุดเสียหาย เพื่อให้ครุภัณฑ์กลับมาอยู่ในสภาพพร้อมใช้งานและเกิดประโยชน์ต่อการปฏิบัติราชการ';
        } else if (tpl === 'building') {
            el.value = 'เพื่อใช้ในการซ่อมบำรุงในงานจ้างเหมาบริการระบบวิศวกรรมอาคาร เพื่อให้ระบบสาธารณูปโภคและอาคารสถานที่ของหน่วยงานมีความพร้อมใช้งานและปลอดภัยต่อการปฏิบัติราชการ';
        }
    }

    function fetchNextDocNo() {
        const year = document.getElementById('fiscal_year').value;
        fetch(`api/procurement.php?action=next_doc_no&year=${encodeURIComponent(year)}`)
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    document.getElementById('doc_no').value = res.next_doc_no;
                }
            });
    }

    // Asset Search Logic
    const assetSearch = document.getElementById('assetSearch');
    const searchResults = document.getElementById('searchResults');
    let debounceTimer;

    assetSearch.addEventListener('input', function(e) {
        clearTimeout(debounceTimer);
        const term = e.target.value.trim();
        if (term.length < 2) {
            searchResults.classList.add('hidden');
            return;
        }

        debounceTimer = setTimeout(() => {
            fetch(`api/assets.php?action=search&q=${encodeURIComponent(term)}`)
                .then(r => r.json())
                .then(data => {
                    searchResults.innerHTML = '';
                    if (Array.isArray(data) && data.length > 0) {
                        searchResults.classList.remove('hidden');
                        data.forEach(asset => {
                            const div = document.createElement('div');
                            div.className = 'p-3 hover:bg-blue-50 cursor-pointer flex justify-between items-center transition';
                            div.innerHTML = `
                                <div>
                                    <p class="font-bold text-gray-800 text-sm">${asset.name}</p>
                                    <p class="text-xs text-gray-500 font-mono">รหัสครุภัณฑ์: ${asset.asset_code || '-'}</p>
                                </div>
                                <span class="text-xs font-bold text-blue-900 bg-blue-100 px-2.5 py-1 rounded-full">+ เลือก</span>
                            `;
                            div.onclick = () => {
                                const firstRow = document.querySelector('#itemTableBody tr');
                                const firstInput = firstRow ? firstRow.querySelector('input[name="items[name][]"]') : null;
                                if (firstInput && firstInput.value.trim() === '') {
                                    firstRow.remove();
                                }
                                const curType = document.getElementById('proc_type').value;
                                addItem(
                                    asset.name,
                                    asset.asset_code ? `หมายเลขครุภัณฑ์ ${asset.asset_code}` : '',
                                    1,
                                    curType === 'repair' ? 'งาน' : 'เครื่อง',
                                    0,
                                    asset.id
                                );
                                assetSearch.value = '';
                                searchResults.classList.add('hidden');
                            };
                            searchResults.appendChild(div);
                        });
                    } else {
                        searchResults.classList.add('hidden');
                    }
                });
        }, 250);
    });

    document.addEventListener('click', function(e) {
        if (!assetSearch.contains(e.target) && !searchResults.contains(e.target)) {
            searchResults.classList.add('hidden');
        }
    });

    function addItem(name = '', spec = '', qty = 1, unit = 'รายการ', price = 0, assetId = '') {
        const tbody = document.getElementById('itemTableBody');
        const tr = document.createElement('tr');
        tr.className = 'hover:bg-blue-50/20 transition';
        tr.innerHTML = `
            <td class="py-2.5 px-2 text-center font-bold text-gray-400 text-xs row-index">1</td>
            <td class="py-2.5 px-2">
                <input type="text" name="items[name][]" value="${escapeHtml(name)}" required
                    class="w-full border border-gray-300 rounded-lg px-2.5 py-1.5 text-sm text-gray-800 focus:border-blue-900 focus:outline-none"
                    placeholder="ระบุชื่อพัสดุ/ครุภัณฑ์/งานจ้าง...">
                <input type="hidden" name="items[asset_id][]" value="${assetId || ''}">
            </td>
            <td class="py-2.5 px-2">
                <input type="text" name="items[spec][]" value="${escapeHtml(spec)}"
                    class="w-full border border-gray-300 rounded-lg px-2.5 py-1.5 text-xs text-gray-700 focus:border-blue-900 focus:outline-none"
                    placeholder="รุ่น/ยี่ห้อ/หมายเลขครุภัณฑ์">
            </td>
            <td class="py-2.5 px-2">
                <input type="number" name="items[qty][]" value="${qty}" min="0.01" step="any" oninput="calcRow(this)"
                    class="w-full text-center border border-gray-300 rounded-lg px-2 py-1.5 text-sm font-bold text-gray-800 focus:border-blue-900 focus:outline-none">
            </td>
            <td class="py-2.5 px-2">
                <input type="text" name="items[unit][]" list="unitOptions" value="${escapeHtml(unit)}"
                    class="w-full text-center border border-gray-300 rounded-lg px-2 py-1.5 text-sm text-gray-800 focus:border-blue-900 focus:outline-none"
                    placeholder="หน่วยนับ">
            </td>
            <td class="py-2.5 px-2">
                <input type="number" name="items[price][]" value="${price}" min="0" step="0.01" oninput="calcRow(this)"
                    class="w-full text-right border border-gray-300 rounded-lg px-2.5 py-1.5 text-sm font-mono text-gray-800 focus:border-blue-900 focus:outline-none">
            </td>
            <td class="py-2.5 px-3 text-right">
                <div class="font-bold text-blue-900 font-mono text-sm total-cell">0.00</div>
            </td>
            <td class="py-2.5 px-2 text-center">
                <button type="button" onclick="this.closest('tr').remove(); reindexRows(); calcTotal();"
                    class="w-7 h-7 rounded-lg bg-red-50 text-red-500 hover:bg-red-500 hover:text-white transition inline-flex items-center justify-center">
                    <i class="fas fa-trash-alt text-xs"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
        reindexRows();
        calcRow(tr.querySelector('input[name="items[qty][]"]'));
    }

    function reindexRows() {
        document.querySelectorAll('#itemTableBody tr').forEach((row, idx) => {
            const idxCell = row.querySelector('.row-index');
            if (idxCell) idxCell.textContent = idx + 1;
        });
    }

    function calcRow(input) {
        if (!input) return;
        const row = input.closest('tr');
        const qty = parseFloat(row.querySelector('input[name="items[qty][]"]').value) || 0;
        const price = parseFloat(row.querySelector('input[name="items[price][]"]').value) || 0;
        const total = qty * price;
        row.querySelector('.total-cell').textContent = total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        calcTotal();
    }

    function calcTotal() {
        let grandTotal = 0;
        document.querySelectorAll('#itemTableBody tr').forEach(row => {
            const qty = parseFloat(row.querySelector('input[name="items[qty][]"]').value) || 0;
            const price = parseFloat(row.querySelector('input[name="items[price][]"]').value) || 0;
            grandTotal += (qty * price);
        });
        document.getElementById('grandTotal').textContent = grandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('thaiBahtText').textContent = '(' + thaiBahtJS(grandTotal) + ')';
    }

    function thaiBahtJS(num) {
        num = parseFloat(num || 0).toFixed(2);
        if (parseFloat(num) === 0) return 'ศูนย์บาทถ้วน';
        const parts = num.split('.');
        const numArr = ['', 'หนึ่ง', 'สอง', 'สาม', 'สี่', 'ห้า', 'หก', 'เจ็ด', 'แปด', 'เก้า'];
        const posArr = ['', 'สิบ', 'ร้อย', 'พัน', 'หมื่น', 'แสน', 'ล้าน'];
        function readInt(str) {
            let res = '';
            const len = str.length;
            for (let i = 0; i < len; i++) {
                const d = parseInt(str.charAt(i));
                const pos = (len - i - 1) % 6;
                if (d !== 0) {
                    if (pos === 1 && d === 1) res += '';
                    else if (pos === 1 && d === 2) res += 'ยี่';
                    else if (pos === 0 && d === 1 && len > 1 && parseInt(str.charAt(i - 1)) !== 0) res += 'เอ็ด';
                    else res += numArr[d];
                    res += posArr[pos];
                }
                if ((len - i - 1) === 6) res += 'ล้าน';
            }
            return res;
        }
        const baht = readInt(parts[0]);
        const satang = parseInt(parts[1]) > 0 ? readInt(parts[1]) + 'สตางค์' : 'ถ้วน';
        return (baht ? baht + 'บาท' : '') + satang;
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    // Modal to import from pending service_requests
    function openImportRequestModal() {
        fetch('api/procurement.php?action=pending_requests')
            .then(r => r.json())
            .then(res => {
                const list = res.data || [];
                if (list.length === 0) {
                    Swal.fire('ไม่พบคำขอที่รอดำเนินการ', 'ยังไม่มีรายการแจ้งซ่อมหรือขอซื้อจากผู้ใช้งานในขณะนี้', 'info');
                    return;
                }
                let rowsHtml = list.map(req => `
                    <div class="p-3 border rounded-xl hover:bg-blue-50 flex items-center justify-between gap-3 mb-2 text-left">
                        <div>
                            <span class="px-2 py-0.5 rounded text-xs font-bold ${req.request_type === 'repair' ? 'bg-orange-100 text-orange-800' : 'bg-blue-100 text-blue-800'}">
                                ${req.request_type === 'repair' ? 'แจ้งซ่อม' : 'ขอจัดซื้อ'}
                            </span>
                            <span class="font-bold text-sm text-gray-800 ml-1">${escapeHtml(req.asset_name || '')} ${escapeHtml(req.asset_description || '')}</span>
                            <p class="text-xs text-gray-500 mt-1">ผู้แจ้ง: ${escapeHtml(req.user_name || '-')} (${escapeHtml(req.department || '-')})</p>
                        </div>
                        <a href="procurement_form.php?from_request=${req.id}"
                            class="px-3 py-1.5 bg-blue-900 text-white rounded-lg text-xs font-bold whitespace-nowrap hover:bg-blue-800">
                            ดึงข้อมูล
                        </a>
                    </div>
                `).join('');

                Swal.fire({
                    title: 'เลือกดึงข้อมูลจากใบแจ้งซ่อม/คำขอซื้อ',
                    html: `<div class="max-h-80 overflow-y-auto pr-1">${rowsHtml}</div>`,
                    width: '650px',
                    showConfirmButton: false,
                    showCloseButton: true
                });
            });
    }

    // Form Submit
    document.getElementById('procurementForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const rows = document.querySelectorAll('#itemTableBody tr');
        if (rows.length === 0) {
            Swal.fire('แจ้งเตือน', 'กรุณาเพิ่มรายการอย่างน้อย 1 รายการ', 'warning');
            return;
        }

        const editId = parseInt(document.getElementById('proc_id').value) || 0;
        const procType = document.getElementById('proc_type').value;
        const reasonVal = document.getElementById('reason_text').value;

        const items = [];
        rows.forEach(row => {
            items.push({
                name: row.querySelector('input[name="items[name][]"]').value,
                spec_detail: row.querySelector('input[name="items[spec][]"]').value,
                qty: row.querySelector('input[name="items[qty][]"]').value,
                unit: row.querySelector('input[name="items[unit][]"]').value,
                price: row.querySelector('input[name="items[price][]"]').value,
                asset_id: row.querySelector('input[name="items[asset_id][]"]').value
            });
        });

        const payload = {
            action: editId > 0 ? 'update' : 'create',
            id: editId,
            type: procType,
            status: document.getElementById('proc_status').value,
            fiscal_year: document.getElementById('fiscal_year').value,
            doc_no: document.getElementById('doc_no').value,
            doc_date: document.getElementById('doc_date').value,
            delivery_date: document.getElementById('delivery_date').value,
            inspect_date: document.getElementById('inspect_date').value,
            subject: document.getElementById('subject').value,
            repair_reason: procType === 'repair' ? reasonVal : null,
            buy_reason: procType !== 'repair' ? reasonVal : reasonVal,
            budget_source: document.getElementById('budget_source').value,
            vendor_name: document.getElementById('vendor_name').value,
            vendor_address: document.getElementById('vendor_address').value,
            receipt_no: document.getElementById('receipt_no').value,
            requester_id: document.getElementById('requester_id').value,
            service_request_id: document.getElementById('service_request_id').value || null,
            inspector_id: document.getElementById('inspector_id').value,
            supply_officer_id: document.getElementById('supply_officer_id').value,
            head_officer_id: document.getElementById('head_officer_id').value,
            director_id: document.getElementById('director_id').value,
            head_director_name: document.getElementById('head_director_name').value,
            head_director_position: document.getElementById('head_director_position').value,
            items: items
        };

        showLoading();
        fetch('api/procurement.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                const savedId = res.id || editId;
                Swal.fire({
                    icon: 'success',
                    title: 'บันทึกข้อมูลเรียบร้อย!',
                    html: `
                        <p class="text-sm text-gray-600 mb-4">คุณต้องการดาวน์โหลดชุดเอกสารราชการ Word (.docx) ทันทีหรือไม่?</p>
                        <div class="flex flex-col gap-2 text-left">
                            <a href="api/procurement_print.php?id=${savedId}&doc=template" target="_blank"
                                class="p-3 rounded-xl bg-blue-50 hover:bg-blue-100 border border-blue-200 flex items-center justify-between text-blue-900 font-bold text-sm">
                                <span><i class="fas fa-file-word text-blue-700 mr-2"></i>ชุดเอกสารครบชุดตามแม่แบบศาล (.docx)</span>
                                <i class="fas fa-download"></i>
                            </a>
                            <a href="api/procurement_print.php?id=${savedId}&doc=full_dynamic" target="_blank"
                                class="p-3 rounded-xl bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 flex items-center justify-between text-indigo-900 font-bold text-sm">
                                <span><i class="fas fa-table text-indigo-700 mr-2"></i>ชุดเอกสารครบชุด + ตารางรายการพัสดุอัตโนมัติ (.docx)</span>
                                <i class="fas fa-download"></i>
                            </a>
                        </div>
                    `,
                    confirmButtonText: 'ไปหน้ารายการจัดซื้อ/จัดจ้าง',
                    confirmButtonColor: '#1e3a8a'
                }).then(() => {
                    window.location.href = 'procurement.php';
                });
            } else {
                Swal.fire('เกิดข้อผิดพลาด', res.message, 'error');
            }
        })
        .catch(err => Swal.fire('Error', err.message, 'error'));
    });
</script>

<?php include 'includes/footer.php'; ?>

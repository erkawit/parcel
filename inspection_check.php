<?php
/**
 * Inspection Check - Mobile-Friendly Inspection Interface (Committee Member View)
 */
require_once 'includes/auth.php';
requireLogin();

$projectId = $_GET['project'] ?? 0;
if (!$projectId) {
    header('Location: inspection.php');
    exit;
}

$currentUser = getCurrentUser();

$stmt = $pdo->prepare("SELECT * FROM inspection_projects WHERE id = ?");
$stmt->execute([$projectId]);
$project = $stmt->fetch();
if (!$project) {
    header('Location: inspection.php');
    exit;
}

$pageTitle = 'ตรวจสอบ: ' . $project['name'];
$isManager = hasRole(['admin', 'supply_officer']);

// Check if user is in committee_members for this project
$isCommitteeMember = false;
if ($project['committee_order_id']) {
    $stmtCm = $pdo->prepare("SELECT 1 FROM committee_members WHERE order_id = ? AND user_id = ? LIMIT 1");
    $stmtCm->execute([$project['committee_order_id'], $currentUser['id']]);
    $isCommitteeMember = (bool) $stmtCm->fetchColumn();
}

// Count my assigned + group items vs total items in project
$stmtMyCount = $pdo->prepare("SELECT COUNT(*) FROM inspection_items ii
                              WHERE ii.project_id = ?
                              AND (ii.assigned_to = ? OR ii.group_id IN (SELECT group_id FROM inspection_group_members WHERE user_id = ?))");
$stmtMyCount->execute([$projectId, $currentUser['id'], $currentUser['id']]);
$myAssignedTotal = (int) $stmtMyCount->fetchColumn();

$stmtAllCount = $pdo->prepare("SELECT COUNT(*) FROM inspection_items WHERE project_id = ?");
$stmtAllCount->execute([$projectId]);
$allProjectTotal = (int) $stmtAllCount->fetchColumn();

// Determine scope: 'my' (assigned to me or my group) or 'all' (all project items)
$scope = $_GET['scope'] ?? ($myAssignedTotal > 0 ? 'my' : 'all');

if ($scope === 'all' && ($isManager || $isCommitteeMember || $myAssignedTotal === 0)) {
    $stmt = $pdo->prepare("SELECT ii.*, a.asset_code, a.name as asset_name, a.brand, a.model, 
                           a.location, a.building, a.floor, a.room, a.serial_number, a.image as asset_image,
                           ig.name as group_name, u.full_name as holder_name
                           FROM inspection_items ii
                           JOIN assets a ON ii.asset_id = a.id
                           LEFT JOIN inspection_groups ig ON ii.group_id = ig.id
                           LEFT JOIN users u ON a.holder_id = u.id
                           WHERE ii.project_id = ?
                           ORDER BY ii.status = 'pending' DESC, a.asset_code");
    $stmt->execute([$projectId]);
} else {
    $scope = 'my';
    // Get items assigned directly to user OR assigned to a group where user is a member
    $stmt = $pdo->prepare("SELECT ii.*, a.asset_code, a.name as asset_name, a.brand, a.model, 
                           a.location, a.building, a.floor, a.room, a.serial_number, a.image as asset_image,
                           ig.name as group_name, u.full_name as holder_name
                           FROM inspection_items ii
                           JOIN assets a ON ii.asset_id = a.id
                           LEFT JOIN inspection_groups ig ON ii.group_id = ig.id
                           LEFT JOIN users u ON a.holder_id = u.id
                           WHERE ii.project_id = ?
                           AND (ii.assigned_to = ? OR ii.group_id IN (SELECT group_id FROM inspection_group_members WHERE user_id = ?))
                           ORDER BY ii.status = 'pending' DESC, a.asset_code");
    $stmt->execute([$projectId, $currentUser['id'], $currentUser['id']]);
}
$myItems = $stmt->fetchAll();

$pendingCount = 0;
$checkedCount = 0;
$itemLocations = [];
foreach ($myItems as $item) {
    if ($item['status'] === 'pending')
        $pendingCount++;
    else
        $checkedCount++;
    if (!empty($item['location']) && trim($item['location']) !== '' && trim($item['location']) !== '-') {
        $itemLocations[trim($item['location'])] = true;
    }
}
$itemLocations = array_keys($itemLocations);
sort($itemLocations);
$isLocked = in_array($project['status'], ['completed', 'cancelled']);

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!-- Breadcrumb & Scope Toggle -->
<div class="mb-4 flex flex-wrap items-center justify-between gap-2">
    <a href="inspection.php" class="text-blue-900 hover:underline text-sm"><i class="fas fa-arrow-left"></i> กลับ</a>
    <?php if ($isManager || $isCommitteeMember || $myAssignedTotal > 0): ?>
        <div class="flex gap-1 bg-gray-200 p-1 rounded-lg text-xs font-semibold">
            <a href="inspection_check.php?project=<?= $projectId ?>&scope=my"
               class="px-3 py-1.5 rounded-md transition <?= $scope === 'my' ? 'bg-blue-900 text-white shadow' : 'text-gray-600 hover:text-gray-900' ?>">
               <i class="fas fa-user-check mr-1"></i>ที่รับผิดชอบ/กลุ่ม (<?= $myAssignedTotal ?>)
            </a>
            <a href="inspection_check.php?project=<?= $projectId ?>&scope=all"
               class="px-3 py-1.5 rounded-md transition <?= $scope === 'all' ? 'bg-blue-900 text-white shadow' : 'text-gray-600 hover:text-gray-900' ?>">
               <i class="fas fa-boxes-stacked mr-1"></i>ทั้งหมดในโครงการ (<?= $allProjectTotal ?>)
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Progress Header -->
<div class="bg-gradient-to-r from-blue-900 to-blue-700 rounded-xl p-5 mb-6 text-white shadow-lg">
    <h1 class="text-lg font-bold mb-1">
        <?= htmlspecialchars($project['name']) ?>
    </h1>
    <p class="text-blue-200 text-sm mb-3">ปีงบประมาณ
        <?= $project['fiscal_year'] ?>
    </p>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-4">
        <a href="inspection_report_print.php?project=<?= $projectId ?>" target="_blank"
            class="w-full bg-yellow-500 hover:bg-yellow-400 text-blue-950 text-sm font-bold py-2 rounded-lg transition flex items-center justify-center gap-2 shadow">
            <i class="fas fa-print"></i> พิมพ์สรุปผล / ออกรายงาน PDF อัตโนมัติ
        </a>
        <button onclick="showReportModal()"
            class="w-full bg-white/20 hover:bg-white/30 text-white text-sm font-semibold py-2 rounded-lg transition flex items-center justify-center gap-2 border border-white/30">
            <i class="fas fa-upload text-red-300"></i> แนบไฟล์รายงานที่เซ็นแล้ว (PDF)
        </button>
    </div>
    <div class="flex items-center gap-4 text-sm">
        <div class="bg-white/20 rounded-lg px-3 py-2">
            <span id="checkedCountText" class="text-yellow-300 font-bold text-xl">
                <?= $checkedCount ?>
            </span>
            <span class="text-blue-200">/
                <?= count($myItems) ?>
            </span>
            <p class="text-xs text-blue-200">ตรวจแล้ว</p>
        </div>
        <div class="flex-1">
            <div class="w-full bg-white/20 rounded-full h-3 overflow-hidden">
                <?php $pct = count($myItems) > 0 ? round(($checkedCount / count($myItems)) * 100) : 0; ?>
                <div id="progressBar" class="h-full bg-yellow-400 rounded-full transition-all" style="width:<?= $pct ?>%"></div>
            </div>
            <p id="progressPctText" class="text-xs text-blue-200 mt-1">
                <?= $pct ?>% สำเร็จ
            </p>
        </div>
    </div>
</div>

<?php if ($isLocked): ?>
    <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 rounded-xl mb-4 text-sm text-yellow-800 font-semibold">
        <i class="fas fa-lock mr-1"></i> โครงการนี้สรุปผลแล้ว (ไม่เปิดให้แก้ไขผลการตรวจเพิ่มเติม)
    </div>
<?php endif; ?>

<!-- Search & Location Filter -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-2 mb-4">
    <div class="relative md:col-span-2">
        <input type="text" id="searchInput" placeholder="ค้นหารหัส, ชื่อ, ที่ตั้ง..."
            class="w-full pl-10 pr-4 py-3 border-2 border-gray-200 rounded-xl text-sm focus:border-blue-900 focus:outline-none bg-white"
            oninput="filterItems()">
        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
    </div>
    <select id="locationFilter" onchange="filterItems()"
        class="w-full px-3 py-3 border-2 border-gray-200 rounded-xl text-sm focus:border-blue-900 focus:outline-none bg-white">
        <option value="">-- ทุกที่ตั้ง / ห้อง (<?= count($itemLocations) ?>) --</option>
        <?php foreach ($itemLocations as $loc): ?>
            <option value="<?= htmlspecialchars($loc) ?>"><?= htmlspecialchars($loc) ?></option>
        <?php endforeach; ?>
    </select>
</div>

<!-- Filter Tabs & Bulk Check Action -->
<div class="flex flex-wrap items-center justify-between gap-2 mb-4">
    <div class="flex gap-2 overflow-x-auto pb-1">
        <button onclick="filterByStatus('all')"
            class="filter-tab active px-4 py-2 rounded-lg text-sm font-semibold bg-blue-900 text-white whitespace-nowrap"
            data-status="all">
            ทั้งหมด (<?= count($myItems) ?>)
        </button>
        <button onclick="filterByStatus('pending')"
            class="filter-tab px-4 py-2 rounded-lg text-sm font-semibold bg-gray-200 text-gray-600 hover:bg-gray-300 whitespace-nowrap"
            data-status="pending">
            รอตรวจ (<?= $pendingCount ?>)
        </button>
        <button onclick="filterByStatus('checked')"
            class="filter-tab px-4 py-2 rounded-lg text-sm font-semibold bg-gray-200 text-gray-600 hover:bg-gray-300 whitespace-nowrap"
            data-status="checked">
            ตรวจแล้ว (<?= $checkedCount ?>)
        </button>
    </div>
    <?php if (!$isLocked && !empty($myItems)): ?>
        <button type="button" onclick="bulkCheckNormal()"
            class="btn-press px-4 py-2 bg-green-600 text-white rounded-lg text-xs font-bold hover:bg-green-700 transition shadow flex items-center gap-1.5">
            <i class="fas fa-check-double"></i> ติ๊ก "ปกติ" ทั้งหมดที่แสดงอยู่ (ตรวจเร็ว)
        </button>
    <?php endif; ?>
</div>

<!-- Item Cards (Mobile-Optimized) -->
<div id="itemsContainer" class="space-y-3 pb-20">
    <?php if (empty($myItems)): ?>
        <div class="bg-white rounded-xl p-12 text-center shadow-sm border border-gray-100">
            <i class="fas fa-clipboard-check text-gray-300 text-5xl mb-4"></i>
            <p class="text-lg font-semibold text-gray-500">ไม่มีรายการที่ได้รับมอบหมาย</p>
        </div>
    <?php else: ?>
        <?php foreach ($myItems as $i => $item): ?>
            <div class="item-card bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden animate-fade-in"
                data-id="<?= $item['id'] ?>"
                data-status="<?= $item['status'] ?>"
                data-location="<?= htmlspecialchars(trim($item['location'] ?? '')) ?>"
                data-search="<?= htmlspecialchars(strtolower($item['asset_code'] . ' ' . $item['asset_name'] . ' ' . $item['location'])) ?>"
                style="animation-delay:<?= $i * 0.03 ?>s">
                <div class="p-4">
                    <!-- Asset Info -->
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex-1">
                            <span class="font-mono text-xs bg-blue-50 text-blue-900 px-2 py-0.5 rounded font-semibold">
                                <?= htmlspecialchars($item['asset_code']) ?>
                            </span>
                            <h3 class="font-bold text-gray-800 mt-1">
                                <?= htmlspecialchars($item['asset_name']) ?>
                            </h3>
                            <?php if ($item['brand']): ?>
                                <p class="text-xs text-gray-500">
                                    <?= htmlspecialchars($item['brand']) ?>
                                    <?= htmlspecialchars($item['model'] ?? '') ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div id="badge-<?= $item['id'] ?>">
                            <?php
                            $badgeMap = ['pending' => 'bg-gray-200 text-gray-700', 'normal' => 'bg-green-100 text-green-800', 'damaged' => 'bg-orange-100 text-orange-800', 'deteriorated' => 'bg-red-100 text-red-800', 'not_found' => 'bg-gray-300 text-gray-700'];
                            $labelMap = ['pending' => 'รอตรวจ', 'normal' => 'ปกติ', 'damaged' => 'ชำรุด', 'deteriorated' => 'เสื่อมสภาพ', 'not_found' => 'ไม่พบ'];
                            ?>
                            <span class="badge <?= $badgeMap[$item['status']] ?>">
                                <?= $labelMap[$item['status']] ?>
                            </span>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 text-xs text-gray-500 mb-3">
                        <span class="flex items-center gap-1">
                            <i class="fas fa-map-marker-alt text-blue-900"></i>
                            <?= htmlspecialchars($item['location'] ?? 'ไม่ระบุ') ?>
                        </span>
                        <?php if (!empty($item['holder_name'])): ?>
                            <span class="flex items-center gap-1">
                                <i class="fas fa-user text-blue-900"></i>
                                <?= htmlspecialchars($item['holder_name']) ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($item['group_name'])): ?>
                            <span class="bg-blue-50 text-blue-800 px-2 py-0.5 rounded font-medium">
                                <i class="fas fa-layer-group mr-1"></i><?= htmlspecialchars($item['group_name']) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Note & Photo Display / Actions -->
                    <div class="flex items-center justify-between gap-2 mb-3 text-xs">
                        <div id="note-display-<?= $item['id'] ?>" class="text-gray-600 italic truncate flex-1">
                            <?= !empty($item['note']) ? '<i class="fas fa-comment-alt text-yellow-600 mr-1"></i>' . htmlspecialchars($item['note']) : '' ?>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <?php if (!empty($item['photo'])): ?>
                                <a href="<?= htmlspecialchars($item['photo']) ?>" target="_blank" id="photo-link-<?= $item['id'] ?>"
                                   class="text-blue-600 hover:underline flex items-center gap-1 bg-blue-50 px-2 py-1 rounded">
                                    <i class="fas fa-image"></i> ดูรูป
                                </a>
                            <?php else: ?>
                                <a href="#" target="_blank" id="photo-link-<?= $item['id'] ?>"
                                   class="hidden text-blue-600 hover:underline items-center gap-1 bg-blue-50 px-2 py-1 rounded">
                                    <i class="fas fa-image"></i> ดูรูป
                                </a>
                            <?php endif; ?>
                            <button type="button" onclick="uploadPhoto(<?= $item['id'] ?>)"
                                    class="text-gray-600 hover:text-blue-900 bg-gray-100 hover:bg-gray-200 px-2.5 py-1 rounded transition">
                                <i class="fas fa-camera"></i> รูปภาพ
                            </button>
                            <button type="button" onclick="editItemNote(<?= $item['id'] ?>, <?= htmlspecialchars(json_encode($item['note'] ?? ''), ENT_QUOTES, 'UTF-8') ?>)"
                                    class="text-gray-600 hover:text-blue-900 bg-gray-100 hover:bg-gray-200 px-2.5 py-1 rounded transition">
                                <i class="fas fa-pen"></i> หมายเหตุ
                            </button>
                        </div>
                    </div>

                    <!-- Status Buttons -->
                    <div class="grid grid-cols-4 gap-2" id="actions-<?= $item['id'] ?>">
                        <button onclick="updateItemStatus(<?= $item['id'] ?>, 'normal')"
                            class="status-btn py-2.5 rounded-lg text-xs font-bold transition-all
                        <?= $item['status'] === 'normal' ? 'bg-green-500 text-white ring-2 ring-green-300 shadow-md' : 'bg-green-50 text-green-700 hover:bg-green-100 border border-green-200' ?>">
                            <i class="fas fa-check block text-base mb-0.5"></i> ปกติ
                        </button>
                        <button onclick="updateItemStatus(<?= $item['id'] ?>, 'damaged')"
                            class="status-btn py-2.5 rounded-lg text-xs font-bold transition-all
                        <?= $item['status'] === 'damaged' ? 'bg-orange-500 text-white ring-2 ring-orange-300 shadow-md' : 'bg-orange-50 text-orange-700 hover:bg-orange-100 border border-orange-200' ?>">
                            <i class="fas fa-wrench block text-base mb-0.5"></i> ชำรุด
                        </button>
                        <button onclick="updateItemStatus(<?= $item['id'] ?>, 'deteriorated')"
                            class="status-btn py-2.5 rounded-lg text-xs font-bold transition-all
                        <?= $item['status'] === 'deteriorated' ? 'bg-red-500 text-white ring-2 ring-red-300 shadow-md' : 'bg-red-50 text-red-700 hover:bg-red-100 border border-red-200' ?>">
                            <i class="fas fa-exclamation-triangle block text-base mb-0.5"></i> เสื่อม
                        </button>
                        <button onclick="updateItemStatus(<?= $item['id'] ?>, 'not_found')"
                            class="status-btn py-2.5 rounded-lg text-xs font-bold transition-all
                        <?= $item['status'] === 'not_found' ? 'bg-gray-500 text-white ring-2 ring-gray-300 shadow-md' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 border border-gray-200' ?>">
                            <i class="fas fa-question block text-base mb-0.5"></i> ไม่พบ
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Sticky Bottom Bar (Mobile) -->
<div
    class="fixed bottom-0 left-0 right-0 md:left-64 bg-blue-900 text-white shadow-2xl z-20 border-t-2 border-yellow-500">
    <div class="flex items-center justify-around py-2">
        <a href="inspection.php"
            class="flex flex-col items-center py-1 px-3 text-blue-200 hover:text-yellow-400 transition">
            <i class="fas fa-th-large text-lg"></i>
            <span class="text-xs mt-0.5">โครงการ</span>
        </a>
        <a href="inspection_check.php?project=<?= $projectId ?>"
            class="flex flex-col items-center py-1 px-3 text-yellow-400">
            <i class="fas fa-clipboard-check text-lg"></i>
            <span class="text-xs mt-0.5">ตรวจสอบ</span>
        </a>
        <button onclick="scanQR()"
            class="flex flex-col items-center py-1 px-3 text-blue-200 hover:text-yellow-400 transition">
            <i class="fas fa-qrcode text-lg"></i>
            <span class="text-xs mt-0.5">สแกน QR</span>
        </button>
        <a href="profile.php"
            class="flex flex-col items-center py-1 px-3 text-blue-200 hover:text-yellow-400 transition">
            <i class="fas fa-user text-lg"></i>
            <span class="text-xs mt-0.5">โปรไฟล์</span>
        </a>
    </div>
</div>

<script>
    const projectId = <?= $projectId ?>;

    function showReportModal() {
        SwalThemed.fire({
            title: '<i class="fas fa-file-pdf text-red-500"></i> รายงานผลการตรวจสอบ',
            html: `
                <div class="text-left space-y-4">
                    <div class="bg-blue-50 p-3 rounded-lg text-sm text-blue-800 flex gap-2">
                        <i class="fas fa-info-circle mt-0.5"></i>
                        <span>เมื่อตรวจสอบครบถ้วนแล้ว ให้แนบไฟล์รายงานผลการตรวจสอบ (PDF) เพื่อยืนยันการปฏิบัติงาน</span>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">ไฟล์รายงาน (PDF)</label>
                        <input type="file" id="reportFile" accept=".pdf" class="w-full border-2 border-dashed border-gray-300 rounded-lg p-4 text-sm text-center bg-gray-50 hover:bg-white transition">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">ข้อความเพิ่มเติม</label>
                        <textarea id="reportComment" class="w-full border-2 border-gray-200 rounded-lg p-3 text-sm focus:border-blue-900 focus:outline-none" placeholder="ระบุหมายเหตุ (ถ้ามี)" rows="3"></textarea>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-paper-plane"></i> ส่งรายงาน',
            cancelButtonText: 'ยกเลิก',
            preConfirm: () => {
                const file = document.getElementById('reportFile').files[0];
                if (!file) { Swal.showValidationMessage('กรุณาแนบไฟล์ PDF'); return false; }
                if (file.type !== 'application/pdf') { Swal.showValidationMessage('รองรับเฉพาะไฟล์ PDF'); return false; }
                return { file, comment: document.getElementById('reportComment').value };
            }
        }).then(r => {
            if (r.isConfirmed) {
                uploadReport(r.value);
            }
        });
    }

    function uploadReport(data) {
        showLoading('กำลังส่งรายงาน...');
        const formData = new FormData();
        formData.append('action', 'upload_report');
        formData.append('project_id', projectId);
        formData.append('report_file', data.file);
        formData.append('comment', data.comment);

        fetch('api/inspection.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(d => {
                if (d.success) SwalThemed.fire({ icon: 'success', title: 'ส่งรายงานสำเร็จ', text: 'บันทึกรายงานเข้าระบบเรียบร้อยแล้ว', timer: 2000, showConfirmButton: false });
                else SwalThemed.fire({ icon: 'error', title: 'ผิดพลาด', text: d.message });
            });
    }

    function updateItemStatus(itemId, status) {
        fetch('api/inspection.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'update_item', item_id: itemId, status: status })
        }).then(r => r.json()).then(data => {
            if (data.success) {
                // Update UI without reload
                const badgeMap = {
                    'normal': '<span class="badge bg-green-100 text-green-800">ปกติ</span>',
                    'damaged': '<span class="badge bg-orange-100 text-orange-800">ชำรุด</span>',
                    'deteriorated': '<span class="badge bg-red-100 text-red-800">เสื่อมสภาพ</span>',
                    'not_found': '<span class="badge bg-gray-300 text-gray-700">ไม่พบ</span>'
                };
                document.getElementById(`badge-${itemId}`).innerHTML = badgeMap[status];

                // Update card attribute
                const card = document.querySelector(`.item-card[data-search] #badge-${itemId}`).closest('.item-card');
                card.dataset.status = status;

                // Recalculate progress & tab counts
                const allCards = document.querySelectorAll('.item-card');
                const totalItems = allCards.length;
                let checkedItems = 0;
                allCards.forEach(c => { if (c.dataset.status !== 'pending') checkedItems++; });
                const pendingItems = totalItems - checkedItems;
                const pct = totalItems > 0 ? Math.round((checkedItems / totalItems) * 100) : 0;

                const checkedEl = document.getElementById('checkedCountText');
                const barEl = document.getElementById('progressBar');
                const pctEl = document.getElementById('progressPctText');
                if (checkedEl) checkedEl.textContent = checkedItems;
                if (barEl) barEl.style.width = pct + '%';
                if (pctEl) pctEl.textContent = pct + '% สำเร็จ';

                const pendingTab = document.querySelector('.filter-tab[data-status="pending"]');
                const checkedTab = document.querySelector('.filter-tab[data-status="checked"]');
                if (pendingTab) pendingTab.textContent = `รอตรวจ (${pendingItems})`;
                if (checkedTab) checkedTab.textContent = `ตรวจแล้ว (${checkedItems})`;

                // Update button styles
                const actions = document.getElementById(`actions-${itemId}`);
                const buttons = actions.querySelectorAll('.status-btn');
                const statusList = ['normal', 'damaged', 'deteriorated', 'not_found'];
                const colorMap = {
                    'normal': { active: 'bg-green-500 text-white ring-2 ring-green-300 shadow-md', inactive: 'bg-green-50 text-green-700 hover:bg-green-100 border border-green-200' },
                    'damaged': { active: 'bg-orange-500 text-white ring-2 ring-orange-300 shadow-md', inactive: 'bg-orange-50 text-orange-700 hover:bg-orange-100 border border-orange-200' },
                    'deteriorated': { active: 'bg-red-500 text-white ring-2 ring-red-300 shadow-md', inactive: 'bg-red-50 text-red-700 hover:bg-red-100 border border-red-200' },
                    'not_found': { active: 'bg-gray-500 text-white ring-2 ring-gray-300 shadow-md', inactive: 'bg-gray-100 text-gray-700 hover:bg-gray-200 border border-gray-200' }
                };
                buttons.forEach((btn, idx) => {
                    const s = statusList[idx];
                    btn.className = `status-btn py-2.5 rounded-lg text-xs font-bold transition-all ${s === status ? colorMap[s].active : colorMap[s].inactive}`;
                });

                // If damaged, ask for photo
                if (status === 'damaged' || status === 'deteriorated') {
                    SwalThemed.fire({
                        title: 'แนบรูปภาพ?',
                        text: 'ต้องการแนบรูปภาพหลักฐานหรือไม่?',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: '<i class="fas fa-camera"></i> ถ่ายรูป/เลือกรูป',
                        cancelButtonText: 'ข้าม',
                    }).then(r => {
                        if (r.isConfirmed) {
                            uploadPhoto(itemId);
                        }
                    });
                }
            } else {
                SwalThemed.fire({ icon: 'error', title: 'ผิดพลาด', text: data.message });
            }
        });
    }

    let currentStatusFilter = 'all';

    function uploadPhoto(itemId) {
        SwalThemed.fire({
            title: '<i class="fas fa-camera text-blue-900"></i> แนบรูปภาพ',
            html: '<input type="file" id="photoInput" accept="image/*" capture="environment" class="w-full border-2 border-gray-200 rounded-lg p-2 text-sm">',
            showCancelButton: true,
            confirmButtonText: 'อัปโหลด',
            cancelButtonText: 'ยกเลิก',
            preConfirm: () => {
                const file = document.getElementById('photoInput').files[0];
                if (!file) { Swal.showValidationMessage('เลือกรูปภาพ'); return false; }
                return file;
            }
        }).then(r => {
            if (r.isConfirmed) {
                showLoading('กำลังอัปโหลด...');
                const formData = new FormData();
                formData.append('action', 'upload_photo');
                formData.append('item_id', itemId);
                formData.append('photo', r.value);

                fetch('api/inspection.php', { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(d => {
                        if (d.success) {
                            const linkEl = document.getElementById(`photo-link-${itemId}`);
                            if (linkEl && d.path) {
                                linkEl.href = d.path;
                                linkEl.classList.remove('hidden');
                                linkEl.classList.add('flex');
                            }
                            SwalThemed.fire({ icon: 'success', title: 'อัปโหลดสำเร็จ!', timer: 1500, showConfirmButton: false });
                        } else {
                            SwalThemed.fire({ icon: 'error', title: 'ผิดพลาด', text: d.message });
                        }
                    });
            }
        });
    }

    function editItemNote(itemId, currentNote = '') {
        SwalThemed.fire({
            title: '<i class="fas fa-pen text-blue-900"></i> บันทึกหมายเหตุ',
            input: 'textarea',
            inputValue: currentNote,
            inputPlaceholder: 'ระบุหมายเหตุผลการตรวจสอบ...',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-save"></i> บันทึก',
            cancelButtonText: 'ยกเลิก'
        }).then(r => {
            if (r.isConfirmed) {
                const noteText = r.value.trim();
                fetch('api/inspection.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'update_item_note', item_id: itemId, note: noteText })
                }).then(res => res.json()).then(d => {
                    if (d.success) {
                        const noteEl = document.getElementById(`note-display-${itemId}`);
                        if (noteEl) {
                            noteEl.innerHTML = noteText ? `<i class="fas fa-comment-alt text-yellow-600 mr-1"></i>${noteText.replace(/</g, '&lt;')}` : '';
                        }
                        SwalThemed.fire({ icon: 'success', title: 'บันทึกหมายเหตุแล้ว', timer: 1200, showConfirmButton: false });
                    } else {
                        SwalThemed.fire({ icon: 'error', title: 'ผิดพลาด', text: d.message });
                    }
                });
            }
        });
    }

    function applyCombinedFilters() {
        const query = (document.getElementById('searchInput').value || '').toLowerCase().trim();
        const loc = document.getElementById('locationFilter')?.value || '';
        let matchCount = 0;
        document.querySelectorAll('.item-card').forEach(card => {
            const matchSearch = !query || card.dataset.search.includes(query);
            const matchLoc = !loc || card.dataset.location === loc;
            let matchStatus = true;
            if (currentStatusFilter === 'pending') matchStatus = card.dataset.status === 'pending';
            else if (currentStatusFilter === 'checked') matchStatus = card.dataset.status !== 'pending';

            const visible = matchSearch && matchLoc && matchStatus;
            card.style.display = visible ? '' : 'none';
            if (visible) matchCount++;
        });
        return matchCount;
    }

    function bulkCheckNormal() {
        const visiblePendingCards = [...document.querySelectorAll('.item-card')].filter(c => c.style.display !== 'none' && c.dataset.status === 'pending');
        if (visiblePendingCards.length === 0) {
            return SwalThemed.fire('ไม่มีรายการรอตรวจ', 'รายการที่แสดงอยู่ตอนนี้ได้รับการตรวจสอบแล้วทั้งหมด', 'info');
        }
        const ids = visiblePendingCards.map(c => c.dataset.id);
        const locText = document.getElementById('locationFilter')?.value;
        SwalThemed.fire({
            icon: 'question',
            title: `ติ๊ก "ปกติ" ทั้งหมด ${ids.length} รายการ?`,
            html: `ระบบจะปรับสถานะครุภัณฑ์ที่ยังรอตรวจซึ่งแสดงอยู่ในหน้าจอนี้${locText ? ` (ที่ตั้ง: <strong>${locText}</strong>)` : ''} จำนวน <strong>${ids.length}</strong> รายการ ให้เป็น <strong>"ปกติ"</strong> ทันที`,
            showCancelButton: true,
            confirmButtonColor: '#16a34a',
            confirmButtonText: '<i class="fas fa-check-double"></i> ยืนยันตรวจเป็นปกติ',
            cancelButtonText: 'ยกเลิก'
        }).then(r => {
            if (r.isConfirmed) {
                showLoading('กำลังบันทึกผลการตรวจ...');
                fetch('api/inspection.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'bulk_update_items', item_ids: ids, status: 'normal' })
                }).then(res => res.json()).then(d => {
                    if (d.success) {
                        SwalThemed.fire({ icon: 'success', title: 'บันทึกสำเร็จ!', text: `ปรับเป็นปกติแล้ว ${d.count} รายการ`, timer: 1500, showConfirmButton: false })
                            .then(() => location.reload());
                    } else {
                        SwalThemed.fire('ผิดพลาด', d.message, 'error');
                    }
                });
            }
        });
    }

    function filterItems() {
        applyCombinedFilters();
    }

    function filterByStatus(status) {
        currentStatusFilter = status;
        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.classList.remove('bg-blue-900', 'text-white');
            tab.classList.add('bg-gray-200', 'text-gray-600');
        });
        const activeTab = document.querySelector(`.filter-tab[data-status="${status}"]`);
        if (activeTab) {
            activeTab.classList.remove('bg-gray-200', 'text-gray-600');
            activeTab.classList.add('bg-blue-900', 'text-white');
        }
        applyCombinedFilters();
    }

    function scanQR() {
        SwalThemed.fire({
            title: '<i class="fas fa-qrcode text-blue-900"></i> สแกน / ค้นหารหัสครุภัณฑ์',
            html: `
            <div class="text-center">
                <p class="text-sm text-gray-500 mb-3">กรอกรหัสครุภัณฑ์หรือยิงสแกน QR / Barcode</p>
                <input id="qr_code" class="w-full border-2 border-gray-200 rounded-lg px-4 py-3 text-center text-lg font-mono focus:border-blue-900 focus:outline-none" placeholder="รหัสครุภัณฑ์" autofocus>
            </div>
        `,
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-search"></i> ค้นหา',
            cancelButtonText: 'ปิด',
        }).then(r => {
            if (r.isConfirmed) {
                const code = document.getElementById('qr_code').value.trim();
                if (code) {
                    document.getElementById('searchInput').value = code;
                    filterByStatus('all');
                    const found = applyCombinedFilters();
                    if (found === 0) {
                        SwalThemed.fire({
                            icon: 'warning',
                            title: 'ไม่พบครุภัณฑ์รหัสนี้',
                            text: `ไม่พบรหัส "${code}" ในรายการที่แสดงอยู่ ลองสลับไปที่แท็บ "ทั้งหมดในโครงการ" ด้านบน`,
                            confirmButtonText: 'ตกลง'
                        });
                    }
                }
            }
        });
    }
</script>

<?php include 'includes/footer.php'; ?>
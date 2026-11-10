<?php
/**
 * Inspection - ตรวจสอบครุภัณฑ์
 */
require_once 'includes/auth.php';
requireLogin();

$pageTitle = 'ตรวจสอบครุภัณฑ์';
$currentUser = getCurrentUser();
$isManager = hasRole(['admin', 'supply_officer']);

// Get inspection projects
if ($isManager) {
    $stmt = $pdo->query("SELECT ip.*, co.fiscal_year as co_year, co.order_number as co_number, u.full_name as created_by_name,
                         (SELECT COUNT(*) FROM inspection_items WHERE project_id = ip.id) as total_items,
                         (SELECT COUNT(*) FROM inspection_items WHERE project_id = ip.id AND status != 'pending') as checked_items,
                         (SELECT COUNT(*) FROM inspection_items WHERE project_id = ip.id AND status = 'normal') as normal_items,
                         (SELECT COUNT(*) FROM inspection_items WHERE project_id = ip.id AND status IN ('damaged', 'deteriorated')) as damaged_items,
                         (SELECT COUNT(*) FROM inspection_items WHERE project_id = ip.id AND status = 'not_found') as lost_items
                         FROM inspection_projects ip
                         LEFT JOIN committee_orders co ON ip.committee_order_id = co.id
                         JOIN users u ON ip.created_by = u.id
                         ORDER BY ip.created_at DESC");
    $projects = $stmt->fetchAll();
} else {
    // For committee members or assigned users
    $userId = $currentUser['id'];
    $stmt = $pdo->prepare("SELECT DISTINCT ip.*, co.fiscal_year as co_year, co.order_number as co_number, u.full_name as created_by_name,
                          (SELECT COUNT(*) FROM inspection_items WHERE project_id = ip.id) as total_items,
                          (SELECT COUNT(*) FROM inspection_items WHERE project_id = ip.id AND status != 'pending') as checked_items,
                          (SELECT COUNT(*) FROM inspection_items WHERE project_id = ip.id AND status = 'normal') as normal_items,
                          (SELECT COUNT(*) FROM inspection_items WHERE project_id = ip.id AND status IN ('damaged', 'deteriorated')) as damaged_items,
                          (SELECT COUNT(*) FROM inspection_items WHERE project_id = ip.id AND status = 'not_found') as lost_items,
                          (SELECT COUNT(*) FROM inspection_items WHERE project_id = ip.id AND (assigned_to = ? OR group_id IN (SELECT group_id FROM inspection_group_members WHERE user_id = ?))) as my_items,
                          (SELECT COUNT(*) FROM inspection_items WHERE project_id = ip.id AND (assigned_to = ? OR group_id IN (SELECT group_id FROM inspection_group_members WHERE user_id = ?)) AND status != 'pending') as my_checked
                          FROM inspection_projects ip
                          JOIN users u ON ip.created_by = u.id
                          LEFT JOIN committee_orders co ON ip.committee_order_id = co.id
                          LEFT JOIN committee_members cm ON ip.committee_order_id = cm.order_id AND cm.user_id = ?
                          LEFT JOIN inspection_items ii ON ip.id = ii.project_id AND ii.assigned_to = ?
                          LEFT JOIN inspection_groups ig ON ip.id = ig.project_id
                          LEFT JOIN inspection_group_members igm ON ig.id = igm.group_id AND igm.user_id = ?
                          WHERE cm.user_id IS NOT NULL OR ii.id IS NOT NULL OR igm.user_id IS NOT NULL
                          ORDER BY ip.created_at DESC");
    $stmt->execute([$userId, $userId, $userId, $userId, $userId, $userId, $userId]);
    $projects = $stmt->fetchAll();
}

// Get committee orders for creating project
$committeeOrders = $pdo->query("SELECT * FROM committee_orders WHERE status = 'active' ORDER BY fiscal_year DESC")->fetchAll();

// Get all assets for import
$allAssets = $pdo->query("SELECT id, asset_code, name, location, building, floor FROM assets WHERE status = 'active' ORDER BY asset_code")->fetchAll();

// Get committee members for assignment
$committeeMembers = [];
if ($isManager) {
    $committeeMembers = $pdo->query("SELECT DISTINCT u.id, u.full_name, u.department FROM users u 
                                     JOIN committee_members cm ON u.id = cm.user_id
                                     JOIN committee_orders co ON cm.order_id = co.id AND co.status = 'active'
                                     ORDER BY u.full_name")->fetchAll();
}

include 'includes/header.php';
include 'includes/sidebar.php';

$projectStatusLabels = ['draft' => 'ร่าง', 'in_progress' => 'กำลังดำเนินการ', 'completed' => 'เสร็จสิ้น', 'cancelled' => 'ยกเลิก'];
$projectStatusBadges = ['draft' => 'badge-pending', 'in_progress' => 'badge-approved', 'completed' => 'badge-completed', 'cancelled' => 'badge-rejected'];
$itemStatusLabels = ['pending' => 'รอตรวจ', 'normal' => 'ปกติ', 'damaged' => 'ชำรุด', 'deteriorated' => 'เสื่อมสภาพ', 'not_found' => 'ไม่พบ'];
?>

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-extrabold text-gray-800 flex items-center gap-2">
            <i class="fas fa-clipboard-check text-blue-900"></i> ตรวจสอบครุภัณฑ์
        </h1>
        <p class="text-gray-500 text-sm mt-1">
            <?= $isManager ? 'จัดการโครงการตรวจสอบครุภัณฑ์ประจำปี' : 'รายการที่ได้รับมอบหมายให้ตรวจสอบ' ?>
        </p>
    </div>
    <?php if ($isManager): ?>
        <button onclick="showCreateProjectForm()"
            class="btn-press bg-blue-900 text-white px-6 py-3 rounded-xl font-semibold border-b-4 border-yellow-700 hover:bg-blue-800 flex items-center gap-2 shadow-lg">
            <i class="fas fa-plus"></i> สร้างโครงการตรวจสอบ
        </button>
    <?php endif; ?>
</div>

<!-- Projects -->
<div class="space-y-4">
    <?php if (empty($projects)): ?>
        <div class="bg-white rounded-xl p-12 text-center shadow-sm border border-gray-100">
            <i class="fas fa-clipboard text-gray-300 text-5xl mb-4"></i>
            <p class="text-lg font-semibold text-gray-500">ยังไม่มีโครงการตรวจสอบ</p>
        </div>
    <?php else: ?>
        <?php foreach ($projects as $i => $proj): ?>
            <?php
            $hasMyItems = !$isManager && isset($proj['my_items']) && $proj['my_items'] > 0;
            $totalItems = $hasMyItems ? $proj['my_items'] : ($proj['total_items'] ?? 0);
            $checkedItems = $hasMyItems ? $proj['my_checked'] : ($proj['checked_items'] ?? 0);
            $progress = $totalItems > 0 ? round(($checkedItems / $totalItems) * 100) : 0;
            ?>
            <div class="card-hover bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden animate-fade-in"
                style="animation-delay:<?= $i * 0.05 ?>s">
                <div class="p-5">
                    <div class="flex flex-col sm:flex-row justify-between gap-3 mb-4">
                        <div>
                            <h3 class="font-bold text-gray-800 text-lg flex items-center gap-2">
                                <i class="fas fa-clipboard-list text-blue-900"></i>
                                <?= htmlspecialchars($proj['name']) ?>
                            </h3>
                            <p class="text-sm text-gray-500 mt-1">ปีงบ
                                <?= $proj['fiscal_year'] ?>
                                <?php if (!empty($proj['co_number']) || !empty($proj['co_year'])): ?>
                                    | คำสั่ง: <?= htmlspecialchars($proj['co_number'] ?: ('ปี ' . $proj['co_year'])) ?>
                                <?php endif; ?>
                                | สร้างโดย <?= htmlspecialchars($proj['created_by_name']) ?>
                            </p>
                        </div>
                        <span class="badge <?= $projectStatusBadges[$proj['status']] ?? '' ?> self-start">
                            <?= $projectStatusLabels[$proj['status']] ?? $proj['status'] ?>
                        </span>
                    </div>

                    <!-- Progress Bar & Quick Summary -->
                    <div class="mb-4">
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-gray-600">ความคืบหน้า<?= $hasMyItems ? ' (ส่วนที่รับผิดชอบ)' : '' ?></span>
                            <span class="font-bold text-gray-800">
                                <?= $checkedItems ?>/<?= $totalItems ?> (<?= $progress ?>%)
                            </span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-4 overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-1000 flex items-center justify-center text-xs text-white font-bold
                                <?= $progress >= 100 ? 'bg-green-500' : ($progress >= 50 ? 'bg-blue-600' : 'bg-yellow-500') ?>"
                                style="width:<?= max($progress, 3) ?>%">
                                <?= $progress >= 20 ? $progress . '%' : '' ?>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2 mt-2 text-xs">
                            <span class="bg-green-50 text-green-700 px-2.5 py-1 rounded-md font-semibold">ปกติ: <?= number_format($proj['normal_items'] ?? 0) ?></span>
                            <span class="bg-orange-50 text-orange-700 px-2.5 py-1 rounded-md font-semibold">ชำรุด/เสื่อม: <?= number_format($proj['damaged_items'] ?? 0) ?></span>
                            <span class="bg-red-50 text-red-700 px-2.5 py-1 rounded-md font-semibold">ไม่พบ: <?= number_format($proj['lost_items'] ?? 0) ?></span>
                            <span class="bg-gray-100 text-gray-600 px-2.5 py-1 rounded-md font-semibold">รอตรวจ: <?= number_format(max(0, ($proj['total_items'] ?? 0) - ($proj['checked_items'] ?? 0))) ?></span>
                        </div>
                    </div>

                    <!-- Date Range -->
                    <?php if ($proj['start_date'] || $proj['end_date']): ?>
                        <div class="text-sm text-gray-500 mb-4">
                            <i class="fas fa-calendar text-blue-900 mr-1"></i>
                            <?= formatThaiDate($proj['start_date']) ?> —
                            <?= formatThaiDate($proj['end_date']) ?>
                        </div>
                    <?php endif; ?>

                    <!-- Action Buttons -->
                    <div class="flex flex-wrap gap-2">
                        <?php if ($isManager): ?>
                            <a href="inspection_detail.php?id=<?= $proj['id'] ?>"
                                class="btn-press px-4 py-2 bg-blue-900 text-white rounded-lg text-sm font-semibold hover:bg-blue-800 transition flex items-center gap-1">
                                <i class="fas fa-cog"></i> จัดการ
                            </a>
                            <a href="inspection_check.php?project=<?= $proj['id'] ?>"
                                class="btn-press px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700 transition flex items-center gap-1">
                                <i class="fas fa-search"></i> ดำเนินการตรวจสอบ
                            </a>
                            <a href="inspection_report_print.php?project=<?= $proj['id'] ?>" target="_blank"
                                class="btn-press px-4 py-2 bg-purple-600 text-white rounded-lg text-sm font-semibold hover:bg-purple-700 transition flex items-center gap-1">
                                <i class="fas fa-print"></i> พิมพ์รายงานสรุป
                            </a>
                            <?php if (in_array($proj['status'], ['draft', 'in_progress'])): ?>
                                <button onclick="importAssets(<?= $proj['id'] ?>)"
                                    class="btn-press px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-semibold hover:bg-green-700 transition flex items-center gap-1">
                                    <i class="fas fa-file-import"></i> Import ครุภัณฑ์
                                </button>
                            <?php endif; ?>
                            <?php if ($proj['status'] === 'draft'): ?>
                                <button onclick="startProject(<?= $proj['id'] ?>)"
                                    class="btn-press px-4 py-2 bg-yellow-600 text-white rounded-lg text-sm font-semibold hover:bg-yellow-700 transition flex items-center gap-1">
                                    <i class="fas fa-play"></i> เริ่มตรวจสอบ
                                </button>
                            <?php elseif ($proj['status'] === 'in_progress'): ?>
                                <button onclick="completeProject(<?= $proj['id'] ?>)"
                                    class="btn-press px-4 py-2 bg-emerald-700 text-white rounded-lg text-sm font-semibold hover:bg-emerald-800 transition flex items-center gap-1">
                                    <i class="fas fa-check-double"></i> สรุปผล
                                </button>
                            <?php endif; ?>
                            <button onclick='editProject(<?= htmlspecialchars(json_encode($proj), ENT_QUOTES, "UTF-8") ?>)'
                                class="btn-press px-3 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-200 transition flex items-center gap-1" title="แก้ไขโครงการ">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="deleteProject(<?= $proj['id'] ?>, '<?= addslashes($proj['name']) ?>')"
                                class="btn-press px-3 py-2 bg-red-50 text-red-600 rounded-lg text-sm font-semibold hover:bg-red-100 transition flex items-center gap-1" title="ลบโครงการ">
                                <i class="fas fa-trash"></i>
                            </button>
                        <?php else: ?>
                            <a href="inspection_check.php?project=<?= $proj['id'] ?>"
                                class="btn-press px-4 py-2 bg-blue-900 text-white rounded-lg text-sm font-semibold hover:bg-blue-800 transition flex items-center gap-1">
                                <i class="fas fa-search"></i> ดำเนินการตรวจสอบ
                            </a>
                            <a href="inspection_report_print.php?project=<?= $proj['id'] ?>" target="_blank"
                                class="btn-press px-4 py-2 bg-purple-600 text-white rounded-lg text-sm font-semibold hover:bg-purple-700 transition flex items-center gap-1">
                                <i class="fas fa-print"></i> พิมพ์รายงานสรุป
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
    const committeeOrders = <?= json_encode($committeeOrders) ?>;
    const allAssets = <?= json_encode($allAssets) ?>;
    const committeeMembers = <?= json_encode($committeeMembers) ?>;

    function showCreateProjectForm(defaultOrderId = '', defaultYear = '') {
        const yearVal = defaultYear || (new Date().getFullYear() + 543);
        let coOptions = committeeOrders.map(co => `<option value="${co.id}" ${co.id == defaultOrderId ? 'selected' : ''}>ปี ${co.fiscal_year} - ${co.order_number || 'ไม่ระบุเลข'}</option>`).join('');

        SwalThemed.fire({
            title: '<i class="fas fa-plus-circle text-blue-900"></i> สร้างโครงการตรวจสอบ',
            html: `
            <div class="text-left space-y-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">ชื่อโครงการ *</label>
                    <input id="ip_name" value="${defaultYear ? 'ตรวจสอบครุภัณฑ์ประจำปีงบประมาณ ' + defaultYear : ''}" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none" placeholder="เช่น ตรวจสอบครุภัณฑ์ประจำปี 2569">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">ปีงบประมาณ *</label>
                        <input id="ip_year" type="number" value="${yearVal}" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">คำสั่งแต่งตั้ง</label>
                        <select id="ip_order" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none">
                            <option value="">-- เลือก --</option>${coOptions}
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">วันเริ่มต้น</label>
                        <input id="ip_start" value="${new Date().toISOString().slice(0, 10)}" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none flatpickr-input" placeholder="เลือกวันที่">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">วันสิ้นสุด</label>
                        <input id="ip_end" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none flatpickr-input" placeholder="เลือกวันที่">
                    </div>
                </div>
                <div class="bg-blue-50 p-3 rounded-xl border border-blue-200 space-y-2 mt-2">
                    <p class="text-xs font-bold text-blue-900"><i class="fas fa-bolt text-yellow-600 mr-1"></i> ตัวช่วยลดขั้นตอนการทำงาน:</p>
                    <label class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer">
                        <input type="checkbox" id="ip_auto_import" checked class="rounded text-blue-900">
                        <span>นำเข้าครุภัณฑ์ที่ใช้งานปกติทั้งหมด (${allAssets.length} รายการ) เข้าโครงการอัตโนมัติ</span>
                    </label>
                    <label class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer">
                        <input type="checkbox" id="ip_auto_start" checked class="rounded text-blue-900">
                        <span>เปิดสถานะ "กำลังดำเนินการ" และแจ้งเตือนคณะกรรมการทันที</span>
                    </label>
                </div>
            </div>
        `,
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-save"></i> สร้างโครงการ',
            cancelButtonText: 'ยกเลิก',
            width: '580px',
            didOpen: () => {
                flatpickr('#ip_start', { locale: 'th', dateFormat: 'Y-m-d' });
                flatpickr('#ip_end', { locale: 'th', dateFormat: 'Y-m-d' });
            },
            preConfirm: () => {
                const name = document.getElementById('ip_name').value.trim();
                if (!name) { Swal.showValidationMessage('กรุณากรอกชื่อโครงการ'); return false; }
                return {
                    name,
                    fiscal_year: document.getElementById('ip_year').value,
                    committee_order_id: document.getElementById('ip_order').value || null,
                    start_date: document.getElementById('ip_start').value || null,
                    end_date: document.getElementById('ip_end').value || null,
                    auto_import_active: document.getElementById('ip_auto_import').checked,
                    auto_start: document.getElementById('ip_auto_start').checked
                };
            }
        }).then(result => {
            if (result.isConfirmed) {
                showLoading('กำลังสร้างโครงการ...');
                fetch('api/inspection.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'create_project', ...result.value })
                }).then(r => r.json()).then(data => {
                    if (data.success) {
                        SwalThemed.fire({
                            icon: 'success',
                            title: 'สร้างโครงการสำเร็จ!',
                            text: data.imported_count ? `นำเข้าครุภัณฑ์พร้อมตรวจสอบแล้ว ${data.imported_count} รายการ` : '',
                            timer: 1800,
                            showConfirmButton: false
                        }).then(() => window.location.href = 'inspection.php');
                    } else SwalThemed.fire({ icon: 'error', title: 'ผิดพลาด', text: data.message });
                });
            }
        });
    }

    function editProject(proj) {
        let coOptions = committeeOrders.map(co => `<option value="${co.id}" ${co.id == proj.committee_order_id ? 'selected' : ''}>ปี ${co.fiscal_year} - ${co.order_number || 'ไม่ระบุเลข'}</option>`).join('');

        SwalThemed.fire({
            title: '<i class="fas fa-edit text-yellow-600"></i> แก้ไขโครงการตรวจสอบ',
            html: `
            <div class="text-left space-y-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">ชื่อโครงการ *</label>
                    <input id="ep_name" value="${proj.name || ''}" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">ปีงบประมาณ *</label>
                        <input id="ep_year" type="number" value="${proj.fiscal_year || ''}" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">คำสั่งแต่งตั้ง</label>
                        <select id="ep_order" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none">
                            <option value="">-- เลือก --</option>${coOptions}
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">วันเริ่มต้น</label>
                        <input id="ep_start" value="${proj.start_date || ''}" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none flatpickr-input">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">วันสิ้นสุด</label>
                        <input id="ep_end" value="${proj.end_date || ''}" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none flatpickr-input">
                    </div>
                </div>
            </div>
        `,
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-save"></i> บันทึก',
            cancelButtonText: 'ยกเลิก',
            width: '550px',
            didOpen: () => {
                flatpickr('#ep_start', { locale: 'th', dateFormat: 'Y-m-d' });
                flatpickr('#ep_end', { locale: 'th', dateFormat: 'Y-m-d' });
            },
            preConfirm: () => {
                const name = document.getElementById('ep_name').value.trim();
                if (!name) { Swal.showValidationMessage('กรุณากรอกชื่อโครงการ'); return false; }
                return {
                    project_id: proj.id,
                    name,
                    fiscal_year: document.getElementById('ep_year').value,
                    committee_order_id: document.getElementById('ep_order').value || null,
                    start_date: document.getElementById('ep_start').value || null,
                    end_date: document.getElementById('ep_end').value || null
                };
            }
        }).then(result => {
            if (result.isConfirmed) {
                showLoading();
                fetch('api/inspection.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'update_project', ...result.value })
                }).then(r => r.json()).then(d => {
                    if (d.success) location.reload();
                    else SwalThemed.fire('ผิดพลาด', d.message, 'error');
                });
            }
        });
    }

    function deleteProject(projectId, name) {
        SwalThemed.fire({
            icon: 'warning',
            title: 'ยืนยันการลบโครงการ?',
            html: `ต้องการลบโครงการ <strong>"${name}"</strong> และรายการตรวจในโครงการนี้ทั้งหมดหรือไม่?`,
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            confirmButtonText: '<i class="fas fa-trash"></i> ลบโครงการ',
            cancelButtonText: 'ยกเลิก'
        }).then(r => {
            if (r.isConfirmed) {
                showLoading('กำลังลบ...');
                fetch('api/inspection.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete_project', project_id: projectId })
                }).then(res => res.json()).then(d => {
                    if (d.success) location.reload();
                    else SwalThemed.fire('ผิดพลาด', d.message, 'error');
                });
            }
        });
    }

    function importAssets(projectId) {
        showLoading('กำลังตรวจสอบรายการครุภัณฑ์...');
        fetch(`api/inspection.php?action=project_imported_ids&project_id=${projectId}`)
            .then(r => r.json())
            .then(res => {
                Swal.close();
                const importedSet = new Set((res.asset_ids || []).map(String));
                const remainingAssets = allAssets.filter(a => !importedSet.has(String(a.id)));

                if (remainingAssets.length === 0) {
                    return SwalThemed.fire('นำเข้าครบแล้ว', 'ครุภัณฑ์ที่ใช้งานปกติทุกรายการอยู่ในโครงการนี้แล้ว', 'info');
                }

                // Get distinct locations for filtering
                const locations = [...new Set(remainingAssets.map(a => a.location).filter(Boolean))].sort();
                const locOpts = locations.map(l => `<option value="${l}">${l}</option>`).join('');

                let rows = remainingAssets.slice(0, 300).map(a => `
                    <tr class="import-row hover:bg-blue-50" data-loc="${a.location || ''}" data-search="${(a.asset_code + ' ' + a.name + ' ' + (a.location || '')).toLowerCase()}">
                        <td class="p-2 text-center"><input type="checkbox" class="import-check rounded" value="${a.id}" checked></td>
                        <td class="p-2 text-xs font-mono">${a.asset_code}</td>
                        <td class="p-2 text-sm">${a.name}</td>
                        <td class="p-2 text-xs text-gray-500">${a.location || '-'}</td>
                    </tr>
                `).join('');

                SwalThemed.fire({
                    title: '<i class="fas fa-file-import text-green-600"></i> Import ครุภัณฑ์เข้าโครงการ',
                    html: `
                    <div class="text-left space-y-2">
                        <div class="bg-green-50 border border-green-200 rounded-lg p-3 flex items-center justify-between">
                            <span class="text-xs text-green-800">พบครุภัณฑ์ที่ยังไม่ได้นำเข้า <strong>${remainingAssets.length}</strong> รายการ</span>
                            <button type="button" onclick="quickImportAllActive(${projectId})" class="bg-green-600 text-white text-xs font-bold px-3 py-1.5 rounded-lg hover:bg-green-700 transition">
                                <i class="fas fa-bolt"></i> นำเข้าทั้งหมดทันที (${remainingAssets.length})
                            </button>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="text" id="imp_search" oninput="filterImportModal()" placeholder="ค้นหารหัส, ชื่อ..." class="border rounded-lg px-3 py-1.5 text-xs">
                            <select id="imp_loc" onchange="filterImportModal()" class="border rounded-lg px-2 py-1.5 text-xs">
                                <option value="">-- ทุกที่ตั้ง --</option>${locOpts}
                            </select>
                        </div>
                        <div class="flex justify-between items-center pt-1">
                            <span class="text-xs text-gray-500">${remainingAssets.length > 300 ? 'แสดง 300 รายการแรก (แนะนำใช้ปุ่มนำเข้าทั้งหมดด้านบน)' : 'เลือกรายการที่ต้องการตรวจสอบ'}</span>
                            <label class="text-xs cursor-pointer"><input type="checkbox" id="checkAll" checked onchange="toggleAllChecks(this)"> เลือกที่แสดงทั้งหมด</label>
                        </div>
                        <div class="max-h-72 overflow-y-auto border rounded-lg">
                            <table class="w-full text-left">
                                <thead class="bg-gray-100 sticky top-0"><tr>
                                    <th class="p-2 w-8"></th><th class="p-2 text-xs">รหัส</th><th class="p-2 text-xs">ชื่อ</th><th class="p-2 text-xs">ที่ตั้ง</th>
                                </tr></thead>
                                <tbody>${rows}</tbody>
                            </table>
                        </div>
                    </div>
                `,
                    showCancelButton: true,
                    confirmButtonText: '<i class="fas fa-download"></i> Import ที่เลือก',
                    cancelButtonText: 'ยกเลิก',
                    width: '720px',
                    preConfirm: () => {
                        const ids = [...document.querySelectorAll('.import-row:not([style*="display: none"]) .import-check:checked')].map(c => c.value);
                        if (ids.length === 0) { Swal.showValidationMessage('กรุณาเลือกอย่างน้อย 1 รายการ'); return false; }
                        return { project_id: projectId, asset_ids: ids };
                    }
                }).then(result => {
                    if (result.isConfirmed) {
                        showLoading(`กำลัง Import ${result.value.asset_ids.length} รายการ...`);
                        fetch('api/inspection.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ action: 'import_assets', ...result.value })
                        }).then(r => r.json()).then(data => {
                            if (data.success) {
                                SwalThemed.fire({ icon: 'success', title: 'Import สำเร็จ!', text: `นำเข้า ${data.count} รายการ`, timer: 1800, showConfirmButton: false }).then(() => location.reload());
                            } else SwalThemed.fire({ icon: 'error', title: 'ผิดพลาด', text: data.message });
                        });
                    }
                });
            });
    }

    function quickImportAllActive(projectId) {
        showLoading('กำลังนำเข้าครุภัณฑ์ทั้งหมด...');
        fetch('api/inspection.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'import_assets', project_id: projectId, import_all_active: true })
        }).then(r => r.json()).then(data => {
            if (data.success) {
                SwalThemed.fire({ icon: 'success', title: 'Import สำเร็จ!', text: `นำเข้า ${data.count} รายการเรียบร้อย`, timer: 1800, showConfirmButton: false }).then(() => location.reload());
            } else SwalThemed.fire({ icon: 'error', title: 'ผิดพลาด', text: data.message });
        });
    }

    function filterImportModal() {
        const q = (document.getElementById('imp_search')?.value || '').toLowerCase().trim();
        const loc = document.getElementById('imp_loc')?.value || '';
        document.querySelectorAll('.import-row').forEach(r => {
            const mQ = !q || r.dataset.search.includes(q);
            const mL = !loc || r.dataset.loc === loc;
            r.style.display = (mQ && mL) ? '' : 'none';
        });
    }

    function toggleAllChecks(el) {
        document.querySelectorAll('.import-row:not([style*="display: none"]) .import-check').forEach(c => c.checked = el.checked);
    }

    function startProject(id) {
        SwalThemed.fire({
            icon: 'question', title: 'เริ่มตรวจสอบ?', text: 'เปลี่ยนสถานะเป็น "กำลังดำเนินการ"',
            showCancelButton: true, confirmButtonText: 'เริ่ม', cancelButtonText: 'ยกเลิก'
        }).then(r => {
            if (r.isConfirmed) {
                showLoading();
                fetch('api/inspection.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'update_status', project_id: id, status: 'in_progress' })
                }).then(r => r.json()).then(d => {
                    if (d.success) SwalThemed.fire({ icon: 'success', title: 'เริ่มเรียบร้อย!', timer: 1500, showConfirmButton: false }).then(() => location.reload());
                    else SwalThemed.fire({ icon: 'error', title: 'ผิดพลาด', text: d.message });
                });
            }
        });
    }

    function completeProject(id) {
        showLoading('กำลังประมวลผลสรุป...');
        fetch(`api/inspection.php?action=project_summary&project_id=${id}`)
            .then(r => r.json())
            .then(res => {
                Swal.close();
                const s = res.summary || { total: 0, pending: 0, normal: 0, damaged: 0, deteriorated: 0, not_found: 0 };
                const pendingCount = parseInt(s.pending || 0);
                const issueCount = parseInt(s.damaged || 0) + parseInt(s.deteriorated || 0) + parseInt(s.not_found || 0);

                SwalThemed.fire({
                    icon: pendingCount > 0 ? 'warning' : 'question',
                    title: 'สรุปผลการตรวจสอบครุภัณฑ์',
                    html: `
                        <div class="text-left space-y-3 text-sm">
                            <div class="grid grid-cols-2 gap-2 bg-gray-50 p-3 rounded-lg border">
                                <div>ทั้งหมด: <strong>${s.total}</strong> รายการ</div>
                                <div class="text-green-700">ปกติ: <strong>${s.normal || 0}</strong> รายการ</div>
                                <div class="text-orange-700">ชำรุด/เสื่อม: <strong>${parseInt(s.damaged || 0) + parseInt(s.deteriorated || 0)}</strong> รายการ</div>
                                <div class="text-red-700">ไม่พบ: <strong>${s.not_found || 0}</strong> รายการ</div>
                            </div>
                            ${pendingCount > 0 ? `
                                <div class="bg-yellow-50 border-l-4 border-yellow-500 p-3 rounded">
                                    <p class="text-xs font-bold text-yellow-800 mb-1"><i class="fas fa-exclamation-triangle"></i> ยังมีรายการรอตรวจอีก ${pendingCount} รายการ</p>
                                    <label class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer mt-1">
                                        <input type="checkbox" id="cp_mark_normal" checked class="rounded text-blue-900">
                                        <span>ปรับรายการที่รอตรวจ (${pendingCount} รายการ) ให้เป็น "ปกติ" ทั้งหมดอัตโนมัติ</span>
                                    </label>
                                </div>
                            ` : ''}
                            ${issueCount > 0 ? `
                                <div class="bg-blue-50 border-l-4 border-blue-600 p-3 rounded">
                                    <label class="flex items-start gap-2 text-xs text-blue-900 cursor-pointer">
                                        <input type="checkbox" id="cp_sync_assets" checked class="rounded text-blue-900 mt-0.5">
                                        <span><strong>ลดขั้นตอนงานพัสดุ:</strong> ซิงค์สถานะครุภัณฑ์ที่ตรวจพบว่า "ชำรุด/เสื่อมสภาพ" และ "ไม่พบ (สูญหาย)" (${issueCount} รายการ) ไปยังทะเบียนครุภัณฑ์หลักอัตโนมัติ</span>
                                    </label>
                                </div>
                            ` : ''}
                            <p class="text-xs text-gray-500 text-center">เมื่อสรุปผลแล้วจะล็อกการแก้ไขผลการตรวจ และสามารถพิมพ์รายงานสรุป PDF ได้ทันที</p>
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: '<i class="fas fa-check-double"></i> ยืนยันสรุปผล',
                    cancelButtonText: 'ยกเลิก',
                    width: '550px',
                    preConfirm: () => ({
                        mark_pending_normal: document.getElementById('cp_mark_normal')?.checked || false,
                        sync_asset_status: document.getElementById('cp_sync_assets')?.checked || false
                    })
                }).then(r => {
                    if (r.isConfirmed) {
                        showLoading('กำลังบันทึกสรุปผล...');
                        fetch('api/inspection.php', {
                            method: 'POST', headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                action: 'update_status',
                                project_id: id,
                                status: 'completed',
                                ...r.value
                            })
                        }).then(r => r.json()).then(d => {
                            if (d.success) {
                                SwalThemed.fire({
                                    icon: 'success',
                                    title: 'สรุปผลเรียบร้อย!',
                                    text: 'คุณสามารถกดปุ่ม "พิมพ์รายงานสรุป" เพื่อออกเอกสารสำหรับลงนามได้ทันที',
                                    timer: 2000,
                                    showConfirmButton: false
                                }).then(() => location.reload());
                            } else SwalThemed.fire({ icon: 'error', title: 'ผิดพลาด', text: d.message });
                        });
                    }
                });
            });
    }

    document.addEventListener('DOMContentLoaded', () => {
        const params = new URLSearchParams(window.location.search);
        if (params.get('create_from_order')) {
            showCreateProjectForm(params.get('create_from_order'), params.get('year') || '');
        }
    });
</script>

<?php include 'includes/footer.php'; ?>
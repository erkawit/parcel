<?php
/**
 * Inspection Detail - จัดการการตรวจสอบ (Chairman/Manager View)
 */
require_once 'includes/auth.php';
requireLogin();

$projectId = $_GET['id'] ?? 0;
if (!$projectId) {
    header('Location: inspection.php');
    exit;
}

if (!hasRole(['admin', 'supply_officer'])) {
    header('Location: inspection_check.php?project=' . urlencode($projectId));
    exit;
}

$stmt = $pdo->prepare("SELECT ip.*, co.fiscal_year as co_year FROM inspection_projects ip 
                       LEFT JOIN committee_orders co ON ip.committee_order_id = co.id WHERE ip.id = ?");
$stmt->execute([$projectId]);
$project = $stmt->fetch();
if (!$project) {
    header('Location: inspection.php');
    exit;
}

$pageTitle = 'จัดการ: ' . $project['name'];

// Get inspection items
$stmt = $pdo->prepare("SELECT ii.*, a.asset_code, a.name as asset_name, a.location, a.building, a.floor, a.room,
                       u.full_name as assigned_name, ui.full_name as inspected_by_name, ig.name as group_name
                       FROM inspection_items ii
                       JOIN assets a ON ii.asset_id = a.id
                       LEFT JOIN users u ON ii.assigned_to = u.id
                       LEFT JOIN users ui ON ii.inspected_by = ui.id
                       LEFT JOIN inspection_groups ig ON ii.group_id = ig.id
                       WHERE ii.project_id = ?
                       ORDER BY ig.name, a.asset_code");
$stmt->execute([$projectId]);
$items = $stmt->fetchAll();

// Get groups
$stmt = $pdo->prepare("SELECT ig.*, 
                       (SELECT COUNT(*) FROM inspection_group_members WHERE group_id = ig.id) as member_count,
                       (SELECT COUNT(*) FROM inspection_items WHERE group_id = ig.id) as item_count
                       FROM inspection_groups ig WHERE ig.project_id = ? ORDER BY ig.name");
$stmt->execute([$projectId]);
$groups = $stmt->fetchAll();

// Get members for each group (both IDs and Names)
$groupMembers = [];
$groupMemberNames = [];
foreach ($groups as $group) {
    $stmt = $pdo->prepare("SELECT igm.user_id, u.full_name FROM inspection_group_members igm JOIN users u ON igm.user_id = u.id WHERE igm.group_id = ?");
    $stmt->execute([$group['id']]);
    $rows = $stmt->fetchAll();
    $groupMembers[$group['id']] = array_column($rows, 'user_id');
    $groupMemberNames[$group['id']] = array_column($rows, 'full_name');
}

// Get uploaded inspection reports (if table exists)
$reports = [];
try {
    $stmtRep = $pdo->prepare("SELECT ir.*, u.full_name FROM inspection_reports ir JOIN users u ON ir.user_id = u.id WHERE ir.project_id = ? ORDER BY ir.created_at DESC");
    $stmtRep->execute([$projectId]);
    $reports = $stmtRep->fetchAll();
} catch (Exception $e) {
    $reports = [];
}

// Committee members
$committeeMembers = [];
if ($project['committee_order_id']) {
    $stmt = $pdo->prepare("SELECT u.id, u.full_name, u.department, cm.role FROM committee_members cm 
                           JOIN users u ON cm.user_id = u.id WHERE cm.order_id = ?");
    $stmt->execute([$project['committee_order_id']]);
    $committeeMembers = $stmt->fetchAll();
}

// Get distinct locations for scope assignment (using 'location' field from assets)
$distinctLocations = [];
foreach ($items as $item) {
    if (!empty($item['location']) && trim($item['location']) !== '' && trim($item['location']) !== '-') {
        $loc = trim($item['location']);
        if (!in_array($loc, $distinctLocations)) {
            $distinctLocations[] = $loc;
        }
    }
}
sort($distinctLocations);

// Group locations mapping
$groupLocations = [];
foreach ($items as $item) {
    if (!empty($item['group_id']) && !empty($item['location'])) {
        $loc = trim($item['location']);
        if ($loc !== '' && $loc !== '-') {
            $groupLocations[$item['group_id']][$loc] = true;
        }
    }
}
foreach ($groupLocations as $gid => $locs) {
    $groupLocations[$gid] = array_keys($locs);
}

include 'includes/header.php';
include 'includes/sidebar.php';

$statusLabels = ['pending' => 'รอตรวจ', 'normal' => 'ปกติ', 'damaged' => 'ชำรุด', 'deteriorated' => 'เสื่อมสภาพ', 'not_found' => 'ไม่พบ'];
$statusColors = ['pending' => 'bg-gray-200 text-gray-700', 'normal' => 'bg-green-100 text-green-800', 'damaged' => 'bg-orange-100 text-orange-800', 'deteriorated' => 'bg-red-100 text-red-800', 'not_found' => 'bg-gray-300 text-gray-800'];
?>

<!-- Breadcrumb -->
<div class="mb-4">
    <a href="inspection.php" class="text-blue-900 hover:underline text-sm"><i class="fas fa-arrow-left"></i> กลับ</a>
</div>

<!-- Project Header -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-6">
    <div class="flex flex-col md:flex-row justify-between gap-4">
        <div>
            <h1 class="text-xl font-extrabold text-gray-800">
                <?= htmlspecialchars($project['name']) ?>
            </h1>
            <p class="text-sm text-gray-500 mt-1">ปีงบประมาณ
                <?= $project['fiscal_year'] ?> |
                <?= formatThaiDate($project['start_date']) ?> —
                <?= formatThaiDate($project['end_date']) ?>
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="inspection_check.php?project=<?= $projectId ?>"
                class="btn-press px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700 flex items-center gap-1">
                <i class="fas fa-search"></i> ดำเนินการตรวจสอบ
            </a>
            <a href="inspection_report_print.php?project=<?= $projectId ?>" target="_blank"
                class="btn-press px-4 py-2 bg-purple-600 text-white rounded-lg text-sm font-semibold hover:bg-purple-700 flex items-center gap-1">
                <i class="fas fa-print"></i> พิมพ์รายงานสรุป
            </a>
            <button onclick="autoGroupByLocation()"
                class="btn-press px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-semibold hover:bg-emerald-700 flex items-center gap-1">
                <i class="fas fa-magic"></i> จัดกลุ่มตามที่ตั้งอัตโนมัติ
            </button>
            <button onclick="manageGroups()"
                class="btn-press px-4 py-2 bg-blue-900 text-white rounded-lg text-sm font-semibold hover:bg-blue-800">
                <i class="fas fa-layer-group"></i> จัดการกลุ่ม/พื้นที่
            </button>
            <button onclick="assignItems()"
                class="btn-press px-4 py-2 bg-yellow-600 text-white rounded-lg text-sm font-semibold hover:bg-yellow-700">
                <i class="fas fa-user-check"></i> มอบหมายรายบุคคล
            </button>
        </div>
    </div>
</div>

<?php if (!empty($reports)): ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
    <h3 class="font-bold text-gray-800 text-sm mb-3"><i class="fas fa-file-pdf text-red-500 mr-1"></i> รายงานผลการตรวจสอบที่อัปโหลดแล้ว (<?= count($reports) ?> ฉบับ)</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <?php foreach ($reports as $rep): ?>
            <div class="flex items-center justify-between bg-gray-50 p-3 rounded-lg border border-gray-200 text-xs">
                <div>
                    <p class="font-bold text-gray-800"><?= htmlspecialchars($rep['full_name']) ?></p>
                    <p class="text-gray-500"><?= formatThaiDate($rep['created_at']) ?><?= !empty($rep['comment']) ? ' — ' . htmlspecialchars($rep['comment']) : '' ?></p>
                </div>
                <a href="<?= htmlspecialchars($rep['file_path']) ?>" target="_blank"
                   class="px-3 py-1.5 bg-red-600 text-white rounded-lg font-semibold hover:bg-red-700 transition flex items-center gap-1">
                    <i class="fas fa-file-pdf"></i> เปิดดู PDF
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Groups Overview -->
<?php if (!empty($groups)): ?>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <?php foreach ($groups as $g): ?>
            <div onclick="editGroupFromCard(<?= $g['id'] ?>)"
                class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 card-hover border-l-4 border-yellow-600 cursor-pointer transition-all hover:shadow-md hover:border-yellow-500 flex items-start justify-between">
                <div>
                    <h3 class="font-bold text-gray-800"><i class="fas fa-layer-group text-blue-900 mr-1"></i>
                        <?= htmlspecialchars($g['name']) ?>
                    </h3>
                    <p class="text-xs text-gray-500 mt-1">กรรมการ
                        <?= $g['member_count'] ?> คน | ครุภัณฑ์
                        <?= $g['item_count'] ?> รายการ
                    </p>
                </div>
                <button type="button" onclick="deleteGroup(<?= $g['id'] ?>, '<?= addslashes($g['name']) ?>', event)"
                    class="text-gray-400 hover:text-red-600 p-1.5 rounded-lg hover:bg-red-50 transition" title="ลบกลุ่ม">
                    <i class="fas fa-trash-alt text-xs"></i>
                </button>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Items Table -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="bg-blue-900 text-white px-5 py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
        <h3 class="font-bold text-sm"><i class="fas fa-list text-yellow-400 mr-1"></i> รายการตรวจสอบ (
            <?= count($items) ?> รายการ)
        </h3>
        <div class="flex flex-wrap gap-2 text-gray-800">
            <input type="text" id="detailSearch" oninput="filterDetailTable()" placeholder="ค้นหารหัส, ชื่อ, ที่ตั้ง..."
                   class="px-3 py-1 rounded-lg text-xs border-0 focus:ring-2 focus:ring-yellow-400">
            <select id="detailGroupFilter" onchange="filterDetailTable()" class="px-2 py-1 rounded-lg text-xs border-0">
                <option value="">ทุกกลุ่ม</option>
                <option value="__none__">ยังไม่จัดกลุ่ม</option>
                <?php foreach ($groups as $g): ?>
                    <option value="<?= htmlspecialchars($g['name']) ?>"><?= htmlspecialchars($g['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="detailStatusFilter" onchange="filterDetailTable()" class="px-2 py-1 rounded-lg text-xs border-0">
                <option value="">ทุกสถานะ</option>
                <?php foreach ($statusLabels as $sk => $sl): ?>
                    <option value="<?= $sk ?>"><?= $sl ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="data-table" id="detailItemsTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>รหัส</th>
                    <th>ชื่อครุภัณฑ์</th>
                    <th>ที่ตั้ง</th>
                    <th>กลุ่ม</th>
                    <th>ผู้รับผิดชอบ</th>
                    <th>สถานะ</th>
                    <th>หลักฐาน/หมายเหตุ</th>
                    <th>วันที่ตรวจ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $i => $item): ?>
                    <tr class="detail-row"
                        data-search="<?= htmlspecialchars(strtolower($item['asset_code'] . ' ' . $item['asset_name'] . ' ' . ($item['location'] ?? ''))) ?>"
                        data-group="<?= htmlspecialchars($item['group_name'] ?? '__none__') ?>"
                        data-status="<?= htmlspecialchars($item['status']) ?>">
                        <td class="text-gray-400">
                            <?= $i + 1 ?>
                        </td>
                        <td><span class="font-mono text-xs bg-blue-50 text-blue-900 px-2 py-0.5 rounded">
                                <?= htmlspecialchars($item['asset_code']) ?>
                            </span></td>
                        <td class="font-semibold text-gray-800 text-sm">
                            <?= htmlspecialchars($item['asset_name']) ?>
                        </td>
                        <td class="text-xs text-gray-500">
                            <?= htmlspecialchars($item['location'] ?? '-') ?>
                        </td>
                        <td class="text-xs">
                            <?= htmlspecialchars($item['group_name'] ?? '-') ?>
                        </td>
                        <td class="text-sm">
                            <?php if (!empty($item['assigned_name'])): ?>
                                <?= htmlspecialchars($item['assigned_name']) ?>
                            <?php elseif (!empty($item['group_id']) && !empty($groupMemberNames[$item['group_id']])): ?>
                                <span class="text-xs text-blue-800" title="<?= htmlspecialchars(implode(', ', $groupMemberNames[$item['group_id']])) ?>">
                                    <i class="fas fa-users text-blue-600 mr-1"></i>กรรมการกลุ่ม (<?= count($groupMemberNames[$item['group_id']]) ?> คน)
                                </span>
                            <?php else: ?>
                                <span class="text-gray-400">ยังไม่มอบหมาย</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= $statusColors[$item['status']] ?? '' ?>">
                                <?= $statusLabels[$item['status']] ?? $item['status'] ?>
                            </span>
                        </td>
                        <td class="text-xs">
                            <?php if (!empty($item['photo'])): ?>
                                <a href="<?= htmlspecialchars($item['photo']) ?>" target="_blank" class="text-blue-600 hover:underline mr-2">
                                    <i class="fas fa-image"></i> รูปภาพ
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($item['note'])): ?>
                                <span class="text-gray-600 italic"><?= htmlspecialchars($item['note']) ?></span>
                            <?php endif; ?>
                            <?php if (empty($item['photo']) && empty($item['note'])): ?>
                                <span class="text-gray-400">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-xs text-gray-500">
                            <?= $item['inspected_at'] ? formatThaiDate($item['inspected_at']) : '-' ?>
                            <?php if (!empty($item['inspected_by_name'])): ?>
                                <div class="text-[10px] text-gray-400">โดย <?= htmlspecialchars($item['inspected_by_name']) ?></div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    const committeeMembers = <?= json_encode($committeeMembers) ?>;
    const existingGroups = <?= json_encode($groups) ?>;
    const groupMembers = <?= json_encode($groupMembers) ?>;
    const groupLocations = <?= json_encode($groupLocations) ?>;
    const distinctLocations = <?= json_encode($distinctLocations) ?>;
    const allItems = <?= json_encode($items) ?>;
    const projectId = <?= $projectId ?>;

    function manageGroups() {
        // Step 1: Select Group
        let groupOptions = `<option value="new">+ สร้างกลุ่มใหม่</option>`;
        existingGroups.forEach(g => {
            groupOptions += `<option value="${g.id}">${g.name}</option>`;
        });

        SwalThemed.fire({
            title: '<i class="fas fa-layer-group text-blue-900"></i> เลือกกลุ่มตรวจสอบ',
            html: `
                <div class="text-left">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">เลือกกลุ่มที่ต้องการจัดการ</label>
                    <select id="selected_group" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none">
                        ${groupOptions}
                    </select>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'ดำเนินการต่อ',
            cancelButtonText: 'ยกเลิก'
        }).then(r => {
            if (r.isConfirmed) {
                const groupId = document.getElementById('selected_group').value;
                const group = groupId === 'new' ? null : existingGroups.find(g => g.id == groupId);
                showEditGroupModal(group);
            }
        });
    }

    // Direct edit from card click
    function editGroupFromCard(groupId) {
        const group = existingGroups.find(g => g.id == groupId);
        if (group) {
            showEditGroupModal(group);
        }
    }

    function showEditGroupModal(group = null) {
        const isNew = !group;
        let memberChecks = '';

        // Get existing members for this group
        const existingMemberIds = group && groupMembers[group.id] ? groupMembers[group.id] : [];

        // Render Member Checkboxes with pre-selection for existing members
        memberChecks = committeeMembers.map(m => {
            const isChecked = existingMemberIds.includes(m.id.toString()) || existingMemberIds.includes(parseInt(m.id));
            return `
            <label class="flex items-center gap-2 p-2 hover:bg-gray-50 rounded border border-gray-100 mb-1 cursor-pointer">
                <input type="checkbox" class="grp-member rounded text-blue-900 focus:ring-blue-900" value="${m.id}" ${isChecked ? 'checked' : ''}> 
                <span class="text-sm text-gray-700">${m.full_name}</span>
            </label>
        `;
        }).join('');

        // Location Checkboxes
        const existingLocations = group && groupLocations[group.id] ? groupLocations[group.id] : [];
        const locationChecks = distinctLocations.map(loc => {
            const isChecked = existingLocations.includes(loc);
            return `
            <label class="flex items-center gap-2 p-2 hover:bg-gray-50 rounded border border-gray-100 mb-1 cursor-pointer">
                <input type="checkbox" class="grp-location rounded text-blue-900 focus:ring-blue-900" value="${loc}" ${isChecked ? 'checked' : ''}> 
                <span class="text-xs text-gray-700">${loc}</span>
            </label>
        `;
        }).join('');

        SwalThemed.fire({
            title: isNew ? 'สร้างกลุ่มใหม่' : `จัดการ: ${group.name}`,
            width: Math.min(window.innerWidth * 0.8, 1280),
            html: `
            <div class="text-left space-y-4">
                <div class="bg-gray-50 p-3 rounded-lg border border-gray-200">
                    <h4 class="font-bold text-blue-900 text-sm mb-2"><i class="fas fa-info-circle"></i> ข้อมูลกลุ่ม</h4>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">ชื่อกลุ่ม *</label>
                        <input id="grp_name" value="${group ? group.name : ''}" class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:border-blue-900 focus:outline-none" placeholder="เช่น กลุ่มตึก A">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Members Column -->
                    <div>
                        <h4 class="font-bold text-gray-700 text-sm mb-2"><i class="fas fa-users text-yellow-600"></i> สมาชิกกลุ่ม</h4>
                        <input type="text" id="search_members" placeholder="🔍 ค้นหารายชื่อ..." class="w-full border border-gray-300 rounded px-3 py-2 text-xs mb-2 focus:border-blue-900 focus:outline-none">
                        <div id="members_list" class="h-96 overflow-y-auto border rounded-lg p-2 bg-white text-sm">
                            ${memberChecks}
                        </div>
                    </div>

                    <!-- Scope Column -->
                    <div>
                        <h4 class="font-bold text-gray-700 text-sm mb-2"><i class="fas fa-map-marked-alt text-green-600"></i> ขอบเขตรับผิดชอบ</h4>
                        <div class="bg-white border rounded-lg p-3 space-y-3 h-auto overflow-y-auto">
                            <p class="text-xs text-gray-500">เลือกที่ตั้งเพื่อมอบหมายครุภัณฑ์เข้ากลุ่มนี้โดยอัตโนมัติ</p>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600">ค้นหาที่ตั้ง</label>
                                <input type="text" id="search_location" placeholder="🔍 พิมพ์เพื่อค้นหา..." class="w-full border rounded text-xs px-2 py-1.5 mt-1 focus:border-blue-900 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600">ที่ตั้ง</label>
                                <div id="location_list" class="h-64 overflow-y-auto border rounded text-xs px-2 py-1.5 mt-1 bg-white">
                                    ${locationChecks}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <input type="hidden" id="scope_trigger" value="0">
            </div>
            `,
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-save"></i> บันทึกข้อมูล',
            cancelButtonText: 'ปิด',
            didOpen: () => {
                // Search Members functionality
                const searchMembers = document.getElementById('search_members');
                const membersList = document.getElementById('members_list');
                const allMemberLabels = membersList.querySelectorAll('label');

                searchMembers.addEventListener('input', (e) => {
                    const searchTerm = e.target.value.toLowerCase().trim();
                    allMemberLabels.forEach(label => {
                        const text = label.textContent.toLowerCase();
                        if (text.includes(searchTerm)) {
                            label.style.display = 'flex';
                        } else {
                            label.style.display = 'none';
                        }
                    });
                });

                // Search Location functionality
                const searchLocation = document.getElementById('search_location');
                const locationList = document.getElementById('location_list');
                const allLocationLabels = locationList.querySelectorAll('label');

                searchLocation.addEventListener('input', (e) => {
                    const searchTerm = e.target.value.toLowerCase().trim();
                    allLocationLabels.forEach(label => {
                        const text = label.textContent.toLowerCase();
                        if (text.includes(searchTerm)) {
                            label.style.display = 'flex';
                        } else {
                            label.style.display = 'none';
                        }
                    });
                });
            },
            preConfirm: () => {
                const name = document.getElementById('grp_name').value.trim();
                const members = [...document.querySelectorAll('.grp-member:checked')].map(c => c.value);
                let scopeTriggered = document.getElementById('scope_trigger').value === '1';

                if (!name) { Swal.showValidationMessage('กรุณากรอกชื่อกลุ่ม'); return false; }

                // Collect scope data
                const locations = [...document.querySelectorAll('.grp-location:checked')].map(c => c.value);

                const scope = {
                    locations: locations
                };

                // Auto-trigger scope if any location is selected
                if (locations.length > 0) scopeTriggered = true;

                return {
                    id: group ? group.id : null,
                    name,
                    members,
                    project_id: projectId,
                    scope,
                    scopeTriggered
                };
            }
        }).then(r => {
            if (r.isConfirmed) {
                saveGroupData(r.value);
            }
        });
    }

    // Helper to handle the "Apply Scope" button click inside SweetAlert is tricky
    // because SweetAlert buttons close the modal.
    // We use a workaround: The button sets a hidden input flag. 
    // BUT actually, we can't easily intercept the button click inside html string without closing unless we attach event listener in didOpen properly.
    // Simpler approach: Just save everything on "Save". 
    // "Use this scope" button is just visual cue? Or we make it real?
    // User requirement: "Can select group... edit members... select assets... then update".
    // I will execute "Assign Scope" AFTER "Create/Update Group".

    // Defining global function for the button inside SwAL
    window.triggerAssignScope = function () {
        document.getElementById('scope_trigger').value = '1';
        SwalThemed.clickConfirm(); // Trigger confirm to proceed
    };

    function saveGroupData(data) {
        showLoading();

        // 1. Save Group First
        fetch('api/inspection.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'manage_group', ...data })
        })
            .then(r => r.json())
            .then(gd => {
                if (gd.success) {
                    const groupId = gd.id;

                    // 2. Always sync scope locations for this group
                    if (data.scope && Array.isArray(data.scope.locations)) {
                        return fetch('api/inspection.php', {
                            method: 'POST', headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                action: 'assign_scope',
                                type: 'location',
                                group_id: groupId,
                                project_id: projectId,
                                locations: data.scope.locations
                            })
                        }).then(r => r.json()).then(sd => {
                            return { success: true, group: gd, scope: sd };
                        });
                    }
                    return { success: true, group: gd };
                } else {
                    throw new Error(gd.message);
                }
            })
            .then(result => {
                SwalThemed.fire({
                    icon: 'success',
                    title: 'บันทึกสำเร็จ!',
                    text: result.scope ? `ปรับปรุงกลุ่มและมอบหมาย ${result.scope.count} รายการ` : 'ปรับปรุงข้อมูลกลุ่มเรียบร้อย',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => location.reload());
            })
            .catch(err => {
                SwalThemed.fire({ icon: 'error', title: 'ผิดพลาด', text: err.message });
            });
    }

    function filterDetailTable() {
        const q = (document.getElementById('detailSearch').value || '').toLowerCase().trim();
        const grp = document.getElementById('detailGroupFilter').value;
        const st = document.getElementById('detailStatusFilter').value;

        document.querySelectorAll('.detail-row').forEach(row => {
            const matchQ = !q || row.dataset.search.includes(q);
            const matchG = !grp || row.dataset.group === grp;
            const matchS = !st || row.dataset.status === st;
            row.style.display = (matchQ && matchG && matchS) ? '' : 'none';
        });
    }

    function assignItems() {
        let memberOpts = committeeMembers.map(m => `<option value="${m.id}">${m.full_name}</option>`).join('');
        let groupOpts = existingGroups.map(g => `<option value="${g.id}">${g.name}</option>`).join('');
        SwalThemed.fire({
            title: '<i class="fas fa-user-check text-yellow-600"></i> มอบหมายกรรมการรายบุคคล',
            html: `
            <div class="text-left space-y-3">
                <p class="text-xs text-gray-500">หมายเหตุ: หากจัดกลุ่มพื้นที่และเพิ่มสมาชิกในกลุ่มแล้ว สมาชิกในกลุ่มสามารถตรวจครุภัณฑ์ในกลุ่มได้ทันทีโดยไม่ต้องมอบหมายรายบุคคลซ้ำ</p>
                <div>
                    <label class="text-xs font-semibold text-gray-600">เลือกกรรมการ *</label>
                    <select id="assign_user" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2.5 text-sm mt-1 focus:border-blue-900 focus:outline-none">
                        <option value="">-- เลือกกรรมการ --</option>${memberOpts}
                    </select>
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600">ขอบเขตกลุ่ม</label>
                    <select id="assign_group" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm mt-1">
                        <option value="">ทุกกลุ่มในโครงการ</option>${groupOpts}
                    </select>
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600">วิธีมอบหมาย</label>
                    <select id="assign_mode" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm mt-1">
                        <option value="unassigned">เฉพาะรายการที่ยังไม่ระบุบุคคล</option>
                        <option value="all">ทุกรายการ (เขียนทับ)</option>
                    </select>
                </div>
            </div>
        `,
            showCancelButton: true, confirmButtonText: 'มอบหมาย', cancelButtonText: 'ยกเลิก',
            preConfirm: () => {
                const userId = document.getElementById('assign_user').value;
                if (!userId) { Swal.showValidationMessage('เลือกกรรมการ'); return false; }
                return {
                    user_id: userId,
                    group_id: document.getElementById('assign_group').value || null,
                    mode: document.getElementById('assign_mode').value,
                    project_id: projectId
                };
            }
        }).then(r => {
            if (r.isConfirmed) {
                showLoading();
                fetch('api/inspection.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'assign_items', ...r.value })
                }).then(r => r.json()).then(d => {
                    if (d.success) SwalThemed.fire({ icon: 'success', title: 'มอบหมายสำเร็จ!', text: `${d.count} รายการ`, timer: 1500, showConfirmButton: false }).then(() => location.reload());
                    else SwalThemed.fire({ icon: 'error', title: 'ผิดพลาด', text: d.message });
                });
            }
        });
    }

    function autoGroupByLocation() {
        SwalThemed.fire({
            title: '<i class="fas fa-magic text-emerald-600"></i> จัดกลุ่มตามที่ตั้งอัตโนมัติ',
            html: `
                <div class="text-left space-y-3 text-sm">
                    <p class="text-gray-600">ระบบจะสร้างกลุ่มตรวจสอบตามชื่อ <strong>"ที่ตั้ง"</strong> ของครุภัณฑ์ที่ยังไม่ได้จัดกลุ่ม และผูกกรรมการเข้ากลุ่มให้อัตโนมัติ</p>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">รูปแบบการกำหนดกรรมการเข้ากลุ่ม</label>
                        <select id="ag_mode" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm">
                            <option value="all">ให้กรรมการทุกคนในคำสั่งอยู่ในทุกกลุ่ม (ช่วยกันตรวจทุกพื้นที่ได้)</option>
                            <option value="round_robin">กระจายกรรมการสลับกันไปตามกลุ่ม (Round-Robin)</option>
                        </select>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-bolt"></i> เริ่มจัดกลุ่มอัตโนมัติ',
            cancelButtonText: 'ยกเลิก',
            preConfirm: () => ({
                project_id: projectId,
                member_mode: document.getElementById('ag_mode').value
            })
        }).then(r => {
            if (r.isConfirmed) {
                showLoading('กำลังจัดกลุ่มอัตโนมัติ...');
                fetch('api/inspection.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'auto_group_by_location', ...r.value })
                }).then(res => res.json()).then(d => {
                    if (d.success) {
                        SwalThemed.fire({
                            icon: 'success',
                            title: 'จัดกลุ่มสำเร็จ!',
                            text: `สร้างกลุ่มใหม่ ${d.groups_created} กลุ่ม (จัดสรรครุภัณฑ์ ${d.items_assigned} รายการ)`,
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => location.reload());
                    } else {
                        SwalThemed.fire('แจ้งเตือน', d.message, 'warning');
                    }
                });
            }
        });
    }

    function deleteGroup(groupId, name, e) {
        if (e) e.stopPropagation();
        SwalThemed.fire({
            icon: 'warning',
            title: 'ลบกลุ่มตรวจสอบ?',
            html: `ต้องการลบกลุ่ม <strong>"${name}"</strong> หรือไม่?<br><small class="text-gray-500">ครุภัณฑ์ในกลุ่มนี้จะถูกปลดสถานะกลุ่ม (ไม่ถูกลบออกจากโครงการ)</small>`,
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            confirmButtonText: 'ลบกลุ่ม',
            cancelButtonText: 'ยกเลิก'
        }).then(r => {
            if (r.isConfirmed) {
                showLoading('กำลังลบกลุ่ม...');
                fetch('api/inspection.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete_group', group_id: groupId })
                }).then(res => res.json()).then(d => {
                    if (d.success) location.reload();
                    else SwalThemed.fire('ผิดพลาด', d.message, 'error');
                });
            }
        });
    }
</script>

<?php include 'includes/footer.php'; ?>
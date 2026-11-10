<?php
/**
 * My Assets - ครุภัณฑ์ที่ถือครอง
 */
require_once 'includes/auth.php';
requireLogin();

$pageTitle = 'ครุภัณฑ์ที่ถือครอง';
$currentUser = getCurrentUser();

$assets = [];

// Logic for fetching assets
if ($currentUser['role'] === 'department_head') {
    // Dept Head: Own + Location + Subordinates' Assets
    $stmt = $pdo->prepare("SELECT a.*, c.name as category_name, u.full_name as holder_name 
                            FROM assets a 
                            LEFT JOIN asset_categories c ON a.category_id = c.id 
                            LEFT JOIN users u ON a.holder_id = u.id 
                            WHERE a.holder_id = ? 
                               OR a.location = ?
                               OR (u.department = ? AND u.department != '')
                            ORDER BY a.asset_code");
    $stmt->execute([$currentUser['id'], $currentUser['department'], $currentUser['department']]);
} else {
    // Regular User: Own + Location
    $stmt = $pdo->prepare("SELECT a.*, c.name as category_name, u.full_name as holder_name 
                            FROM assets a 
                            LEFT JOIN asset_categories c ON a.category_id = c.id 
                            LEFT JOIN users u ON a.holder_id = u.id 
                            WHERE a.holder_id = ? 
                               OR a.location = ?
                            ORDER BY a.asset_code");
    $stmt->execute([$currentUser['id'], $currentUser['department']]);
}
$assets = $stmt->fetchAll();

// Fetch Unit Types for Purchase Form
$stmt = $pdo->query("SELECT * FROM type_volume ORDER BY type_volume");
$units = $stmt->fetchAll();

// Fetch My Service Requests
$stmt = $pdo->prepare("SELECT * FROM service_requests WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$currentUser['id']]);
$myRequests = $stmt->fetchAll();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
<style>
    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter,
    .dataTables_wrapper .dataTables_info,
    .dataTables_wrapper .dataTables_processing,
    .dataTables_wrapper .dataTables_paginate {
        margin-bottom: 1rem;
        margin-top: 1rem;
        color: #4b5563;
        font-size: 0.875rem;
    }

    .dataTables_wrapper .dataTables_filter input {
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
        padding: 0.5rem;
        margin-left: 0.5rem;
    }

    .dataTables_wrapper .dataTables_length select {
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
        padding: 0.25rem 2rem 0.25rem 0.5rem;
    }

    table.dataTable.no-footer {
        border-bottom: 1px solid #e5e7eb;
    }

    table.dataTable thead th,
    table.dataTable thead td {
        border-bottom: 1px solid #e5e7eb;
    }
</style>

<div class="flex justify-between items-end mb-6">
    <div>
        <h1 class="text-2xl font-extrabold text-gray-800 flex items-center gap-2">
            <i class="fas fa-boxes-stacked text-blue-900"></i> ครุภัณฑ์ที่ถือครอง
        </h1>
        <p class="text-gray-500 text-sm mt-1">
            รายการครุภัณฑ์ที่คุณรับผิดชอบ
            <?php if ($currentUser['role'] === 'department_head'): ?>
                และครุภัณฑ์ภายใน <strong class="text-blue-900"><?= htmlspecialchars($currentUser['department']) ?></strong>
            <?php endif; ?>
        </p>
    </div>
    <div class="flex gap-3">
        <button onclick="openServiceModal('repair')"
            class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-lg shadow transition-colors flex items-center gap-2">
            <i class="fas fa-tools"></i> แจ้งขอซ่อม
        </button>
        <button onclick="openServiceModal('purchase')"
            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg shadow transition-colors flex items-center gap-2">
            <i class="fas fa-cart-plus"></i> แจ้งขอซื้อ
        </button>
    </div>
</div>

<!-- Request History Table -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-8 p-4">
    <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
        <i class="fas fa-history text-blue-600"></i> ประวัติการแจ้งซ่อม/ซื้อ
    </h3>
    <div class="overflow-x-auto">
        <table id="requestsTable" class="data-table display w-full">
            <thead>
                <tr>
                    <th class="w-12 text-center">#</th>
                    <th>ประเภท</th>
                    <th>รายการ</th>
                    <th>วันที่บันทึก</th>
                    <th>สถานะ</th>
                    <th class="text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($myRequests as $i => $req): ?>
                    <tr class="hover:bg-gray-50 transition border-b border-gray-100">
                        <td class="text-center text-gray-500"><?= $i + 1 ?></td>
                        <td>
                            <?php if ($req['request_type'] == 'repair'): ?>
                                <span
                                    class="px-2 py-1 bg-orange-100 text-orange-700 rounded text-xs font-bold border border-orange-200">แจ้งซ่อม</span>
                            <?php else: ?>
                                <span
                                    class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs font-bold border border-green-200">แจ้งขอซื้อ</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="font-semibold text-gray-800 text-sm"><?= htmlspecialchars($req['asset_name']) ?>
                            </div>
                            <div class="text-xs text-gray-500"><?= htmlspecialchars($req['asset_description']) ?></div>
                        </td>
                        <td class="text-sm text-gray-600">
                            <?= formatThaiDate($req['created_at']) ?>
                        </td>
                        <td>
                            <?php
                            $reqStatusBadges = [
                                'pending' => ['class' => 'badge-pending', 'label' => 'รอดำเนินการ'],
                                'in_progress' => ['class' => 'bg-blue-100 text-blue-700', 'label' => 'กำลังดำเนินการ'],
                                'completed' => ['class' => 'badge-active', 'label' => 'เสร็จสิ้น'],
                                'rejected' => ['class' => 'badge-damaged', 'label' => 'ยกเลิก/ปฏิเสธ']
                            ];
                            $rs = $reqStatusBadges[$req['status'] ?? 'pending'] ?? $reqStatusBadges['pending'];
                            ?>
                            <span class="badge <?= $rs['class'] ?>"><?= $rs['label'] ?></span>
                        </td>
                        <td class="text-center">
                            <div class="flex items-center justify-center gap-2">
                                <?php if ($req['file_path']): ?>
                                    <a href="<?= $req['file_path'] ?>" target="_blank"
                                        class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg transition" title="ดาวน์โหลด">
                                        <i class="fas fa-file-word"></i>
                                    </a>
                                <?php endif; ?>
                                <button onclick="editRequest(<?= $req['id'] ?>)"
                                    class="p-1.5 text-yellow-600 hover:bg-yellow-50 rounded-lg transition" title="แก้ไข">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="deleteRequest(<?= $req['id'] ?>)"
                                    class="p-1.5 text-red-600 hover:bg-red-50 rounded-lg transition" title="ลบ">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Assets Table -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden p-4">
    <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
        <i class="fas fa-list text-blue-600"></i> รายการครุภัณฑ์ที่ถือครอง
    </h3>
    <div class="overflow-x-auto">
        <table id="assetsTable" class="data-table display w-full">
            <thead>
                <tr>
                    <th class="w-12 text-center">#</th>
                    <th>รหัสครุภัณฑ์</th>
                    <th>รายการ</th>
                    <th>หมวดหมู่</th>
                    <th>ผู้ถือครอง</th>
                    <th>สถานะ</th>
                    <th class="text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($assets as $i => $asset): ?>
                    <tr class="hover:bg-gray-50 transition border-b border-gray-100 last:border-0">
                        <td class="text-gray-400 text-center font-mono text-sm"><?= $i + 1 ?></td>
                        <td class="font-mono text-sm text-blue-600 font-semibold">
                            <?= htmlspecialchars($asset['asset_code']) ?>
                        </td>
                        <td>
                            <p class="font-semibold text-gray-800 text-sm"><?= htmlspecialchars($asset['name']) ?></p>
                            <p class="text-xs text-gray-500">
                                <?= htmlspecialchars($asset['brand'] . ' ' . $asset['model']) ?>
                            </p>
                        </td>
                        <td>
                            <span class="px-2 py-1 rounded text-xs bg-gray-100 text-gray-600 border border-gray-200">
                                <?= htmlspecialchars($asset['category_name'] ?? '-') ?>
                            </span>
                        </td>
                        <td>
                            <div class="flex items-center gap-2">
                                <div
                                    class="w-6 h-6 rounded-full bg-blue-100 flex items-center justify-center text-xs font-bold text-blue-800 border border-blue-200">
                                    <?= mb_substr($asset['holder_name'] ?? '?', 0, 1) ?>
                                </div>
                                <span
                                    class="text-sm text-gray-700"><?= htmlspecialchars($asset['holder_name'] ?? '-') ?></span>
                            </div>
                        </td>
                        <td>
                            <?php
                            $sBadge = ['active' => 'badge-active', 'damaged' => 'badge-damaged', 'lost' => 'badge-lost', 'retired' => 'badge-retired', 'transferred' => 'badge-pending', 'repair' => 'badge-damaged'];
                            $sLabel = ['active' => 'ปกติ', 'damaged' => 'ชำรุด', 'lost' => 'สูญหาย', 'retired' => 'จำหน่าย', 'transferred' => 'โอนย้าย', 'repair' => 'ส่งซ่อม'];
                            ?>
                            <span class="badge <?= $sBadge[$asset['status']] ?? '' ?>">
                                <?= $sLabel[$asset['status']] ?? $asset['status'] ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="flex items-center justify-center gap-2">
                                <?php if ($currentUser['role'] === 'department_head'): ?>
                                    <a href="assets.php?search=<?= urlencode($asset['asset_code']) ?>"
                                        class="p-1.5 text-indigo-600 hover:bg-indigo-50 rounded-lg transition"
                                        title="จัดการข้อมูล">
                                        <i class="fas fa-cog"></i>
                                    </a>
                                <?php endif; ?>

                                <button
                                    onclick="openServiceModal('repair', <?= $asset['id'] ?>, '<?= addslashes($asset['name']) ?>', '<?= addslashes($asset['brand'] . ' ' . $asset['model']) ?>')"
                                    class="p-1.5 text-orange-600 hover:bg-orange-50 rounded-lg transition" title="แจ้งซ่อม">
                                    <i class="fas fa-tools"></i>
                                </button>

                                <button
                                    onclick="confirmTransfer(<?= $asset['id'] ?>, '<?= addslashes($asset['name']) ?>', '<?= addslashes($asset['asset_code']) ?>')"
                                    class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg transition" title="ขอโอนย้าย">
                                    <i class="fas fa-exchange-alt"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>

<script>
    // Inject Units from PHP
    const unitOptions = <?= json_encode($units) ?>;

    $(document).ready(function () {
        $('.data-table').DataTable({
            "pageLength": 5,
            "lengthMenu": [5, 10, 25, 50],
            "language": {
                "sProcessing": "กำลังดำเนินการ...",
                "sLengthMenu": "แสดง _MENU_ รายการ",
                "sZeroRecords": "ไม่พบข้อมูล",
                "sInfo": "แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ",
                "sInfoEmpty": "แสดง 0 ถึง 0 จาก 0 รายการ",
                "sInfoFiltered": "(กรองข้อมูล _MAX_ ทุกรายการ)",
                "sInfoPostFix": "",
                "sSearch": "ค้นหา:",
                "sUrl": "",
                "oPaginate": {
                    "sFirst": "หน้าแรก",
                    "sPrevious": "ก่อนหน้า",
                    "sNext": "ถัดไป",
                    "sLast": "หน้าสุดท้าย"
                }
            },
            "order": [] // Disable initial sort to keep PHP sort or custom
        });
    });

    // ... (Existing Functions) ...
    // Update openServiceModal to handle Edit Mode

    function deleteRequest(id) {
        SwalThemed.fire({
            title: 'ยืนยันการลบ?',
            text: "ข้อมูลและไฟล์เอกสารจะถูกลบถาวร",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'ลบข้อมูล',
            confirmButtonColor: '#dc2626',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                // Initial POST with action=delete
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);

                fetch('api/service_request.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            SwalThemed.fire('ลบสำเร็จ!', '', 'success').then(() => location.reload());
                        } else {
                            SwalThemed.fire('เกิดข้อผิดพลาด', data.message, 'error');
                        }
                    });
            }
        });
    }

    function editRequest(id) {
        // Fetch details
        fetch(`api/service_request.php?action=get&id=${id}`)
            .then(r => r.json())
            .then(res => {
                if (!res.success) {
                    SwalThemed.fire('Error', res.message, 'error');
                    return;
                }
                const data = res.data;
                // Open Modal with Data
                openServiceModal(data.request_type, data.asset_id, data.asset_name, data.asset_description, data);
            });
    }

    function confirmTransfer(id, name, code) {
        SwalThemed.fire({
            title: 'ยืนยันการขอโอนย้าย?',
            html: `ระบบจะนำคุณไปยังหน้าโอนย้ายครุภัณฑ์เพื่อดำเนินการโอนย้าย<br><b class="text-blue-600">${name}</b> (${code})`,
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-arrow-right"></i> ดำเนินการต่อ',
            cancelButtonText: 'ยกเลิก',
            confirmButtonColor: '#1d4ed8'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `transfer.php?action=create&asset_id=${id}`;
            }
        });
    }

    // Service Request Modal
    function openServiceModal(type, assetId = null, assetName = '', assetDesc = '', editData = null) {
        const isRepair = type === 'repair';

        // Default values from arguments or editData
        const initialAssetName = editData ? editData.asset_name : assetName;
        const initialAssetDesc = editData ? editData.asset_description : assetDesc;
        const initialAssetId = editData ? editData.asset_id : assetId;

        // Initialize State for this modal instance
        window.serviceRequestState = {
            repair: {
                asset_name: initialAssetName,
                asset_description: initialAssetDesc,
                details_maintain: editData && type === 'repair' ? editData.details : '', // DB maps details_maintain -> details
                asset_id: initialAssetId
            },
            purchase: {
                asset_name: initialAssetName,
                asset_description: initialAssetDesc,
                details: editData && type === 'purchase' ? editData.details : '', // Reason
                details_buy: editData && type === 'purchase' ? editData.details_buy : '', // Extra Details
                asset_volume: editData && type === 'purchase' ? editData.asset_volume : '',
                type_asset: editData && type === 'purchase' ? editData.type_asset : '',
                asset_id: ''
            },
            currentType: type,
            requestId: editData ? editData.id : null,
            isEdit: !!editData
        };

        const initialColor = isRepair ? 'orange' : 'green';
        const initialTitle = isRepair ? (editData ? 'แก้ไขแจ้งซ่อม' : 'แจ้งซ่อมครุภัณฑ์') : (editData ? 'แก้ไขแจ้งขอซื้อ' : 'แจ้งขอซื้อครุภัณฑ์/วัสดุ');
        const initialIcon = isRepair ? 'fa-tools' : 'fa-cart-plus';

        // Generate Unit Options HTML
        let unitOptionsHtml = '<option value="">-- เลือกหน่วยนับ --</option>';
        unitOptions.forEach(u => {
            unitOptionsHtml += `<option value="${u.id}">${u.type_volume}</option>`;
        });

        Swal.fire({
            width: '50%',
            customClass: {
                popup: 'rounded-2xl overflow-hidden shadow-2xl',
                confirmButton: 'rounded-xl px-6 py-3 text-sm font-bold shadow-lg transition-transform hover:-translate-y-0.5',
                cancelButton: 'rounded-xl px-6 py-3 text-sm font-bold bg-gray-100 text-gray-600 hover:bg-gray-200 border-0'
            },
            didOpen: () => {
                const popup = Swal.getPopup();
                popup.style.maxWidth = '1280px';

                // Restore initial state (which is now populated with editData if exists)
                const initialState = window.serviceRequestState[type];
                document.getElementById('reqAssetName').value = initialState.asset_name;
                document.getElementById('reqAssetDesc').value = initialState.asset_description;
                document.getElementById('hiddenAssetId').value = initialState.asset_id;

                // Repair Fields
                if (document.getElementById('reqDetailsMaintain'))
                    document.getElementById('reqDetailsMaintain').value = initialState.details_maintain;

                // Purchase Fields
                if (document.getElementById('reqDetails'))
                    document.getElementById('reqDetails').value = initialState.details;
                if (document.getElementById('reqDetailsBuy'))
                    document.getElementById('reqDetailsBuy').value = initialState.details_buy;
                if (document.getElementById('reqAssetVolume'))
                    document.getElementById('reqAssetVolume').value = initialState.asset_volume;
                if (document.getElementById('reqTypeAsset'))
                    document.getElementById('reqTypeAsset').value = initialState.type_asset;

                toggleAssetInputs(type);
            },
            html: `
                <div class="overflow-hidden rounded-t-lg">
                    <div id="modalHeader" class="bg-${initialColor}-600 px-8 py-6 flex items-center gap-4 transition-colors duration-300">
                        <div class="w-14 h-14 rounded-full bg-white/20 flex items-center justify-center text-white backdrop-blur-sm shadow-inner">
                            <i id="modalIcon" class="fas ${initialIcon} text-2xl"></i>
                        </div>
                        <div class="text-left text-white">
                            <h3 id="modalTitle" class="text-2xl font-extrabold tracking-tight">${initialTitle}</h3>
                            <p class="text-sm text-${initialColor}-100 opacity-90 font-medium">แบบฟอร์มบันทึกข้อความขออนุมัติ (e-Service)</p>
                        </div>
                    </div>
                </div>

                <div class="p-8 text-left bg-gray-50/50">
                    <form id="serviceRequestForm">
                        <input type="hidden" name="asset_id" id="hiddenAssetId" value="">
                        
                        <!-- Info Box -->
                        <div class="mb-8 bg-blue-50 border border-blue-100 rounded-xl p-5 flex gap-4 items-start shadow-sm">
                            <div class="w-10 h-10 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-info text-lg"></i>
                            </div>
                            <div>
                                <h4 class="text-base font-bold text-blue-900">คำแนะนำการใช้งาน</h4>
                                <p class="text-sm text-blue-800/80 leading-relaxed mt-1">
                                    ข้อมูลที่ท่านกรอกจะถูกนำไปสร้างเป็น <strong>บันทึกข้อความ (Word)</strong> โดยอัตโนมัติ 
                                    <br>ระบบจะดึงข้อมูลโครงสร้างองค์กร วันที่ และผู้ลงนามให้ทันที 
                                    <span class="block mt-1 text-blue-700 font-semibold text-xs bg-blue-100/50 px-2 py-1 rounded inline-block">
                                        * โปรดใช้ถ้อยคำภาษาราชการที่ถูกต้อง
                                    </span>
                                </p>
                            </div>
                        </div>

                        <!-- Type Selector (Disable if Editing?) -->
                        <div class="mb-8">
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">เลือกประเภทรายการ</label>
                            <div class="grid grid-cols-2 gap-6">
                                <label class="cursor-pointer group relative">
                                    <input type="radio" name="request_type" value="repair" ${isRepair ? 'checked' : ''} onchange="handleChangeType('repair')" class="peer hidden" ${editData ? 'disabled' : ''}>
                                    <div class="absolute inset-0 bg-white rounded-xl shadow-sm border-2 border-gray-200 transition-all peer-checked:border-orange-500 peer-checked:shadow-orange-100 group-hover:border-orange-300 ${editData ? 'bg-gray-50 opacity-50' : ''}"></div>
                                    <div class="relative p-4 flex items-center gap-4 transition-all opacity-60 peer-checked:opacity-100">
                                        <div class="w-12 h-12 rounded-full bg-orange-100 text-orange-600 flex items-center justify-center text-xl">
                                            <i class="fas fa-tools"></i>
                                        </div>
                                        <div>
                                            <span class="block font-bold text-gray-800 text-lg">แจ้งซ่อม</span>
                                            <span class="text-xs text-gray-500">สำหรับครุภัณฑ์ที่ชำรุด</span>
                                        </div>
                                        <div class="ml-auto text-orange-500 opacity-0 peer-checked:opacity-100 transition-opacity">
                                            <i class="fas fa-check-circle text-2xl"></i>
                                        </div>
                                    </div>
                                </label>
                                
                                <label class="cursor-pointer group relative">
                                    <input type="radio" name="request_type" value="purchase" ${!isRepair ? 'checked' : ''} onchange="handleChangeType('purchase')" class="peer hidden" ${editData ? 'disabled' : ''}>
                                    <div class="absolute inset-0 bg-white rounded-xl shadow-sm border-2 border-gray-200 transition-all peer-checked:border-green-500 peer-checked:shadow-green-100 group-hover:border-green-300 ${editData ? 'bg-gray-50 opacity-50' : ''}"></div>
                                    <div class="relative p-4 flex items-center gap-4 transition-all opacity-60 peer-checked:opacity-100">
                                        <div class="w-12 h-12 rounded-full bg-green-100 text-green-600 flex items-center justify-center text-xl">
                                            <i class="fas fa-cart-plus"></i>
                                        </div>
                                        <div>
                                            <span class="block font-bold text-gray-800 text-lg">แจ้งขอซื้อ</span>
                                            <span class="text-xs text-gray-500">สำหรับวัสดุ/ครุภัณฑ์ใหม่</span>
                                        </div>
                                        <div class="ml-auto text-green-500 opacity-0 peer-checked:opacity-100 transition-opacity">
                                            <i class="fas fa-check-circle text-2xl"></i>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="space-y-6">
                            <!-- Shared Fields: Name & Desc -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">
                                        ชื่อรายการ (ครุภัณฑ์/วัสดุ) <span class="text-red-500">*</span>
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-tag text-gray-400"></i>
                                        </div>
                                        <input type="text" name="asset_name" id="reqAssetName" 
                                            class="w-full pl-10 pr-4 py-3 bg-white border border-gray-300 rounded-xl focus:ring-4 focus:ring-blue-50 focus:border-blue-500 transition-all font-medium text-gray-700 shadow-sm" 
                                            value="" placeholder="ระบุชื่อรายการ...">
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">
                                        ยี่ห้อ / รุ่น / รายละเอียด <span class="text-red-500">*</span>
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-barcode text-gray-400"></i>
                                        </div>
                                        <input type="text" name="asset_description" id="reqAssetDesc" 
                                            class="w-full pl-10 pr-4 py-3 bg-white border border-gray-300 rounded-xl focus:ring-4 focus:ring-blue-50 focus:border-blue-500 transition-all font-medium text-gray-700 shadow-sm" 
                                            value="" placeholder="ระบุรายละเอียด, ยี่ห้อ, รุ่น...">
                                    </div>
                                </div>
                            </div>

                            <!-- Repair Specific Fields -->
                            <div id="repairFields">
                                <label class="block text-sm font-bold text-gray-700 mb-2">
                                    เหตุผลความจำเป็น / อาการชำรุด <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <div class="absolute top-3 left-3 pointer-events-none">
                                        <i class="fas fa-align-left text-gray-400"></i>
                                    </div>
                                    <textarea name="details_maintain" id="reqDetailsMaintain"
                                        class="w-full pl-10 pr-4 py-3 bg-white border border-gray-300 rounded-xl focus:ring-4 focus:ring-blue-50 focus:border-blue-500 transition-all font-medium text-gray-700 shadow-sm h-32 resize-none leading-relaxed" 
                                        placeholder="ระบุสาเหตุที่ต้องซ่อม หรือเหตุผลความจำเป็น... (กรุณาใช้ภาษาราชการ)"></textarea>
                                </div>
                                <p class="text-xs text-gray-500 mt-2 flex items-center justify-end">
                                    <i class="fas fa-pen-nib mr-1.5 opacity-70"></i> ใช้สำหรับออกบันทึกข้อความทางการ
                                </p>
                            </div>

                            <!-- Purchase Specific Fields -->
                            <div id="purchaseFields" class="hidden space-y-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-bold text-gray-700 mb-2">
                                            จำนวน (Quantity) <span class="text-red-500">*</span>
                                        </label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-layer-group text-gray-400"></i>
                                            </div>
                                            <input type="number" name="asset_volume" id="reqAssetVolume" min="1"
                                                class="w-full pl-10 pr-4 py-3 bg-white border border-gray-300 rounded-xl focus:ring-4 focus:ring-green-50 focus:border-green-500 transition-all font-medium text-gray-700 shadow-sm" 
                                                placeholder="ระบุจำนวน">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-bold text-gray-700 mb-2">
                                            หน่วยนับ (Unit) <span class="text-red-500">*</span>
                                        </label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-ruler text-gray-400"></i>
                                            </div>
                                            <select name="type_asset" id="reqTypeAsset"
                                                class="w-full pl-10 pr-4 py-3 bg-white border border-gray-300 rounded-xl focus:ring-4 focus:ring-green-50 focus:border-green-500 transition-all font-medium text-gray-700 shadow-sm appearance-none">
                                                ${unitOptionsHtml}
                                            </select>
                                            <div class="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none">
                                                <i class="fas fa-chevron-down text-gray-400"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">
                                        เหตุผลความจำเป็นในการขอซื้อ <span class="text-red-500">*</span>
                                    </label>
                                    <div class="relative">
                                        <div class="absolute top-3 left-3 pointer-events-none">
                                            <i class="fas fa-comment-dollar text-gray-400"></i>
                                        </div>
                                        <textarea name="details" id="reqDetails"
                                            class="w-full pl-10 pr-4 py-3 bg-white border border-gray-300 rounded-xl focus:ring-4 focus:ring-green-50 focus:border-green-500 transition-all font-medium text-gray-700 shadow-sm h-24 resize-none leading-relaxed" 
                                            placeholder="ระบุเหตุผลความจำเป็น... (กรุณาใช้ภาษาราชการ)"></textarea>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">
                                        รายละเอียดเพิ่มเติม
                                    </label>
                                    <div class="relative">
                                        <div class="absolute top-3 left-3 pointer-events-none">
                                            <i class="fas fa-align-left text-gray-400"></i>
                                        </div>
                                        <textarea name="details_buy" id="reqDetailsBuy"
                                            class="w-full pl-10 pr-4 py-3 bg-white border border-gray-300 rounded-xl focus:ring-4 focus:ring-green-50 focus:border-green-500 transition-all font-medium text-gray-700 shadow-sm h-24 resize-none leading-relaxed" 
                                            placeholder="ระบุรายละเอียดเพิ่มเติม... (กรุณาใช้ภาษาราชการ)"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: editData ? '<i class="fas fa-save mr-2"></i>บันทึกการแก้ไข' : '<i class="fas fa-save mr-2"></i>บันทึกและสร้างเอกสาร',
            cancelButtonText: 'ยกเลิก',
            confirmButtonColor: isRepair ? '#ea580c' : '#16a34a',
            preConfirm: () => {
                const form = document.getElementById('serviceRequestForm');
                const formData = new FormData(form);
                const currentType = window.serviceRequestState.currentType;

                // Ensure the correct asset_id is sent based on the current state
                formData.set('asset_id', window.serviceRequestState[currentType].asset_id);
                formData.set('request_type', currentType);

                // Add Action & ID if editing
                if (window.serviceRequestState.requestId) {
                    formData.append('action', 'update');
                    formData.append('id', window.serviceRequestState.requestId);
                } else {
                    formData.append('action', 'create');
                }

                // Validation
                if (!formData.get('asset_name')) {
                    Swal.showValidationMessage('กรุณาระบุชื่อรายการ'); return false;
                }

                if (currentType === 'repair') {
                    if (!formData.get('details_maintain')) {
                        Swal.showValidationMessage('กรุณาระบุอาการชำรุด'); return false;
                    }
                } else {
                    // Purchase Validation
                    if (!formData.get('asset_volume')) {
                        Swal.showValidationMessage('กรุณาระบุจำนวน'); return false;
                    }
                    if (!formData.get('type_asset')) {
                        Swal.showValidationMessage('กรุณาเลือกหน่วยนับ'); return false;
                    }
                    if (!formData.get('details')) {
                        Swal.showValidationMessage('กรุณาระบุเหตุผลความจำเป็น'); return false;
                    }
                }

                return fetch('api/service_request.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) throw new Error(data.message || 'เกิดข้อผิดพลาด');
                        return data;
                    })
                    .catch(error => {
                        Swal.showValidationMessage(`เกิดข้อผิดพลาด: ${error}`);
                    });
            }
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                Swal.fire({
                    icon: 'success',
                    title: editData ? 'แก้ไขสำเร็จ!' : 'สร้างเอกสารสำเร็จ!',
                    html: `
                        <p class="text-gray-600 mb-4">${editData ? 'บันทึกข้อมูลเรียบร้อยแล้ว' : 'ระบบได้บันทึกข้อมูลและสร้างไฟล์บันทึกข้อความเรียบร้อยแล้ว'}</p>
                        <a href="${result.value.file_url}" target="_blank" 
                           class="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 text-white rounded-xl hover:bg-blue-700 shadow-lg shadow-blue-200 transition-all font-bold">
                            <i class="fas fa-file-word"></i> ดาวน์โหลดเอกสาร
                        </a>
                    `,
                    showConfirmButton: false,
                    showCloseButton: true
                }).then(() => location.reload()); // Reload to show updated data
            }
        });
    }

    // Handle change of type wrapper
    function handleChangeType(newType) {
        // 1. Save current state from DOM to the state object
        const currentType = window.serviceRequestState.currentType;

        let stateData = {
            asset_name: document.getElementById('reqAssetName').value,
            asset_description: document.getElementById('reqAssetDesc').value,
            asset_id: document.getElementById('hiddenAssetId').value
        };

        if (currentType === 'repair') {
            stateData.details_maintain = document.getElementById('reqDetailsMaintain').value;
        } else {
            stateData.details = document.getElementById('reqDetails').value;
            stateData.details_buy = document.getElementById('reqDetailsBuy').value;
            stateData.asset_volume = document.getElementById('reqAssetVolume').value;
            stateData.type_asset = document.getElementById('reqTypeAsset').value;
        }

        // Save to previous state storage
        window.serviceRequestState[currentType] = { ...window.serviceRequestState[currentType], ...stateData };

        // 2. Update Current Type in state
        window.serviceRequestState.currentType = newType;

        // 3. Restore/Load state for new type into DOM
        const newState = window.serviceRequestState[newType];
        document.getElementById('reqAssetName').value = newState.asset_name;
        document.getElementById('reqAssetDesc').value = newState.asset_description;
        document.getElementById('hiddenAssetId').value = newState.asset_id;

        if (newType === 'repair') {
            document.getElementById('reqDetailsMaintain').value = newState.details_maintain || '';
        } else {
            document.getElementById('reqDetails').value = newState.details || '';
            document.getElementById('reqDetailsBuy').value = newState.details_buy || '';
            document.getElementById('reqAssetVolume').value = newState.asset_volume || '';
            document.getElementById('reqTypeAsset').value = newState.type_asset || '';
        }

        // 4. Update Visuals
        updateModalStyle(newType);
    }

    // Update Header Style & Inputs Visuals Only
    function updateModalStyle(type) {
        const isRepair = type === 'repair';
        const header = document.getElementById('modalHeader');
        const icon = document.getElementById('modalIcon');
        const title = document.getElementById('modalTitle');
        const confirmBtn = Swal.getConfirmButton();

        // Remove old classes
        header.classList.remove('bg-orange-600', 'bg-green-600');
        icon.classList.remove('fa-tools', 'fa-cart-plus');

        // Add new classes
        if (isRepair) {
            header.classList.add('bg-orange-600');
            icon.classList.add('fa-tools');
            title.textContent = 'แจ้งซ่อมครุภัณฑ์';
            confirmBtn.style.backgroundColor = '#ea580c'; // Orange-600
        } else {
            header.classList.add('bg-green-600');
            icon.classList.add('fa-cart-plus');
            title.textContent = 'แจ้งขอซื้อครุภัณฑ์/วัสดุ';
            confirmBtn.style.backgroundColor = '#16a34a'; // Green-600
        }

        toggleAssetInputs(type);
    }

    function toggleAssetInputs(type) {
        const isRepair = type === 'repair';
        const nameInput = document.getElementById('reqAssetName');
        const descInput = document.getElementById('reqAssetDesc');
        const assetIdInput = document.getElementById('hiddenAssetId');

        const repairFields = document.getElementById('repairFields');
        const purchaseFields = document.getElementById('purchaseFields');

        if (isRepair) {
            // Repair Mode
            repairFields.classList.remove('hidden');
            purchaseFields.classList.add('hidden');

            // If there is an asset ID, it means it's a specific repair, so lock inputs
            if (assetIdInput.value) {
                nameInput.readOnly = true;
                descInput.readOnly = true;
                nameInput.classList.add('bg-gray-100');
                descInput.classList.add('bg-gray-100');
            } else {
                // If no asset ID (generic repair), inputs are editable
                nameInput.readOnly = false;
                descInput.readOnly = false;
                nameInput.classList.remove('bg-gray-100');
                descInput.classList.remove('bg-gray-100');
            }
        } else {
            // Purchase Mode
            repairFields.classList.add('hidden');
            purchaseFields.classList.remove('hidden');

            // Purchase is always editable
            nameInput.readOnly = false;
            descInput.readOnly = false;
            nameInput.classList.remove('bg-gray-100');
            descInput.classList.remove('bg-gray-100');
        }
    }
</script>

<?php include 'includes/footer.php'; ?>
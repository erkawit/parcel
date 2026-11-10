<?php
/**
 * Transfer Management - โอนย้ายครุภัณฑ์
 */
require_once 'includes/auth.php';
requireLogin();

$pageTitle = 'โอนย้ายครุภัณฑ์';
$currentUser = getCurrentUser();
$isManager = hasRole(['admin', 'supply_officer']);

// Get transfers
if ($isManager) {
    $stmt = $pdo->query("SELECT tr.*, a.name as asset_name, a.asset_code, 
                         uf.full_name as from_name, ut.full_name as to_name,
                         ua.full_name as approved_name
                         FROM transfer_requests tr
                         JOIN assets a ON tr.asset_id = a.id
                         JOIN users uf ON tr.from_user_id = uf.id
                         LEFT JOIN users ut ON tr.to_user_id = ut.id
                         LEFT JOIN users ua ON tr.approved_by = ua.id
                         ORDER BY tr.created_at DESC");
    $transfers = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT tr.*, a.name as asset_name, a.asset_code, 
                          uf.full_name as from_name, ut.full_name as to_name,
                          ua.full_name as approved_name
                          FROM transfer_requests tr
                          JOIN assets a ON tr.asset_id = a.id
                          JOIN users uf ON tr.from_user_id = uf.id
                          LEFT JOIN users ut ON tr.to_user_id = ut.id
                          LEFT JOIN users ua ON tr.approved_by = ua.id
                          WHERE tr.from_user_id = ? OR tr.to_user_id = ?
                          ORDER BY tr.created_at DESC");
    $stmt->execute([$currentUser['id'], $currentUser['id']]);
    $transfers = $stmt->fetchAll();
}

include 'includes/header.php';
include 'includes/sidebar.php';

$typeLabels = ['user' => 'โอนให้ผู้ใช้', 'supply' => 'คืนพัสดุ', 'it' => 'ส่งซ่อม IT'];
$statusLabels = ['pending' => 'รอดำเนินการ', 'approved' => 'อนุมัติ', 'rejected' => 'ไม่อนุมัติ', 'completed' => 'เสร็จสิ้น'];
$statusBadges = ['pending' => 'badge-pending', 'approved' => 'badge-approved', 'rejected' => 'badge-rejected', 'completed' => 'badge-completed'];
?>

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-extrabold text-gray-800 flex items-center gap-2">
            <i class="fas fa-arrow-right-arrow-left text-blue-900"></i> โอนย้ายครุภัณฑ์
        </h1>
        <p class="text-gray-500 text-sm mt-1">รายการโอนย้ายทั้งหมด
            <?= number_format(count($transfers)) ?> รายการ
        </p>
    </div>
    <button onclick="showTransferForm()"
        class="btn-press bg-blue-900 text-white px-6 py-3 rounded-xl font-semibold border-b-4 border-yellow-700 hover:bg-blue-800 flex items-center gap-2 shadow-lg">
        <i class="fas fa-plus"></i> สร้างคำขอโอนย้าย
    </button>
</div>

<!-- Status Summary Cards -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
    <?php
    $counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'completed' => 0];
    foreach ($transfers as $t)
        $counts[$t['status']]++;
    $icons = ['pending' => 'fa-clock', 'approved' => 'fa-check', 'rejected' => 'fa-times', 'completed' => 'fa-check-double'];
    $colors = ['pending' => 'yellow', 'approved' => 'blue', 'rejected' => 'red', 'completed' => 'green'];
    foreach (['pending', 'approved', 'rejected', 'completed'] as $s):
        ?>
        <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 text-center card-hover">
            <i class="fas <?= $icons[$s] ?> text-<?= $colors[$s] ?>-600 text-2xl mb-2"></i>
            <p class="text-2xl font-extrabold text-gray-800">
                <?= $counts[$s] ?>
            </p>
            <p class="text-xs text-gray-500 font-medium">
                <?= $statusLabels[$s] ?>
            </p>
        </div>
    <?php endforeach; ?>
</div>

<!-- Transfer Table -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>ครุภัณฑ์</th>
                    <th>ผู้โอน</th>
                    <th>ปลายทาง</th>
                    <th>ประเภท</th>
                    <th>สถานะ</th>
                    <th>วันที่</th>
                    <th>ระยะเวลา</th>
                    <th class="text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transfers)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-12 text-gray-400">
                            <i class="fas fa-inbox text-4xl mb-3"></i>
                            <p>ไม่มีรายการโอนย้าย</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($transfers as $i => $tr): ?>
                        <tr class="animate-fade-in" style="animation-delay:<?= $i * 0.03 ?>s">
                            <td class="text-gray-400">
                                <?= $i + 1 ?>
                            </td>
                            <td>
                                <p class="font-semibold text-gray-800">
                                    <?= htmlspecialchars($tr['asset_name']) ?>
                                </p>
                                <span class="font-mono text-xs text-blue-600">
                                    <?= htmlspecialchars($tr['asset_code']) ?>
                                </span>
                            </td>
                            <td class="text-sm">
                                <?= htmlspecialchars($tr['from_name']) ?>
                            </td>
                            <td class="text-sm">
                                <?= htmlspecialchars($tr['to_name'] ?? 'เจ้าหน้าที่พัสดุ/IT') ?>
                            </td>
                            <td><span class="text-sm font-medium">
                                    <?= $typeLabels[$tr['transfer_type']] ?? $tr['transfer_type'] ?>
                                </span></td>
                            <td><span class="badge <?= $statusBadges[$tr['status']] ?>">
                                    <?= $statusLabels[$tr['status']] ?>
                                </span></td>
                            <td class="text-sm text-gray-500">
                                <?= formatThaiDate($tr['created_at']) ?>
                            </td>
                            <td>
                                <?php
                                if ($tr['status'] == 'pending') {
                                    $days = floor((time() - strtotime($tr['created_at'])) / (60 * 60 * 24));
                                    echo '<span class="text-orange-600 font-bold"><i class="fas fa-clock"></i> ' . $days . ' วัน</span>';
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td class="text-center">
                                <?php
                                $canAction = $isManager || ($tr['transfer_type'] === 'user' && $tr['to_user_id'] == $currentUser['id']);
                                if ($tr['status'] === 'pending' && $canAction):
                                ?>
                                    <div class="flex items-center justify-center gap-1">
                                        <button onclick="approveTransfer(<?= $tr['id'] ?>)"
                                            class="px-3 py-1.5 bg-green-600 text-white rounded-lg text-xs font-semibold hover:bg-green-700 transition">
                                            <i class="fas fa-check"></i> <?= $isManager ? 'อนุมัติ' : 'รับเข้า' ?>
                                        </button>
                                        <button onclick="rejectTransfer(<?= $tr['id'] ?>)"
                                            class="px-3 py-1.5 bg-red-600 text-white rounded-lg text-xs font-semibold hover:bg-red-700 transition">
                                            <i class="fas fa-times"></i> ปฏิเสธ
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <span class="text-xs text-gray-400">
                                        <?php if ($tr['approved_name']): ?>
                                            โดย
                                            <?= htmlspecialchars($tr['approved_name']) ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    let allAssets = [];
    let allUsers = [];

    // Load data on page load
    document.addEventListener('DOMContentLoaded', function () {
        loadAssetsAndUsers();
        checkPendingTransfers(); // Receiver Alert (chains to Sender Alert if none)
    });

    function loadAssetsAndUsers() {
        // Load user's assets
        fetch('api/assets.php?action=list')
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    allAssets = data.assets;
                }
            });

        // Load users
        fetch('api/users.php?action=list')
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    allUsers = data.users;
                }
            });
    }

    function showTransferForm() {
        SwalThemed.fire({
            title: '<i class="fas fa-arrow-right-arrow-left text-blue-900"></i> สร้างคำขอโอนย้ายครุภัณฑ์',
            html: `
                <div class="text-left space-y-4">
                    <!-- Asset Search -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-search text-blue-600"></i> ค้นหาครุภัณฑ์
                        </label>
                        <input type="text" 
                               id="assetSearch" 
                               placeholder="พิมพ์ชื่อหรือรหัสครุภัณฑ์..."
                               class="w-full border-2 border-gray-300 rounded-lg px-4 py-2 text-sm focus:border-blue-900 focus:outline-none"
                               oninput="searchAssets()">
                        <div id="assetResults" class="mt-2 max-h-48 overflow-y-auto"></div>
                        <input type="hidden" id="selectedAssetId">
                        <div id="selectedAssetDisplay" class="mt-2"></div>
                    </div>

                    <!-- Transfer Type -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-route text-blue-600"></i> ประเภทการโอนย้าย
                        </label>
                        <div class="grid grid-cols-2 gap-3">
                            <button type="button" 
                                    onclick="selectTransferType('person')" 
                                    id="typePersonBtn"
                                    class="transfer-type-btn border-2 border-gray-300 rounded-lg p-3 text-center hover:border-blue-500 transition">
                                <i class="fas fa-user text-2xl text-blue-600 mb-2"></i>
                                <p class="text-sm font-semibold">โอนให้บุคคล</p>
                                <p class="text-xs text-gray-500">ผู้รับต้องยืนยันรับ</p>
                            </button>
                            <button type="button" 
                                    onclick="selectTransferType('location')" 
                                    id="typeLocationBtn"
                                    class="transfer-type-btn border-2 border-gray-300 rounded-lg p-3 text-center hover:border-blue-500 transition">
                                <i class="fas fa-map-marker-alt text-2xl text-green-600 mb-2"></i>
                                <p class="text-sm font-semibold">โอนไปที่ตั้ง</p>
                                <p class="text-xs text-gray-500">เจ้าหน้าที่รับเข้า</p>
                            </button>
                        </div>
                        <input type="hidden" id="transferType">
                    </div>

                    <!-- Destination (Person) -->
                    <div id="personDestination" style="display: none;">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-user-check text-blue-600"></i> ผู้รับ
                        </label>
                        <select id="toUserId" 
                                class="w-full border-2 border-gray-300 rounded-lg px-4 py-2 text-sm focus:border-blue-900 focus:outline-none">
                            <option value="">-- เลือกผู้รับ --</option>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">
                            <i class="fas fa-info-circle"></i> ผู้รับจะได้รับแจ้งเตือนและต้องยืนยันรับครุภัณฑ์
                        </p>
                    </div>

                    <!-- Destination (Location) -->
                    <div id="locationDestination" style="display: none;">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-map-marker-alt text-blue-600"></i> ที่ตั้งใหม่
                        </label>
                        <select id="newLocation" 
                                class="w-full border-2 border-gray-300 rounded-lg px-4 py-2 text-sm focus:border-blue-900 focus:outline-none">
                            <option value="">-- เลือกที่ตั้ง --</option>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">
                            <i class="fas fa-info-circle"></i> เลือกที่ตั้งที่มีอยู่ในระบบ เจ้าหน้าที่พัสดุจะรับเข้า
                        </p>
                    </div>

                    <!-- Reason -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-comment-dots text-blue-600"></i> เหตุผล (ถ้ามี)
                        </label>
                        <textarea id="transferReason" 
                                  rows="3" 
                                  placeholder="ระบุเหตุผลการโอนย้าย..."
                                  class="w-full border-2 border-gray-300 rounded-lg px-4 py-2 text-sm focus:border-blue-900 focus:outline-none"></textarea>
                    </div>
                </div>
            `,
            width: '700px',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-paper-plane"></i> ส่งคำขอ',
            cancelButtonText: 'ยกเลิก',
            didOpen: () => {
                populateUserSelect();
                populateLocationSelect();
                $('#toUserId').select2({
                    dropdownParent: Swal.getPopup(),
                    width: '100%',
                    placeholder: '-- เลือกผู้รับ --',
                    allowClear: true
                });
            },
            preConfirm: () => {
                const assetId = document.getElementById('selectedAssetId').value;
                const transferType = document.getElementById('transferType').value;
                const toUserId = document.getElementById('toUserId').value;
                const newLocation = document.getElementById('newLocation').value;
                const reason = document.getElementById('transferReason').value;

                if (!assetId) {
                    Swal.showValidationMessage('กรุณาเลือกครุภัณฑ์');
                    return false;
                }

                if (!transferType) {
                    Swal.showValidationMessage('กรุณาเลือกประเภทการโอนย้าย');
                    return false;
                }

                if (transferType === 'person' && !toUserId) {
                    Swal.showValidationMessage('กรุณาเลือกผู้รับ');
                    return false;
                }

                if (transferType === 'location' && !newLocation.trim()) {
                    Swal.showValidationMessage('กรุณาระบุที่ตั้งใหม่');
                    return false;
                }

                return {
                    asset_id: assetId,
                    transfer_type: transferType === 'person' ? 'user' : 'supply',
                    to_user_id: transferType === 'person' ? toUserId : null,
                    new_location: transferType === 'location' ? newLocation : null,
                    reason: reason
                };
            }
        }).then(result => {
            if (result.isConfirmed) {
                showLoading('กำลังส่งคำขอ...');
                fetch('api/transfer.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'create', ...result.value })
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            SwalThemed.fire({
                                icon: 'success',
                                title: 'ส่งคำขอเรียบร้อย!',
                                text: 'คำขอโอนย้ายของคุณได้ถูกส่งไปยังเจ้าหน้าที่แล้ว',
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => location.reload());
                        } else {
                            SwalThemed.fire({
                                icon: 'error',
                                title: 'เกิดข้อผิดพลาด',
                                text: data.message || 'ไม่สามารถส่งคำขอได้'
                            });
                        }
                    });
            }
        });
    }

    function searchAssets() {
        const query = document.getElementById('assetSearch').value.toLowerCase();
        const resultsDiv = document.getElementById('assetResults');

        if (query.length < 2) {
            resultsDiv.innerHTML = '';
            return;
        }

        const filtered = allAssets.filter(asset =>
            asset.name.toLowerCase().includes(query) ||
            asset.asset_code.toLowerCase().includes(query)
        ).slice(0, 10);

        if (filtered.length === 0) {
            resultsDiv.innerHTML = '<p class="text-sm text-gray-400 p-2">ไม่พบครุภัณฑ์</p>';
            return;
        }

        resultsDiv.innerHTML = filtered.map(asset => `
            <div class="border border-gray-200 rounded-lg p-3 mb-2 hover:bg-blue-50 cursor-pointer transition"
                 onclick="selectAsset(${asset.id}, '${asset.name.replace(/'/g, "\\'")}', '${asset.asset_code}')">
                <p class="font-semibold text-sm text-gray-800">${asset.name}</p>
                <p class="text-xs text-blue-600 font-mono">${asset.asset_code}</p>
                ${asset.location ? `<p class="text-xs text-gray-500"><i class="fas fa-map-marker-alt"></i> ${asset.location}</p>` : ''}
            </div>
        `).join('');
    }

    function selectAsset(id, name, code) {
        document.getElementById('selectedAssetId').value = id;
        document.getElementById('assetSearch').value = '';
        document.getElementById('assetResults').innerHTML = '';
        document.getElementById('selectedAssetDisplay').innerHTML = `
            <div class="bg-blue-50 border-l-4 border-blue-600 p-3 rounded">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="font-semibold text-sm text-gray-800">${name}</p>
                        <p class="text-xs text-blue-600 font-mono">${code}</p>
                    </div>
                    <button onclick="clearAssetSelection()" class="text-red-500 hover:text-red-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        `;
    }

    function clearAssetSelection() {
        document.getElementById('selectedAssetId').value = '';
        document.getElementById('selectedAssetDisplay').innerHTML = '';
    }

    function selectTransferType(type) {
        document.getElementById('transferType').value = type;

        // Update button styles
        const personBtn = document.getElementById('typePersonBtn');
        const locationBtn = document.getElementById('typeLocationBtn');

        if (type === 'person') {
            personBtn.classList.add('bg-blue-50', 'border-blue-600');
            personBtn.classList.remove('border-gray-300');
            locationBtn.classList.remove('bg-green-50', 'border-green-600');
            locationBtn.classList.add('border-gray-300');

            document.getElementById('personDestination').style.display = 'block';
            document.getElementById('locationDestination').style.display = 'none';
        } else {
            locationBtn.classList.add('bg-green-50', 'border-green-600');
            locationBtn.classList.remove('border-gray-300');
            personBtn.classList.remove('bg-blue-50', 'border-blue-600');
            personBtn.classList.add('border-gray-300');

            document.getElementById('locationDestination').style.display = 'block';
            document.getElementById('personDestination').style.display = 'none';
        }
    }

    function populateUserSelect() {
        const select = document.getElementById('toUserId');
        select.innerHTML = '<option value="">-- เลือกผู้รับ --</option>';

        allUsers.forEach(user => {
            const option = document.createElement('option');
            option.value = user.id;
            option.textContent = `${user.full_name}${user.department ? ' (' + user.department + ')' : ''}`;
            select.appendChild(option);
        });
    }

    function populateLocationSelect() {
        fetch('api/assets.php?action=list_locations')
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const select = document.getElementById('newLocation');
                    select.innerHTML = '<option value="">-- เลือกที่ตั้ง --</option>';
                    data.locations.forEach(loc => {
                        const option = document.createElement('option');
                        option.value = loc;
                        option.textContent = loc;
                        select.appendChild(option);
                    });
                }
            });
    }

    // --- ALERTS SYSTEM ---

    function checkPendingTransfers() {
        // Run only for logged in users (implied by this page)
        // Check local storage if we already showed it this session? No, user says "When logging in". 
        // But since this is a specific page, maybe they want it every reload OR strictly on login.
        // Given 'transfer.php' context, I'll run it here. If I need it on index, I'd move it.
        // But the user specifically asked modifications on 'transfer.php'.
        // So I'll put it here.

        fetch('api/transfer.php?action=list_pending_acceptance')
            .then(r => r.json())
            .then(data => {
                if (data.success && data.transfers.length > 0) {
                    showReceiverAlert(data.transfers);
                } else {
                    checkSenderNotifications();
                }
            });
    }

    function showReceiverAlert(transfers) {
        let html = `
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-100 uppercase text-xs">
                        <tr>
                            <th class="px-4 py-2">ครุภัณฑ์</th>
                            <th class="px-4 py-2">จาก</th>
                            <th class="px-4 py-2">วันที่ส่ง</th>
                            <th class="px-4 py-2">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${transfers.map(tr => `
                            <tr class="border-b">
                                <td class="px-4 py-2 font-medium">${tr.asset_name}<br><span class="text-xs text-blue-600">${tr.asset_code}</span></td>
                                <td class="px-4 py-2">${tr.from_name}</td>
                                <td class="px-4 py-2">${tr.created_at.split(' ')[0]}</td>
                                <td class="px-4 py-2 flex gap-1">
                                    <button onclick="approveTransfer(${tr.id})" class="text-white bg-green-600 hover:bg-green-700 px-3 py-1 rounded text-xs">รับเข้า</button>
                                    <button onclick="rejectTransfer(${tr.id})" class="text-white bg-red-600 hover:bg-red-700 px-3 py-1 rounded text-xs">ปฏิเสธ</button>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;

        Swal.fire({
            title: 'รายการรอรับเข้า',
            html: html,
            width: '80%',
            customClass: {
                popup: 'max-w-7xl' // limit to ~1280px via tailwind max-w-7xl (80rem = 1280px)
            },
            showConfirmButton: false,
            showCloseButton: true
        });
    }

    function checkSenderNotifications() {
        // Shows summary of outgoing transfers
        // Logic: specific to requirement 4.

        fetch('api/transfer.php?action=list_my_requests')
            .then(r => r.json())
            .then(data => {
                if (data.success && data.transfers.length > 0) {
                    // Check if all completed
                    const pendingCount = data.transfers.filter(t => t.status === 'pending').length;

                    // Logic: "If all accepted (no pending)... close... mark as read".
                    // We need localStorage to track "read" state if no pending items exist to avoid spamming.
                    const lastUpdate = data.transfers[0].updated_at || data.transfers[0].created_at;
                    const storageKey = 'sender_notif_read_' + lastUpdate;

                    if (pendingCount === 0 && localStorage.getItem(storageKey)) {
                        return; // Already read and no pending
                    }

                    showSenderAlert(data.transfers, pendingCount, storageKey);
                }
            });
    }

    function showSenderAlert(transfers, pendingCount, storageKey) {
        let html = `
            <div class="overflow-x-auto max-h-96 overflow-y-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-100 uppercase text-xs sticky top-0">
                        <tr>
                            <th class="px-4 py-2">ครุภัณฑ์</th>
                            <th class="px-4 py-2">ปลายทาง</th>
                            <th class="px-4 py-2">สถานะ</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${transfers.map(tr => {
            let statusColor = tr.status === 'approved' ? 'text-green-600' : (tr.status === 'rejected' ? 'text-red-600' : 'text-yellow-600');
            let statusText = tr.status === 'approved' ? 'รับแล้ว' : (tr.status === 'rejected' ? 'ปฏิเสธ' : 'รอรับ');
            return `
                                <tr class="border-b">
                                    <td class="px-4 py-2 font-medium">${tr.asset_name}</td>
                                    <td class="px-4 py-2">${tr.to_name || '-'}</td>
                                    <td class="px-4 py-2 font-bold ${statusColor}">${statusText}</td>
                                </tr>
                            `;
        }).join('')}
                    </tbody>
                </table>
            </div>
        `;

        Swal.fire({
            title: `สถานะการโอนย้าย (${pendingCount} รอดำเนินการ)`,
            html: html,
            position: 'top-end',
            width: '600px',
            showConfirmButton: true,
            confirmButtonText: 'ตกลง (รับทราบ)',
        }).then((result) => {
            if (result.isConfirmed) {
                if (pendingCount === 0) {
                    localStorage.setItem(storageKey, 'true');
                }
            }
        });
    }

    function approveTransfer(id) {
        SwalThemed.fire({
            icon: 'question',
            title: 'อนุมัติการโอนย้าย?',
            text: 'ยืนยันการอนุมัติคำขอโอนย้ายนี้',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-check"></i> อนุมัติ',
            cancelButtonText: 'ยกเลิก',
            confirmButtonColor: '#16a34a'
        }).then(result => {
            if (result.isConfirmed) {
                showLoading();
                fetch('api/transfer.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'approve', id: id })
                }).then(r => r.json()).then(data => {
                    if (data.success) {
                        SwalThemed.fire({ icon: 'success', title: 'อนุมัติเรียบร้อย!', timer: 1500, showConfirmButton: false }).then(() => location.reload());
                    } else SwalThemed.fire({ icon: 'error', title: 'ผิดพลาด', text: data.message });
                });
            }
        });
    }

    function rejectTransfer(id) {
        SwalThemed.fire({
            icon: 'warning',
            title: 'ปฏิเสธการโอนย้าย?',
            input: 'textarea',
            inputPlaceholder: 'ระบุเหตุผล...',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-times"></i> ปฏิเสธ',
            confirmButtonColor: '#dc2626',
            cancelButtonText: 'ยกเลิก'
        }).then(result => {
            if (result.isConfirmed) {
                showLoading();
                fetch('api/transfer.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'reject', id: id, notes: result.value })
                }).then(r => r.json()).then(data => {
                    if (data.success) {
                        SwalThemed.fire({ icon: 'success', title: 'ปฏิเสธเรียบร้อย', timer: 1500, showConfirmButton: false }).then(() => location.reload());
                    } else SwalThemed.fire({ icon: 'error', title: 'ผิดพลาด', text: data.message });
                });
            }
        });
    }
</script>

<?php
// Auto-open create form if requested
if (isset($_GET['action']) && $_GET['action'] === 'create' && isset($_GET['asset_id'])) {
    $assetId = (int) $_GET['asset_id'];
    $stmt = $pdo->prepare("SELECT id, name, asset_code FROM assets WHERE id = ?");
    $stmt->execute([$assetId]);
    $preAsset = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($preAsset) {
        echo "<script>
            document.addEventListener('DOMContentLoaded', () => {
                // Wait for other scripts (like fetching lists)
                setTimeout(() => {
                    showTransferForm();
                    setTimeout(() => {
                        selectAsset({$preAsset['id']}, '" . addslashes($preAsset['name']) . "', '" . addslashes($preAsset['asset_code']) . "');
                    }, 300);
                }, 500); 
            });
        </script>";
    }
}
?>

<?php include 'includes/footer.php'; ?>
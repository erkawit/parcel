<?php
/**
 * Committee Management - คณะกรรมการตรวจสอบ
 */
require_once 'includes/auth.php';
requireLogin();
requireRole(['admin', 'supply_officer']);

$pageTitle = 'คณะกรรมการตรวจสอบ';

// Get committee orders
$stmt = $pdo->query("SELECT co.*, u.full_name as created_by_name,
                     ur.full_name as report_uploaded_by_name,
                     (SELECT COUNT(*) FROM committee_members WHERE order_id = co.id) as member_count,
                     (SELECT COUNT(*) FROM inspection_projects WHERE committee_order_id = co.id) as project_count
                     FROM committee_orders co
                     JOIN users u ON co.created_by = u.id
                     LEFT JOIN users ur ON co.meeting_report_uploaded_by = ur.id
                     ORDER BY co.fiscal_year DESC, co.created_at DESC");
$orders = $stmt->fetchAll();

// Get all users for member selection
$allUsers = $pdo->query("SELECT id, full_name, department, position FROM users WHERE status = 1 ORDER BY full_name")->fetchAll();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-extrabold text-gray-800 flex items-center gap-2">
            <i class="fas fa-users-gear text-blue-900"></i> คณะกรรมการตรวจสอบครุภัณฑ์
        </h1>
        <p class="text-gray-500 text-sm mt-1">จัดการคำสั่งแต่งตั้งคณะกรรมการตรวจสอบประจำปี</p>
    </div>
    <button onclick="showCreateOrderForm()"
        class="btn-press bg-blue-900 text-white px-6 py-3 rounded-xl font-semibold border-b-4 border-yellow-700 hover:bg-blue-800 flex items-center gap-2 shadow-lg">
        <i class="fas fa-plus"></i> สร้างคำสั่งใหม่
    </button>
</div>

<!-- Orders List -->
<div class="space-y-4">
    <?php if (empty($orders)): ?>
        <div class="bg-white rounded-xl p-12 text-center shadow-sm border border-gray-100">
            <i class="fas fa-users text-gray-300 text-5xl mb-4"></i>
            <p class="text-lg font-semibold text-gray-500">ยังไม่มีคำสั่งแต่งตั้ง</p>
            <p class="text-sm text-gray-400">คลิก "สร้างคำสั่งใหม่" เพื่อเริ่มต้น</p>
        </div>
    <?php else: ?>
        <?php foreach ($orders as $i => $order): ?>
            <div class="card-hover bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden animate-fade-in"
                style="animation-delay:<?= $i * 0.05 ?>s">
                <div class="flex flex-col md:flex-row">
                    <!-- Left Accent -->
                    <div class="w-full md:w-2 bg-gradient-to-b from-blue-900 to-blue-700"></div>

                    <div class="flex-1 p-5">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-3">
                            <div>
                                <h3 class="font-bold text-gray-800 text-lg">
                                    <i class="fas fa-scroll text-yellow-600 mr-1"></i>
                                    คำสั่งปีงบประมาณ
                                    <?= $order['fiscal_year'] ?>
                                </h3>
                                <?php if ($order['order_number']): ?>
                                    <p class="text-sm text-gray-500">เลขที่คำสั่ง:
                                        <?= htmlspecialchars($order['order_number']) ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="badge <?= $order['status'] === 'active' ? 'badge-active' : 'badge-retired' ?>">
                                    <?= $order['status'] === 'active' ? 'ใช้งาน' : 'ยกเลิก' ?>
                                </span>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm mb-4">
                            <div class="bg-gray-50 rounded-lg p-3">
                                <p class="text-gray-500 text-xs">วันที่คำสั่ง</p>
                                <p class="font-semibold text-gray-800">
                                    <?= formatThaiDate($order['order_date']) ?>
                                </p>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-3">
                                <p class="text-gray-500 text-xs">จำนวนกรรมการ</p>
                                <p class="font-semibold text-gray-800">
                                    <?= $order['member_count'] ?> คน
                                </p>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-3">
                                <p class="text-gray-500 text-xs">ผู้สร้าง</p>
                                <p class="font-semibold text-gray-800">
                                    <?= htmlspecialchars($order['created_by_name']) ?>
                                </p>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-3">
                                <p class="text-gray-500 text-xs">ไฟล์คำสั่ง</p>
                                <p class="font-semibold">
                                    <?php if ($order['pdf_file']): ?>
                                        <button
                                            onclick="previewPDF('<?= addslashes($order['pdf_file']) ?>', '<?= $order['fiscal_year'] ?>')"
                                            class="text-yellow-600 hover:text-yellow-700">
                                            <i class="fas fa-file-pdf"></i> ดูคำสั่ง
                                        </button>
                                    <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-3">
                                <p class="text-gray-500 text-xs">รายงานสรุปการประชุม</p>
                                <p class="font-semibold">
                                    <?php if ($order['meeting_report_file']): ?>
                                        <button
                                            onclick="previewPDF('<?= addslashes($order['meeting_report_file']) ?>', 'รายงานสรุป ปี <?= $order['fiscal_year'] ?>')"
                                            class="text-green-600 hover:text-green-700">
                                            <i class="fas fa-file-pdf"></i> ดูรายงาน
                                        </button>
                                    <?php else: ?>
                                        <span class="text-gray-400">ยังไม่อัปโหลด</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>

                        <?php if ($order['meeting_report_file']): ?>
                            <div class="bg-green-50 border-l-4 border-green-600 p-3 rounded mb-3">
                                <p class="text-sm text-green-800">
                                    <i class="fas fa-check-circle"></i>
                                    <strong>รายงานสรุปการประชุมอัปโหลดแล้ว</strong>
                                </p>
                                <p class="text-xs text-green-700 mt-1">
                                    โดย <?= htmlspecialchars($order['report_uploaded_by_name'] ?? 'ไม่ทราบ') ?>
                                    เมื่อ <?= formatThaiDate($order['meeting_report_uploaded_at']) ?>
                                </p>
                            </div>
                        <?php endif; ?>

                        <!-- Action Buttons -->
                        <div class="flex flex-wrap gap-2">
                            <button onclick="viewMembers(<?= $order['id'] ?>)"
                                class="btn-press px-4 py-2 bg-blue-900 text-white rounded-lg text-sm font-semibold hover:bg-blue-800 transition flex items-center gap-1">
                                <i class="fas fa-users"></i> ดูรายชื่อ
                            </button>
                            <a href="inspection.php?create_from_order=<?= $order['id'] ?>&year=<?= $order['fiscal_year'] ?>"
                                class="btn-press px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700 transition flex items-center gap-1">
                                <i class="fas fa-clipboard-check"></i> สร้างโครงการตรวจสอบ <?= $order['project_count'] > 0 ? "({$order['project_count']})" : '' ?>
                            </a>
                            <button onclick="editOrder(<?= $order['id'] ?>)"
                                class="btn-press px-4 py-2 bg-yellow-600 text-white rounded-lg text-sm font-semibold hover:bg-yellow-700 transition flex items-center gap-1">
                                <i class="fas fa-edit"></i> แก้ไข
                            </button>
                            <?php if ($order['pdf_file']): ?>
                                <button
                                    onclick="previewPDF('<?= addslashes($order['pdf_file']) ?>', '<?= $order['fiscal_year'] ?>')"
                                    class="btn-press px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-semibold hover:bg-red-700 transition flex items-center gap-1">
                                    <i class="fas fa-file-pdf"></i> ดูคำสั่ง PDF
                                </button>
                            <?php endif; ?>
                            <?php if (!$order['meeting_report_file']): ?>
                                <button onclick="uploadMeetingReport(<?= $order['id'] ?>)"
                                    class="btn-press px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-semibold hover:bg-green-700 transition flex items-center gap-1">
                                    <i class="fas fa-upload"></i> อัปโหลดรายงานสรุป
                                </button>
                            <?php else: ?>
                                <button
                                    onclick="previewPDF('<?= addslashes($order['meeting_report_file']) ?>', 'รายงานสรุป ปี <?= $order['fiscal_year'] ?>')"
                                    class="btn-press px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-semibold hover:bg-green-700 transition flex items-center gap-1">
                                    <i class="fas fa-file-pdf"></i> ดูรายงานสรุป
                                </button>
                                <button onclick="uploadMeetingReport(<?= $order['id'] ?>)"
                                    class="btn-press px-3 py-2 bg-green-100 text-green-800 rounded-lg text-sm font-semibold hover:bg-green-200 transition flex items-center gap-1" title="เปลี่ยนไฟล์รายงานสรุป">
                                    <i class="fas fa-sync-alt"></i> เปลี่ยนไฟล์รายงาน
                                </button>
                            <?php endif; ?>
                            <button onclick="toggleOrderStatus(<?= $order['id'] ?>, '<?= $order['status'] === 'active' ? 'cancelled' : 'active' ?>')"
                                class="btn-press px-3 py-2 <?= $order['status'] === 'active' ? 'bg-gray-200 text-gray-700 hover:bg-gray-300' : 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200' ?> rounded-lg text-sm font-semibold transition flex items-center gap-1">
                                <i class="fas <?= $order['status'] === 'active' ? 'fa-ban' : 'fa-check' ?>"></i>
                                <?= $order['status'] === 'active' ? 'ยกเลิกคำสั่ง' : 'เปิดใช้งาน' ?>
                            </button>
                            <button onclick="deleteOrder(<?= $order['id'] ?>, '<?= $order['fiscal_year'] ?>')"
                                class="btn-press px-3 py-2 bg-red-50 text-red-600 rounded-lg text-sm font-semibold hover:bg-red-100 transition flex items-center gap-1" title="ลบคำสั่ง">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<style>
    /* Select2 Customization */
    .select2-container .select2-selection--single {
        height: 42px !important;
        border: 2px solid #e5e7eb !important;
        border-radius: 0.5rem !important;
        padding: 6px 0 !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 40px !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 28px !important;
        font-size: 0.875rem !important;
        color: #1f2937 !important;
    }

    .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable {
        background-color: #1e3a8a !important;
        /* blue-900 */
    }

    .select2-dropdown {
        border: 2px solid #e5e7eb !important;
        border-radius: 0.5rem !important;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06) !important;
        z-index: 9999 !important;
        /* Ensure it stays on top of SweetAlert */
    }
</style>

<script>
    const allUsers = <?= json_encode($allUsers) ?>;
    let memberRows = 0;

    function showCreateOrderForm(orderData = null) {
        memberRows = 0;
        const isEdit = !!orderData;

        SwalThemed.fire({
            title: isEdit
                ? '<i class="fas fa-edit text-yellow-600"></i> แก้ไขคำสั่งแต่งตั้ง'
                : '<i class="fas fa-plus-circle text-blue-900"></i> สร้างคำสั่งแต่งตั้ง',
            html: `
            <form id="orderForm" enctype="multipart/form-data" class="text-left space-y-3">
                <input type="hidden" id="co_id" value="${isEdit ? orderData.id : ''}">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">ปีงบประมาณ (พ.ศ.) *</label>
                        <input id="co_year" type="number" value="${isEdit ? orderData.fiscal_year : new Date().getFullYear() + 543}" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">เลขที่คำสั่ง</label>
                        <input id="co_number" value="${isEdit && orderData.order_number ? orderData.order_number : ''}" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none" placeholder="เช่น 001/2569">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">วันที่คำสั่ง</label>
                    <input id="co_date" value="${isEdit && orderData.order_date ? orderData.order_date : ''}" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none flatpickr-input" placeholder="เลือกวันที่">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">ไฟล์คำสั่ง (PDF) ${isEdit ? '<span class="text-gray-400 font-normal">(อัปโหลดใหม่เพื่อเปลี่ยน)</span>' : ''}</label>
                    <input id="co_file" type="file" accept=".pdf" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none">
                    ${isEdit && orderData.pdf_file ? `<div class="mt-1 text-xs text-blue-600"><i class="fas fa-file-pdf"></i> มีไฟล์เดิมอยู่แล้ว</div>` : ''}
                </div>
                <hr class="my-3">
                <div class="flex items-center justify-between mb-2">
                    <label class="text-sm font-bold text-gray-700"><i class="fas fa-users text-blue-900"></i> รายชื่อคณะกรรมการ</label>
                    <div class="flex gap-1.5">
                        <?php if (!empty($orders)): ?>
                            <button type="button" onclick="copyFromLatestOrder()" class="text-xs bg-indigo-100 text-indigo-800 px-3 py-1.5 rounded-lg hover:bg-indigo-200 transition font-semibold">
                                <i class="fas fa-copy"></i> ดึงรายชื่อจากปีล่าสุด
                            </button>
                        <?php endif; ?>
                        <button type="button" onclick="addMemberRow()" class="text-xs bg-blue-900 text-white px-3 py-1.5 rounded-lg hover:bg-blue-800 transition">
                            <i class="fas fa-plus"></i> เพิ่ม
                        </button>
                    </div>
                </div>
                <div id="membersContainer" class="space-y-2 max-h-60 overflow-y-auto px-1"></div>
            </form>
        `,
            showCancelButton: true,
            confirmButtonText: isEdit ? '<i class="fas fa-save"></i> บันทึกการแก้ไข' : '<i class="fas fa-save"></i> บันทึก',
            cancelButtonText: 'ยกเลิก',
            width: '700px',
            didOpen: () => {
                flatpickr('#co_date', { locale: 'th', dateFormat: 'Y-m-d' });

                if (isEdit && orderData.members && orderData.members.length > 0) {
                    orderData.members.forEach(m => addMemberRow(m.role, m.user_id));
                } else {
                    // Add default rows
                    addMemberRow('chairman');
                    addMemberRow('member');
                    addMemberRow('secretary');
                }
            },
            preConfirm: () => {
                const year = document.getElementById('co_year').value;
                if (!year) { Swal.showValidationMessage('กรุณากรอกปีงบประมาณ'); return false; }

                // Collect members & check duplicates
                const members = [];
                const seenIds = new Set();
                let hasDuplicate = false;
                document.querySelectorAll('.member-row').forEach(row => {
                    const userId = $(row).find('.member-user').val(); // Start retrieving value from select2 properly
                    const role = row.querySelector('.member-role').value;
                    if (userId) {
                        if (seenIds.has(userId)) {
                            hasDuplicate = true;
                        }
                        seenIds.add(userId);
                        members.push({ user_id: userId, role: role });
                    }
                });

                if (hasDuplicate) { Swal.showValidationMessage('พบบุคคลซ้ำในรายชื่อคณะกรรมการ กรุณาตรวจสอบอีกครั้ง'); return false; }
                if (members.length === 0) { Swal.showValidationMessage('กรุณาเพิ่มรายชื่อกรรมการอย่างน้อย 1 คน'); return false; }

                return {
                    id: document.getElementById('co_id').value,
                    fiscal_year: year,
                    order_number: document.getElementById('co_number').value,
                    order_date: document.getElementById('co_date').value,
                    members: members,
                    file: document.getElementById('co_file').files[0]
                };
            }
        }).then(result => {
            if (result.isConfirmed) {
                showLoading('กำลังบันทึก...');

                const formData = new FormData();
                formData.append('action', isEdit ? 'update' : 'create');
                if (isEdit) formData.append('id', result.value.id);

                formData.append('fiscal_year', result.value.fiscal_year);
                formData.append('order_number', result.value.order_number);
                formData.append('order_date', result.value.order_date);
                formData.append('members', JSON.stringify(result.value.members));
                if (result.value.file) formData.append('pdf_file', result.value.file);

                fetch('api/committee.php', { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            SwalThemed.fire({ icon: 'success', title: 'สำเร็จ!', text: isEdit ? 'แก้ไขข้อมูลเรียบร้อย' : 'สร้างคำสั่งแต่งตั้งเรียบร้อย', timer: 2000, showConfirmButton: false })
                                .then(() => location.reload());
                        } else SwalThemed.fire({ icon: 'error', title: 'ผิดพลาด', text: data.message });
                    });
            }
        });
    }

    function addMemberRow(defaultRole = 'member', defaultUser = '') {
        const container = document.getElementById('membersContainer');
        const userOptions = allUsers.map(u => `<option value="${u.id}">${u.full_name} (${u.department || 'ไม่ระบุ'})</option>`).join('');

        const rowId = `member-row-${memberRows}`;
        const row = document.createElement('div');
        row.className = 'member-row flex gap-2 items-center mb-2';
        row.innerHTML = `
        <select class="member-role flex-shrink-0 w-36 border-2 border-gray-200 rounded-lg px-2 py-2 text-xs focus:border-blue-900 focus:outline-none">
            <option value="chairman" ${defaultRole === 'chairman' ? 'selected' : ''}>ประธานกรรมการ</option>
            <option value="member" ${defaultRole === 'member' ? 'selected' : ''}>กรรมการ</option>
            <option value="secretary" ${defaultRole === 'secretary' ? 'selected' : ''}>เลขานุการ</option>
        </select>
        <div class="flex-1">
            <select id="${rowId}-user" class="member-user w-full text-xs">
                <option value="">-- เลือกบุคคล --</option>${userOptions}
            </select>
        </div>
        <button type="button" onclick="removeMemberRow(this)" class="text-red-500 hover:text-red-700 p-1 flex-shrink-0"><i class="fas fa-times"></i></button>
    `;
        container.appendChild(row);

        // Initialize Select2
        $(`#${rowId}-user`).select2({
            width: '100%',
            dropdownParent: Swal.getPopup(), // Important for SweetAlert2
            placeholder: '-- เลือกบุคคล --',
            allowClear: true,
            language: {
                noResults: function () { return "ไม่พบรายชื่อ"; }
            }
        });

        if (defaultUser) {
            $(`#${rowId}-user`).val(defaultUser).trigger('change');
        }

        memberRows++;
    }

    function removeMemberRow(btn) {
        const row = btn.closest('.member-row');
        // Destroy select2 instance before removing
        $(row).find('.member-user').select2('destroy');
        row.remove();
    }

    function viewMembers(orderId) {
        fetch(`api/committee.php?action=members&order_id=${orderId}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success) return SwalThemed.fire('ผิดพลาด', data.message, 'error');

                const roleIcons = { chairman: '👑', member: '👤', secretary: '📝' };
                const roleNames = { chairman: 'ประธานกรรมการ', member: 'กรรมการ', secretary: 'เลขานุการ' };

                let html = '<div class="space-y-2 text-left">';
                data.members.forEach(m => {
                    html += `
                <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                    <span class="text-2xl">${roleIcons[m.role] || '👤'}</span>
                    <div>
                        <p class="font-semibold text-gray-800">${m.full_name}</p>
                        <p class="text-xs text-gray-500">${roleNames[m.role] || m.role} | ${m.department || '-'} | ${m.position || '-'}</p>
                    </div>
                </div>
            `;
                });
                html += '</div>';

                SwalThemed.fire({
                    title: '<i class="fas fa-users text-blue-900"></i> รายชื่อคณะกรรมการ',
                    html: html,
                    width: '550px',
                    confirmButtonText: 'ปิด'
                });
            });
    }

    function editOrder(orderId) {
        showLoading('กำลังโหลดข้อมูล...');
        fetch(`api/committee.php?action=get&id=${orderId}`)
            .then(r => r.json())
            .then(data => {
                Swal.close();
                if (data.success) {
                    showCreateOrderForm(data.order);
                } else {
                    SwalThemed.fire('ผิดพลาด', data.message, 'error');
                }
            })
            .catch(err => SwalThemed.fire('ผิดพลาด', 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้', 'error'));
    }

    function uploadMeetingReport(orderId) {
        SwalThemed.fire({
            title: '<i class="fas fa-upload text-green-600"></i> อัปโหลดรายงานสรุปการประชุม',
            html: `
                <div class="text-left space-y-3">
                    <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded">
                        <p class="text-sm text-gray-700">
                            <i class="fas fa-info-circle text-blue-500"></i> 
                            อัปโหลดไฟล์รายงานสรุปการประชุมคณะกรรมการตรวจรับครุภัณฑ์
                        </p>
                        <p class="text-xs text-gray-600 mt-2">
                            • รองรับไฟล์ PDF เท่านั้น<br>
                            • ขนาดไฟล์ไม่เกิน 10 MB<br>
                            • ควรอัปโหลดหลังจากตรวจสอบครุภัณฑ์เสร็จสิ้นแล้ว
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-file-pdf text-red-600"></i> เลือกไฟล์ PDF
                        </label>
                        <input type="file" 
                               id="meetingReportFile" 
                               accept=".pdf"
                               class="w-full border-2 border-gray-300 rounded-lg px-4 py-2 text-sm focus:border-blue-900 focus:outline-none">
                    </div>
                </div>
            `,
            width: '600px',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-upload"></i> อัปโหลด',
            cancelButtonText: 'ยกเลิก',
            confirmButtonColor: '#16a34a',
            preConfirm: () => {
                const fileInput = document.getElementById('meetingReportFile');
                const file = fileInput.files[0];

                if (!file) {
                    Swal.showValidationMessage('กรุณาเลือกไฟล์');
                    return false;
                }

                if (file.type !== 'application/pdf') {
                    Swal.showValidationMessage('กรุณาเลือกไฟล์ PDF เท่านั้น');
                    return false;
                }

                if (file.size > 10 * 1024 * 1024) {
                    Swal.showValidationMessage('ขนาดไฟล์ต้องไม่เกิน 10 MB');
                    return false;
                }

                return { file: file };
            }
        }).then(result => {
            if (result.isConfirmed) {
                showLoading('กำลังอัปโหลด...');

                const formData = new FormData();
                formData.append('action', 'upload_meeting_report');
                formData.append('order_id', orderId);
                formData.append('meeting_report_file', result.value.file);

                fetch('api/committee.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            SwalThemed.fire({
                                icon: 'success',
                                title: 'อัปโหลดสำเร็จ!',
                                text: 'รายงานสรุปการประชุมถูกอัปโหลดเรียบร้อยแล้ว',
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => location.reload());
                        } else {
                            SwalThemed.fire({
                                icon: 'error',
                                title: 'เกิดข้อผิดพลาด',
                                text: data.message || 'ไม่สามารถอัปโหลดไฟล์ได้'
                            });
                        }
                    })
                    .catch(error => {
                        SwalThemed.fire({
                            icon: 'error',
                            title: 'เกิดข้อผิดพลาด',
                            text: 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้'
                        });
                    });
            }
        });
    }

    function previewPDF(filePath, year) {
        Swal.fire({
            title: `<strong><i class="fas fa-file-pdf text-red-600"></i> คำสั่งแต่งตั้งคณะกรรมการ ปี ${year}</strong>`,
            html: `<iframe src="${filePath}" width="100%" height="500px" style="border:none; border-radius:8px;"></iframe>`,
            showCloseButton: true,
            focusConfirm: false,
            confirmButtonText: 'ปิดหน้าต่าง',
            confirmButtonColor: '#1e3a8a',
            width: '800px'
        });
    }

    function copyFromLatestOrder() {
        fetch('api/committee.php?action=latest_members')
            .then(r => r.json())
            .then(data => {
                if (!data.success || !data.members || data.members.length === 0) {
                    return Swal.showValidationMessage(data.message || 'ไม่พบรายชื่อจากคำสั่งล่าสุด');
                }
                const container = document.getElementById('membersContainer');
                container.querySelectorAll('.member-row').forEach(row => {
                    $(row).find('.member-user').select2('destroy');
                    row.remove();
                });
                data.members.forEach(m => addMemberRow(m.role, m.user_id));
            });
    }

    function toggleOrderStatus(orderId, newStatus) {
        const label = newStatus === 'active' ? 'เปิดใช้งาน' : 'ยกเลิก';
        SwalThemed.fire({
            icon: 'question',
            title: `ยืนยันการ${label}คำสั่ง?`,
            showCancelButton: true,
            confirmButtonText: 'ยืนยัน',
            cancelButtonText: 'ปิด'
        }).then(r => {
            if (r.isConfirmed) {
                showLoading();
                fetch('api/committee.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'toggle_status', id: orderId, status: newStatus })
                }).then(res => res.json()).then(d => {
                    if (d.success) location.reload();
                    else SwalThemed.fire('ผิดพลาด', d.message, 'error');
                });
            }
        });
    }

    function deleteOrder(orderId, year) {
        SwalThemed.fire({
            icon: 'warning',
            title: `ลบคำสั่งแต่งตั้ง ปี ${year}?`,
            html: 'การลบจะยกเลิกการผูกคำสั่งนี้กับโครงการตรวจสอบที่เกี่ยวข้อง<br><small class="text-red-500">การกระทำนี้ไม่สามารถย้อนกลับได้</small>',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            confirmButtonText: '<i class="fas fa-trash"></i> ลบคำสั่ง',
            cancelButtonText: 'ยกเลิก'
        }).then(r => {
            if (r.isConfirmed) {
                showLoading('กำลังลบ...');
                fetch('api/committee.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete', id: orderId })
                }).then(res => res.json()).then(d => {
                    if (d.success) {
                        SwalThemed.fire({ icon: 'success', title: 'ลบเรียบร้อย!', timer: 1200, showConfirmButton: false })
                            .then(() => location.reload());
                    } else {
                        SwalThemed.fire('ผิดพลาด', d.message, 'error');
                    }
                });
            }
        });
    }
</script>

<?php include 'includes/footer.php'; ?>
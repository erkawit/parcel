<?php
/**
 * Profile - โปรไฟล์ผู้ใช้งาน
 */
require_once 'includes/auth.php';
requireLogin();

$pageTitle = 'โปรไฟล์';
$currentUser = getCurrentUser();

// Get committee history
$stmt = $pdo->prepare("SELECT cm.role, co.fiscal_year, co.order_number, co.order_date, co.pdf_file, co.id as order_id
                       FROM committee_members cm
                       JOIN committee_orders co ON cm.order_id = co.id
                       WHERE cm.user_id = ?
                       ORDER BY co.fiscal_year DESC");
$stmt->execute([$currentUser['id']]);
$committeeHistory = $stmt->fetchAll();

// Get my assets count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM assets WHERE holder_id = ?");
$stmt->execute([$currentUser['id']]);
$myAssetCount = $stmt->fetchColumn();

// Get my transfer history
$stmt = $pdo->prepare("SELECT COUNT(*) FROM transfer_requests WHERE from_user_id = ? OR to_user_id = ?");
$stmt->execute([$currentUser['id'], $currentUser['id']]);
$myTransferCount = $stmt->fetchColumn();

// Get inspections assigned (directly or via inspection group)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM inspection_items WHERE assigned_to = ? OR group_id IN (SELECT group_id FROM inspection_group_members WHERE user_id = ?)");
$stmt->execute([$currentUser['id'], $currentUser['id']]);
$myInspectionCount = $stmt->fetchColumn();

$roleLabels = ['chairman' => 'ประธานกรรมการ', 'member' => 'กรรมการ', 'secretary' => 'เลขานุการ'];

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="max-w-4xl mx-auto">

    <!-- Profile Card -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-6 animate-fade-in">
        <div class="bg-gradient-to-r from-blue-900 to-blue-700 px-6 py-8 text-white text-center">
            <div
                class="w-24 h-24 bg-yellow-500 rounded-full mx-auto flex items-center justify-center text-blue-900 text-4xl font-extrabold shadow-lg mb-3">
                <?= mb_substr($currentUser['full_name'], 0, 1, 'UTF-8') ?>
            </div>
            <h1 class="text-2xl font-extrabold">
                <?= htmlspecialchars($currentUser['full_name']) ?>
            </h1>
            <p class="text-blue-200 mt-1">
                <?= htmlspecialchars($currentUser['position'] ?? '') ?> |
                <?= htmlspecialchars($currentUser['department'] ?? '') ?>
            </p>
            <span class="inline-block mt-2 bg-yellow-500 text-blue-900 text-xs font-bold px-4 py-1 rounded-full">
                <?= getRoleLabel($currentUser['role']) ?>
            </span>
        </div>

        <div class="grid grid-cols-3 divide-x divide-gray-100">
            <div class="p-4 text-center">
                <p class="text-2xl font-extrabold text-blue-900">
                    <?= $myAssetCount ?>
                </p>
                <p class="text-xs text-gray-500">ครุภัณฑ์ถือครอง</p>
            </div>
            <div class="p-4 text-center">
                <p class="text-2xl font-extrabold text-yellow-600">
                    <?= $myTransferCount ?>
                </p>
                <p class="text-xs text-gray-500">การโอนย้าย</p>
            </div>
            <div class="p-4 text-center">
                <p class="text-2xl font-extrabold text-green-600">
                    <?= $myInspectionCount ?>
                </p>
                <p class="text-xs text-gray-500">ตรวจสอบ</p>
            </div>
        </div>
    </div>

    <!-- Info -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6 animate-fade-in"
        style="animation-delay:0.1s">
        <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fas fa-id-card text-blue-900"></i> ข้อมูลส่วนตัว
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div class="bg-gray-50 rounded-lg p-3">
                <label class="text-gray-500 text-xs">ชื่อผู้ใช้</label>
                <p class="font-semibold text-gray-800">
                    <?= htmlspecialchars($currentUser['username']) ?>
                </p>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
                <label class="text-gray-500 text-xs">อีเมล</label>
                <p class="font-semibold text-gray-800">
                    <?= htmlspecialchars($currentUser['email'] ?? '-') ?>
                </p>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
                <label class="text-gray-500 text-xs">โทรศัพท์</label>
                <p class="font-semibold text-gray-800">
                    <?= htmlspecialchars($currentUser['phone'] ?? '-') ?>
                </p>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
                <label class="text-gray-500 text-xs">แผนก</label>
                <p class="font-semibold text-gray-800">
                    <?= htmlspecialchars($currentUser['department'] ?? '-') ?>
                </p>
            </div>
        </div>
        <div class="mt-4">
            <button onclick="editProfile()"
                class="btn-press bg-blue-900 text-white px-5 py-2.5 rounded-lg font-semibold text-sm hover:bg-blue-800 border-b-3 border-yellow-700">
                <i class="fas fa-edit"></i> แก้ไขข้อมูล
            </button>
            <button onclick="changePassword()"
                class="btn-press bg-gray-600 text-white px-5 py-2.5 rounded-lg font-semibold text-sm hover:bg-gray-700 ml-2">
                <i class="fas fa-key"></i> เปลี่ยนรหัสผ่าน
            </button>
        </div>
    </div>

    <!-- Committee History Tab -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden animate-fade-in"
        style="animation-delay:0.2s">
        <div class="bg-blue-900 text-white px-5 py-3 flex items-center gap-2">
            <i class="fas fa-scroll text-yellow-400"></i>
            <h3 class="font-bold text-sm">ประวัติคณะกรรมการ</h3>
        </div>

        <?php if (empty($committeeHistory)): ?>
            <div class="p-8 text-center text-gray-400">
                <i class="fas fa-users text-4xl mb-3"></i>
                <p class="text-sm">ยังไม่มีประวัติการแต่งตั้งเป็นคณะกรรมการ</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>ปีงบประมาณ</th>
                            <th>เลขที่คำสั่ง</th>
                            <th>วันที่คำสั่ง</th>
                            <th>ตำแหน่ง</th>
                            <th class="text-center">คำสั่ง</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($committeeHistory as $i => $ch): ?>
                            <tr>
                                <td class="text-gray-400">
                                    <?= $i + 1 ?>
                                </td>
                                <td class="font-semibold">
                                    <?= $ch['fiscal_year'] ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($ch['order_number'] ?? '-') ?>
                                </td>
                                <td class="text-sm">
                                    <?= formatThaiDate($ch['order_date']) ?>
                                </td>
                                <td>
                                    <span
                                        class="badge <?= $ch['role'] === 'chairman' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800' ?>">
                                        <?= $roleLabels[$ch['role']] ?? $ch['role'] ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if ($ch['pdf_file']): ?>
                                        <button
                                            onclick="previewPDF('<?= addslashes($ch['pdf_file']) ?>', '<?= $ch['fiscal_year'] ?>')"
                                            class="btn-press px-4 py-1.5 bg-yellow-600 text-white rounded-lg text-xs font-semibold hover:bg-yellow-700 transition">
                                            <i class="fas fa-file-pdf"></i> ดูคำสั่ง
                                        </button>
                                    <?php else: ?>
                                        <span class="text-gray-400 text-sm">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
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

    function editProfile() {
        const user = <?= json_encode($currentUser) ?>;
        SwalThemed.fire({
            title: '<i class="fas fa-edit text-blue-900"></i> แก้ไขข้อมูล',
            html: `
            <div class="text-left space-y-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">ชื่อ-สกุล</label>
                    <input id="ep_name" value="${user.full_name}" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">อีเมล</label>
                    <input id="ep_email" value="${user.email || ''}" type="email" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">โทรศัพท์</label>
                    <input id="ep_phone" value="${user.phone || ''}" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none">
                </div>
            </div>
        `,
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-save"></i> บันทึก',
            cancelButtonText: 'ยกเลิก',
            preConfirm: () => ({
                full_name: document.getElementById('ep_name').value,
                email: document.getElementById('ep_email').value,
                phone: document.getElementById('ep_phone').value
            })
        }).then(r => {
            if (r.isConfirmed) {
                showLoading();
                fetch('api/users.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'update_profile', ...r.value })
                }).then(r => r.json()).then(d => {
                    if (d.success) SwalThemed.fire({ icon: 'success', title: 'บันทึกเรียบร้อย!', timer: 1500, showConfirmButton: false }).then(() => location.reload());
                    else SwalThemed.fire({ icon: 'error', title: 'ผิดพลาด', text: d.message });
                });
            }
        });
    }

    window.togglePassword = function (id, btn) {
        const input = document.getElementById(id);
        const icon = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    function changePassword() {
        SwalThemed.fire({
            title: '<i class="fas fa-key text-yellow-600"></i> เปลี่ยนรหัสผ่าน',
            html: `
            <div class="text-left space-y-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">รหัสผ่านปัจจุบัน</label>
                    <div class="relative">
                        <input id="cp_old" type="password" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none pr-10">
                        <button type="button" onclick="window.togglePassword('cp_old', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-blue-900 focus:outline-none">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">รหัสผ่านใหม่</label>
                    <div class="relative">
                        <input id="cp_new" type="password" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none pr-10">
                        <button type="button" onclick="window.togglePassword('cp_new', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-blue-900 focus:outline-none">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">ยืนยันรหัสผ่านใหม่</label>
                    <div class="relative">
                        <input id="cp_confirm" type="password" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none pr-10">
                        <button type="button" onclick="window.togglePassword('cp_confirm', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-blue-900 focus:outline-none">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
            </div>
        `,
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-save"></i> เปลี่ยน',
            cancelButtonText: 'ยกเลิก',
            preConfirm: () => {
                const o = document.getElementById('cp_old').value;
                const n = document.getElementById('cp_new').value;
                const c = document.getElementById('cp_confirm').value;
                if (!o || !n) { Swal.showValidationMessage('กรุณากรอกข้อมูลให้ครบ'); return false; }
                if (n !== c) { Swal.showValidationMessage('รหัสผ่านใหม่ไม่ตรงกัน'); return false; }
                if (n.length < 6) { Swal.showValidationMessage('รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร'); return false; }
                return { old_password: o, new_password: n };
            }
        }).then(r => {
            if (r.isConfirmed) {
                showLoading();
                fetch('api/users.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'change_password', ...r.value })
                }).then(r => r.json()).then(d => {
                    if (d.success) SwalThemed.fire({ icon: 'success', title: 'เปลี่ยนรหัสผ่านสำเร็จ!', timer: 2000, showConfirmButton: false });
                    else SwalThemed.fire({ icon: 'error', title: 'ผิดพลาด', text: d.message });
                });
            }
        });
    }
</script>

<?php include 'includes/footer.php'; ?>
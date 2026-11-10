<?php
/**
 * User Management - จัดการผู้ใช้งาน (Admin Only)
 */
require_once 'includes/auth.php';
requireLogin();
requireRole(['admin']);

// Get Dropdown Options
$locations = $pdo->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != '' ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);
$positions = $pdo->query("SELECT * FROM authen_position ORDER BY id")->fetchAll();
$levels = $pdo->query("SELECT * FROM authen_l_position ORDER BY id")->fetchAll();

$pageTitle = 'จัดการผู้ใช้งาน';

// Filters
$search = $_GET['search'] ?? '';
$roleFilter = $_GET['role'] ?? '';

// Build query
$where = [];
$params = [];

if ($search) {
    $where[] = "(username LIKE ? OR full_name LIKE ? OR email LIKE ? OR department LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%"]);
}

if ($roleFilter) {
    $where[] = "role = ?";
    $params[] = $roleFilter;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Execute query
$stmt = $pdo->prepare("SELECT * FROM users $whereSQL ORDER BY role, full_name");
$stmt->execute($params);
$users = $stmt->fetchAll();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-extrabold text-gray-800 flex items-center gap-2">
            <i class="fas fa-users-cog text-blue-900"></i> จัดการผู้ใช้งาน
        </h1>
        <p class="text-gray-500 text-sm mt-1">ทั้งหมด
            <?= count($users) ?> คน
        </p>
    </div>
    <button onclick="showAddUserForm()"
        class="btn-press bg-blue-900 text-white px-6 py-3 rounded-xl font-semibold border-b-4 border-yellow-700 hover:bg-blue-800 flex items-center gap-2 shadow-lg">
        <i class="fas fa-user-plus"></i> เพิ่มผู้ใช้ใหม่
    </button>
</div>

<!-- Search & Filters -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div class="relative md:col-span-2">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                placeholder="ค้นหาชื่อ, ชื่อผู้ใช้, อีเมล, แผนก..."
                class="w-full pl-10 pr-4 py-2.5 border-2 border-gray-200 rounded-xl text-sm focus:border-blue-900 focus:outline-none transition">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
        </div>
        <div class="flex gap-2">
            <select name="role" onchange="this.form.submit()"
                class="border-2 border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:border-blue-900 focus:outline-none transition flex-1 cursor-pointer">
                <option value="">-- ทุกสิทธิ์ --</option>
                <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>ผู้ดูแลระบบ</option>
                <option value="director" <?= $roleFilter === 'director' ? 'selected' : '' ?>>ผู้อำนวยการฯ</option>
                <option value="supply_officer" <?= $roleFilter === 'supply_officer' ? 'selected' : '' ?>>เจ้าหน้าที่พัสดุ
                </option>
                <option value="department_head" <?= $roleFilter === 'department_head' ? 'selected' : '' ?>>หัวหน้าส่วน
                </option>
                <option value="user" <?= $roleFilter === 'user' ? 'selected' : '' ?>>ผู้ใช้งานทั่วไป</option>
            </select>
            <button type="submit"
                class="bg-blue-900 text-white rounded-xl px-6 py-2.5 font-semibold hover:bg-blue-800 transition shadow-lg">
                <i class="fas fa-search"></i>
            </button>
            <?php if ($search || $roleFilter): ?>
                <a href="users.php"
                    class="bg-gray-200 text-gray-600 rounded-xl px-4 py-2.5 hover:bg-gray-300 transition flex items-center justify-center"
                    title="ล้างค่าค้นหา">
                    <i class="fas fa-times"></i>
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Users Table -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>ชื่อ-สกุล</th>
                    <th>ชื่อผู้ใช้</th>
                    <th>อีเมล</th>
                    <th>แผนก</th>
                    <th>สิทธิ์</th>
                    <th>สถานะ</th>
                    <th class="text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $i => $user): ?>
                    <tr class="animate-fade-in" style="animation-delay:<?= $i * 0.03 ?>s">
                        <td class="text-gray-400">
                            <?= $i + 1 ?>
                        </td>
                        <td>
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-9 h-9 bg-blue-900 rounded-full flex items-center justify-center text-yellow-400 font-bold text-sm flex-shrink-0">
                                    <?= mb_substr($user['full_name'], 0, 1, 'UTF-8') ?>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-800">
                                        <?= htmlspecialchars($user['full_name']) ?>
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        <?= htmlspecialchars($user['position'] ?? '') ?>
                                    </p>
                                </div>
                            </div>
                        </td>
                        <td class="font-mono text-sm">
                            <?= htmlspecialchars($user['username']) ?>
                        </td>
                        <td class="text-sm">
                            <?= htmlspecialchars($user['email'] ?? '-') ?>
                        </td>
                        <td class="text-sm">
                            <?= htmlspecialchars($user['department'] ?? '-') ?>
                        </td>
                        <td>
                            <?php
                            $roleBadge = [
                                'admin' => 'bg-red-100 text-red-800',
                                'supply_officer' => 'bg-yellow-100 text-yellow-800',
                                'department_head' => 'bg-purple-100 text-purple-800',
                                'director' => 'bg-indigo-100 text-indigo-800',
                                'user' => 'bg-blue-100 text-blue-800'
                            ];
                            ?>
                            <span class="badge <?= $roleBadge[$user['role']] ?? '' ?>">
                                <?= getRoleLabel($user['role']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?= $user['status'] ? 'badge-active' : 'badge-retired' ?>">
                                <?= $user['status'] ? 'ใช้งาน' : 'ปิด' ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="flex items-center justify-center gap-1">
                                <button onclick="editUser(<?= htmlspecialchars(json_encode($user)) ?>)"
                                    class="p-2 text-yellow-600 hover:bg-yellow-50 rounded-lg transition" title="แก้ไข">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button
                                    onclick="resetPassword(<?= $user['id'] ?>, '<?= htmlspecialchars($user['full_name']) ?>')"
                                    class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition" title="รีเซ็ตรหัสผ่าน">
                                    <i class="fas fa-key"></i>
                                </button>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <button onclick="toggleUserStatus(<?= $user['id'] ?>, <?= $user['status'] ?>)"
                                        class="p-2 text-<?= $user['status'] ? 'red' : 'green' ?>-600 hover:bg-gray-50 rounded-lg transition"
                                        title="<?= $user['status'] ? 'ปิดการใช้งาน' : 'เปิดการใช้งาน' ?>">
                                        <i class="fas fa-<?= $user['status'] ? 'ban' : 'check' ?>"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    const locations = <?= json_encode($locations) ?>;
    const positions = <?= json_encode($positions) ?>;
    const levels = <?= json_encode($levels) ?>;

    function renderSelect(id, items, selected, labelKey) {
        let html = `<select id="${id}" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none">`;
        html += '<option value="">-- เลือก --</option>';
        items.forEach(item => {
            const val = typeof item === 'object' ? item[labelKey] : item;
            const label = typeof item === 'object' ? item[labelKey] : item;
            const isSelected = selected === val ? 'selected' : '';
            html += `<option value="${val}" ${isSelected}>${label}</option>`;
        });
        html += '</select>';
        return html;
    }

    function showAddUserForm() {
        SwalThemed.fire({
            title: '<i class="fas fa-user-plus text-blue-900"></i> เพิ่มผู้ใช้ใหม่',
            html: `
            <div class="text-left space-y-3">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">ชื่อผู้ใช้ *</label>
                        <input id="nu_user" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none" placeholder="username">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">รหัสผ่าน *</label>
                        <input id="nu_pass" type="password" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none" placeholder="password">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">ชื่อ-สกุล *</label>
                    <input id="nu_name" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none" placeholder="ชื่อ-สกุล">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">อีเมล</label>
                        <input id="nu_email" type="email" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">โทรศัพท์</label>
                        <input id="nu_phone" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">ห้องปฏิบัติงาน</label>
                        ${renderSelect('nu_dept', locations, '', '')}
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">ตำแหน่ง</label>
                        ${renderSelect('nu_pos', positions, '', 'position')}
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">ระดับตำแหน่ง</label>
                        ${renderSelect('nu_lvl', levels, '', 'level_position')}
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">สิทธิ์</label>
                        <select id="nu_role" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none">
                            <option value="user">ผู้ใช้งานทั่วไป</option>
                            <option value="department_head">หัวหน้าส่วน</option>
                            <option value="supply_officer">เจ้าหน้าที่พัสดุ</option>
                            <option value="director">ผู้อำนวยการฯ</option>
                            <option value="admin">ผู้ดูแลระบบ</option>
                        </select>
                    </div>
                </div>
            </div>
        `,
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-save"></i> บันทึก',
            cancelButtonText: 'ยกเลิก',
            width: '600px',
            preConfirm: () => {
                const u = document.getElementById('nu_user').value.trim();
                const p = document.getElementById('nu_pass').value;
                const n = document.getElementById('nu_name').value.trim();
                if (!u || !p || !n) { Swal.showValidationMessage('กรุณากรอกข้อมูลที่จำเป็น'); return false; }
                return {
                    username: u, password: p, full_name: n,
                    email: document.getElementById('nu_email').value,
                    phone: document.getElementById('nu_phone').value,
                    department: document.getElementById('nu_dept').value,
                    position: document.getElementById('nu_pos').value,
                    position_level: document.getElementById('nu_lvl').value,
                    role: document.getElementById('nu_role').value
                };
            }
        }).then(r => {
            if (r.isConfirmed) {
                showLoading();
                fetch('api/users.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'create', ...r.value })
                }).then(r => r.json()).then(d => {
                    if (d.success) SwalThemed.fire({ icon: 'success', title: 'เพิ่มผู้ใช้สำเร็จ!', timer: 1500, showConfirmButton: false }).then(() => location.reload());
                    else SwalThemed.fire({ icon: 'error', title: 'ผิดพลาด', text: d.message });
                });
            }
        });
    }

    function editUser(user) {
        SwalThemed.fire({
            title: '<i class="fas fa-edit text-yellow-600"></i> แก้ไขผู้ใช้',
            html: `
            <div class="text-left space-y-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">ชื่อ-สกุล</label>
                    <input id="eu_name" value="${user.full_name}" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">อีเมล</label>
                        <input id="eu_email" value="${user.email || ''}" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">โทรศัพท์</label>
                        <input id="eu_phone" value="${user.phone || ''}" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">ห้องปฏิบัติงาน</label>
                        ${renderSelect('eu_dept', locations, user.department, '')}
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">ตำแหน่ง</label>
                        ${renderSelect('eu_pos', positions, user.position, 'position')}
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">ระดับตำแหน่ง</label>
                        ${renderSelect('eu_lvl', levels, user.position_level, 'level_position')}
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">สิทธิ์</label>
                        <select id="eu_role" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none">
                            <option value="user" ${user.role === 'user' ? 'selected' : ''}>ผู้ใช้งานทั่วไป</option>
                            <option value="department_head" ${user.role === 'department_head' ? 'selected' : ''}>หัวหน้าส่วน</option>
                            <option value="supply_officer" ${user.role === 'supply_officer' ? 'selected' : ''}>เจ้าหน้าที่พัสดุ</option>
                            <option value="director" ${user.role === 'director' ? 'selected' : ''}>ผู้อำนวยการฯ</option>
                            <option value="admin" ${user.role === 'admin' ? 'selected' : ''}>ผู้ดูแลระบบ</option>
                        </select>
                    </div>
                </div>
            </div>
        `,
            showCancelButton: true, confirmButtonText: '<i class="fas fa-save"></i> บันทึก', cancelButtonText: 'ยกเลิก', width: '600px',
            preConfirm: () => ({
                id: user.id,
                full_name: document.getElementById('eu_name').value,
                email: document.getElementById('eu_email').value,
                phone: document.getElementById('eu_phone').value,
                department: document.getElementById('eu_dept').value,
                position: document.getElementById('eu_pos').value,
                position_level: document.getElementById('eu_lvl').value,
                role: document.getElementById('eu_role').value
            })
        }).then(r => {
            if (r.isConfirmed) {
                showLoading();
                fetch('api/users.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'update', ...r.value })
                }).then(r => r.json()).then(d => {
                    if (d.success) SwalThemed.fire({ icon: 'success', title: 'บันทึกเรียบร้อย!', timer: 1500, showConfirmButton: false }).then(() => location.reload());
                    else SwalThemed.fire({ icon: 'error', title: 'ผิดพลาด', text: d.message });
                });
            }
        });
    }

    function toggleUserStatus(id, currentStatus) {
        const newStatus = currentStatus ? 0 : 1;
        SwalThemed.fire({
            icon: 'warning', title: currentStatus ? 'ปิดการใช้งาน?' : 'เปิดการใช้งาน?',
            text: currentStatus ? 'ผู้ใช้จะไม่สามารถเข้าสู่ระบบได้' : 'ผู้ใช้จะสามารถเข้าสู่ระบบได้อีกครั้ง',
            showCancelButton: true, confirmButtonText: 'ยืนยัน', cancelButtonText: 'ยกเลิก',
            confirmButtonColor: currentStatus ? '#dc2626' : '#16a34a'
        }).then(r => {
            if (r.isConfirmed) {
                showLoading();
                fetch('api/users.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'toggle_status', id, status: newStatus })
                }).then(r => r.json()).then(d => {
                    if (d.success) SwalThemed.fire({ icon: 'success', title: 'สำเร็จ!', timer: 1500, showConfirmButton: false }).then(() => location.reload());
                    else SwalThemed.fire({ icon: 'error', title: 'ผิดพลาด', text: d.message });
                });
            }
        });
    }

    function resetPassword(id, name) {
        SwalThemed.fire({
            title: '<i class="fas fa-key text-blue-900"></i> รีเซ็ตรหัสผ่าน',
            html: `ยืนยันรีเซ็ตรหัสผ่านของ <b>${name}</b><br><span class="text-sm text-gray-500">ระบบจะสุ่มรหัสผ่านใหม่ให้ทันที</span>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'ยืนยันรีเซ็ต',
            cancelButtonText: 'ยกเลิก',
            confirmButtonColor: '#1e3a8a'
        }).then((result) => {
            if (result.isConfirmed) {
                showLoading();
                fetch('api/users.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'reset_password', id: id })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            SwalThemed.fire({
                                title: 'รีเซ็ตสำเร็จ!',
                                html: `
                                <div class="text-center">
                                    <p class="text-sm text-gray-600 mb-2">รหัสผ่านใหม่คือ:</p>
                                    <div class="bg-gray-100 p-4 rounded-lg border-2 border-dashed border-yellow-500 font-mono text-xl font-bold tracking-wider relative group mx-auto max-w-xs">
                                        <span id="new-pwd" class="text-blue-900 select-all">${data.new_password}</span>
                                        <button onclick="copyToClipboard('${data.new_password}', this)" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-blue-600 transition p-2" title="คัดลอก">
                                            <i class="far fa-copy"></i>
                                        </button>
                                    </div>
                                    <p class="text-xs text-red-500 mt-3"><i class="fas fa-exclamation-triangle"></i> กรุณาบันทึกรหัสผ่านนี้ไว้ก่อนปิดหน้าต่าง</p>
                                </div>
                            `,
                                icon: 'success',
                                confirmButtonText: 'ปิด'
                            });
                        } else {
                            SwalThemed.fire('ผิดพลาด', data.message || 'ไม่สามารถรีเซ็ตได้', 'error');
                        }
                    });
            }
        });
    }

    function copyToClipboard(text, btn) {
        navigator.clipboard.writeText(text).then(() => {
            const icon = btn.querySelector('i');
            if (icon) {
                icon.className = 'fas fa-check text-green-500';
                setTimeout(() => icon.className = 'far fa-copy', 2000);
            }
            const Toast = Swal.mixin({
                toast: true, position: 'top-end', showConfirmButton: false, timer: 1500, timerProgressBar: true
            });
            Toast.fire({ icon: 'success', title: 'คัดลอกแล้ว' });
        });
    }
</script>

<?php include 'includes/footer.php'; ?>
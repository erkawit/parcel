<?php
/**
 * Dashboard - แดชบอร์ด
 */
require_once 'includes/auth.php';
requireLogin();

$pageTitle = 'แดชบอร์ด';
$currentUser = getCurrentUser();

// Get statistics
$stats = [];

// Total assets
$stmt = $pdo->query("SELECT COUNT(*) FROM assets");
$stats['total_assets'] = $stmt->fetchColumn();

// Active assets
$stmt = $pdo->query("SELECT COUNT(*) FROM assets WHERE status = 'active'");
$stats['active_assets'] = $stmt->fetchColumn();

// Damaged assets
$stmt = $pdo->query("SELECT COUNT(*) FROM assets WHERE status = 'damaged'");
$stats['damaged_assets'] = $stmt->fetchColumn();

// Pending transfers
$stmt = $pdo->query("SELECT COUNT(*) FROM transfer_requests WHERE status = 'pending'");
$stats['pending_transfers'] = $stmt->fetchColumn();

// My assets (for user role)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM assets WHERE holder_id = ?");
$stmt->execute([$currentUser['id']]);
$stats['my_assets'] = $stmt->fetchColumn();

// Total users
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 1");
$stats['total_users'] = $stmt->fetchColumn();

// Asset value
$stmt = $pdo->query("SELECT COALESCE(SUM(purchase_price), 0) FROM assets");
$stats['total_value'] = $stmt->fetchColumn();

// Active inspections
$stmt = $pdo->query("SELECT COUNT(*) FROM inspection_projects WHERE status = 'in_progress'");
$stats['active_inspections'] = $stmt->fetchColumn();

// Recent activities
$stmt = $pdo->query("SELECT al.*, u.full_name FROM activity_logs al LEFT JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT 10");
$activities = $stmt->fetchAll();

// Assets by category
$stmt = $pdo->query("SELECT c.name, COUNT(a.id) as count FROM asset_categories c LEFT JOIN assets a ON c.id = a.category_id GROUP BY c.id, c.name ORDER BY count DESC");
$assetsByCategory = $stmt->fetchAll();

// Assets by status
$stmt = $pdo->query("SELECT status, COUNT(*) as count FROM assets GROUP BY status");
$assetsByStatus = $stmt->fetchAll();

// Recent transfers
$stmt = $pdo->query("SELECT tr.*, a.name as asset_name, a.asset_code, 
                     uf.full_name as from_name, ut.full_name as to_name
                     FROM transfer_requests tr 
                     JOIN assets a ON tr.asset_id = a.id
                     JOIN users uf ON tr.from_user_id = uf.id
                     LEFT JOIN users ut ON tr.to_user_id = ut.id
                     ORDER BY tr.created_at DESC LIMIT 5");
$recentTransfers = $stmt->fetchAll();

include 'includes/header.php';
include 'includes/sidebar.php';

$statusLabels = ['active' => 'ใช้งาน', 'damaged' => 'ชำรุด', 'lost' => 'สูญหาย', 'retired' => 'จำหน่ายแล้ว', 'transferred' => 'โอนย้าย'];
$statusColors = ['active' => '#22c55e', 'damaged' => '#f97316', 'lost' => '#ef4444', 'retired' => '#6b7280', 'transferred' => '#3b82f6'];
?>

<!-- Dashboard Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

    <!-- Total Assets -->
    <div class="card-hover bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden animate-fade-in">
        <div class="flex items-center p-5">
            <div class="flex-shrink-0 w-14 h-14 bg-blue-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-boxes-stacked text-blue-900 text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-500 font-medium">ครุภัณฑ์ทั้งหมด</p>
                <p class="text-2xl font-extrabold text-gray-800">
                    <?= number_format($stats['total_assets']) ?>
                </p>
            </div>
        </div>
        <div class="h-1 bg-gradient-to-r from-blue-900 to-blue-600"></div>
    </div>

    <!-- Active Assets -->
    <div class="card-hover bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden animate-fade-in"
        style="animation-delay:0.1s">
        <div class="flex items-center p-5">
            <div class="flex-shrink-0 w-14 h-14 bg-green-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-check-circle text-green-600 text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-500 font-medium">ใช้งานปกติ</p>
                <p class="text-2xl font-extrabold text-gray-800">
                    <?= number_format($stats['active_assets']) ?>
                </p>
            </div>
        </div>
        <div class="h-1 bg-gradient-to-r from-green-500 to-green-400"></div>
    </div>

    <!-- Pending Transfers -->
    <div class="card-hover bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden animate-fade-in"
        style="animation-delay:0.2s">
        <div class="flex items-center p-5">
            <div class="flex-shrink-0 w-14 h-14 bg-yellow-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-arrow-right-arrow-left text-yellow-600 text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-500 font-medium">รอโอนย้าย</p>
                <p class="text-2xl font-extrabold text-gray-800">
                    <?= number_format($stats['pending_transfers']) ?>
                </p>
            </div>
        </div>
        <div class="h-1 bg-gradient-to-r from-yellow-500 to-yellow-400"></div>
    </div>

    <!-- Total Value -->
    <div class="card-hover bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden animate-fade-in"
        style="animation-delay:0.3s">
        <div class="flex items-center p-5">
            <div class="flex-shrink-0 w-14 h-14 bg-purple-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-coins text-purple-600 text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-500 font-medium">มูลค่ารวม</p>
                <p class="text-lg font-extrabold text-gray-800">
                    <?= number_format($stats['total_value'], 0) ?> ฿
                </p>
            </div>
        </div>
        <div class="h-1 bg-gradient-to-r from-purple-500 to-purple-400"></div>
    </div>
</div>

<!-- Charts & Info Row -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">

    <!-- Asset Status Chart -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 animate-fade-in" style="animation-delay:0.4s">
        <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fas fa-chart-pie text-blue-900"></i> สถานะครุภัณฑ์
        </h3>
        <div class="space-y-3">
            <?php
            $total = max(array_sum(array_column($assetsByStatus, 'count')), 1);
            foreach ($assetsByStatus as $s):
                $pct = round(($s['count'] / $total) * 100);
                $color = $statusColors[$s['status']] ?? '#6b7280';
                $label = $statusLabels[$s['status']] ?? $s['status'];
                ?>
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-600 font-medium">
                            <?= $label ?>
                        </span>
                        <span class="font-bold text-gray-800">
                            <?= $s['count'] ?> (
                            <?= $pct ?>%)
                        </span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-3 overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-1000"
                            style="width:<?= $pct ?>%; background:<?= $color ?>"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Assets by Category -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 animate-fade-in" style="animation-delay:0.5s">
        <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fas fa-tags text-blue-900"></i> ครุภัณฑ์ตามหมวดหมู่
        </h3>
        <div class="space-y-3">
            <?php
            $catColors = ['#1e3a8a', '#ca8a04', '#059669', '#dc2626', '#7c3aed', '#6b7280'];
            foreach ($assetsByCategory as $i => $cat):
                $catPct = $total > 0 ? round(($cat['count'] / $total) * 100) : 0;
                ?>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 rounded-full" style="background:<?= $catColors[$i % count($catColors)] ?>">
                        </div>
                        <span class="text-sm text-gray-600">
                            <?= htmlspecialchars($cat['name']) ?>
                        </span>
                    </div>
                    <span class="text-sm font-bold text-gray-800 bg-gray-100 px-2 py-0.5 rounded-full">
                        <?= $cat['count'] ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Quick Actions / Info -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 animate-fade-in" style="animation-delay:0.6s">
        <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fas fa-bolt text-yellow-600"></i> การดำเนินการด่วน
        </h3>
        <div class="space-y-3">
            <?php if (hasRole(['admin', 'supply_officer'])): ?>
                <a href="assets.php?action=add"
                    class="btn-press flex items-center gap-3 p-3 bg-blue-50 hover:bg-blue-100 rounded-xl transition-all group">
                    <div
                        class="w-10 h-10 bg-blue-900 rounded-lg flex items-center justify-center group-hover:scale-110 transition-transform">
                        <i class="fas fa-plus text-yellow-400"></i>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-800">เพิ่มครุภัณฑ์ใหม่</p>
                        <p class="text-xs text-gray-500">ลงทะเบียนครุภัณฑ์เข้าสู่ระบบ</p>
                    </div>
                </a>
                <a href="committee.php"
                    class="btn-press flex items-center gap-3 p-3 bg-yellow-50 hover:bg-yellow-100 rounded-xl transition-all group">
                    <div
                        class="w-10 h-10 bg-yellow-600 rounded-lg flex items-center justify-center group-hover:scale-110 transition-transform">
                        <i class="fas fa-users text-white"></i>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-800">แต่งตั้งคณะกรรมการ</p>
                        <p class="text-xs text-gray-500">สร้างคำสั่งแต่งตั้งคณะกรรมการ</p>
                    </div>
                </a>
            <?php endif; ?>
            <a href="transfer.php"
                class="btn-press flex items-center gap-3 p-3 bg-green-50 hover:bg-green-100 rounded-xl transition-all group">
                <div
                    class="w-10 h-10 bg-green-600 rounded-lg flex items-center justify-center group-hover:scale-110 transition-transform">
                    <i class="fas fa-arrow-right-arrow-left text-white"></i>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-800">โอนย้ายครุภัณฑ์</p>
                    <p class="text-xs text-gray-500">ส่งคำขอโอนย้ายหรือคืนครุภัณฑ์</p>
                </div>
            </a>
            <a href="inspection.php"
                class="btn-press flex items-center gap-3 p-3 bg-purple-50 hover:bg-purple-100 rounded-xl transition-all group">
                <div
                    class="w-10 h-10 bg-purple-600 rounded-lg flex items-center justify-center group-hover:scale-110 transition-transform">
                    <i class="fas fa-clipboard-check text-white"></i>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-800">ตรวจสอบครุภัณฑ์</p>
                    <p class="text-xs text-gray-500">ดำเนินการตรวจสอบประจำปี</p>
                </div>
            </a>
        </div>
    </div>
</div>

<!-- Recent Transfers & Activity -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

    <!-- Recent Transfers -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden animate-fade-in"
        style="animation-delay:0.7s">
        <div class="bg-blue-900 text-white px-5 py-3 flex items-center justify-between">
            <h3 class="font-bold text-sm flex items-center gap-2">
                <i class="fas fa-arrow-right-arrow-left text-yellow-400"></i> การโอนย้ายล่าสุด
            </h3>
            <a href="transfer.php" class="text-yellow-400 text-xs hover:underline">ดูทั้งหมด →</a>
        </div>
        <div class="divide-y divide-gray-50">
            <?php if (empty($recentTransfers)): ?>
                <div class="p-8 text-center text-gray-400">
                    <i class="fas fa-inbox text-3xl mb-2"></i>
                    <p class="text-sm">ยังไม่มีรายการโอนย้าย</p>
                </div>
            <?php else: ?>
                <?php foreach ($recentTransfers as $tr): ?>
                    <div class="px-5 py-3 hover:bg-blue-50/30 transition-colors">
                        <div class="flex items-center justify-between">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-800 truncate">
                                    <?= htmlspecialchars($tr['asset_name']) ?>
                                </p>
                                <p class="text-xs text-gray-500">
                                    <?= htmlspecialchars($tr['from_name']) ?> →
                                    <?= htmlspecialchars($tr['to_name'] ?? 'เจ้าหน้าที่พัสดุ') ?>
                                </p>
                            </div>
                            <?php
                            $trStatusMap = ['pending' => 'badge-pending', 'approved' => 'badge-approved', 'rejected' => 'badge-rejected', 'completed' => 'badge-completed'];
                            $trLabelMap = ['pending' => 'รอดำเนินการ', 'approved' => 'อนุมัติ', 'rejected' => 'ไม่อนุมัติ', 'completed' => 'เสร็จสิ้น'];
                            ?>
                            <span class="badge <?= $trStatusMap[$tr['status']] ?? '' ?>">
                                <?= $trLabelMap[$tr['status']] ?? $tr['status'] ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Activity Log -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden animate-fade-in"
        style="animation-delay:0.8s">
        <div class="bg-blue-900 text-white px-5 py-3">
            <h3 class="font-bold text-sm flex items-center gap-2">
                <i class="fas fa-clock-rotate-left text-yellow-400"></i> กิจกรรมล่าสุด
            </h3>
        </div>
        <div class="divide-y divide-gray-50 max-h-80 overflow-y-auto">
            <?php if (empty($activities)): ?>
                <div class="p-8 text-center text-gray-400">
                    <i class="fas fa-history text-3xl mb-2"></i>
                    <p class="text-sm">ยังไม่มีกิจกรรม</p>
                </div>
            <?php else: ?>
                <?php foreach ($activities as $act): ?>
                    <div class="px-5 py-3 hover:bg-blue-50/30 transition-colors">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
                                <i
                                    class="fas fa-<?= $act['action'] === 'login' ? 'sign-in-alt' : ($act['action'] === 'logout' ? 'sign-out-alt' : 'cog') ?> text-blue-900 text-xs"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-gray-800">
                                    <strong>
                                        <?= htmlspecialchars($act['full_name'] ?? 'ระบบ') ?>
                                    </strong>
                                    <span class="text-gray-500"> —
                                        <?= htmlspecialchars($act['description']) ?>
                                    </span>
                                </p>
                                <p class="text-xs text-gray-400">
                                    <?= formatThaiDate($act['created_at']) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
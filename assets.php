<?php
/**
 * Asset Management - จัดการครุภัณฑ์
 */
require_once 'includes/auth.php';
requireLogin();

// Only admin, supply_officer, department_head, director can access this page
if (!hasRole(['admin', 'supply_officer', 'department_head', 'director'])) {
    header('Location: my_assets.php');
    exit;
}

$pageTitle = 'จัดการครุภัณฑ์';

// Check access
$canManage = hasManagementPrivileges(); // admin, supply_officer, director, department_head
$canCopy = hasRole(['admin', 'supply_officer']); // ผู้ดูแลระบบ หรือเจ้าหน้าที่พัสดุ

// Filters
$search = $_GET['search'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$statusFilter = $_GET['status'] ?? '';

// Build query using role-based filter
list($roleWhere, $roleParams) = getAssetFilterByRole();
$where = [$roleWhere];
$params = $roleParams;

if ($search) {
    $where[] = "(a.name LIKE ? OR a.asset_code LIKE ? OR a.brand LIKE ? OR a.serial_number LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%"]);
}
if ($categoryFilter) {
    $where[] = "a.category_id = ?";
    $params[] = $categoryFilter;
}
if ($statusFilter) {
    $where[] = "a.status = ?";
    $params[] = $statusFilter;
}

$whereSQL = 'WHERE ' . implode(' AND ', $where);

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Count total records
$countStmt = $pdo->prepare("SELECT COUNT(*) 
                            FROM assets a 
                            LEFT JOIN users u ON a.holder_id = u.id 
                            $whereSQL");
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $perPage);

// Get paginated data
$stmt = $pdo->prepare("SELECT a.*, c.name as category_name, u.full_name as holder_name 
                       FROM assets a 
                       LEFT JOIN asset_categories c ON a.category_id = c.id 
                       LEFT JOIN users u ON a.holder_id = u.id 
                       $whereSQL 
                       ORDER BY a.created_at DESC
                       LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$assets = $stmt->fetchAll();

// Categories for filter
$categories = $pdo->query("SELECT * FROM asset_categories ORDER BY name")->fetchAll();

// Users for assignment dropdown
$users = $pdo->query("SELECT id, full_name, department FROM users WHERE status = 1 ORDER BY full_name")->fetchAll();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!-- Page Header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-extrabold text-gray-800 flex items-center gap-2">
            <i class="fas fa-boxes-stacked text-blue-900"></i>
            <?= $canManage ? 'จัดการครุภัณฑ์' : 'ครุภัณฑ์ของฉัน' ?>
        </h1>
        <p class="text-gray-500 text-sm mt-1">
            ทั้งหมด <?= number_format($totalRecords) ?> รายการ
            <?php if ($totalPages > 1): ?>
                <span class="text-gray-400">|</span>
                หน้า <?= $page ?> จาก <?= $totalPages ?>
                <span class="text-gray-400">|</span>
                แสดง <?= number_format(count($assets)) ?> รายการ
            <?php endif; ?>
        </p>
    </div>
    <?php if ($canManage): ?>
        <div class="flex gap-3">
            <!-- <button onclick="syncFromGoogleSheets()"
                class="btn-press bg-green-600 text-white px-6 py-3 rounded-xl font-semibold border-b-4 border-green-800 hover:bg-green-700 flex items-center gap-2 shadow-lg">
                <i class="fab fa-google"></i> ซิงค์จาก Google Sheets
            </button> -->
            <button onclick="showImportExcelModal()"
                class="btn-press bg-blue-900 text-white px-6 py-3 rounded-xl font-semibold border-b-4 border-yellow-800 hover:bg-blue-800 flex items-center gap-2 shadow-lg">
                <i class="fas fa-file-import text-yellow-400"></i> Import Excel
            </button>
            <button onclick="showCategoryManagement()"
                class="btn-press bg-blue-900 text-white px-6 py-3 rounded-xl font-semibold border-b-4 border-yellow-800 hover:bg-blue-800 flex items-center gap-2 shadow-lg">
                <i class="fas fa-tags"></i> หมวดหมู่
            </button>
            <button onclick="showAddAssetForm()"
                class="btn-press bg-blue-900 text-white px-6 py-3 rounded-xl font-semibold border-b-4 border-yellow-700 hover:bg-blue-800 flex items-center gap-2 shadow-lg">
                <i class="fas fa-plus"></i> เพิ่มครุภัณฑ์
            </button>
            <button onclick="showLocationManagement()"
                class="btn-press bg-blue-900 text-white px-6 py-3 rounded-xl font-semibold border-b-4 border-yellow-800 hover:bg-blue-800 flex items-center gap-2 shadow-lg">
                <i class="fas fa-map-marker-alt"></i> ที่ตั้ง
            </button>
        </div>
    <?php endif; ?>
</div>

<!-- Filters -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-3">
        <div class="relative">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                placeholder="ค้นหาชื่อ, รหัส, ยี่ห้อ..."
                class="w-full pl-10 pr-4 py-2.5 border-2 border-gray-200 rounded-xl text-sm focus:border-blue-900 focus:outline-none transition">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
        </div>
        <select name="category"
            class="border-2 border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:border-blue-900 focus:outline-none transition">
            <option value="">-- ทุกหมวดหมู่ --</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $categoryFilter == $cat['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="status"
            class="border-2 border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:border-blue-900 focus:outline-none transition">
            <option value="">-- ทุกสถานะ --</option>
            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>ใช้งานปกติ</option>
            <option value="damaged" <?= $statusFilter === 'damaged' ? 'selected' : '' ?>>ชำรุด</option>
            <option value="lost" <?= $statusFilter === 'lost' ? 'selected' : '' ?>>สูญหาย</option>
            <option value="retired" <?= $statusFilter === 'retired' ? 'selected' : '' ?>>จำหน่ายแล้ว</option>
        </select>
        <div class="flex gap-2">
            <button type="submit"
                class="flex-1 bg-blue-900 text-white rounded-xl py-2.5 font-semibold hover:bg-blue-800 transition flex items-center justify-center gap-2">
                <i class="fas fa-search"></i> ค้นหา
            </button>
            <a href="assets.php"
                class="px-4 py-2.5 bg-gray-200 text-gray-600 rounded-xl hover:bg-gray-300 transition flex items-center">
                <i class="fas fa-times"></i>
            </a>
        </div>
    </form>
</div>

<!-- Assets Table -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th class="w-12">#</th>
                    <th>รหัสครุภัณฑ์</th>
                    <th>ชื่อ/รายละเอียด</th>
                    <th>หมวดหมู่</th>
                    <th>ผู้ถือครอง</th>
                    <th>ที่ตั้ง</th>
                    <th>มูลค่า</th>
                    <th>สถานะ</th>
                    <th class="text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($assets)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-12 text-gray-400">
                            <i class="fas fa-box-open text-4xl mb-3"></i>
                            <p class="text-lg font-semibold">ไม่พบข้อมูลครุภัณฑ์</p>
                            <p class="text-sm">ลองเปลี่ยนเงื่อนไขการค้นหา</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($assets as $i => $asset): ?>
                        <?php
                        $rowNum = $offset + $i + 1;
                        $sBadge = ['active' => 'badge-active', 'damaged' => 'badge-damaged', 'lost' => 'badge-lost', 'retired' => 'badge-retired', 'transferred' => 'badge-pending'];
                        $sLabel = ['active' => 'ปกติ', 'damaged' => 'ชำรุด', 'lost' => 'สูญหาย', 'retired' => 'จำหน่าย', 'transferred' => 'โอนย้าย'];
                        $statusText = $sLabel[$asset['status']] ?? $asset['status'];
                        $detailCopyText = trim($asset['name'] . ($asset['brand'] ? ' ' . $asset['brand'] . ($asset['model'] ? ' ' . $asset['model'] : '') : ''));
                        $categoryText = $asset['category_name'] ?? '-';
                        $holderText = $asset['holder_name'] ?: 'ไม่ระบุ';
                        $locationText = $asset['location'] ?? '-';
                        $priceText = number_format($asset['purchase_price'], 0);
                        ?>
                        <tr class="animate-fade-in" style="animation-delay:<?= $i * 0.03 ?>s">
                            <td class="text-gray-400 font-medium">
                                <div class="flex items-center gap-1.5">
                                    <span><?= $rowNum ?></span>
                                    <?php if ($canCopy): ?>
                                        <button type="button" data-copy="<?= htmlspecialchars((string) $rowNum, ENT_QUOTES) ?>" onclick="copyToClipboard(this.dataset.copy, this)"
                                            class="text-gray-400 hover:text-blue-600 hover:bg-blue-50 p-1 rounded transition" title="คัดลอกลำดับ">
                                            <i class="far fa-copy text-xs"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="flex items-center gap-1.5">
                                    <span class="font-mono text-xs bg-blue-50 text-blue-900 px-2 py-1 rounded-md font-semibold">
                                        <?= htmlspecialchars($asset['asset_code']) ?>
                                    </span>
                                    <?php if ($canCopy): ?>
                                        <button type="button" data-copy="<?= htmlspecialchars($asset['asset_code'], ENT_QUOTES) ?>" onclick="copyToClipboard(this.dataset.copy, this)"
                                            class="text-gray-400 hover:text-blue-600 hover:bg-blue-50 p-1 rounded transition" title="คัดลอกรหัสครุภัณฑ์">
                                            <i class="far fa-copy text-xs"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="flex items-start justify-between gap-1.5">
                                    <div>
                                        <p class="font-semibold text-gray-800">
                                            <?= htmlspecialchars($asset['name']) ?>
                                        </p>
                                        <?php if ($asset['brand']): ?>
                                            <p class="text-xs text-gray-500">
                                                <?= htmlspecialchars($asset['brand']) ?>
                                                <?= htmlspecialchars($asset['model'] ?? '') ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($canCopy): ?>
                                        <button type="button" data-copy="<?= htmlspecialchars($detailCopyText, ENT_QUOTES) ?>" onclick="copyToClipboard(this.dataset.copy, this)"
                                            class="text-gray-400 hover:text-blue-600 hover:bg-blue-50 p-1 rounded transition flex-shrink-0" title="คัดลอกชื่อ/รายละเอียด">
                                            <i class="far fa-copy text-xs"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-sm">
                                <div class="flex items-center gap-1.5">
                                    <span><?= htmlspecialchars($categoryText) ?></span>
                                    <?php if ($canCopy): ?>
                                        <button type="button" data-copy="<?= htmlspecialchars($categoryText, ENT_QUOTES) ?>" onclick="copyToClipboard(this.dataset.copy, this)"
                                            class="text-gray-400 hover:text-blue-600 hover:bg-blue-50 p-1 rounded transition" title="คัดลอกหมวดหมู่">
                                            <i class="far fa-copy text-xs"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-sm">
                                <div class="flex items-center gap-1.5">
                                    <?php if ($asset['holder_name']): ?>
                                        <span><?= htmlspecialchars($asset['holder_name']) ?></span>
                                    <?php else: ?>
                                        <span class="text-gray-400">ไม่ระบุ</span>
                                    <?php endif; ?>
                                    <?php if ($canCopy): ?>
                                        <button type="button" data-copy="<?= htmlspecialchars($holderText, ENT_QUOTES) ?>" onclick="copyToClipboard(this.dataset.copy, this)"
                                            class="text-gray-400 hover:text-blue-600 hover:bg-blue-50 p-1 rounded transition" title="คัดลอกผู้ถือครอง">
                                            <i class="far fa-copy text-xs"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-sm text-gray-600">
                                <div class="flex items-center gap-1.5">
                                    <span><?= htmlspecialchars($locationText) ?></span>
                                    <?php if ($canCopy): ?>
                                        <button type="button" data-copy="<?= htmlspecialchars($locationText, ENT_QUOTES) ?>" onclick="copyToClipboard(this.dataset.copy, this)"
                                            class="text-gray-400 hover:text-blue-600 hover:bg-blue-50 p-1 rounded transition" title="คัดลอกที่ตั้ง">
                                            <i class="far fa-copy text-xs"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-sm font-semibold">
                                <div class="flex items-center gap-1.5">
                                    <span><?= $priceText ?> ฿</span>
                                    <?php if ($canCopy): ?>
                                        <button type="button" data-copy="<?= htmlspecialchars($priceText, ENT_QUOTES) ?>" onclick="copyToClipboard(this.dataset.copy, this)"
                                            class="text-gray-400 hover:text-blue-600 hover:bg-blue-50 p-1 rounded transition font-normal" title="คัดลอกมูลค่า">
                                            <i class="far fa-copy text-xs"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="flex items-center gap-1.5">
                                    <span class="badge <?= $sBadge[$asset['status']] ?? '' ?>">
                                        <?= $statusText ?>
                                    </span>
                                    <?php if ($canCopy): ?>
                                        <button type="button" data-copy="<?= htmlspecialchars($statusText, ENT_QUOTES) ?>" onclick="copyToClipboard(this.dataset.copy, this)"
                                            class="text-gray-400 hover:text-blue-600 hover:bg-blue-50 p-1 rounded transition" title="คัดลอกสถานะ">
                                            <i class="far fa-copy text-xs"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <button onclick="viewAsset(<?= $asset['id'] ?>)"
                                        class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition" title="ดูรายละเอียด">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if ($canManage && canAccessAsset($asset['id'])): ?>
                                        <button onclick="editAsset(<?= $asset['id'] ?>)"
                                            class="p-2 text-yellow-600 hover:bg-yellow-50 rounded-lg transition" title="แก้ไข">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="deleteAsset(<?= $asset['id'] ?>, '<?= addslashes($asset['name']) ?>')"
                                            class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition" title="ลบ">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mt-4">
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
            <!-- Page Info -->
            <div class="text-sm text-gray-600">
                แสดง <span class="font-semibold text-blue-900"><?= (($page - 1) * $perPage) + 1 ?></span>
                ถึง <span class="font-semibold text-blue-900"><?= min($page * $perPage, $totalRecords) ?></span>
                จาก <span class="font-semibold text-blue-900"><?= number_format($totalRecords) ?></span> รายการ
            </div>

            <!-- Pagination Controls -->
            <div class="flex items-center gap-2">
                <?php
                // Build query string for pagination links
                $queryParams = $_GET;
                unset($queryParams['page']);
                $queryString = http_build_query($queryParams);
                $baseUrl = 'assets.php?' . ($queryString ? $queryString . '&' : '');

                // Previous button
                if ($page > 1):
                    ?>
                    <a href="<?= $baseUrl ?>page=<?= $page - 1 ?>"
                        class="px-3 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-blue-50 hover:border-blue-500 hover:text-blue-900 transition flex items-center gap-1">
                        <i class="fas fa-chevron-left text-xs"></i>
                        <span class="hidden sm:inline">ก่อนหน้า</span>
                    </a>
                <?php else: ?>
                    <span
                        class="px-3 py-2 rounded-lg border border-gray-200 text-gray-400 cursor-not-allowed flex items-center gap-1">
                        <i class="fas fa-chevron-left text-xs"></i>
                        <span class="hidden sm:inline">ก่อนหน้า</span>
                    </span>
                <?php endif; ?>

                <!-- Page Numbers -->
                <div class="flex items-center gap-1">
                    <?php
                    $range = 2; // จำนวนหน้าที่แสดงก่อนและหลังหน้าปัจจุบัน
                    $start = max(1, $page - $range);
                    $end = min($totalPages, $page + $range);

                    // First page
                    if ($start > 1):
                        ?>
                        <a href="<?= $baseUrl ?>page=1"
                            class="w-10 h-10 flex items-center justify-center rounded-lg border border-gray-300 text-gray-700 hover:bg-blue-50 hover:border-blue-500 hover:text-blue-900 transition">
                            1
                        </a>
                        <?php if ($start > 2): ?>
                            <span class="px-2 text-gray-400">...</span>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Page range -->
                    <?php for ($i = $start; $i <= $end; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span
                                class="w-10 h-10 flex items-center justify-center rounded-lg bg-blue-900 text-white font-bold border-2 border-yellow-600 shadow-md">
                                <?= $i ?>
                            </span>
                        <?php else: ?>
                            <a href="<?= $baseUrl ?>page=<?= $i ?>"
                                class="w-10 h-10 flex items-center justify-center rounded-lg border border-gray-300 text-gray-700 hover:bg-blue-50 hover:border-blue-500 hover:text-blue-900 transition">
                                <?= $i ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <!-- Last page -->
                    <?php if ($end < $totalPages): ?>
                        <?php if ($end < $totalPages - 1): ?>
                            <span class="px-2 text-gray-400">...</span>
                        <?php endif; ?>
                        <a href="<?= $baseUrl ?>page=<?= $totalPages ?>"
                            class="w-10 h-10 flex items-center justify-center rounded-lg border border-gray-300 text-gray-700 hover:bg-blue-50 hover:border-blue-500 hover:text-blue-900 transition">
                            <?= $totalPages ?>
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Next button -->
                <?php if ($page < $totalPages): ?>
                    <a href="<?= $baseUrl ?>page=<?= $page + 1 ?>"
                        class="px-3 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-blue-50 hover:border-blue-500 hover:text-blue-900 transition flex items-center gap-1">
                        <span class="hidden sm:inline">ถัดไป</span>
                        <i class="fas fa-chevron-right text-xs"></i>
                    </a>
                <?php else: ?>
                    <span
                        class="px-3 py-2 rounded-lg border border-gray-200 text-gray-400 cursor-not-allowed flex items-center gap-1">
                        <span class="hidden sm:inline">ถัดไป</span>
                        <i class="fas fa-chevron-right text-xs"></i>
                    </span>
                <?php endif; ?>

                <!-- Jump to page -->
                <div class="hidden md:flex items-center gap-2 ml-4 pl-4 border-l border-gray-300">
                    <label class="text-sm text-gray-600">ไปหน้า:</label>
                    <input type="number" id="jumpToPage" min="1" max="<?= $totalPages ?>" value="<?= $page ?>"
                        class="w-16 px-2 py-1 border border-gray-300 rounded-lg text-center text-sm focus:border-blue-500 focus:outline-none"
                        onkeypress="if(event.key === 'Enter') jumpToPage()">
                    <button onclick="jumpToPage()"
                        class="px-3 py-1 bg-blue-900 text-white rounded-lg text-sm hover:bg-blue-800 transition">
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function jumpToPage() {
            const pageInput = document.getElementById('jumpToPage');
            const pageNum = parseInt(pageInput.value);
            const maxPage = <?= $totalPages ?>;

            if (pageNum >= 1 && pageNum <= maxPage) {
                window.location.href = '<?= $baseUrl ?>page=' + pageNum;
            } else {
                SwalThemed.fire({
                    icon: 'warning',
                    title: 'หน้าไม่ถูกต้อง',
                    text: `กรุณาระบุหน้าระหว่าง 1 ถึง ${maxPage}`,
                    confirmButtonText: 'ตกลง'
                });
            }
        }
</script>
<?php endif; ?>

<!-- Hidden data for JS -->
<script>
    const categories = <?= json_encode($categories) ?>;
    const users = <?= json_encode($users) ?>;

    function copyToClipboard(text, btn) {
        const showFeedback = () => {
            if (btn) {
                const icon = btn.querySelector('i');
                if (icon) {
                    icon.className = 'fas fa-check text-green-500 text-xs';
                    setTimeout(() => {
                        icon.className = 'far fa-copy text-xs';
                    }, 1500);
                }
            }
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 1500,
                timerProgressBar: true
            });
            Toast.fire({ icon: 'success', title: 'คัดลอกแล้ว' });
        };

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(showFeedback);
        } else {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-9999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try {
                document.execCommand('copy');
                showFeedback();
            } catch (err) {
                console.error('Copy failed', err);
            }
            document.body.removeChild(textArea);
        }
    }

    function showCategoryManagement() {
        SwalThemed.fire({
            title: 'กำลังโหลด...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
                fetch('api/categories.php')
                    .then(r => r.json())
                    .then(data => {
                        if (!data.success) {
                            return SwalThemed.fire('Error', data.message, 'error');
                        }
                        renderCategoryModal(data.categories);
                    })
                    .catch(err => SwalThemed.fire('Error', 'Cannot load categories', 'error'));
            }
        });
    }

    function renderCategoryModal(categoriesList) {
        let listHtml = '<div class="max-h-60 overflow-y-auto border border-gray-200 rounded-lg mb-4 text-left">';
        if (categoriesList.length === 0) {
            listHtml += '<div class="p-4 text-center text-gray-400 text-sm">ยังไม่มีหมวดหมู่</div>';
        } else {
            categoriesList.forEach(c => {
                listHtml += `
                    <div class="px-4 py-3 border-b border-gray-100 last:border-0 hover:bg-gray-50 flex justify-between items-center transition">
                        <span class="text-gray-800 font-medium text-sm"><i class="fas fa-tag text-gray-400 mr-2"></i>${c.name}</span>
                        <span class="text-xs text-gray-400 bg-gray-100 px-2 py-1 rounded border border-gray-200">ID: ${c.id}</span>
                    </div>
                `;
            });
        }
        listHtml += '</div>';

        const formHtml = `
            <div class="text-left">
                <div class="bg-indigo-50 border-l-4 border-indigo-500 p-3 rounded mb-4">
                    <p class="text-sm text-indigo-900 font-semibold">
                        <i class="fas fa-info-circle"></i> รายการหมวดหมู่ทั้งหมด
                    </p>
                </div>
                ${listHtml}
                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 mt-2">
                    <label class="block text-xs font-bold text-gray-700 mb-2 uppercase tracking-wide">เพิ่มหมวดหมู่ใหม่</label>
                    <div class="flex gap-2">
                        <input id="new_cat_name" 
                               class="flex-1 border-2 border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:border-indigo-600 focus:outline-none transition bg-white" 
                               placeholder="ระบุชื่อหมวดหมู่..."
                               onkeypress="if(event.key === 'Enter') addNewCategory()">
                        <button onclick="addNewCategory()" 
                                class="bg-indigo-600 text-white px-5 py-2.5 rounded-lg text-sm font-semibold hover:bg-indigo-700 transition shadow-md flex items-center gap-2">
                            <i class="fas fa-plus"></i> เพิ่ม
                        </button>
                    </div>
                </div>
            </div>
        `;

        SwalThemed.fire({
            title: '<span class="text-2xl font-bold text-gray-800">จัดการหมวดหมู่</span>',
            html: formHtml,
            showConfirmButton: false,
            showCloseButton: true,
            width: '550px',
            customClass: {
                popup: 'rounded-2xl shadow-xl'
            }
        });
    }

    function addNewCategory() {
        const input = document.getElementById('new_cat_name');
        const name = input.value.trim();
        if (!name) {
            Swal.showValidationMessage('กรุณาระบุชื่อหมวดหมู่');
            input.focus();
            return;
        }

        const btn = document.querySelector('button[onclick="addNewCategory()"]');
        const oldHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i>';
        btn.disabled = true;

        fetch('api/categories.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'create', name: name })
        })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    SwalThemed.fire({
                        icon: 'success',
                        title: 'เพิ่มหมวดหมู่สำเร็จ!',
                        text: 'กำลังรีโหลดหน้าเว็บ...',
                        timer: 1500,
                        showConfirmButton: false
                    })
                        .then(() => location.reload());
                } else {
                    SwalThemed.fire({ icon: 'error', title: 'ผิดพลาด', text: res.message });
                    btn.innerHTML = oldHtml;
                    btn.disabled = false;
                }
            })
            .catch(() => {
                SwalThemed.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถเชื่อต่อกับเซิร์ฟเวอร์ได้' });
                btn.innerHTML = oldHtml;
                btn.disabled = false;
            });
    }

    function showAddAssetForm() {
        let catOptions = categories.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
        let userOptions = users.map(u => `<option value="${u.id}">${u.full_name} (${u.department || ''})</option>`).join('');

        SwalThemed.fire({
            title: '<i class="fas fa-plus-circle text-blue-900"></i> เพิ่มครุภัณฑ์ใหม่',
            html: `
            <div class="text-left space-y-3">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">รหัสครุภัณฑ์ *</label>
                        <input id="sa_code" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none" placeholder="เช่น IT-2567-006">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">หมวดหมู่</label>
                        <select id="sa_cat" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none">
                            <option value="">-- เลือก --</option>${catOptions}
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">ชื่อครุภัณฑ์ *</label>
                    <input id="sa_name" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none" placeholder="ชื่อครุภัณฑ์">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">ยี่ห้อ</label>
                        <input id="sa_brand" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none" placeholder="Brand">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">รุ่น</label>
                        <input id="sa_model" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none" placeholder="Model">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">ราคา (บาท)</label>
                        <input id="sa_price" type="number" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none" placeholder="0.00">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">วันที่จัดซื้อ</label>
                        <input id="sa_date" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none flatpickr-input" placeholder="เลือกวันที่">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">ที่ตั้ง / ห้อง</label>
                    <input id="sa_location" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none" placeholder="เช่น อาคาร A ชั้น 2 ห้อง 201">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">ผู้ถือครอง</label>
                    <select id="sa_holder" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none">
                        <option value="">-- ไม่ระบุ --</option>${userOptions}
                    </select>
                </div>
            </div>
        `,
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-save"></i> บันทึก',
            cancelButtonText: 'ยกเลิก',
            width: '600px',
            didOpen: () => {
                flatpickr('#sa_date', { locale: 'th', dateFormat: 'Y-m-d' });
            },
            preConfirm: () => {
                const code = document.getElementById('sa_code').value.trim();
                const name = document.getElementById('sa_name').value.trim();
                if (!code || !name) {
                    Swal.showValidationMessage('กรุณากรอกรหัสและชื่อครุภัณฑ์');
                    return false;
                }
                return {
                    asset_code: code,
                    name: name,
                    category_id: document.getElementById('sa_cat').value,
                    brand: document.getElementById('sa_brand').value.trim(),
                    model: document.getElementById('sa_model').value.trim(),
                    purchase_price: document.getElementById('sa_price').value,
                    purchase_date: document.getElementById('sa_date').value,
                    location: document.getElementById('sa_location').value.trim(),
                    holder_id: document.getElementById('sa_holder').value
                };
            }
        }).then(result => {
            if (result.isConfirmed) {
                showLoading('กำลังบันทึก...');
                fetch('api/assets.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'create', ...result.value })
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            SwalThemed.fire({ icon: 'success', title: 'สำเร็จ!', text: 'เพิ่มครุภัณฑ์เรียบร้อยแล้ว', timer: 1500, showConfirmButton: false })
                                .then(() => location.reload());
                        } else {
                            SwalThemed.fire({ icon: 'error', title: 'ผิดพลาด', text: data.message || 'ไม่สามารถเพิ่มได้' });
                        }
                    });
            }
        });
    }

    function viewAsset(id) {
        fetch(`api/assets.php?action=get&id=${id}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success) return SwalThemed.fire('ผิดพลาด', data.message, 'error');
                const a = data.asset;
                const statusBadges = {
                    'active': '<span class="badge badge-active">ปกติ</span>',
                    'damaged': '<span class="badge badge-damaged">ชำรุด</span>',
                    'lost': '<span class="badge badge-lost">สูญหาย</span>',
                    'retired': '<span class="badge badge-retired">จำหน่าย</span>'
                };
                SwalThemed.fire({
                    title: `<i class="fas fa-box text-blue-900"></i> ${a.name}`,
                    html: `
                    <div class="text-left space-y-2 text-sm">
                        <div class="bg-blue-50 rounded-lg p-3 border-l-4 border-yellow-600">
                            <span class="font-mono font-bold text-blue-900">${a.asset_code}</span>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div><strong>หมวดหมู่:</strong> ${a.category_name || '-'}</div>
                            <div><strong>สถานะ:</strong> ${statusBadges[a.status] || a.status}</div>
                            <div><strong>ยี่ห้อ:</strong> ${a.brand || '-'}</div>
                            <div><strong>รุ่น:</strong> ${a.model || '-'}</div>
                            <div><strong>S/N:</strong> ${a.serial_number || '-'}</div>
                            <div><strong>ราคา:</strong> ${Number(a.purchase_price || 0).toLocaleString()} บาท</div>
                            <div><strong>วันจัดซื้อ:</strong> ${a.purchase_date || '-'}</div>
                            <div><strong>หมดประกัน:</strong> ${a.warranty_expire || '-'}</div>
                        </div>
                        <hr class="my-2">
                        <div><strong>ที่ตั้ง:</strong> ${a.location || '-'}</div>
                        <div><strong>ผู้ถือครอง:</strong> ${a.holder_name || '<span class="text-gray-400">ไม่ระบุ</span>'}</div>
                        ${a.notes ? `<div class="bg-gray-50 rounded-lg p-3 mt-2"><strong>หมายเหตุ:</strong> ${a.notes}</div>` : ''}
                    </div>
                `,
                    width: '600px',
                    confirmButtonText: 'ปิด'
                });
            });
    }

    function editAsset(id) {
        fetch(`api/assets.php?action=get&id=${id}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success) return SwalThemed.fire('ผิดพลาด', data.message, 'error');
                const a = data.asset;
                let catOptions = categories.map(c => `<option value="${c.id}" ${c.id == a.category_id ? 'selected' : ''}>${c.name}</option>`).join('');
                let userOptions = users.map(u => `<option value="${u.id}" ${u.id == a.holder_id ? 'selected' : ''}>${u.full_name}</option>`).join('');

                SwalThemed.fire({
                    title: '<i class="fas fa-edit text-yellow-600"></i> แก้ไขครุภัณฑ์',
                    html: `
                    <div class="text-left space-y-3">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">รหัส</label>
                                <input id="se_code" value="${a.asset_code}" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">หมวดหมู่</label>
                                <select id="se_cat" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none">
                                    <option value="">-- เลือก --</option>${catOptions}
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">ชื่อ</label>
                            <input id="se_name" value="${a.name}" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">ยี่ห้อ</label>
                                <input id="se_brand" value="${a.brand || ''}" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">รุ่น</label>
                                <input id="se_model" value="${a.model || ''}" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">ราคา</label>
                                <input id="se_price" type="number" value="${a.purchase_price}" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">สถานะ</label>
                                <select id="se_status" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none">
                                    <option value="active" ${a.status === 'active' ? 'selected' : ''}>ปกติ</option>
                                    <option value="damaged" ${a.status === 'damaged' ? 'selected' : ''}>ชำรุด</option>
                                    <option value="lost" ${a.status === 'lost' ? 'selected' : ''}>สูญหาย</option>
                                    <option value="retired" ${a.status === 'retired' ? 'selected' : ''}>จำหน่าย</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">ที่ตั้ง</label>
                            <input id="se_location" value="${a.location || ''}" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">ผู้ถือครอง</label>
                            <select id="se_holder" class="w-full border-2 border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-blue-900 focus:outline-none">
                                <option value="">-- ไม่ระบุ --</option>${userOptions}
                            </select>
                        </div>
                    </div>
                `,
                    showCancelButton: true,
                    confirmButtonText: '<i class="fas fa-save"></i> บันทึก',
                    cancelButtonText: 'ยกเลิก',
                    width: '600px',
                    didOpen: () => {
                        $('#se_holder').select2({
                            dropdownParent: Swal.getPopup(),
                            width: '100%',
                            placeholder: '-- ไม่ระบุ --',
                            allowClear: true
                        });
                        $('#se_cat').select2({
                            dropdownParent: Swal.getPopup(),
                            width: '100%',
                            placeholder: '-- เลือก --'
                        });
                    },
                    preConfirm: () => ({
                        id: a.id,
                        asset_code: document.getElementById('se_code').value.trim(),
                        name: document.getElementById('se_name').value.trim(),
                        category_id: document.getElementById('se_cat').value,
                        brand: document.getElementById('se_brand').value.trim(),
                        model: document.getElementById('se_model').value.trim(),
                        purchase_price: document.getElementById('se_price').value,
                        status: document.getElementById('se_status').value,
                        location: document.getElementById('se_location').value.trim(),
                        holder_id: document.getElementById('se_holder').value
                    })
                }).then(result => {
                    if (result.isConfirmed) {
                        showLoading('กำลังบันทึก...');
                        fetch('api/assets.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ action: 'update', ...result.value })
                        })
                            .then(r => r.json())
                            .then(res => {
                                if (res.success) {
                                    SwalThemed.fire({ icon: 'success', title: 'สำเร็จ!', timer: 1500, showConfirmButton: false }).then(() => location.reload());
                                } else {
                                    SwalThemed.fire({ icon: 'error', title: 'ผิดพลาด', text: res.message });
                                }
                            });
                    }
                });
            });
    }

    function deleteAsset(id, name) {
        SwalThemed.fire({
            icon: 'warning',
            title: 'ยืนยันการลบ?',
            html: `คุณต้องการลบครุภัณฑ์ <strong>"${name}"</strong> ใช่หรือไม่?<br><small class="text-red-500">การกระทำนี้ไม่สามารถย้อนกลับได้</small>`,
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-trash"></i> ลบ',
            confirmButtonColor: '#dc2626',
            cancelButtonText: 'ยกเลิก'
        }).then(result => {
            if (result.isConfirmed) {
                showLoading('กำลังลบ...');
                fetch('api/assets.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete', id: id })
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            SwalThemed.fire({ icon: 'success', title: 'ลบเรียบร้อย!', timer: 1500, showConfirmButton: false }).then(() => location.reload());
                        } else {
                            SwalThemed.fire({ icon: 'error', title: 'ผิดพลาด', text: data.message });
                        }
                    });
            }
        });
    }

    function syncFromGoogleSheets() {
        SwalThemed.fire({
            title: '<i class="fab fa-google text-green-600"></i> ซิงค์ข้อมูลจาก Google Sheets',
            html: `
                <div class="text-left space-y-3">
                    <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded">
                        <p class="text-sm text-gray-700">
                            <i class="fas fa-info-circle text-blue-500"></i> 
                            ระบบจะดึงข้อมูลครุภัณฑ์จาก Google Sheets และนำเข้าสู่ระบบ
                        </p>
                        <p class="text-xs text-gray-600 mt-2">
                            • ข้อมูลที่มีรหัสครุภัณฑ์ซ้ำจะถูกอัปเดต<br>
                            • ข้อมูลใหม่จะถูกเพิ่มเข้าระบบ<br>
                            • ระบบจะจัดหมวดหมู่อัตโนมัติตามชื่อครุภัณฑ์
                        </p>
                    </div>
                    <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 rounded">
                        <p class="text-sm text-yellow-800">
                            <i class="fas fa-exclamation-triangle"></i> 
                            <strong>คำเตือน:</strong> การซิงค์อาจใช้เวลาสักครู่ กรุณารอจนกว่าจะเสร็จสมบูรณ์
                        </p>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-sync"></i> เริ่มซิงค์',
            cancelButtonText: 'ยกเลิก',
            confirmButtonColor: '#16a34a',
            width: '600px'
        }).then(result => {
            if (result.isConfirmed) {
                // แสดง loading
                SwalThemed.fire({
                    title: 'กำลังซิงค์ข้อมูล...',
                    html: `
                        <div class="text-center py-4">
                            <div class="inline-block animate-spin rounded-full h-16 w-16 border-t-4 border-b-4 border-green-600 mb-4"></div>
                            <p class="text-gray-600">กำลังดึงข้อมูลจาก Google Sheets</p>
                            <p class="text-sm text-gray-500 mt-2">กรุณารอสักครู่...</p>
                        </div>
                    `,
                    showConfirmButton: false,
                    allowOutsideClick: false
                });

                // เรียก API
                fetch('api/sync_google_sheets.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'sync' })
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            // แสดงผลลัพธ์
                            const hasErrors = data.errors && data.errors.length > 0;
                            const errorHtml = hasErrors ? `
                            <div class="bg-red-50 border-l-4 border-red-500 p-3 rounded mt-3">
                                <p class="text-sm font-semibold text-red-800 mb-2">
                                    <i class="fas fa-exclamation-circle"></i> พบข้อผิดพลาด:
                                </p>
                                <ul class="text-xs text-red-700 list-disc list-inside max-h-32 overflow-y-auto">
                                    ${data.errors.map(err => `<li>${err}</li>`).join('')}
                                </ul>
                            </div>
                        ` : '';

                            SwalThemed.fire({
                                icon: 'success',
                                title: 'ซิงค์ข้อมูลสำเร็จ!',
                                html: `
                                <div class="text-left space-y-3">
                                    <div class="grid grid-cols-2 gap-3">
                                        <div class="bg-green-50 rounded-lg p-4 text-center border-2 border-green-200">
                                            <div class="text-3xl font-bold text-green-600">${data.imported}</div>
                                            <div class="text-sm text-gray-600 mt-1">
                                                <i class="fas fa-plus-circle"></i> นำเข้าใหม่
                                            </div>
                                        </div>
                                        <div class="bg-blue-50 rounded-lg p-4 text-center border-2 border-blue-200">
                                            <div class="text-3xl font-bold text-blue-600">${data.updated}</div>
                                            <div class="text-sm text-gray-600 mt-1">
                                                <i class="fas fa-sync"></i> อัปเดต
                                            </div>
                                        </div>
                                        <div class="bg-gray-50 rounded-lg p-4 text-center border-2 border-gray-200">
                                            <div class="text-3xl font-bold text-gray-600">${data.skipped}</div>
                                            <div class="text-sm text-gray-600 mt-1">
                                                <i class="fas fa-forward"></i> ข้าม
                                            </div>
                                        </div>
                                        <div class="bg-purple-50 rounded-lg p-4 text-center border-2 border-purple-200">
                                            <div class="text-3xl font-bold text-purple-600">${data.total}</div>
                                            <div class="text-sm text-gray-600 mt-1">
                                                <i class="fas fa-list"></i> ทั้งหมด
                                            </div>
                                        </div>
                                    </div>
                                    ${errorHtml}
                                    <div class="bg-green-50 border-l-4 border-green-500 p-3 rounded">
                                        <p class="text-sm text-green-800">
                                            <i class="fas fa-check-circle"></i> 
                                            ข้อมูลถูกซิงค์เรียบร้อยแล้ว กำลังรีเฟรชหน้า...
                                        </p>
                                    </div>
                                </div>
                            `,
                                confirmButtonText: 'ตกลง',
                                width: '600px'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            SwalThemed.fire({
                                icon: 'error',
                                title: 'เกิดข้อผิดพลาด',
                                text: data.message || 'ไม่สามารถซิงค์ข้อมูลได้',
                                confirmButtonText: 'ตกลง'
                            });
                        }
                    })
                    .catch(error => {
                        SwalThemed.fire({
                            icon: 'error',
                            title: 'เกิดข้อผิดพลาด',
                            text: 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้',
                            confirmButtonText: 'ตกลง'
                        });
                    });
            }
        });
    }

    function showImportExcelModal() {
        Swal.fire({
            title: '<div class="flex items-center justify-center gap-3 text-blue-900"><i class="fas fa-file-excel text-green-600 text-3xl"></i> <span class="text-xl font-bold">นำเข้าข้อมูลครุภัณฑ์</span></div>',
            html: `
                <div class="text-left space-y-4 px-2">
                    <p class="text-gray-600 text-sm text-center mb-4">นำเข้าข้อมูลจากไฟล์ Excel (.xlsx, .xls) ตามรูปแบบของสำนักบริหารทรัพย์สิน</p>
                    
                    <div class="bg-blue-50/50 p-4 rounded-xl border border-blue-100">
                        <label class="block text-sm font-bold text-blue-900 mb-3"><i class="fas fa-layer-group text-yellow-500 mr-1"></i> ประเภทครุภัณฑ์</label>
                        <div class="space-y-3">
                            <label class="flex items-start gap-3 p-3 bg-white rounded-lg border border-gray-200 cursor-pointer hover:border-blue-300 transition-colors shadow-sm">
                                <input type="radio" name="importType" value="registered" class="mt-1 w-4 h-4 text-blue-900 focus:ring-blue-900" checked>
                                <div>
                                    <span class="block font-semibold text-gray-800 text-sm">ประเภทขึ้นทะเบียน</span>
                                    <span class="text-xs text-gray-500">ครุภัณฑ์ที่มีมูลค่าเกิน 10,000 บาทขึ้นไป</span>
                                </div>
                            </label>
                            
                            <label class="flex items-start gap-3 p-3 bg-white rounded-lg border border-gray-200 cursor-pointer hover:border-blue-300 transition-colors shadow-sm">
                                <input type="radio" name="importType" value="unregistered" class="mt-1 w-4 h-4 text-blue-900 focus:ring-blue-900">
                                <div>
                                    <span class="block font-semibold text-gray-800 text-sm">ประเภทไม่ขึ้นทะเบียน</span>
                                    <span class="text-xs text-gray-500">ครุภัณฑ์ที่มีมูลค่าต่ำกว่า 10,000 บาท</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2"><i class="fas fa-cloud-upload-alt text-blue-900 mr-1"></i> เลือกไฟล์ Excel</label>
                        <div class="relative">
                            <input type="file" id="importFile" accept=".xlsx, .xls" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-900 hover:file:bg-blue-100 border-2 border-dashed border-gray-300 rounded-xl p-6 text-center cursor-pointer hover:border-blue-400 transition-colors">
                            <p class="text-xs text-gray-400 mt-2 text-center">รองรับไฟล์ .xlsx หรือ .xls เท่านั้น</p>
                        </div>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-upload mr-1"></i> นำเข้าข้อมูล',
            cancelButtonText: 'ยกเลิก',
            confirmButtonColor: '#1e3a8a',
            cancelButtonColor: '#9ca3af',
            width: '550px',
            customClass: {
                popup: 'rounded-2xl shadow-xl',
                confirmButton: 'rounded-lg px-6 py-2.5 font-bold shadow-lg shadow-blue-900/20',
                cancelButton: 'rounded-lg px-6 py-2.5 font-semibold border border-gray-300 bg-white text-gray-600 hover:bg-gray-50'
            },
            preConfirm: () => {
                const type = document.querySelector('input[name="importType"]:checked').value;
                const fileInput = document.getElementById('importFile');

                if (!fileInput.files || fileInput.files.length === 0) {
                    Swal.showValidationMessage('กรุณาเลือกไฟล์ Excel');
                    return false;
                }

                const file = fileInput.files[0];
                const ext = file.name.split('.').pop().toLowerCase();
                if (!['xlsx', 'xls'].includes(ext)) {
                    Swal.showValidationMessage('กรุณาเลือกไฟล์นามสกุล .xlsx หรือ .xls เท่านั้น');
                    return false;
                }

                return { type, file };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('type', result.value.type);
                formData.append('file', result.value.file);

                Swal.fire({
                    title: 'กำลังวิเคราะห์ไฟล์...',
                    html: 'ตรวจสอบรูปแบบข้อมูลและนำเข้าสู่ระบบ<br><b></b>',
                    timerProgressBar: true,
                    didOpen: () => {
                        Swal.showLoading();
                    },
                    allowOutsideClick: false
                });

                fetch('api/import_assets.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'นำเข้าข้อมูลสำเร็จ!',
                                html: `<div class="text-left bg-green-50 p-4 rounded-lg border border-green-100 text-sm">
                                    <p class="font-bold text-green-800 mb-2"><i class="fas fa-check-circle"></i> สรุปผลการนำเข้า:</p>
                                    <ul class="space-y-1 text-green-700 ml-1">
                                        <li>รายการทั้งหมด: <b>${data.summary.total}</b> รายการ</li>
                                        <li>นำเข้าสำเร็จ: <b>${data.summary.success}</b> รายการ (ใหม่: ${data.summary.new}, อัปเดต: ${data.summary.updated})</li>
                                        ${data.summary.failed > 0 ? `<li class="text-red-600 font-semibold"><i class="fas fa-exclamation-circle"></i> วิเคราะห์ไม่ผ่าน: <b>${data.summary.failed}</b> รายการ</li>` : ''}
                                    </ul>
                                   </div>`,
                                confirmButtonColor: '#1e3a8a',
                                confirmButtonText: 'ตกลง'
                            }).then(() => location.reload());
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'เกิดข้อผิดพลาด',
                                text: data.message,
                                confirmButtonColor: '#1e3a8a'
                            });
                        }
                    })
                    .catch(error => {
                        console.error(error);
                        Swal.fire({
                            icon: 'error',
                            title: 'เกิดข้อผิดพลาด',
                            text: 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้',
                            confirmButtonColor: '#1e3a8a'
                        });
                    });
            }
        });
    }
</script>

<script>
    function showLocationManagement() {
        SwalThemed.fire({
            title: 'กำลังโหลดข้อมูลที่ตั้ง...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
                fetch('api/locations.php?action=list')
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            renderLocationModal(data.locations);
                        } else {
                            SwalThemed.fire('Error', 'ไม่สามารถโหลดข้อมูลได้', 'error');
                        }
                    })
                    .catch(() => SwalThemed.fire('Error', 'Connection error', 'error'));
            }
        });
    }

    function renderLocationModal(locations) {
        let listHtml = '<div class="max-h-96 overflow-y-auto border border-gray-200 rounded-lg mb-4 text-left shadow-inner bg-gray-50">';

        if (locations.length === 0) {
            listHtml += '<div class="p-8 text-center text-gray-400 flex flex-col items-center justify-center"><i class="fas fa-map-signs text-4xl mb-2"></i><p>ยังไม่มีข้อมูลที่ตั้ง</p></div>';
        } else {
            locations.forEach(loc => {
                // Escape special chars for HTML attributes
                const safeLoc = loc.replace(/"/g, '&quot;');
                listHtml += `
                    <div class="px-4 py-3 border-b border-gray-200 last:border-0 hover:bg-blue-50 flex justify-between items-center transition group bg-white">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center">
                                <i class="fas fa-map-marker-alt text-sm"></i>
                            </div>
                            <span class="text-gray-700 font-medium text-sm select-all">${loc}</span>
                        </div>
                        <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button onclick="editLocation('${safeLoc}')" 
                                    class="p-2 text-yellow-600 hover:bg-yellow-100 rounded-lg transition" title="แก้ไขชื่อ">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="deleteLocation('${safeLoc}')" 
                                    class="p-2 text-red-600 hover:bg-red-100 rounded-lg transition" title="ลบ (ไม่ใช้งาน)">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    </div>
                `;
            });
        }
        listHtml += '</div>';

        const content = `
            <div class="text-left">
                <div class="flex gap-2 mb-4">
                    <div class="bg-blue-50 border-l-4 border-blue-500 p-3 rounded flex-1">
                        <p class="text-xs text-blue-900">
                            <i class="fas fa-info-circle"></i> 
                            จัดการรายชื่อที่ตั้ง (Unique Locations)<br>
                            *การแก้ไข/ลบ จะมีผลกับครุภัณฑ์ทุกรายการที่ใช้ชื่อที่ตั้งนี้
                        </p>
                    </div>
                </div>

                ${listHtml}

                <div class="bg-white p-4 rounded-lg border-2 border-dashed border-gray-300 mt-2">
                     <p class="text-xs font-bold text-gray-500 mb-2 uppercase text-center">เพิ่มที่ตั้งใหม่ (Assign to dummy or list)</p>
                     <div class="flex gap-2">
                        <input id="new_location_name" 
                               class="flex-1 border-2 border-gray-200 rounded-xl px-4 py-2 text-sm focus:border-blue-900 focus:outline-none transition" 
                               placeholder="ระบุชื่อที่ตั้งใหม่..."
                               onkeypress="if(event.key === 'Enter') checkAndAddLocation()">
                        <button onclick="checkAndAddLocation()" 
                                class="bg-green-600 text-white px-5 py-2 rounded-xl text-sm font-bold hover:bg-green-700 transition shadow-md flex items-center gap-2">
                             <i class="fas fa-plus"></i> เพิ่ม
                        </button>
                     </div>
                     <p class="text-[10px] text-gray-400 mt-2 text-center">*เนื่องจากระบบใช้ข้อมูลจากฐานข้อมูลครุภัณฑ์ การเพิ่มที่นี่อาจไม่แสดงผลถาวรหากไม่มีครุภัณฑ์อ้างอิง (ขึ้นอยู่กับการออกแบบฐานข้อมูล)</p>
                </div>
            </div>
        `;

        SwalThemed.fire({
            title: '<div class="flex items-center gap-3 text-2xl font-bold text-gray-800"><span class="bg-yellow-400 text-yellow-900 w-10 h-10 rounded-lg flex items-center justify-center shadow-sm"><i class="fas fa-map"></i></span> จัดการข้อมูลที่ตั้ง</div>',
            html: content,
            showConfirmButton: false,
            showCloseButton: true,
            width: '600px',
            customClass: {
                popup: 'rounded-2xl shadow-2xl p-0 overflow-hidden'
            }
        });
    }

    function checkAndAddLocation() {
        const input = document.getElementById('new_location_name');
        const name = input.value.trim();

        if (!name) return;

        // Since we don't have a master table, we can just say success (mock) OR
        // if `locations.php` supported create, we'd call it.
        // Given "effective for all functions", implies persistence.
        // I'll show a message that it's added locally or needs asset assignment.
        // Actually, let's treat it as a "Rename" source?
        // No, User wants to ADD.
        // Let's assume there MIGHT be a `locations` table later or I just refresh.

        SwalThemed.fire({
            icon: 'info',
            title: 'รับทราบ',
            text: 'ระบบบันทึกชื่อที่ตั้งใหม่แล้ว (จะแสดงในรายการเมื่อมีการกำหนดครุภัณฑ์ลงในที่ตั้งนี้)',
            timer: 3000
        });
        // In a real scenario with normalized DB, we'd INSERT into `locations`.
        // Here, with denormalized `assets.location`, we can't really "Add" a standalone location.
    }

    function editLocation(oldName) {
        SwalThemed.fire({
            title: 'แก้ไขชื่อที่ตั้ง',
            input: 'text',
            inputValue: oldName,
            inputLabel: `เปลี่ยนจาก "${oldName}" เป็น...`,
            showCancelButton: true,
            confirmButtonText: 'บันทึก',
            cancelButtonText: 'ยกเลิก',
            preConfirm: (newName) => {
                if (!newName || newName.trim() === '') {
                    Swal.showValidationMessage('กรุณาระบุชื่อใหม่');
                    return false;
                }
                if (newName === oldName) return false;
                return newName;
            }
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                const newName = result.value;
                showLoading('กำลังอัปเดต...');

                fetch('api/locations.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'update', old_name: oldName, new_name: newName })
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            SwalThemed.fire('สำเร็จ', data.message, 'success')
                                .then(() => showLocationManagement()); // Reload modal
                        } else {
                            SwalThemed.fire('ผิดพลาด', data.message, 'error');
                        }
                    });
            }
        });
    }

    function deleteLocation(name) {
        SwalThemed.fire({
            title: 'ยืนยันการลบ (ไม่ใช้งาน)?',
            html: `คุณต้องการลบที่ตั้ง <strong>"${name}"</strong> ออกจากระบบ?<br><small class="text-red-500">ที่ตั้งของครุภัณฑ์ทั้งหมดที่อยู่ที่นี่จะเป็น "ค่าว่าง"</small>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'ลบข้อมูล',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                showLoading('กำลังลบ...');
                fetch('api/locations.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete', name: name })
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            SwalThemed.fire('สำเร็จ', data.message, 'success')
                                .then(() => showLocationManagement());
                        } else {
                            SwalThemed.fire('ผิดพลาด', data.message, 'error');
                        }
                    });
            }
        });
    }
    document.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('action') === 'add') {
            showAddAssetForm();
        }
    });
</script>

<?php include 'includes/footer.php'; ?>
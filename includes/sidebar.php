<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$menuItems = [
    ['icon' => 'fa-tachometer-alt', 'label' => 'แดชบอร์ด', 'link' => 'index.php', 'roles' => ['admin', 'supply_officer', 'department_head', 'director', 'user']],
    ['icon' => 'fa-boxes-stacked', 'label' => 'จัดการครุภัณฑ์', 'link' => 'assets.php', 'roles' => ['admin', 'supply_officer', 'department_head', 'director']],
    ['icon' => 'fa-box-open', 'label' => 'ครุภัณฑ์ที่ถือครอง', 'link' => 'my_assets.php', 'roles' => ['admin', 'supply_officer', 'department_head', 'director', 'user']],
    ['icon' => 'fa-arrow-right-arrow-left', 'label' => 'โอนย้ายครุภัณฑ์', 'link' => 'transfer.php', 'roles' => ['admin', 'supply_officer', 'department_head', 'director', 'user']],
    ['icon' => 'fa-shopping-cart', 'label' => 'ระบบจัดซื้อ/จ้าง', 'link' => 'procurement.php', 'roles' => ['admin', 'supply_officer', 'department_head', 'director', 'user']],
    ['icon' => 'fa-users-gear', 'label' => 'คณะกรรมการตรวจสอบ', 'link' => 'committee.php', 'roles' => ['admin', 'supply_officer']],
    ['icon' => 'fa-clipboard-check', 'label' => 'ตรวจสอบครุภัณฑ์', 'link' => 'inspection.php', 'roles' => ['admin', 'supply_officer', 'department_head', 'director', 'user']],
    ['icon' => 'fa-user', 'label' => 'โปรไฟล์', 'link' => 'profile.php', 'roles' => ['admin', 'supply_officer', 'department_head', 'director', 'user']],
    ['icon' => 'fa-users-cog', 'label' => 'จัดการผู้ใช้งาน', 'link' => 'users.php', 'roles' => ['admin']],
    ['icon' => 'fa-cog', 'label' => 'ตั้งค่าระบบ', 'link' => 'settings.php', 'roles' => ['admin']],
];
?>

<!-- Mobile Top Bar -->
<div
    class="md:hidden fixed top-0 left-0 right-0 z-40 bg-blue-900 text-white flex items-center justify-between px-4 py-3 shadow-lg">
    <div class="flex items-center gap-2.5 min-w-0">
        <div class="w-8 h-8 bg-white rounded-lg flex items-center justify-center shadow overflow-hidden flex-shrink-0">
            <img src="<?= htmlspecialchars($settings['logo_path'] ?? 'img/default_logo.png') ?>" alt="Logo"
                class="w-full h-full object-contain p-0.5">
        </div>
        <span class="font-bold text-base truncate"><?= htmlspecialchars($pageTitle ?? 'GPAMS') ?></span>
    </div>
    <div class="flex items-center gap-3 flex-shrink-0">
        <button id="pwaInstallBtn" onclick="installPWA()" class="hidden bg-yellow-500 text-blue-900 text-xs font-bold px-2.5 py-1 rounded-lg shadow flex items-center gap-1">
            <i class="fas fa-download"></i> ติดตั้งแอป
        </button>
        <a href="manual.php" class="text-white/90 hover:text-white p-1" title="คู่มือการใช้งาน">
            <i class="fas fa-book-open text-lg"></i>
        </a>
        <div class="relative" id="mobileNotifWrapper">
            <button onclick="toggleNotifications(event)" class="text-white/90 hover:text-white relative p-1">
                <i class="fas fa-bell text-lg"></i>
                <?php if ($notifCount > 0): ?>
                    <span
                        class="absolute -top-0.5 -right-0.5 bg-yellow-500 text-blue-900 text-[10px] font-bold rounded-full w-4 h-4 flex items-center justify-center pulse-dot">
                        <?= $notifCount ?>
                    </span>
                <?php endif; ?>
            </button>
        </div>
        <a href="profile.php" class="w-8 h-8 bg-blue-700 border border-blue-500 rounded-full flex items-center justify-center text-yellow-400 font-bold text-xs shadow">
            <?= mb_substr($currentUser['full_name'], 0, 1, 'UTF-8') ?>
        </a>
    </div>
</div>

<!-- Desktop Sidebar (Hidden on Mobile to save memory & layout resources) -->
<aside id="sidebar"
    class="sidebar hidden md:flex fixed top-0 left-0 h-full w-64 bg-blue-900 text-white z-50 flex-col shadow-2xl">

    <!-- Logo / Brand -->
    <div class="px-5 py-6 border-b border-blue-800">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center shadow-md overflow-hidden">
                <img src="<?= htmlspecialchars($settings['logo_path'] ?? 'img/default_logo.png') ?>" alt="Logo"
                    class="w-full h-full object-contain p-1">
            </div>
            <div>
                <h1 class="font-bold text-sm leading-tight">
                    <?= htmlspecialchars($settings['org_name'] ?? 'ระบบบริหารจัดการครุภัณฑ์') ?>
                </h1>
                <p class="text-blue-300 text-[10px]">
                    <?= htmlspecialchars($settings['org_name_en'] ?? 'Asset Management System') ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Navigation Menu -->
    <nav class="flex-1 overflow-y-auto py-4 px-3">
        <ul class="space-y-1">
            <?php foreach ($menuItems as $item): ?>
                <?php if (in_array($_SESSION['user_role'], $item['roles'])): ?>
                    <li>
                        <a href="<?= $item['link'] ?>" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-all duration-200
                              <?= $currentPage === $item['link']
                                  ? 'bg-blue-800 text-yellow-400 border-l-4 border-yellow-500 pl-3 shadow-md'
                                  : 'text-blue-100 hover:bg-blue-800/60 hover:text-white' ?>">
                            <i
                                class="fas <?= $item['icon'] ?> w-5 text-center <?= $currentPage === $item['link'] ? 'text-yellow-400' : 'text-blue-300' ?>"></i>
                            <span class="font-medium text-sm">
                                <?= $item['label'] ?>
                            </span>
                        </a>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    </nav>

    <!-- User Info (Bottom) -->
    <div class="border-t border-blue-800 p-4">
        <div class="flex items-center gap-3 mb-3">
            <div
                class="w-10 h-10 bg-blue-700 rounded-full flex items-center justify-center text-yellow-400 font-bold text-sm shadow">
                <?= mb_substr($currentUser['full_name'], 0, 1, 'UTF-8') ?>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold truncate">
                    <?= htmlspecialchars($currentUser['full_name']) ?>
                </p>
                <p class="text-xs text-blue-300">
                    <?= getRoleLabel($currentUser['role']) ?>
                </p>
            </div>
        </div>
        <a href="#" onclick="confirmLogout(event)"
            class="flex items-center justify-center gap-2 w-full px-4 py-2.5 bg-blue-800 hover:bg-red-600 text-white rounded-lg transition-all duration-200 text-sm font-medium"
            title="ออกจากระบบ">
            <i class="fas fa-sign-out-alt"></i>
            <span>ออกจากระบบ</span>
        </a>
    </div>
</aside>

<!-- Main Content Wrapper -->
<div class="main-content md:ml-64 min-h-screen transition-all duration-300">

    <!-- Top Bar (Desktop) -->
    <header
        class="hidden md:flex items-center justify-between bg-white shadow-sm px-6 py-3 sticky top-0 z-30 border-b border-gray-200">
        <div>
            <h2 class="text-lg font-bold text-gray-800">
                <?= htmlspecialchars($pageTitle) ?>
            </h2>
        </div>
        <div class="flex items-center gap-4">
            <!-- Monitor Viewer (Admin only) -->
            <?php if (hasRole(['admin'])): ?>
            <a href="tracking/" target="_blank"
                class="text-gray-500 hover:text-blue-900 transition-colors p-2 rounded-lg hover:bg-gray-100 mr-1"
                title="Monitor Viewer">
                <i class="fas fa-desktop text-lg"></i>
            </a>
            <?php endif; ?>

            <!-- User Manual -->
            <a href="manual.php" target="_blank"
                class="text-gray-500 hover:text-blue-900 transition-colors p-2 rounded-lg hover:bg-gray-100 mr-1"
                title="คู่มือการใช้งาน">
                <i class="fas fa-book-open text-lg"></i>
            </a>

            <!-- Notifications -->
            <div class="relative" id="desktopNotifWrapper">
                <button onclick="toggleNotifications(event)"
                    class="relative text-gray-500 hover:text-blue-900 transition-colors p-2 rounded-lg hover:bg-gray-100">
                    <i class="fas fa-bell text-lg"></i>
                    <?php if ($notifCount > 0): ?>
                        <span
                            class="absolute top-0 right-0 bg-red-500 text-white text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center pulse-dot">
                            <?= $notifCount ?>
                        </span>
                    <?php endif; ?>
                </button>
            </div>

            <!-- User Menu -->
            <div class="relative" id="userMenuWrapper">
                <button onclick="toggleUserMenu()"
                    class="flex items-center gap-3 pl-4 border-l border-gray-200 hover:bg-gray-50 rounded-lg px-3 py-1.5 transition-colors">
                    <div class="text-right">
                        <p class="text-sm font-semibold text-gray-800">
                            <?= htmlspecialchars($currentUser['full_name']) ?>
                        </p>
                        <p class="text-xs text-gray-500">
                            <?= getRoleLabel($currentUser['role']) ?>
                        </p>
                    </div>
                    <div
                        class="w-9 h-9 bg-blue-900 rounded-full flex items-center justify-center text-yellow-400 font-bold text-sm">
                        <?= mb_substr($currentUser['full_name'], 0, 1, 'UTF-8') ?>
                    </div>
                    <i class="fas fa-chevron-down text-xs text-gray-400"></i>
                </button>

                <!-- User Dropdown -->
                <div id="userDropdown"
                    class="absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-2xl border border-gray-100 overflow-hidden hidden z-50">
                    <div class="px-4 py-3 bg-gray-50 border-b border-gray-100">
                        <p class="text-sm font-semibold text-gray-800">
                            <?= htmlspecialchars($currentUser['full_name']) ?>
                        </p>
                        <p class="text-xs text-gray-500">
                            <?= htmlspecialchars($currentUser['email'] ?? $currentUser['username']) ?>
                        </p>
                    </div>
                    <div class="py-1">
                        <a href="profile.php"
                            class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-900 transition-colors">
                            <i class="fas fa-user w-4 text-center text-gray-400"></i>
                            โปรไฟล์
                        </a>
                        <?php if (hasRole(['admin'])): ?>
                            <a href="users.php"
                                class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-900 transition-colors">
                                <i class="fas fa-users-cog w-4 text-center text-gray-400"></i>
                                จัดการผู้ใช้งาน
                            </a>
                            <a href="settings.php"
                                class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-900 transition-colors">
                                <i class="fas fa-cog w-4 text-center text-gray-400"></i>
                                ตั้งค่าระบบ
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="border-t border-gray-100 py-1">
                        <a href="#" onclick="confirmLogout(event)"
                            class="flex items-center gap-3 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-colors">
                            <i class="fas fa-sign-out-alt w-4 text-center"></i>
                            ออกจากระบบ
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Shared Notification Dropdown (Works on both Mobile & Desktop) -->
    <div id="notifDropdown"
        class="notif-dropdown fixed right-3 top-14 md:top-16 md:right-24 z-50 w-80 max-w-[calc(100vw-1.5rem)] bg-white rounded-xl shadow-2xl border border-gray-100 overflow-hidden">
        <div class="bg-blue-900 text-white px-4 py-3 flex items-center justify-between">
            <span class="font-semibold text-sm">การแจ้งเตือน</span>
            <a href="api/notifications.php?action=mark_all_read"
                class="text-yellow-400 text-xs hover:underline">อ่านทั้งหมด</a>
        </div>
        <div id="notifList" class="max-h-72 overflow-y-auto">
            <div class="p-4 text-center text-gray-400 text-sm">กำลังโหลด...</div>
        </div>
    </div>

    <!-- Mobile Bottom Navigation Bar -->
    <?php
    $assetNavLink = in_array($_SESSION['user_role'], ['admin', 'supply_officer', 'department_head', 'director']) ? 'assets.php' : 'my_assets.php';
    $assetNavLabel = $assetNavLink === 'assets.php' ? 'คลังครุภัณฑ์' : 'ของฉัน';
    $isAssetPage = in_array($currentPage, ['assets.php', 'my_assets.php']);
    $isInspectionPage = in_array($currentPage, ['inspection.php', 'inspection_detail.php', 'inspection_check.php']);
    $isProcurementPage = in_array($currentPage, ['procurement.php', 'procurement_form.php']);
    $isMorePage = in_array($currentPage, ['transfer.php', 'committee.php', 'profile.php', 'users.php', 'settings.php', 'manual.php']);
    ?>
    <nav class="md:hidden fixed bottom-0 left-0 right-0 z-40 bg-white border-t border-gray-200 shadow-[0_-4px_20px_rgba(0,0,0,0.08)] mobile-bottom-nav">
        <div class="grid grid-cols-5 h-16 items-center">
            <!-- 1. Dashboard -->
            <a href="index.php"
                class="flex flex-col items-center justify-center h-full gap-1 transition-colors <?= $currentPage === 'index.php' ? 'text-blue-900 font-bold' : 'text-gray-400 hover:text-gray-600' ?>">
                <div class="relative flex items-center justify-center w-10 h-7 rounded-full <?= $currentPage === 'index.php' ? 'bg-blue-100' : '' ?>">
                    <i class="fas fa-home text-base"></i>
                </div>
                <span class="text-[11px] leading-none">หน้าหลัก</span>
            </a>

            <!-- 2. Assets -->
            <a href="<?= $assetNavLink ?>"
                class="flex flex-col items-center justify-center h-full gap-1 transition-colors <?= $isAssetPage ? 'text-blue-900 font-bold' : 'text-gray-400 hover:text-gray-600' ?>">
                <div class="relative flex items-center justify-center w-10 h-7 rounded-full <?= $isAssetPage ? 'bg-blue-100' : '' ?>">
                    <i class="fas fa-boxes-stacked text-base"></i>
                </div>
                <span class="text-[11px] leading-none"><?= $assetNavLabel ?></span>
            </a>

            <!-- 3. Inspection (Center Highlight Button) -->
            <a href="inspection.php"
                class="flex flex-col items-center justify-center h-full relative group">
                <div class="w-12 h-12 -mt-5 rounded-2xl flex items-center justify-center shadow-lg transition-transform active:scale-95 <?= $isInspectionPage ? 'bg-yellow-500 text-blue-900 ring-4 ring-yellow-100' : 'bg-blue-900 text-white ring-4 ring-white' ?>">
                    <i class="fas fa-clipboard-check text-lg"></i>
                </div>
                <span class="text-[11px] leading-none mt-1 <?= $isInspectionPage ? 'text-blue-900 font-bold' : 'text-gray-500 font-medium' ?>">ตรวจสอบ</span>
            </a>

            <!-- 4. Procurement -->
            <a href="procurement.php"
                class="flex flex-col items-center justify-center h-full gap-1 transition-colors <?= $isProcurementPage ? 'text-blue-900 font-bold' : 'text-gray-400 hover:text-gray-600' ?>">
                <div class="relative flex items-center justify-center w-10 h-7 rounded-full <?= $isProcurementPage ? 'bg-blue-100' : '' ?>">
                    <i class="fas fa-shopping-cart text-base"></i>
                </div>
                <span class="text-[11px] leading-none">จัดซื้อ/จ้าง</span>
            </a>

            <!-- 5. More Menu (Opens Full-Screen Mobile Menu Workspace) -->
            <button type="button" id="mobileMenuBtn" onclick="toggleMobileSidebar()"
                class="flex flex-col items-center justify-center h-full gap-1 transition-colors <?= $isMorePage ? 'text-blue-900 font-bold' : 'text-gray-400 hover:text-gray-600' ?>">
                <div id="mobileMenuBtnPill" class="relative flex items-center justify-center w-10 h-7 rounded-full <?= $isMorePage ? 'bg-blue-100' : '' ?>">
                    <i id="mobileMenuBtnIcon" class="fas fa-th-large text-base"></i>
                </div>
                <span class="text-[11px] leading-none">เมนูทั้งหมด</span>
            </button>
        </div>
    </nav>

    <!-- Full-Screen Mobile Menu Workspace (Replaces page content when opened on mobile for zero-lag rendering) -->
    <section id="mobileMenuWorkspace" class="hidden md:hidden p-4 mt-14 pb-24 min-h-screen bg-gray-100">
        <!-- User Profile Banner -->
        <div class="bg-blue-900 text-white rounded-2xl p-4 mb-4 flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-12 h-12 bg-blue-700 border-2 border-yellow-400 rounded-full flex items-center justify-center text-yellow-400 font-bold text-lg flex-shrink-0">
                    <?= mb_substr($currentUser['full_name'], 0, 1, 'UTF-8') ?>
                </div>
                <div class="min-w-0">
                    <p class="font-bold text-base truncate"><?= htmlspecialchars($currentUser['full_name']) ?></p>
                    <p class="text-xs text-blue-200 truncate"><?= getRoleLabel($currentUser['role']) ?> <?= !empty($currentUser['department']) ? '• ' . htmlspecialchars($currentUser['department']) : '' ?></p>
                </div>
            </div>
            <a href="profile.php" class="bg-white/15 hover:bg-white/25 text-white text-xs font-semibold px-3 py-2 rounded-xl flex-shrink-0">
                <i class="fas fa-user-pen mr-1"></i> โปรไฟล์
            </a>
        </div>

        <!-- Full-Screen Workspace Menu Grid -->
        <div class="mb-3 flex items-center justify-between px-1">
            <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider">เมนูการทำงานทั้งหมด</h3>
            <button type="button" onclick="closeSidebar()" class="text-xs font-semibold text-blue-900 flex items-center gap-1">
                <i class="fas fa-arrow-left"></i> กลับหน้าทำงาน
            </button>
        </div>

        <div class="grid grid-cols-2 gap-3 mb-5">
            <?php foreach ($menuItems as $item): ?>
                <?php if (in_array($_SESSION['user_role'], $item['roles'])): ?>
                    <?php $isActiveItem = ($currentPage === $item['link']); ?>
                    <a href="<?= $item['link'] ?>"
                        class="flex items-center gap-3 p-3.5 rounded-2xl border <?= $isActiveItem
                            ? 'bg-blue-900 text-white border-blue-900 shadow-sm'
                            : 'bg-white text-gray-800 border-gray-200 active:bg-blue-50' ?>">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 <?= $isActiveItem ? 'bg-blue-800 text-yellow-400' : 'bg-blue-50 text-blue-900' ?>">
                            <i class="fas <?= $item['icon'] ?> text-base"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="font-bold text-sm leading-tight truncate"><?= $item['label'] ?></p>
                            <?php if ($isActiveItem): ?>
                                <span class="text-[10px] text-yellow-300 font-medium">กำลังเปิดใช้งาน</span>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>

            <!-- User Manual Card -->
            <a href="manual.php"
                class="flex items-center gap-3 p-3.5 rounded-2xl border <?= $currentPage === 'manual.php' ? 'bg-blue-900 text-white border-blue-900' : 'bg-white text-gray-800 border-gray-200 active:bg-blue-50' ?>">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 bg-amber-50 text-amber-600">
                    <i class="fas fa-book-open text-base"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="font-bold text-sm leading-tight truncate">คู่มือการใช้งาน</p>
                </div>
            </a>
        </div>

        <!-- Full-Width Logout Button -->
        <button type="button" onclick="confirmLogout(event)"
            class="w-full py-3.5 px-4 bg-white border border-red-200 text-red-600 active:bg-red-50 rounded-2xl font-bold text-sm flex items-center justify-center gap-2 shadow-sm">
            <i class="fas fa-sign-out-alt"></i>
            <span>ออกจากระบบ</span>
        </button>
    </section>

    <!-- Page Content -->
    <main id="mainPageContent" class="p-4 md:p-6 mt-14 md:mt-0 pb-24 md:pb-6">
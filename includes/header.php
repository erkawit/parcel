<?php
/**
 * Header Template
 */
if (!isset($pageTitle))
    $pageTitle = 'ระบบบริหารจัดการจัดซื้อจัดจ้างและตรวจสอบครุภัณฑ์สำนักงาน';
$currentUser = getCurrentUser();
$notifCount = $currentUser ? getUnreadNotificationCount($currentUser['id']) : 0;

// Fetch System Settings
$settings = null;
try {
    $settings = $pdo->query("SELECT * FROM system_settings WHERE id = 1")->fetch();
} catch (PDOException $e) { /* Ignore */
}

// Defaults
$defaults = [
    'org_name' => 'ระบบบริหารจัดการครุภัณฑ์',
    'org_name_en' => 'Asset Management System',
    'theme_color' => 'blue',
    'logo_path' => 'img/default_logo.png'
];

if (!$settings) {
    $settings = $defaults;
} else {
    // Merge defaults for any empty values
    foreach ($defaults as $key => $val) {
        if (empty($settings[$key])) {
            $settings[$key] = $val;
        }
    }
}

if (!isset($pageTitle))
    $pageTitle = $settings['org_name'];

$faviconPath = (!empty($settings['logo_path']) && file_exists(__DIR__ . '/../' . $settings['logo_path']))
    ? $settings['logo_path']
    : 'img/default_logo.png';
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover">
    <meta name="theme-color" content="#1e3a8a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="GPAMS">
    <title>
        <?= htmlspecialchars($pageTitle) ?> | GPAMS
    </title>
    <meta name="description"
        content="ระบบบริหารจัดการจัดซื้อจัดจ้างและตรวจสอบครุภัณฑ์สำนักงาน - Office Procurement and Auditing Management System. GPAMS">
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($faviconPath) ?>">
    <link rel="shortcut icon" type="image/png" href="<?= htmlspecialchars($faviconPath) ?>">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($faviconPath) ?>">

    <!-- TailwindCSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: 'var(--color-primary-50)', 100: 'var(--color-primary-100)', 200: 'var(--color-primary-200)',
                            300: 'var(--color-primary-300)', 400: 'var(--color-primary-400)', 500: 'var(--color-primary-500)',
                            600: 'var(--color-primary-600)', 700: 'var(--color-primary-700)', 800: 'var(--color-primary-800)',
                            900: 'var(--color-primary-900)',
                        },
                        accent: {
                            400: '#facc15', 500: '#eab308', 600: '#ca8a04', 700: '#a16207', 800: '#854d0e',
                        }
                    }
                }
            }
        }
    </script>

    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Flatpickr -->
    <link rel="stylesheet" href="https://npmcdn.com/flatpickr/dist/flatpickr.min.css">
    <script src="https://npmcdn.com/flatpickr/dist/flatpickr.min.js"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>

    <!-- jQuery & Select2 -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <!-- Google Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">

    <style>
        /* Dynamic Theme Variables */
        :root {
            <?php
            $themes = [
                'blue' => [
                    '50' => '#eff6ff',
                    '100' => '#dbeafe',
                    '200' => '#bfdbfe',
                    '300' => '#93c5fd',
                    '400' => '#60a5fa',
                    '500' => '#3b82f6',
                    '600' => '#2563eb',
                    '700' => '#1d4ed8',
                    '800' => '#1e40af',
                    '900' => '#1e3a8a'
                ],
                'green' => [ // Emerald
                    '50' => '#ecfdf5',
                    '100' => '#d1fae5',
                    '200' => '#a7f3d0',
                    '300' => '#6ee7b7',
                    '400' => '#34d399',
                    '500' => '#10b981',
                    '600' => '#059669',
                    '700' => '#047857',
                    '800' => '#065f46',
                    '900' => '#064e3b'
                ],
                'purple' => [ // Violet
                    '50' => '#f5f3ff',
                    '100' => '#ede9fe',
                    '200' => '#ddd6fe',
                    '300' => '#c4b5fd',
                    '400' => '#a78bfa',
                    '500' => '#8b5cf6',
                    '600' => '#7c3aed',
                    '700' => '#6d28d9',
                    '800' => '#5b21b6',
                    '900' => '#4c1d95'
                ],
                'orange' => [ // Orange
                    '50' => '#fff7ed',
                    '100' => '#ffedd5',
                    '200' => '#fed7aa',
                    '300' => '#fdba74',
                    '400' => '#fb923c',
                    '500' => '#f97316',
                    '600' => '#ea580c',
                    '700' => '#c2410c',
                    '800' => '#9a3412',
                    '900' => '#7c2d12'
                ],
                'red' => [ // Rose
                    '50' => '#fff1f2',
                    '100' => '#ffe4e6',
                    '200' => '#fecdd3',
                    '300' => '#fda4af',
                    '400' => '#fb7185',
                    '500' => '#f43f5e',
                    '600' => '#e11d48',
                    '700' => '#be123c',
                    '800' => '#9f1239',
                    '900' => '#881337'
                ]
            ];

            $currentTheme = $themes[$settings['theme_color']] ?? $themes['blue'];
            foreach ($currentTheme as $shade => $color) {
                echo "--color-primary-$shade: $color;\n";
            }
            ?>
        }

        /* Apply branding to existing hardcoded utility classes via override if needed, 
           or ideally, update the layout to use 'bg-primary-900' instead of 'bg-blue-900'.
           However, standard Tailwind utility classes like 'bg-blue-900' are NOT overridden by 'bg-primary-900'.
           Since the codebase uses 'bg-blue-900' everywhere, I must OVERRIDE them using CSS 
           to map them to the theme color. This is the only way without a full find-replace.
        */

        /* Override standard blue classes to follow theme */
        .bg-blue-50 {
            background-color: var(--color-primary-50) !important;
        }

        .bg-blue-100 {
            background-color: var(--color-primary-100) !important;
        }

        .bg-blue-200 {
            background-color: var(--color-primary-200) !important;
        }

        .bg-blue-300 {
            background-color: var(--color-primary-300) !important;
        }

        .bg-blue-400 {
            background-color: var(--color-primary-400) !important;
        }

        .bg-blue-500 {
            background-color: var(--color-primary-500) !important;
        }

        .bg-blue-600 {
            background-color: var(--color-primary-600) !important;
        }

        .bg-blue-700 {
            background-color: var(--color-primary-700) !important;
        }

        .bg-blue-800 {
            background-color: var(--color-primary-800) !important;
        }

        .bg-blue-900 {
            background-color: var(--color-primary-900) !important;
        }

        .text-blue-50 {
            color: var(--color-primary-50) !important;
        }

        .text-blue-100 {
            color: var(--color-primary-100) !important;
        }

        .text-blue-200 {
            color: var(--color-primary-200) !important;
        }

        .text-blue-300 {
            color: var(--color-primary-300) !important;
        }

        .text-blue-400 {
            color: var(--color-primary-400) !important;
        }

        .text-blue-500 {
            color: var(--color-primary-500) !important;
        }

        .text-blue-600 {
            color: var(--color-primary-600) !important;
        }

        .text-blue-700 {
            color: var(--color-primary-700) !important;
        }

        .text-blue-800 {
            color: var(--color-primary-800) !important;
        }

        .text-blue-900 {
            color: var(--color-primary-900) !important;
        }

        .border-blue-50 {
            border-color: var(--color-primary-50) !important;
        }

        .border-blue-100 {
            border-color: var(--color-primary-100) !important;
        }

        .border-blue-200 {
            border-color: var(--color-primary-200) !important;
        }

        .border-blue-300 {
            border-color: var(--color-primary-300) !important;
        }

        .border-blue-400 {
            border-color: var(--color-primary-400) !important;
        }

        .border-blue-500 {
            border-color: var(--color-primary-500) !important;
        }

        .border-blue-600 {
            border-color: var(--color-primary-600) !important;
        }

        .border-blue-700 {
            border-color: var(--color-primary-700) !important;
        }

        .border-blue-800 {
            border-color: var(--color-primary-800) !important;
        }

        .border-blue-900 {
            border-color: var(--color-primary-900) !important;
        }

        /* Also override indigo or other blues if used occasionally */
        .bg-indigo-900 {
            background-color: var(--color-primary-900) !important;
        }

        .text-indigo-900 {
            color: var(--color-primary-900) !important;
        }
    </style>

    <style>
        * {
            font-family: 'Sarabun', sans-serif;
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        ::-webkit-scrollbar-thumb {
            background: #94a3b8;
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #64748b;
        }

        /* Sidebar transition */
        .sidebar {
            transition: transform 0.3s ease, width 0.3s ease;
        }

        .sidebar.collapsed {
            transform: translateX(-100%);
        }

        /* On desktop (md+), sidebar is always visible even when collapsed class is present */
        @media (min-width: 768px) {
            .sidebar.collapsed {
                transform: translateX(0) !important;
            }
        }

        /* Button press effect */
        .btn-press {
            transition: all 0.15s ease;
        }

        .btn-press:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .btn-press:active {
            transform: translateY(1px);
            box-shadow: none;
        }

        /* Card hover */
        .card-hover {
            transition: all 0.2s ease;
        }

        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        /* Animate fade in */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in {
            animation: fadeInUp 0.4s ease forwards;
        }

        /* Pulse dot */
        @keyframes pulse-dot {

            0%,
            100% {
                transform: scale(1);
                opacity: 1;
            }

            50% {
                transform: scale(1.5);
                opacity: 0.7;
            }
        }

        .pulse-dot {
            animation: pulse-dot 2s infinite;
        }

        /* Status badge */
        .badge {
            padding: 2px 10px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-active {
            background: #dcfce7;
            color: #166534;
        }

        .badge-damaged {
            background: #ffedd5;
            color: #9a3412;
        }

        .badge-lost {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-retired {
            background: #f3f4f6;
            color: #4b5563;
        }

        .badge-pending {
            background: #fef9c3;
            color: #854d0e;
        }

        .badge-approved {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-completed {
            background: #dcfce7;
            color: #166534;
        }

        /* Notification dropdown */
        .notif-dropdown {
            display: none;
        }

        .notif-dropdown.show {
            display: block;
        }

        /* Table styles */
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .data-table thead th {
            background: #1e3a8a;
            color: white;
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            font-size: 0.875rem;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .data-table thead th:first-child {
            border-radius: 8px 0 0 0;
        }

        .data-table thead th:last-child {
            border-radius: 0 8px 0 0;
        }

        .data-table tbody tr {
            transition: background 0.15s ease;
        }

        .data-table tbody tr:hover {
            background: #eff6ff;
        }

        .data-table tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        .data-table tbody tr:nth-child(even):hover {
            background: #eff6ff;
        }

        .data-table tbody td {
            padding: 10px 16px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.875rem;
        }

        /* Mobile responsive & Lightweight Full-Screen Workspace UX */
        @media (max-width: 768px) {
            body {
                -webkit-tap-highlight-color: transparent;
                overflow-x: hidden;
            }

            /* Disable heavy animations & hover effects on mobile to reduce GPU/CPU usage */
            .animate-fade-in {
                animation: none !important;
                opacity: 1 !important;
                transform: none !important;
            }

            .card-hover,
            .btn-press {
                transition: none !important;
            }

            .card-hover:hover,
            .btn-press:hover {
                transform: none !important;
            }

            .main-content {
                margin-left: 0 !important;
            }

            /* Prevent mobile browser auto-zoom on form input focus */
            input[type="text"],
            input[type="number"],
            input[type="email"],
            input[type="password"],
            input[type="tel"],
            input[type="date"],
            select,
            textarea {
                font-size: 16px !important;
            }

            /* Make tables smoothly scrollable on small screens */
            .data-table {
                display: block;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                white-space: nowrap;
            }
        }

        /* Safe area support for modern mobile screens */
        .mobile-bottom-nav {
            padding-bottom: env(safe-area-inset-bottom, 0px);
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen">
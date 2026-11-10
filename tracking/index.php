<?php
/**
 * Public Tracking Page
 */
require_once '../includes/auth.php';

// Fetch Requests
// Join users to get requester name
$stmt = $pdo->query("SELECT r.*, u.full_name FROM service_requests r LEFT JOIN users u ON r.user_id = u.id ORDER BY r.created_at DESC");
$requests = $stmt->fetchAll();

// Current User (if logged in)
$currentUser = getCurrentUser();

// System logo for favicon
$faviconPath = '../img/default_logo.png';
try {
    $stLogo = $pdo->query("SELECT logo_path FROM system_settings WHERE id = 1")->fetchColumn();
    if (!empty($stLogo) && file_exists(__DIR__ . '/../' . $stLogo)) {
        $faviconPath = '../' . $stLogo;
    }
} catch (Exception $e) {
}

// Helper for Duration
function getDuration($date)
{
    if (!$date)
        return '-';
    $start = new DateTime($date);
    $now = new DateTime();
    $diff = $start->diff($now);

    if ($diff->days > 0) {
        return $diff->days . ' วัน ' . $diff->h . ' ชม.';
    } elseif ($diff->h > 0) {
        return $diff->h . ' ชม. ' . $diff->i . ' นาที';
    } else {
        return $diff->i . ' นาที';
    }
}

// Helper for Status Badge
function getStatusBadge($status)
{
    if ($status == 'pending')
        return '<span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-lg text-xs font-bold border border-yellow-200">รอตรวจสอบ</span>';
    if ($status == 'approved' || $status == 'in_progress')
        return '<span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-lg text-xs font-bold border border-blue-200">กำลังดำเนินการ</span>';
    if ($status == 'completed')
        return '<span class="px-2 py-1 bg-green-100 text-green-800 rounded-lg text-xs font-bold border border-green-200">เสร็จสิ้น</span>';
    if ($status == 'rejected')
        return '<span class="px-2 py-1 bg-red-100 text-red-800 rounded-lg text-xs font-bold border border-red-200">ยกเลิก/ไม่อนุมัติ</span>';
    return '<span class="px-2 py-1 bg-gray-100 text-gray-800 rounded-lg text-xs font-bold border border-gray-200">' . htmlspecialchars($status) . '</span>';
}

// Helper for Type Badge
function getTypeBadge($type)
{
    if ($type == 'repair')
        return '<span class="px-2 py-1 bg-orange-100 text-orange-800 rounded-lg text-xs font-bold border border-orange-200"><i class="fas fa-tools mr-1"></i>ซ่อม</span>';
    if ($type == 'purchase')
        return '<span class="px-2 py-1 bg-green-100 text-green-800 rounded-lg text-xs font-bold border border-green-200"><i class="fas fa-cart-shopping mr-1"></i>ซื้อ</span>';
    return $type;
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ติดตามสถานะแจ้งซ่อม/จัดซื้อ | Parcel System</title>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($faviconPath) ?>">
    <link rel="shortcut icon" type="image/png" href="<?= htmlspecialchars($faviconPath) ?>">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($faviconPath) ?>">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>

    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f3f4f6;
        }

        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            margin: 1rem 0;
            font-size: 0.875rem;
            color: #4b5563;
        }

        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            padding: 0.5rem;
            margin-left: 0.5rem;
        }

        table.dataTable.no-footer {
            border-bottom: 1px solid #e5e7eb;
        }

        table.dataTable thead th,
        table.dataTable thead td {
            border-bottom: 1px solid #e5e7eb;
        }
    </style>
</head>

<body class="bg-gray-50 text-gray-800">

    <!-- Navbar -->
    <!-- Navbar -->
    <nav class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-50">
        <div class="w-full px-4">
            <div class="flex justify-between h-16">
                <!-- Logo -->
                <div class="flex items-center gap-3">
                    <div
                        class="bg-blue-600 w-10 h-10 rounded-xl flex items-center justify-center text-white shadow-lg shadow-blue-200">
                        <i class="fas fa-boxes-stacked text-xl"></i>
                    </div>
                    <div>
                        <h1 class="text-lg font-bold text-gray-800 leading-tight">ระบบติดตามสถานะ</h1>
                        <p class="text-xs text-gray-500">แจ้งซ่อม/จัดซื้อครุภัณฑ์</p>
                    </div>
                </div>

                <!-- Right Side -->
                <!-- <div class="flex items-center gap-4">
                    <a href="../login.php" class="text-sm font-medium text-blue-600 hover:text-blue-800 transition">
                        <?php if ($currentUser): ?>
                            <i class="fas fa-user-circle mr-1"></i>
                            <?= htmlspecialchars($currentUser['full_name'] ?? $currentUser['username']) ?>
                        <?php else: ?>
                            <i class="fas fa-sign-in-alt mr-1"></i> เข้าสู่ระบบเจ้าหน้าที่
                        <?php endif; ?>
                    </a>
                </div> -->
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="w-full px-4 py-8 min-h-screen bg-gray-50/50">

        <!-- Header Section -->
        <div
            class="mb-8 text-center sm:text-left sm:flex sm:items-end sm:justify-between bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
            <div>
                <h2 class="text-3xl font-extrabold text-gray-900">
                    <span class="bg-clip-text text-transparent bg-gradient-to-r from-blue-600 to-indigo-600">
                        รายการแจ้งซ่อม/จัดซื้อล่าสุด
                    </span>
                </h2>
                <p class="mt-2 text-sm text-gray-500">ตรวจสอบสถานะการดำเนินงานแบบเรียลไทม์</p>
            </div>
            <div class="mt-4 sm:mt-0 flex flex-col items-end gap-2">
                <span
                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-100">
                    <span class="w-2 h-2 mr-2 bg-blue-500 rounded-full animate-pulse"></span>
                    สถานะ: ออนไลน์
                </span>
                <span class="text-xs text-gray-400">อัปเดตล่าสุด: <?= date('H:i:s') ?></span>
            </div>
        </div>

        <!-- Use strict generic table -->
        <div class="bg-white rounded-2xl shadow-lg border border-gray-200 overflow-hidden">
            <div class="p-2">
                <table id="trackingTable" class="w-full text-left border-collapse hover">
                    <thead>
                        <tr class="bg-gray-50 text-gray-600 text-sm uppercase tracking-wider border-b border-gray-200">
                            <th class="py-4 px-4 font-semibold rounded-tl-lg">วันที่แจ้ง</th>
                            <th class="py-4 px-4 font-semibold">ประเภท</th>
                            <th class="py-4 px-4 font-semibold">ผู้แจ้ง</th>
                            <th class="py-4 px-4 font-semibold">รายการ</th>
                            <th class="py-4 px-4 font-semibold">ที่ตั้ง</th>
                            <th class="py-4 px-4 font-semibold w-1/4">อาการ/เหตุผล</th>
                            <th class="py-4 px-4 font-semibold text-center">ระยะเวลา</th>
                            <th class="py-4 px-4 font-semibold text-center">สถานะ</th>
                            <th class="py-4 px-4 font-semibold text-center rounded-tr-lg">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm divide-y divide-gray-100">
                        <!-- Data via Ajax -->
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Script -->
    <script>
        $(document).ready(function () {
            // Helper functions for renderer
            const formatDate = (dateString) => {
                if (!dateString) return '-';
                const date = new Date(dateString);
                const day = date.getDate().toString().padStart(2, '0');
                const month = (date.getMonth() + 1).toString().padStart(2, '0');
                const year = (date.getFullYear() + 543);
                const time = date.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' });
                return `${day}/${month}/${year} ${time}`;
            };

            const getDuration = (dateString) => {
                if (!dateString) return '-';
                const start = new Date(dateString);
                const now = new Date();
                const diffMs = now - start;

                const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
                const diffHrs = Math.floor((diffMs % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const diffMins = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));

                if (diffDays > 0) return `${diffDays} วัน ${diffHrs} ชม.`;
                if (diffHrs > 0) return `${diffHrs} ชม. ${diffMins} นาที`;
                return `${diffMins} นาที`;
            };

            const getTypeBadge = (type) => {
                if (type === 'repair') return '<span class="px-2 py-1 bg-orange-100 text-orange-800 rounded-lg text-xs font-bold border border-orange-200"><i class="fas fa-tools mr-1"></i>ซ่อม</span>';
                if (type === 'purchase') return '<span class="px-2 py-1 bg-green-100 text-green-800 rounded-lg text-xs font-bold border border-green-200"><i class="fas fa-cart-shopping mr-1"></i>ซื้อ</span>';
                return type;
            };

            const getStatusBadge = (status) => {
                const badges = {
                    'pending': '<span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-lg text-xs font-bold border border-yellow-200">รอตรวจสอบ</span>',
                    'in_progress': '<span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-lg text-xs font-bold border border-blue-200">กำลังดำเนินการ</span>',
                    'completed': '<span class="px-2 py-1 bg-green-100 text-green-800 rounded-lg text-xs font-bold border border-green-200">เสร็จสิ้น</span>',
                    'rejected': '<span class="px-2 py-1 bg-red-100 text-red-800 rounded-lg text-xs font-bold border border-red-200">ยกเลิก/ไม่อนุมัติ</span>'
                };
                return badges[status] || `<span class="px-2 py-1 bg-gray-100 text-gray-800 rounded-lg text-xs font-bold border border-gray-200">${status}</span>`;
            };

            const table = $('#trackingTable').DataTable({
                "ajax": "../api/get_tracking_data.php",
                "pageLength": 10,
                "lengthMenu": [10, 25, 50, 100],
                "order": [[0, "desc"]],
                "language": {
                    "search": "ค้นหา:",
                    "lengthMenu": "แสดง _MENU_ รายการ",
                    "info": "แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ",
                    "paginate": { "first": "หน้าแรก", "last": "หน้าสุดท้าย", "next": "ถัดไป", "previous": "ก่อนหน้า" },
                    "emptyTable": "ไม่พบข้อมูลการแจ้งซ่อม/จัดซื้อ"
                },
                "columns": [
                    {
                        "data": "created_at",
                        "render": function (data) {
                            return `<div class="text-gray-600 whitespace-nowrap group-hover:text-blue-600"><i class="far fa-calendar-alt mr-1.5 opacity-50"></i>${formatDate(data)}</div>`;
                        }
                    },
                    {
                        "data": "request_type",
                        "render": function (data) { return getTypeBadge(data); }
                    },
                    {
                        "data": null,
                        "render": function (data) {
                            const name = data.full_name || data.fname_lname || 'Unknown';
                            const initial = name.charAt(0).toUpperCase();
                            return `<div class="flex items-center gap-2">
                                        <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 text-xs font-bold border border-gray-200">${initial}</div>
                                        <span class="font-medium text-gray-700 group-hover:text-gray-900 transition-colors">${name}</span>
                                    </div>`;
                        }
                    },
                    {
                        "data": "asset_name",
                        "className": "text-blue-700 font-semibold group-hover:text-blue-800"
                    },
                    {
                        "data": "asset_location",
                        "className": "text-gray-600 font-medium",
                        "render": function (data) {
                            return data ? `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800"><i class="fas fa-map-marker-alt mr-1 text-gray-400"></i>${data}</span>` : '<span class="text-gray-400">-</span>';
                        }
                    },
                    {
                        "data": "details",
                        "className": "text-gray-600 text-sm leading-relaxed",
                        "render": function (data) {
                            return `<div class="line-clamp-2" title="${data}">${data}</div>`;
                        }
                    },
                    {
                        "data": "created_at",
                        "className": "text-center",
                        "render": function (data) {
                            return `<div class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 border border-gray-200"><i class="far fa-clock mr-1.5 text-gray-400"></i>${getDuration(data)}</div>`;
                        }
                    },
                    {
                        "data": "status",
                        "className": "text-center",
                        "render": function (data) { return getStatusBadge(data); }
                    },
                    {
                        "data": null,
                        "className": "text-center",
                        "render": function (data) {
                            // Escape data for function call
                            const safeData = JSON.stringify(data).replace(/"/g, '&quot;');
                            const safeName = (data.asset_name || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');

                            return `<div class="flex items-center justify-center gap-2">
                                        <button onclick='openViewModal(${safeData})' class="inline-flex items-center justify-center w-8 h-8 rounded-full text-blue-600 bg-blue-50 hover:bg-blue-600 hover:text-white transition-all shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500" title="ดูรายละเอียด"><i class="fas fa-eye text-sm"></i></button>
                                        <button onclick="openReportModal(${data.id}, '${data.status}', '${safeName}')" class="inline-flex items-center justify-center w-8 h-8 rounded-full text-indigo-600 bg-indigo-50 hover:bg-indigo-600 hover:text-white transition-all shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" title="รายงานผล"><i class="fas fa-edit text-sm"></i></button>
                                    </div>`;
                        }
                    }
                ],
                "createdRow": function (row, data, dataIndex) {
                    const status = data.status;
                    const created = new Date(data.created_at);
                    const now = new Date();
                    const diffTime = Math.abs(now - created);
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                    const isOverdue = (diffDays > 3 && status !== 'completed' && status !== 'rejected');

                    if (isOverdue) {
                        $(row).addClass('bg-red-50 hover:bg-red-100');
                    } else {
                        $(row).addClass('hover:bg-blue-50/30');
                    }
                    $(row).addClass('transition-colors duration-150 group');
                }
            });

            // Polling every 10 seconds
            setInterval(function () {
                table.ajax.reload(null, false); // User paging is not reset on reload
            }, 10000);
        });

        function openReportModal(id, currentStatus, assetName) {
            Swal.fire({
                title: 'บันทึกผลการดำเนินงาน',
                html: `
                    <div class="space-y-5 text-left">
                        <!-- Item Header -->
                        <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 flex items-start gap-4 shadow-sm">
                            <div class="bg-blue-100 text-blue-600 rounded-lg w-12 h-12 flex items-center justify-center flex-shrink-0 shadow-inner">
                                <i class="fas fa-box-open text-xl"></i>
                            </div>
                            <div>
                                <p class="text-xs text-blue-600 font-bold uppercase tracking-wide mb-0.5">รายการแจ้งซ่อม/จัดซื้อ</p>
                                <p class="text-gray-800 font-bold text-lg leading-tight">${assetName}</p>
                            </div>
                        </div>

                        <form id="reportForm" class="space-y-4">
                            <!-- Reporter Name -->
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1.5 flex items-center gap-2">
                                    <i class="fas fa-user-tag text-indigo-500"></i>
                                    ผู้รายงานผล <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="reporterName" 
                                    class="w-full px-4 py-3 bg-gray-50 border border-gray-300 rounded-lg text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent focus:bg-white transition-all shadow-sm"
                                    placeholder="ระบุชื่อ-นามสกุลของท่าน" required>
                            </div>

                            <!-- Status -->
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1.5 flex items-center gap-2">
                                    <i class="fas fa-tasks text-indigo-500"></i>
                                    สถานะการดำเนินงาน <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <select id="updateStatus" 
                                        class="w-full pl-4 pr-10 py-3 bg-gray-50 border border-gray-300 rounded-lg text-gray-900 appearance-none focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent focus:bg-white transition-all shadow-sm cursor-pointer">
                                        <option value="pending" ${currentStatus === 'pending' ? 'selected' : ''}>รอตรวจสอบ</option>
                                        <option value="in_progress" ${currentStatus === 'in_progress' ? 'selected' : ''}>กำลังดำเนินการ</option>
                                        <option value="completed" ${currentStatus === 'completed' ? 'selected' : ''}>เสร็จสิ้น</option>
                                        <option value="rejected" ${currentStatus === 'rejected' ? 'selected' : ''}>ยกเลิก/ไม่อนุมัติ</option>
                                    </select>
                                    <div class="absolute inset-y-0 right-0 flex items-center px-4 pointer-events-none text-gray-500">
                                        <i class="fas fa-chevron-down text-xs"></i>
                                    </div>
                                </div>
                            </div>

                            <!-- Details/Note -->
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1.5 flex items-center gap-2">
                                    <i class="fas fa-comment-alt text-indigo-500"></i>
                                    รายละเอียด/หมายเหตุ <span class="text-red-500">*</span>
                                </label>
                                <textarea id="updateNote" rows="4" 
                                    class="w-full px-4 py-3 bg-gray-50 border border-gray-300 rounded-lg text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent focus:bg-white transition-all shadow-sm resize-none text-base"
                                    placeholder="ระบุรายละเอียดผลการดำเนินงาน..." required></textarea>
                            </div>
                        </form>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'บันทึกผล',
                cancelButtonText: 'ยกเลิก',
                confirmButtonColor: '#4f46e5',
                preConfirm: () => {
                    const reporterName = document.getElementById('reporterName').value.trim();
                    const status = document.getElementById('updateStatus').value;
                    const note = document.getElementById('updateNote').value.trim();

                    if (!reporterName) {
                        Swal.showValidationMessage('กรุณาระบุชื่อผู้รายงานผล');
                        return false;
                    }
                    if (!note) {
                        Swal.showValidationMessage('กรุณาระบุรายละเอียด/หมายเหตุ');
                        return false;
                    }

                    return fetch('../api/update_tracking_status.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `id=${id}&status=${status}&note=${encodeURIComponent(note)}&reporter_name=${encodeURIComponent(reporterName)}`
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (!data.success) throw new Error(data.message);
                            return data;
                        })
                        .catch(error => {
                            Swal.showValidationMessage(`Request failed: ${error}`);
                        });
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire('บันทึกสำเร็จ!', '', 'success').then(() => location.reload());
                }
            });
        }
        function openViewModal(data) {
            // Helper to format date
            const formatDate = (dateString) => {
                if (!dateString) return '-';
                const date = new Date(dateString);
                const day = date.getDate().toString().padStart(2, '0');
                const month = (date.getMonth() + 1).toString().padStart(2, '0');
                const year = (date.getFullYear() + 543);
                const time = date.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' });
                return `${day}/${month}/${year} ${time}`;
            };

            const statusBadges = {
                'pending': '<span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-lg text-xs font-bold border border-yellow-200">รอตรวจสอบ</span>',
                'in_progress': '<span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-lg text-xs font-bold border border-blue-200">กำลังดำเนินการ</span>',
                'completed': '<span class="px-2 py-1 bg-green-100 text-green-800 rounded-lg text-xs font-bold border border-green-200">เสร็จสิ้น</span>',
                'rejected': '<span class="px-2 py-1 bg-red-100 text-red-800 rounded-lg text-xs font-bold border border-red-200">ยกเลิก/ไม่อนุมัติ</span>'
            };

            Swal.fire({
                title: 'รายละเอียดการแจ้งซ่อม/จัดซื้อ',
                width: '80%',
                padding: '2rem',
                showConfirmButton: false,
                showCloseButton: true,
                html: `
                    <div class="text-left mt-2">
                        <!-- Top Header Info -->
                        <div class="bg-gray-50 rounded-xl p-5 border border-gray-100 shadow-sm mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                            <div class="flex items-center gap-4">
                                <div class="bg-white p-3 rounded-lg shadow-sm border border-gray-100 text-blue-600">
                                    <i class="fas fa-file-invoice text-2xl"></i>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 font-semibold uppercase tracking-wider">รหัสรายการ</p>
                                    <p class="text-xl font-bold text-gray-800">#${data.id}</p>
                                </div>
                            </div>
                            <div class="flex flex-col items-end">
                                <p class="text-xs text-gray-500 mb-1">สถานะปัจจุบัน</p>
                                <div>${statusBadges[data.status] || data.status}</div>
                            </div>
                        </div>

                        <!-- 2 Column Grid -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            
                            <!-- Left Column: Request Info -->
                            <div class="space-y-6">
                                <h3 class="text-lg font-bold text-gray-800 border-b pb-2 flex items-center gap-2">
                                    <i class="fas fa-info-circle text-blue-500"></i> ข้อมูลการแจ้ง
                                </h3>
                                
                                <div class="grid grid-cols-1 gap-4">
                                    <div class="bg-white p-4 rounded-lg border border-gray-100 shadow-sm">
                                        <p class="text-sm text-gray-500 mb-1"><i class="far fa-calendar-alt mr-1"></i> วันที่แจ้ง</p>
                                        <p class="font-medium text-gray-800">${formatDate(data.created_at)}</p>
                                    </div>
                                    <div class="bg-white p-4 rounded-lg border border-gray-100 shadow-sm">
                                        <p class="text-sm text-gray-500 mb-1"><i class="fas fa-user mr-1"></i> ผู้แจ้ง</p>
                                        <p class="font-medium text-gray-800">${data.full_name || data.fname_lname || 'ไม่ระบุ'}</p>
                                    </div>
                                    <div class="bg-white p-4 rounded-lg border border-gray-100 shadow-sm">
                                        <p class="text-sm text-gray-500 mb-1"><i class="fas fa-box mr-1"></i> รายการ/ครุภัณฑ์</p>
                                        <p class="font-medium text-blue-600 text-lg">${data.asset_name}</p>
                                    </div>
                                    <div class="bg-white p-4 rounded-lg border border-gray-100 shadow-sm">
                                        <p class="text-sm text-gray-500 mb-1"><i class="fas fa-map-marker-alt mr-1"></i> ที่ตั้ง</p>
                                        <p class="font-medium text-gray-800">${data.asset_location || '-'}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Column: Details & Response -->
                            <div class="space-y-6">
                                <h3 class="text-lg font-bold text-gray-800 border-b pb-2 flex items-center gap-2">
                                    <i class="fas fa-align-left text-indigo-500"></i> รายละเอียด
                                </h3>

                                <div class="space-y-4">
                                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                        <p class="text-sm text-gray-500 font-bold mb-2">อาการ/เหตุผลความจำเป็น:</p>
                                        <p class="text-gray-700 leading-relaxed whitespace-pre-wrap">${data.details || '-'}</p>
                                    </div>

                                    ${data.admin_note ? `
                                    <div class="bg-blue-50 p-4 rounded-lg border border-blue-100">
                                        <p class="text-sm text-blue-600 font-bold mb-2"><i class="fas fa-clipboard-check mr-1"></i> บันทึกจากเจ้าหน้าที่/ผลการดำเนินงาน:</p>
                                        <p class="text-gray-800 leading-relaxed whitespace-pre-wrap">${data.admin_note}</p>
                                        ${data.updated_at ? `<p class="text-xs text-blue-400 mt-2 text-right">อัปเดตเมื่อ: ${formatDate(data.updated_at)}</p>` : ''}
                                    </div>
                                    ` : ''}
                                </div>
                            </div>

                        </div>
                    </div>
                `
            });
        }
    </script>
</body>

</html>
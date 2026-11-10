<?php
/**
 * User Manual Page
 */
require_once 'includes/auth.php';
requireLogin();

$pageTitle = 'คู่มือการใช้งานระบบ';
include 'includes/header.php';
include 'includes/sidebar.php';

// Define manual steps based on assumed flow 1-18
$steps = [
    1 => [
        'title' => 'การเข้าสู่ระบบ (Login)',
        'desc' => 'ผู้ใช้งานกรอกชื่อผู้ใช้และรหัสผ่านเพื่อเข้าสู่ระบบ หากข้อมูลถูกต้องระบบจะนำท่านไปยังหน้าหลัก (Dashboard)'
    ],
    2 => [
        'title' => 'หน้าหลัก (Dashboard)',
        'desc' => 'แสดงภาพรวมของระบบ เช่น จำนวนครุภัณฑ์ทั้งหมด, สถานะครุภัณฑ์, รายการรอโอนย้าย และกราฟสรุปข้อมูลต่างๆ'
    ],
    3 => [
        'title' => 'เมนูใช้งาน (Sidebar)',
        'desc' => 'แถบเมนูทางซ้ายมือใช้สำหรับเข้าถึงฟังก์ชันต่างๆ ของระบบ เช่น จัดการครุภัณฑ์, โอนย้าย, ตรวจสอบสถานะ ฯลฯ'
    ],
    4 => [
        'title' => 'รายการครุภัณฑ์ (Asset List)',
        'desc' => 'หน้าแสดงรายชื่อครุภัณฑ์ทั้งหมดในระบบ สามารถค้นหา กรองตามหมวดหมู่ หรือสถานะได้'
    ],
    5 => [
        'title' => 'การเพิ่มครุภัณฑ์ใหม่ (Add Asset)',
        'desc' => 'กดปุ่ม "เพิ่มครุภัณฑ์" เพื่อเปิดฟอร์มสำหรับลงทะเบียนครุภัณฑ์ใหม่เข้าสู่ระบบ'
    ],
    6 => [
        'title' => 'กรอกข้อมูลครุภัณฑ์',
        'desc' => 'ระบุรายละเอียดให้ครบถ้วน เช่น รหัส, ชื่อ, ยี่ห้อ, ราคา, วันที่ซื้อ และสถานที่ตั้ง'
    ],
    7 => [
        'title' => 'รายละเอียดครุภัณฑ์',
        'desc' => 'เมื่อคลิกที่ชื่อหรือไอคอนดูรายละเอียด จะแสดงข้อมูลเชิงลึกของครุภัณฑ์ชิ้นนั้นๆ'
    ],
    8 => [
        'title' => 'การแก้ไขข้อมูล',
        'desc' => 'ผู้ดูแลสามารถแก้ไขข้อมูลครุภัณฑ์ได้โดยกดปุ่มแก้ไข และบันทึกข้อมูลใหม่'
    ],
    9 => [
        'title' => 'ระบบโอนย้าย (Transfer)',
        'desc' => 'เลือกครุภัณฑ์ที่ต้องการโอนย้ายไปยังผู้ถือครองอื่น หรือย้ายสถานที่'
    ],
    10 => [
        'title' => 'อนุมัติการโอนย้าย',
        'desc' => 'ผู้มีสิทธิ์ (Admin/หน.พัสดุ) จะเห็นรายการคำขอและสามารถกดอนุมัติหรือปฏิเสธได้'
    ],
    11 => [
        'title' => 'ระบบจัดซื้อจัดจ้าง (Procurement)',
        'desc' => 'หน้าแสดงรายการจัดซื้อ/จัดจ้าง หรือซ่อมบำรุงพัสดุครุภัณฑ์'
    ],
    12 => [
        'title' => 'สร้างรายการขอซื้อ/จ้าง',
        'desc' => 'กรอกแบบฟอร์มเพื่อสร้างคำขออนุมัติจัดซื้อหรือซ่อมแซม พร้อมระบุรายละเอียดและราคา'
    ],
    13 => [
        'title' => 'คณะกรรมการตรวจสอบ',
        'desc' => 'จัดการแต่งตั้งคณะกรรมการสำหรับตรวจรับพัสดุหรือตรวจสอบครุภัณฑ์ประจำปี'
    ],
    14 => [
        'title' => 'โครงการตรวจสอบ (Inspection)',
        'desc' => 'สร้างโครงการตรวจสอบครุภัณฑ์ประจำปี กำหนดช่วงเวลาและคณะกรรมการ'
    ],
    15 => [
        'title' => 'บันทึกผลการตรวจสอบ',
        'desc' => 'คณะกรรมการสามารถบันทึกสถานะของครุภัณฑ์แต่ะรายการว่า ปกติ, ชำรุด หรือสูญหาย'
    ],
    16 => [
        'title' => 'หน้าโปรไฟล์',
        'desc' => 'เป็นหน้าแสดงข้อมูลส่วนตัวของผู้ใช้งานและสามารถแก้ไขข้อมูลส่วนตัวพร้อมทั้งเปลี่ยนรหัสผ่านได้'
    ],
    17 => [
        'title' => 'จัดการผู้ใช้งาน',
        'desc' => 'หน้าจัดการผู้ใช้งาน เพิ่ม ลบ แก้ไข ผู้ใช้งานได้'
    ],
    18 => [
        'title' => 'ตั้งค่าระบบ',
        'desc' => 'หน้าตั้งค่าระบบ สามารถตั้งค่าชื่อระบบ หน่วยงาน หรือตั้งค่าอื่นๆได้'
    ]
];
?>

<div class="max-w-5xl mx-auto">
    <div class="flex flex-col md:flex-row items-center justify-between gap-4 mb-6 print:hidden">
        <!-- Title Section -->
        <div class="flex items-center gap-4">
            <div class="w-16 h-16 bg-blue-100 rounded-2xl flex items-center justify-center text-blue-900 shadow-sm">
                <i class="fas fa-book-reader text-3xl"></i>
            </div>
            <div>
                <h1 class="text-2xl font-extrabold text-gray-800">คู่มือการใช้งานระบบ</h1>
                <p class="text-gray-500">รวมขั้นตอนการใช้งานฟังก์ชันต่างๆ ของระบบบริหารจัดการครุภัณฑ์</p>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center gap-3">
            <button onclick="generatePDF()"
                class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg shadow-sm hover:shadow transition-all flex items-center gap-2 font-medium btn-press">
                <i class="fas fa-file-pdf"></i>
                <span>Preview PDF</span>
            </button>
            <button onclick="window.print()"
                class="bg-blue-900 hover:bg-blue-800 text-white px-4 py-2 rounded-lg shadow-sm hover:shadow transition-all flex items-center gap-2 font-medium btn-press">
                <i class="fas fa-print"></i>
                <span>พิมพ์คู่มือ</span>
            </button>
        </div>
    </div>

    <div id="manual-content"
        class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 mb-8 print:shadow-none print:border-0 print:p-0">
        <!-- Print Header (Visible only in Print/PDF) -->
        <div class="hidden print:block mb-8 text-center border-b pb-6">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">คู่มือการใช้งานระบบ</h1>
            <p class="text-gray-500">ระบบบริหารจัดการจัดซื้อจัดจ้างและตรวจสอบครุภัณฑ์สำนักงาน (GPAMS)</p>
        </div>

        <!-- Content Grid -->
        <div class="grid grid-cols-1 gap-8">
            <?php foreach ($steps as $i => $step): ?>
                <?php
                $img = "img/manual/{$i}.png";
                $diskPath = __DIR__ . '/img/manual/' . $i . '.png';
                $hasImg = file_exists($diskPath);
                ?>
                <div
                    class="step-item bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-all duration-300 group print:shadow-none print:border print:border-gray-200 print:break-inside-avoid">
                    <!-- Header -->
                    <div
                        class="px-6 py-4 border-b border-gray-50 flex items-center justify-between bg-gray-50/50 print:bg-gray-100">
                        <div class="flex items-center gap-3">
                            <span
                                class="flex items-center justify-center w-8 h-8 rounded-lg bg-blue-900 text-white font-bold shadow-sm text-sm print:bg-black print:text-white">
                                <?= $i ?>
                            </span>
                            <h3 class="font-bold text-lg text-gray-800">
                                <?= htmlspecialchars($step['title']) ?>
                            </h3>
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="p-6">
                        <!-- Image -->
                        <?php if ($hasImg): ?>
                            <div class="manual-image-container rounded-xl overflow-hidden border border-gray-100 shadow-sm mb-4 bg-gray-100 cursor-pointer relative group-hover:shadow-md transition-all print:shadow-none print:border-0"
                                onclick="viewImage('<?= $img ?>', '<?= addslashes($step['title']) ?>')">
                                <img src="<?= $img ?>?v=<?= time() ?>" alt="Step <?= $i ?>"
                                    class="w-full h-auto object-contain max-h-[500px] hover:scale-[1.01] transition-transform duration-500 print:max-h-[300px]">
                                <div
                                    class="absolute inset-0 bg-black/0 group-hover:bg-black/5 transition-colors flex items-center justify-center opacity-0 group-hover:opacity-100 print:hidden">
                                    <span
                                        class="bg-white/90 text-gray-800 px-3 py-1 rounded-full text-xs font-bold shadow-sm backdrop-blur">
                                        <i class="fas fa-search-plus mr-1"></i> ขยาย
                                    </span>
                                </div>
                            </div>
                        <?php else: ?>
                            <div
                                class="h-32 bg-gray-50 rounded-xl border-2 border-dashed border-gray-200 flex flex-col items-center justify-center text-gray-400 mb-4 print:hidden">
                                <i class="fas fa-image text-3xl mb-2 opacity-30"></i>
                                <span class="text-xs">ไม่พบรูปภาพประกอบ</span>
                            </div>
                        <?php endif; ?>

                        <!-- Description -->
                        <div
                            class="prose prose-blue max-w-none text-gray-600 text-sm leading-relaxed bg-blue-50/30 p-4 rounded-xl border border-blue-50 print:bg-white print:border-0 print:p-0 print:text-black">
                            <i class="fas fa-info-circle text-blue-400 mr-2 print:hidden"></i>
                            <?= htmlspecialchars($step['desc']) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
    function viewImage(src, title) {
        Swal.fire({
            title: title,
            imageUrl: src,
            imageAlt: title,
            width: '80%',
            padding: '1em',
            confirmButtonText: 'ปิด',
            confirmButtonColor: '#1e3a8a',
            customClass: {
                popup: 'rounded-2xl',
                image: 'rounded-xl shadow-lg border border-gray-200'
            }
        });
    }

    function generatePDF() {
        // Show loading
        Swal.fire({
            title: 'กำลังสร้างไฟล์ PDF...',
            text: 'กรุณารอสักครู่ ระบบกำลังประมวลผล',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        const element = document.getElementById('manual-content');

        // Options for html2pdf
        const opt = {
            margin: [10, 10, 10, 10], // top, left, bottom, right
            filename: 'GPAMS_User_Manual.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true, logging: false },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
            pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
        };

        // Generate PDF
        html2pdf().set(opt).from(element).toPdf().get('pdf').then(function (pdf) {
            // Create blob URL for preview
            const blob = pdf.output('blob');
            const url = URL.createObjectURL(blob);

            // Close loading
            Swal.close();

            // Open Preview Modal
            Swal.fire({
                title: '<strong>ตัวอย่างไฟล์ PDF</strong>',
                html: `
                    <div style="height: 80vh;">
                        <iframe src="${url}" style="width: 100%; height: 100%; border: none; border-radius: 8px;"></iframe>
                    </div>
                `,
                width: '90%',
                showCloseButton: true,
                showConfirmButton: true,
                confirmButtonText: '<i class="fas fa-download"></i> ดาวน์โหลด',
                confirmButtonColor: '#1e3a8a',
                showCancelButton: true,
                cancelButtonText: 'ปิด',
                customClass: {
                    popup: 'swal2-pdf-preview'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Trigger download
                    html2pdf().set(opt).from(element).save();
                }
            });
        }).catch(err => {
            console.error(err);
            Swal.fire({
                icon: 'error',
                title: 'เกิดข้อผิดพลาด',
                text: 'ไม่สามารถสร้างไฟล์ PDF ได้ กรุณาลองใหม่อีกครั้ง',
                confirmButtonColor: '#d33'
            });
        });
    }
</script>

<style>
    /* Print Styles */
    @media print {
        @page {
            margin: 20mm;
            size: A4;
        }

        body * {
            visibility: hidden;
        }

        #manual-content,
        #manual-content * {
            visibility: visible;
        }

        #manual-content {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            margin: 0;
            padding: 0;
            background: white !important;
            box-shadow: none !important;
            border: none !important;
        }

        .no-print {
            display: none !important;
        }

        /* Ensure images and text are black/white safe or high contrast */
        img {
            max-width: 100% !important;
            page-break-inside: avoid;
        }

        .step-item {
            break-inside: avoid;
            page-break-inside: avoid;
            margin-bottom: 20px;
            border: 1px solid #ddd;
        }
    }

    /* Custom Scrollbar for PDF Preview */
    .swal2-pdf-preview .swal2-html-container {
        margin: 10px 0 0 !important;
        padding: 0 !important;
        overflow: hidden;
    }
</style>

<?php include 'includes/footer.php'; ?>
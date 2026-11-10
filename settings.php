<?php
/**
 * System Settings Page
 */
require_once 'includes/auth.php';
requireLogin();
requireRole(['admin']);

$pageTitle = 'ตั้งค่าระบบ';
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!-- CropperJS CSS -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet">


<div class="w-full px-6">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center gap-3 text-gray-800 mb-2">
            <!-- <button onclick="history.back()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="fas fa-arrow-left"></i>
            </button> -->
            <h1 class="text-2xl font-bold">จัดการระบบ (System Management)</h1>
        </div>
        <p class="text-gray-500 text-sm ml-7">ตั้งค่าโลโก้ ชื่อระบบ และข้อมูลพื้นฐานผู้ใช้งาน</p>
    </div>

    <!-- Settings Form -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <!-- Section Header -->
        <div class="bg-green-50 px-6 py-4 flex items-center gap-4 border-b border-green-100">
            <div
                class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center text-green-600 font-bold text-lg">
                1</div>
            <h2 class="text-xl font-bold text-gray-800">ตั้งค่าข้อมูลระบบ (Branding)</h2>
        </div>

        <div class="p-8">
            <form id="settingsForm" class="flex flex-col md:flex-row gap-8">

                <!-- Logo Upload Section -->
                <div class="w-full md:w-1/3 flex flex-col items-center">
                    <div class="relative group cursor-pointer" onclick="document.getElementById('logoInput').click()">
                        <div class="w-64 h-64 bg-gray-50 border-2 border-dashed border-gray-300 rounded-xl flex flex-col items-center justify-center overflow-hidden hover:border-blue-400 transition-colors"
                            id="logoPreviewContainer">
                            <img id="currentLogo" src="img/default_logo.png"
                                class="max-w-full max-h-full object-contain p-4 transition-transform group-hover:scale-105"
                                alt="System Logo">
                            <div
                                class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                <span class="text-white font-medium text-sm"><i
                                        class="fas fa-camera mr-2"></i>อัปโหลดโลโก้</span>
                            </div>
                        </div>
                        <p class="text-xs text-center text-gray-400 mt-3 group-hover:text-blue-600">
                            คลิกเพื่ออัปโหลดรูปภาพใหม่ (JPG, PNG)</p>
                    </div>
                    <input type="file" id="logoInput" accept="image/png, image/jpeg" class="hidden"
                        onchange="previewImage(this)">
                </div>

                <!-- Form Inputs -->
                <div class="flex-1 space-y-6">

                    <!-- Organization Name -->
                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">ชื่อหน่วยงาน (Organization Name)</label>
                            <div class="relative">
                                <span class="absolute left-4 top-3 text-gray-400"><i class="fas fa-building"></i></span>
                                <input type="text" name="agency_name" id="agencyName"
                                    class="w-full pl-10 pr-4 py-3 bg-gray-50 border-2 border-gray-100 rounded-xl text-gray-800 font-medium focus:border-green-500 focus:bg-white focus:outline-none transition-all placeholder-gray-300"
                                    placeholder="ระบุชื่อหน่วยงาน เช่น วิทยาลัยเทคนิคอุดรธานี">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">ชื่อระบบ (ภาษาไทย)</label>
                            <div class="relative">
                                <span class="absolute left-4 top-3 text-gray-400"><i class="fas fa-font"></i></span>
                                <input type="text" name="org_name" id="orgName"
                                    class="w-full pl-10 pr-4 py-3 bg-gray-50 border-2 border-gray-100 rounded-xl text-gray-800 font-medium focus:border-green-500 focus:bg-white focus:outline-none transition-all placeholder-gray-300"
                                    placeholder="ระบุชื่อระบบ">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">ชื่อระบบ (ภาษาอังกฤษ)</label>
                            <div class="relative">
                                <span class="absolute left-4 top-3 text-gray-400"><i class="fas fa-globe"></i></span>
                                <input type="text" name="org_name_en" id="orgNameEn"
                                    class="w-full pl-10 pr-4 py-3 bg-gray-50 border-2 border-gray-100 rounded-xl text-gray-800 font-medium focus:border-green-500 focus:bg-white focus:outline-none transition-all placeholder-gray-300"
                                    placeholder="Ex. Asset Management System">
                            </div>
                        </div>
                    </div>

                    <!-- Theme Selection -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-3">ธีมสีของระบบ (System Theme)</label>
                        <div class="flex flex-wrap gap-4">
                            <!-- Theme Option: Blue (Default) -->
                            <label class="cursor-pointer group">
                                <input type="radio" name="theme_color" value="blue" class="peer hidden" checked>
                                <div
                                    class="w-16 h-16 rounded-xl bg-blue-500 flex items-center justify-center text-white peer-checked:ring-4 peer-checked:ring-blue-200 peer-checked:bg-blue-600 transition-all shadow-sm">
                                    <i class="fas fa-check opacity-0 peer-checked:opacity-100 text-xl"></i>
                                </div>
                                <span
                                    class="block text-center text-xs text-gray-500 mt-2 font-medium group-hover:text-blue-600">ฟ้า
                                    (Default)</span>
                            </label>

                            <!-- Theme Option: Green -->
                            <label class="cursor-pointer group">
                                <input type="radio" name="theme_color" value="green" class="peer hidden">
                                <div
                                    class="w-16 h-16 rounded-xl bg-emerald-500 flex items-center justify-center text-white peer-checked:ring-4 peer-checked:ring-emerald-200 peer-checked:bg-emerald-600 transition-all shadow-sm">
                                    <i class="fas fa-check opacity-0 peer-checked:opacity-100 text-xl"></i>
                                </div>
                                <span
                                    class="block text-center text-xs text-gray-500 mt-2 font-medium group-hover:text-emerald-600">เขียว</span>
                            </label>

                            <!-- Theme Option: Purple -->
                            <label class="cursor-pointer group">
                                <input type="radio" name="theme_color" value="purple" class="peer hidden">
                                <div
                                    class="w-16 h-16 rounded-xl bg-purple-500 flex items-center justify-center text-white peer-checked:ring-4 peer-checked:ring-purple-200 peer-checked:bg-purple-600 transition-all shadow-sm">
                                    <i class="fas fa-check opacity-0 peer-checked:opacity-100 text-xl"></i>
                                </div>
                                <span
                                    class="block text-center text-xs text-gray-500 mt-2 font-medium group-hover:text-purple-600">ม่วง</span>
                            </label>

                            <!-- Theme Option: Orange -->
                            <label class="cursor-pointer group">
                                <input type="radio" name="theme_color" value="orange" class="peer hidden">
                                <div
                                    class="w-16 h-16 rounded-xl bg-orange-500 flex items-center justify-center text-white peer-checked:ring-4 peer-checked:ring-orange-200 peer-checked:bg-orange-600 transition-all shadow-sm">
                                    <i class="fas fa-check opacity-0 peer-checked:opacity-100 text-xl"></i>
                                </div>
                                <span
                                    class="block text-center text-xs text-gray-500 mt-2 font-medium group-hover:text-orange-600">ส้ม</span>
                            </label>

                            <!-- Theme Option: Red -->
                            <label class="cursor-pointer group">
                                <input type="radio" name="theme_color" value="red" class="peer hidden">
                                <div
                                    class="w-16 h-16 rounded-xl bg-rose-500 flex items-center justify-center text-white peer-checked:ring-4 peer-checked:ring-rose-200 peer-checked:bg-rose-600 transition-all shadow-sm">
                                    <i class="fas fa-check opacity-0 peer-checked:opacity-100 text-xl"></i>
                                </div>
                                <span
                                    class="block text-center text-xs text-gray-500 mt-2 font-medium group-hover:text-rose-600">แดง</span>
                            </label>
                        </div>
                    </div>

                    <!-- Save Button -->
                    <div class="pt-4 text-right">
                        <button type="button" onclick="saveSettings()"
                            class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-8 rounded-xl shadow-lg shadow-green-600/30 transform hover:-translate-y-1 transition-all flex items-center gap-2 ml-auto">
                            <i class="fas fa-save"></i> บันทึกการตั้งค่า
                        </button>
                    </div>

                </div>
            </form>
        </div>
    </div>
</div>

<!-- Cropper Modal -->
<div id="cropperModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/80 backdrop-blur-sm">
    <div class="bg-white rounded-2xl w-full max-w-2xl overflow-hidden shadow-2xl m-4">
        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
            <h3 class="font-bold text-gray-800"><i class="fas fa-crop-alt mr-2 text-blue-600"></i>ปรับแต่งรูปภาพ</h3>
            <button onclick="closeCropper()" class="text-gray-400 hover:text-gray-600"><i
                    class="fas fa-times text-xl"></i></button>
        </div>
        <div class="p-6 h-[400px] bg-gray-900 flex items-center justify-center">
            <img id="cropperImage" class="max-w-full max-h-full">
        </div>
        <div class="px-6 py-4 bg-gray-50 flex justify-between items-center">
            <div class="flex gap-2">
                <button onclick="rotateImage(-90)"
                    class="p-2 bg-white border border-gray-200 rounded-lg hover:bg-gray-100 text-gray-600"
                    title="หมุนซ้าย"><i class="fas fa-undo"></i></button>
                <button onclick="rotateImage(90)"
                    class="p-2 bg-white border border-gray-200 rounded-lg hover:bg-gray-100 text-gray-600"
                    title="หมุนขวา"><i class="fas fa-redo"></i></button>
                <button onclick="resetCropper()"
                    class="p-2 bg-white border border-gray-200 rounded-lg hover:bg-gray-100 text-gray-600"
                    title="รีเซ็ต"><i class="fas fa-sync"></i></button>
            </div>
            <div class="flex gap-3">
                <button onclick="closeCropper()"
                    class="px-5 py-2.5 bg-gray-200 text-gray-700 font-semibold rounded-xl hover:bg-gray-300 transition block">ยกเลิก</button>
                <button onclick="cropAndSave()"
                    class="px-6 py-2.5 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700 shadow-lg shadow-blue-600/30 transition block">ยืนยัน</button>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
<script>
    let cropper;
    let croppedBlob = null;

    document.addEventListener('DOMContentLoaded', () => {
        loadSettings();
    });

    function loadSettings() {
        fetch('api/system_settings.php')
            .then(r => r.json())
            .then(data => {
                if (data.success && data.settings) {
                    const s = data.settings;
                    document.getElementById('agencyName').value = s.agency_name || '';
                    document.getElementById('orgName').value = s.org_name || '';
                    document.getElementById('orgNameEn').value = s.org_name_en || '';
                    if (s.logo_path) {
                        document.getElementById('currentLogo').src = s.logo_path + '?t=' + new Date().getTime(); // Prevent cache
                    }
                    if (s.theme_color) {
                        const radio = document.querySelector(`input[name="theme_color"][value="${s.theme_color}"]`);
                        if (radio) radio.checked = true;
                    }
                }
            });
    }

    function previewImage(input) {
        if (input.files && input.files[0]) {
            const file = input.files[0];
            const reader = new FileReader();

            reader.onload = function (e) {
                document.getElementById('cropperImage').src = e.target.result;
                openCropper();
            }
            reader.readAsDataURL(file);
        }
    }

    function openCropper() {
        const modal = document.getElementById('cropperModal');
        modal.classList.remove('hidden');

        const image = document.getElementById('cropperImage');
        if (cropper) cropper.destroy();

        cropper = new Cropper(image, {
            aspectRatio: 1, // Square logo preferred
            viewMode: 1,
            autoCropArea: 0.8,
        });
    }

    function closeCropper() {
        document.getElementById('cropperModal').classList.add('hidden');
        if (cropper) cropper.destroy();
        document.getElementById('logoInput').value = ''; // Reset input
    }

    function rotateImage(deg) {
        if (cropper) cropper.rotate(deg);
    }

    function resetCropper() {
        if (cropper) cropper.reset();
    }

    function cropAndSave() {
        if (!cropper) return;

        cropper.getCroppedCanvas({
            width: 300,
            height: 300
        }).toBlob((blob) => {
            croppedBlob = blob;
            const url = URL.createObjectURL(blob);
            document.getElementById('currentLogo').src = url;
            closeCropper();
        }, 'image/png');
    }

    function saveSettings() {
        const formData = new FormData();
        formData.append('agency_name', document.getElementById('agencyName').value);
        formData.append('org_name', document.getElementById('orgName').value);
        formData.append('org_name_en', document.getElementById('orgNameEn').value);
        formData.append('theme_color', document.querySelector('input[name="theme_color"]:checked').value);

        if (croppedBlob) {
            formData.append('logo_file', croppedBlob, 'logo.png');
        }

        Swal.fire({
            title: 'กำลังบันทึก...',
            didOpen: () => Swal.showLoading(),
            allowOutsideClick: false
        });

        fetch('api/system_settings.php', {
            method: 'POST',
            body: formData
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'บันทึกสำเร็จ',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => location.reload());
                } else {
                    Swal.fire('Error', data.message || 'บันทึกไม่สำเร็จ', 'error');
                }
            })
            .catch(err => Swal.fire('Error', 'Connection failed', 'error'));
    }
</script>

<!-- ══════════════════════════════════════════════════════════════
     LINE OA NOTIFICATION SETTINGS SECTION
     ══════════════════════════════════════════════════════════════ -->
<div class="w-full px-6 mt-8 mb-10">

  <!-- ── Section Card ── -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

    <!-- Section Header -->
    <div class="bg-green-50 px-6 py-4 flex items-center gap-4 border-b border-green-100">
      <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center text-green-600 font-bold text-lg">2</div>
      <div>
        <h2 class="text-xl font-bold text-gray-800">ตั้งค่าการแจ้งเตือนผ่าน Line OA (Line Messaging API)</h2>
        <p class="text-sm text-gray-500 mt-0.5">เชื่อมต่อ Line Official Account เพื่อส่งการแจ้งเตือนเข้ากลุ่ม Line อัตโนมัติ</p>
      </div>
    </div>

    <div class="p-8 space-y-8">

      <!-- ════════════════════════════════════════════════
           STEP-BY-STEP GUIDE BOX
           ════════════════════════════════════════════════ -->
      <div class="bg-gradient-to-br from-green-50 to-emerald-50 border border-green-200 rounded-2xl p-6">
        <div class="flex items-center gap-3 mb-5">
          <div class="w-9 h-9 bg-green-500 rounded-xl flex items-center justify-center flex-shrink-0">
            <i class="fas fa-book-open text-white text-sm"></i>
          </div>
          <h3 class="text-lg font-bold text-green-800">📋 คู่มือการตั้งค่า Line Official Account — อ่านก่อนกรอกข้อมูล</h3>
        </div>

        <div class="space-y-6">

          <!-- STEP 1 -->
          <div class="bg-white rounded-xl border border-green-100 p-5">
            <div class="flex items-start gap-4">
              <div class="w-8 h-8 rounded-full bg-green-500 text-white font-bold flex items-center justify-center flex-shrink-0 text-sm mt-0.5">1</div>
              <div class="flex-1">
                <p class="font-bold text-gray-800 mb-1">สร้าง Line Official Account และ Messaging API Channel</p>
                <p class="text-sm text-gray-600 mb-3">ต้องสมัคร Line OA ก่อน จึงจะสามารถใช้ Messaging API ได้</p>
                <div class="bg-green-50 rounded-lg p-3 space-y-2">
                  <p class="text-xs font-semibold text-green-700 uppercase tracking-wide">ลิงค์ที่ต้องเข้า:</p>
                  <a href="https://developers.line.biz/console/" target="_blank" rel="noopener noreferrer"
                     class="flex items-center gap-2 text-sm text-green-700 font-semibold hover:text-green-900 underline underline-offset-2 transition-colors">
                    <i class="fas fa-external-link-alt text-xs"></i>
                    https://developers.line.biz/console/
                    <span class="text-xs bg-green-100 text-green-600 px-2 py-0.5 rounded-full font-normal">Line Developers Console</span>
                  </a>
                  <a href="https://manager.line.biz/" target="_blank" rel="noopener noreferrer"
                     class="flex items-center gap-2 text-sm text-green-700 font-semibold hover:text-green-900 underline underline-offset-2 transition-colors">
                    <i class="fas fa-external-link-alt text-xs"></i>
                    https://manager.line.biz/
                    <span class="text-xs bg-green-100 text-green-600 px-2 py-0.5 rounded-full font-normal">Line Official Account Manager</span>
                  </a>
                </div>
                <ol class="mt-3 space-y-1.5 text-sm text-gray-600 list-none">
                  <li class="flex gap-2"><span class="text-green-500 font-bold">→</span> เข้าเว็บ Line Developers Console แล้ว Login ด้วย Line Account</li>
                  <li class="flex gap-2"><span class="text-green-500 font-bold">→</span> คลิก <strong>Create a new provider</strong> หรือเลือก Provider ที่มีอยู่</li>
                  <li class="flex gap-2"><span class="text-green-500 font-bold">→</span> คลิก <strong>Create a Messaging API channel</strong></li>
                  <li class="flex gap-2"><span class="text-green-500 font-bold">→</span> กรอกชื่อ Channel ของ Line OA แล้วกด <strong>Create</strong></li>
                </ol>
              </div>
            </div>
          </div>

          <!-- STEP 2 -->
          <div class="bg-white rounded-xl border border-green-100 p-5">
            <div class="flex items-start gap-4">
              <div class="w-8 h-8 rounded-full bg-blue-500 text-white font-bold flex items-center justify-center flex-shrink-0 text-sm mt-0.5">2</div>
              <div class="flex-1">
                <p class="font-bold text-gray-800 mb-1">รับ Channel Access Token</p>
                <p class="text-sm text-gray-600 mb-2">Token นี้คือ "กุญแจ" ที่ระบบใช้ส่งข้อความในนาม Line OA ของคุณ</p>
                <div class="bg-blue-50 rounded-lg p-3 mb-3">
                  <a href="https://developers.line.biz/console/" target="_blank" rel="noopener noreferrer"
                     class="flex items-center gap-2 text-sm text-blue-700 font-semibold hover:text-blue-900 underline underline-offset-2">
                    <i class="fas fa-external-link-alt text-xs"></i>
                    Line Developers Console → เลือก Channel → แท็บ Messaging API
                  </a>
                </div>
                <ol class="space-y-1.5 text-sm text-gray-600 list-none">
                  <li class="flex gap-2"><span class="text-blue-500 font-bold">→</span> เข้า Console แล้วคลิกที่ Channel ที่สร้าง</li>
                  <li class="flex gap-2"><span class="text-blue-500 font-bold">→</span> คลิกแท็บ <strong class="text-blue-700">"Messaging API"</strong> ด้านบน</li>
                  <li class="flex gap-2"><span class="text-blue-500 font-bold">→</span> เลื่อนลงหา <strong class="text-blue-700">"Channel access token (long-lived)"</strong></li>
                  <li class="flex gap-2"><span class="text-blue-500 font-bold">→</span> คลิกปุ่ม <strong class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded text-xs">Issue</strong> เพื่อสร้าง Token</li>
                  <li class="flex gap-2"><span class="text-blue-500 font-bold">→</span> คัดลอก Token ทั้งหมด (ยาวมาก ประมาณ 170 ตัวอักษร) มาวางในช่องด้านล่าง</li>
                </ol>
                <div class="mt-3 bg-yellow-50 border border-yellow-200 rounded-lg p-3 flex gap-2">
                  <i class="fas fa-exclamation-triangle text-yellow-500 flex-shrink-0 mt-0.5"></i>
                  <p class="text-xs text-yellow-700"><strong>สำคัญ:</strong> Token มีอายุยาว (Long-lived) แต่ถ้ากด Issue ใหม่ Token เก่าจะใช้ไม่ได้ ควรเก็บไว้ในที่ปลอดภัย</p>
                </div>
              </div>
            </div>
          </div>

          <!-- STEP 3 -->
          <div class="bg-white rounded-xl border border-green-100 p-5">
            <div class="flex items-start gap-4">
              <div class="w-8 h-8 rounded-full bg-purple-500 text-white font-bold flex items-center justify-center flex-shrink-0 text-sm mt-0.5">3</div>
              <div class="flex-1">
                <p class="font-bold text-gray-800 mb-1">เพิ่ม Line OA เข้ากลุ่ม Line และรับ Group ID</p>
                <p class="text-sm text-gray-600 mb-2">Line OA ต้องอยู่ในกลุ่มก่อน จึงจะสามารถส่งข้อความในกลุ่มได้</p>
                <div class="bg-purple-50 rounded-lg p-3 mb-3">
                  <a href="https://developers.line.biz/en/docs/messaging-api/group-chats/" target="_blank" rel="noopener noreferrer"
                     class="flex items-center gap-2 text-sm text-purple-700 font-semibold hover:text-purple-900 underline underline-offset-2">
                    <i class="fas fa-external-link-alt text-xs"></i>
                    คู่มือ Line Messaging API — Group Chat (เอกสารทางการ)
                  </a>
                </div>
                <ol class="space-y-1.5 text-sm text-gray-600 list-none">
                  <li class="flex gap-2"><span class="text-purple-500 font-bold">→</span> เปิดแอป Line บนมือถือ → ไปที่กลุ่มที่ต้องการรับแจ้งเตือน</li>
                  <li class="flex gap-2"><span class="text-purple-500 font-bold">→</span> กดเชิญ (Invite) และค้นหา Line OA ของคุณเพื่อเพิ่มเข้ากลุ่ม</li>
                  <li class="flex gap-2"><span class="text-purple-500 font-bold">→</span> เข้า Line Developers Console → แท็บ <strong class="text-purple-700">"Messaging API"</strong></li>
                  <li class="flex gap-2"><span class="text-purple-500 font-bold">→</span> เปิด Webhook URL แล้วตั้งค่า Webhook ด้วยเครื่องมือช่วยรับ Group ID เช่น:</li>
                </ol>
                <div class="mt-3 bg-purple-50 rounded-lg p-3 space-y-2">
                  <p class="text-xs font-semibold text-purple-700 uppercase tracking-wide">วิธีง่ายที่สุดในการรับ Group ID — ใช้เครื่องมือออนไลน์:</p>
                  <a href="https://notify-bot.line.me/my/" target="_blank" rel="noopener noreferrer"
                     class="flex items-center gap-2 text-sm text-purple-700 font-semibold hover:text-purple-900 underline underline-offset-2 transition-colors">
                    <i class="fas fa-external-link-alt text-xs"></i>
                    https://notify-bot.line.me/my/
                    <span class="text-xs bg-purple-100 text-purple-600 px-2 py-0.5 rounded-full font-normal">Line Notify (ทางเลือก)</span>
                  </a>
                  <p class="text-xs text-purple-600 mt-1">หรือใช้ Webhook เพื่อรับ Event จากกลุ่ม แล้วดู <code class="bg-purple-100 px-1 rounded">source.groupId</code> ใน JSON ที่ได้รับ</p>
                  <div class="bg-white rounded-lg p-2 mt-1 font-mono text-xs text-gray-700 border border-purple-100 overflow-x-auto">
                    <span class="text-gray-400">// ตัวอย่าง Group ID ที่ได้รับจาก Webhook:</span><br>
                    <span class="text-purple-600">"source"</span>: {<br>
                    &nbsp;&nbsp;<span class="text-purple-600">"type"</span>: <span class="text-green-600">"group"</span>,<br>
                    &nbsp;&nbsp;<span class="text-purple-600">"groupId"</span>: <span class="text-green-600">"<strong>C1234567890abcdef...</strong>"</span><br>
                    }
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- STEP 4 -->
          <div class="bg-white rounded-xl border border-green-100 p-5">
            <div class="flex items-start gap-4">
              <div class="w-8 h-8 rounded-full bg-orange-500 text-white font-bold flex items-center justify-center flex-shrink-0 text-sm mt-0.5">4</div>
              <div class="flex-1">
                <p class="font-bold text-gray-800 mb-1">เปิดใช้งาน "Allow bot to join group chats"</p>
                <p class="text-sm text-gray-600 mb-2">Line OA ต้องได้รับอนุญาตให้เข้ากลุ่มได้ก่อน</p>
                <ol class="space-y-1.5 text-sm text-gray-600 list-none">
                  <li class="flex gap-2"><span class="text-orange-500 font-bold">→</span> ไปที่ <a href="https://manager.line.biz/" target="_blank" class="text-orange-600 underline font-semibold">Line OA Manager</a> → เลือก OA ของคุณ</li>
                  <li class="flex gap-2"><span class="text-orange-500 font-bold">→</span> เมนู <strong>Settings → Messaging API</strong></li>
                  <li class="flex gap-2"><span class="text-orange-500 font-bold">→</span> ที่ <strong class="text-orange-700">"Allow bot to join group chats"</strong> → เปลี่ยนเป็น <span class="text-green-600 font-bold">Enabled</span></li>
                  <li class="flex gap-2"><span class="text-orange-500 font-bold">→</span> กลับมากรอก Token และ Group ID ในช่องด้านล่าง แล้วกด <strong>บันทึก</strong> และ <strong>ทดสอบส่ง</strong></li>
                </ol>
              </div>
            </div>
          </div>

        </div><!-- /steps -->
      </div><!-- /guide box -->

      <!-- ════════════════════════════════════════════════
           INPUT FORM
           ════════════════════════════════════════════════ -->
      <div class="space-y-6">

        <!-- Token Field -->
        <div>
          <label class="block text-sm font-bold text-gray-700 mb-1">
            🔑 Channel Access Token
            <span class="ml-1 text-xs font-normal text-gray-400">(ได้จาก Line Developers Console → Messaging API)</span>
          </label>
          <div class="text-xs text-gray-500 mb-2 bg-gray-50 rounded-lg px-3 py-2 border border-gray-100">
            <i class="fas fa-info-circle text-blue-400 mr-1"></i>
            Token จะถูกเข้ารหัสในการแสดงผล เมื่อกรอก Token ใหม่จะเขียนทับ Token เดิมอัตโนมัติ
          </div>
          <div class="relative">
            <span class="absolute left-4 top-3.5 text-gray-400"><i class="fas fa-key"></i></span>
            <input type="text" id="lineToken" name="channel_access_token"
                   class="w-full pl-10 pr-12 py-3 bg-gray-50 border-2 border-gray-100 rounded-xl text-gray-800 font-mono text-sm focus:border-green-500 focus:bg-white focus:outline-none transition-all placeholder-gray-300"
                   placeholder="วาง Channel Access Token ที่ได้จาก Line Developers Console ที่นี่ (ยาวประมาณ 170 ตัวอักษร)">
            <button type="button" onclick="toggleTokenVisibility()"
                    class="absolute right-3 top-3 text-gray-400 hover:text-gray-600 transition-colors p-1"
                    title="แสดง/ซ่อน Token">
              <i id="tokenEyeIcon" class="fas fa-eye"></i>
            </button>
          </div>
          <p id="lineTokenStatus" class="text-xs mt-1.5 text-gray-400 hidden"></p>
        </div>

        <!-- Group ID Field -->
        <div>
          <label class="block text-sm font-bold text-gray-700 mb-1">
            👥 Line Group ID
            <span class="ml-1 text-xs font-normal text-gray-400">(รหัสกลุ่ม Line ที่ต้องการรับแจ้งเตือน)</span>
          </label>
          <div class="text-xs text-gray-500 mb-2 bg-gray-50 rounded-lg px-3 py-2 border border-gray-100">
            <i class="fas fa-info-circle text-purple-400 mr-1"></i>
            Group ID ขึ้นต้นด้วย <code class="bg-gray-100 px-1 rounded font-mono">C</code> ตามด้วยตัวเลขและตัวอักษร เช่น <code class="bg-gray-100 px-1 rounded font-mono text-purple-600">C1a2b3c4d5e6f7890...</code>
          </div>
          <div class="relative">
            <span class="absolute left-4 top-3.5 text-gray-400"><i class="fas fa-users"></i></span>
            <input type="text" id="lineGroupId" name="group_id"
                   class="w-full pl-10 pr-4 py-3 bg-gray-50 border-2 border-gray-100 rounded-xl text-gray-800 font-mono text-sm focus:border-green-500 focus:bg-white focus:outline-none transition-all placeholder-gray-300"
                   placeholder="วาง Group ID ที่นี่ เช่น C1234567890abcdef1234567890abcdef">
          </div>
        </div>

        <!-- Enable Toggle -->
        <div class="flex items-center justify-between bg-gray-50 rounded-xl border-2 border-gray-100 px-5 py-4">
          <div>
            <p class="font-bold text-gray-800">เปิดใช้งานการแจ้งเตือน Line OA</p>
            <p class="text-xs text-gray-500 mt-0.5">เมื่อเปิดใช้งาน ระบบจะส่งแจ้งเตือนเข้ากลุ่ม Line อัตโนมัติเมื่อมีกิจกรรมในระบบ</p>
          </div>
          <label class="relative inline-flex items-center cursor-pointer flex-shrink-0 ml-4">
            <input type="checkbox" id="lineNotifyEnabled" class="sr-only peer">
            <div class="w-14 h-7 bg-gray-200 peer-focus:outline-none rounded-full peer
                        peer-checked:after:translate-x-7 peer-checked:bg-green-500
                        after:content-[''] after:absolute after:top-0.5 after:left-0.5
                        after:bg-white after:rounded-full after:h-6 after:w-6
                        after:transition-all shadow-inner"></div>
          </label>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-wrap gap-3 pt-2">
          <button type="button" onclick="saveLineSettings()"
                  class="flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-7 rounded-xl shadow-lg shadow-green-600/25 transform hover:-translate-y-0.5 transition-all">
            <i class="fas fa-save"></i> บันทึกการตั้งค่า Line OA
          </button>
          <button type="button" onclick="testLineNotification()"
                  id="testLineBtn"
                  class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-7 rounded-xl shadow-lg shadow-blue-600/25 transform hover:-translate-y-0.5 transition-all">
            <i class="fas fa-paper-plane"></i> ทดสอบส่งข้อความ
          </button>
        </div>

        <!-- Status Bar -->
        <div id="lineStatusBar" class="hidden items-center gap-3 bg-gray-50 rounded-xl border border-gray-200 px-5 py-3">
          <div id="lineStatusIcon" class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0"></div>
          <div>
            <p id="lineStatusTitle" class="text-sm font-bold text-gray-800"></p>
            <p id="lineStatusMsg"   class="text-xs text-gray-500 mt-0.5"></p>
          </div>
        </div>

      </div><!-- /input form -->

    </div><!-- /p-8 -->
  </div><!-- /card -->
</div>

<!-- Line Settings Script -->
<script>
(function () {
  /* ── Load current settings on page load ── */
  fetch('api/line_settings.php')
    .then(r => r.json())
    .then(data => {
      if (!data.success) return;
      const s = data.settings;
      const tokenInput = document.getElementById('lineToken');
      if (s.has_token && s.channel_access_token_masked) {
        tokenInput.value = s.channel_access_token_masked;
        tokenInput.type  = 'password';
        document.getElementById('tokenEyeIcon').className = 'fas fa-eye-slash';
        const st = document.getElementById('lineTokenStatus');
        st.textContent = '✅ มี Token บันทึกไว้แล้ว — กรอก Token ใหม่เพื่อเปลี่ยน';
        st.className = 'text-xs mt-1.5 text-green-600';
        st.classList.remove('hidden');
      }
      document.getElementById('lineGroupId').value      = s.group_id || '';
      document.getElementById('lineNotifyEnabled').checked = !!s.notify_enabled;
    })
    .catch(() => {});

  /* ── Toggle token input visibility ── */
  window.toggleTokenVisibility = function () {
    const inp  = document.getElementById('lineToken');
    const icon = document.getElementById('tokenEyeIcon');
    if (inp.type === 'password') {
      inp.type = 'text';
      icon.className = 'fas fa-eye';
    } else {
      inp.type = 'password';
      icon.className = 'fas fa-eye-slash';
    }
  };

  /* ── Save settings ── */
  window.saveLineSettings = function () {
    Swal.fire({ title: 'กำลังบันทึก...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });

    const body = new URLSearchParams({
      action:               'save',
      channel_access_token: document.getElementById('lineToken').value.trim(),
      group_id:             document.getElementById('lineGroupId').value.trim(),
    });
    if (document.getElementById('lineNotifyEnabled').checked) body.append('notify_enabled', '1');

    fetch('api/line_settings.php', { method: 'POST', body })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          Swal.fire({ icon: 'success', title: 'บันทึกสำเร็จ', timer: 1500, showConfirmButton: false })
            .then(() => location.reload());
        } else {
          Swal.fire('ผิดพลาด', data.message || 'บันทึกไม่สำเร็จ', 'error');
        }
      })
      .catch(() => Swal.fire('Error', 'Connection failed', 'error'));
  };

  /* ── Test send ── */
  window.testLineNotification = function () {
    const btn = document.getElementById('testLineBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังส่ง...';

    const bar = document.getElementById('lineStatusBar');
    bar.classList.add('hidden');

    fetch('api/line_settings.php?action=test', { method: 'POST', body: new URLSearchParams({ action: 'test' }) })
      .then(r => r.json())
      .then(data => {
        bar.classList.remove('hidden');
        bar.style.display = 'flex';
        const icon  = document.getElementById('lineStatusIcon');
        const title = document.getElementById('lineStatusTitle');
        const msg   = document.getElementById('lineStatusMsg');

        if (data.success) {
          bar.className = 'flex items-center gap-3 bg-green-50 rounded-xl border border-green-200 px-5 py-3';
          icon.className = 'w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 bg-green-100 text-green-600';
          icon.innerHTML = '<i class="fas fa-check"></i>';
          title.textContent = '✅ ส่งข้อความทดสอบสำเร็จ!';
          msg.textContent   = 'ตรวจสอบข้อความในกลุ่ม Line ของคุณได้เลย';
        } else {
          bar.className = 'flex items-center gap-3 bg-red-50 rounded-xl border border-red-200 px-5 py-3';
          icon.className = 'w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 bg-red-100 text-red-600';
          icon.innerHTML = '<i class="fas fa-times"></i>';
          title.textContent = '❌ ส่งไม่สำเร็จ';
          msg.textContent   = data.message || 'กรุณาตรวจสอบ Token และ Group ID';
        }
      })
      .catch(() => {
        const bar = document.getElementById('lineStatusBar');
        bar.classList.remove('hidden');
        bar.style.display = 'flex';
        document.getElementById('lineStatusTitle').textContent = '❌ Connection Error';
        document.getElementById('lineStatusMsg').textContent   = 'ไม่สามารถเชื่อมต่อกับ API ได้';
      })
      .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> ทดสอบส่งข้อความ';
      });
  };
})();
</script>

<?php include 'includes/footer.php'; ?>
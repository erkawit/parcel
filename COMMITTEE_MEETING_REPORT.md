# 📋 Committee Meeting Report Upload - อัปโหลดรายงานสรุปการประชุม

## ✅ สรุปการปรับปรุง

ได้เพิ่มฟีเจอร์อัปโหลดไฟล์รายงานสรุปการประชุมคณะกรรมการตรวจรับครุภัณฑ์แล้ว!

---

## 🎯 ฟีเจอร์ใหม่

### 1. **ฟิลด์ฐานข้อมูลใหม่**

เพิ่มคอลัมน์ในตาราง `committee_orders`:

- ✅ `meeting_report_file` - เก็บ path ไฟล์รายงาน
- ✅ `meeting_report_uploaded_at` - วันที่อัปโหลด
- ✅ `meeting_report_uploaded_by` - ผู้อัปโหลด (FK → users)

### 2. **การแสดงผลในหน้ารายการ**

```
┌────────────────────────────────────┐
│ คำสั่งปีงบประมาณ 2567             │
├────────────────────────────────────┤
│ วันที่คำสั่ง | จำนวนกรรมการ      │
│ ผู้สร้าง     | ไฟล์คำสั่ง        │
│ รายงานสรุปการประชุม               │
│ ┌──────────────────────────────┐  │
│ │ ✅ รายงานสรุปการประชุม      │  │
│ │    อัปโหลดแล้ว               │  │
│ │ โดย: สมชาย ใจดี              │  │
│ │ เมื่อ: 10 ก.พ. 2567          │  │
│ └──────────────────────────────┘  │
│                                    │
│ [ดูรายชื่อ] [แก้ไข]              │
│ [ดูคำสั่ง PDF] [ดูรายงานสรุป]   │
└────────────────────────────────────┘
```

### 3. **ปุ่มอัปโหลด**

- ✅ แสดงปุ่ม "อัปโหลดรายงานสรุป" (สีเขียว) เมื่อยังไม่มีไฟล์
- ✅ เปลี่ยนเป็นปุ่ม "ดูรายงานสรุป" เมื่ออัปโหลดแล้ว
- ✅ แสดงสถานะการอัปโหลดพร้อมข้อมูลผู้อัปโหลด

### 4. **ฟอร์มอัปโหลด**

```
┌──────────────────────────────────────┐
│ 📤 อัปโหลดรายงานสรุปการประชุม      │
├──────────────────────────────────────┤
│ ℹ️ ข้อมูล:                          │
│ • รองรับไฟล์ PDF เท่านั้น           │
│ • ขนาดไฟล์ไม่เกิน 10 MB             │
│ • ควรอัปโหลดหลังตรวจสอบเสร็จสิ้น   │
│                                      │
│ 📄 เลือกไฟล์ PDF                    │
│ [เลือกไฟล์...]                      │
│                                      │
│ [ยกเลิก] [📤 อัปโหลด]              │
└──────────────────────────────────────┘
```

---

## 💻 โค้ดที่เพิ่มเข้ามา

### 1. Database Migration

```sql
ALTER TABLE `committee_orders`
ADD COLUMN `meeting_report_file` VARCHAR(255) DEFAULT NULL,
ADD COLUMN `meeting_report_uploaded_at` DATETIME DEFAULT NULL,
ADD COLUMN `meeting_report_uploaded_by` INT DEFAULT NULL,
ADD FOREIGN KEY (`meeting_report_uploaded_by`)
    REFERENCES `users`(`id`) ON DELETE SET NULL;
```

### 2. Frontend - committee.php

#### แสดงสถานะรายงาน

```html
<div class="bg-gray-50 rounded-lg p-3">
  <p class="text-gray-500 text-xs">รายงานสรุปการประชุม</p>
  <p class="font-semibold">
    <?php if ($order['meeting_report_file']): ?>
    <button onclick="previewPDF(...)">
      <i class="fas fa-file-pdf"></i> ดูรายงาน
    </button>
    <?php else: ?>
    <span class="text-gray-400">ยังไม่อัปโหลด</span>
    <?php endif; ?>
  </p>
</div>
```

#### แสดงข้อมูลการอัปโหลด

```html
<?php if ($order['meeting_report_file']): ?>
<div class="bg-green-50 border-l-4 border-green-600 p-3">
  <p class="text-sm text-green-800">✅ รายงานสรุปการประชุมอัปโหลดแล้ว</p>
  <p class="text-xs text-green-700 mt-1">
    โดย <?= $order['report_uploaded_by_name'] ?> เมื่อ <?=
    formatThaiDate($order['meeting_report_uploaded_at']) ?>
  </p>
</div>
<?php endif; ?>
```

#### ปุ่มอัปโหลด/ดูรายงาน

```html
<?php if (!$order['meeting_report_file']): ?>
<button
  onclick="uploadMeetingReport(<?= $order['id'] ?>)"
  class="btn-press bg-green-600 text-white..."
>
  <i class="fas fa-upload"></i> อัปโหลดรายงานสรุป
</button>
<?php else: ?>
<button onclick="previewPDF(...)" class="btn-press bg-green-600 text-white...">
  <i class="fas fa-file-pdf"></i> ดูรายงานสรุป
</button>
<?php endif; ?>
```

#### JavaScript Function

```javascript
function uploadMeetingReport(orderId) {
  SwalThemed.fire({
    title: "อัปโหลดรายงานสรุปการประชุม",
    html: `
            <input type="file" 
                   id="meetingReportFile" 
                   accept=".pdf">
        `,
    preConfirm: () => {
      const file = document.getElementById("meetingReportFile").files[0];

      // Validation
      if (!file) return false;
      if (file.type !== "application/pdf") return false;
      if (file.size > 10 * 1024 * 1024) return false;

      return { file: file };
    },
  }).then((result) => {
    if (result.isConfirmed) {
      const formData = new FormData();
      formData.append("action", "upload_meeting_report");
      formData.append("order_id", orderId);
      formData.append("meeting_report_file", result.value.file);

      fetch("api/committee.php", {
        method: "POST",
        body: formData,
      });
    }
  });
}
```

### 3. Backend - api/committee.php

```php
if ($action === 'upload_meeting_report') {
    try {
        $orderId = $_POST['order_id'] ?? 0;

        // Validate order exists
        $stmt = $pdo->prepare("SELECT id FROM committee_orders WHERE id = ?");
        $stmt->execute([$orderId]);
        if (!$stmt->fetch()) {
            throw new Exception('ไม่พบคำสั่งแต่งตั้ง');
        }

        // Validate file
        if (!isset($_FILES['meeting_report_file'])) {
            throw new Exception('กรุณาเลือกไฟล์');
        }

        $file = $_FILES['meeting_report_file'];

        // Validate type
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            throw new Exception('อนุญาตเฉพาะไฟล์ PDF');
        }

        // Validate size (10MB)
        if ($file['size'] > 10 * 1024 * 1024) {
            throw new Exception('ขนาดไฟล์ต้องไม่เกิน 10 MB');
        }

        // Create directory
        $uploadDir = __DIR__ . '/../uploads/committee_reports/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Generate filename
        $filename = 'meeting_report_' . $orderId . '_' . time() . '_' . uniqid() . '.pdf';
        $uploadPath = $uploadDir . $filename;
        $dbPath = 'uploads/committee_reports/' . $filename;

        // Upload file
        move_uploaded_file($file['tmp_name'], $uploadPath);

        // Update database
        $stmt = $pdo->prepare("UPDATE committee_orders
                               SET meeting_report_file = ?,
                                   meeting_report_uploaded_at = NOW(),
                                   meeting_report_uploaded_by = ?
                               WHERE id = ?");
        $stmt->execute([$dbPath, $_SESSION['user_id'], $orderId]);

        logActivity($_SESSION['user_id'], 'committee_report_upload',
                   "อัปโหลดรายงานสรุปการประชุม ID: {$orderId}");

        echo json_encode(['success' => true, 'file_path' => $dbPath]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
```

---

## 🔄 Flow การทำงาน

```
1. เจ้าหน้าที่เข้าหน้า committee.php
   ↓
2. เห็นรายการคำสั่งแต่งตั้งคณะกรรมการ
   ↓
3. คลิกปุ่ม "อัปโหลดรายงานสรุป" (สีเขียว)
   ↓
4. เลือกไฟล์ PDF (ไม่เกิน 10 MB)
   ↓
5. คลิก "อัปโหลด"
   ↓
6. ระบบตรวจสอบ:
   - ประเภทไฟล์ (PDF เท่านั้น)
   - ขนาดไฟล์ (≤ 10 MB)
   - คำสั่งแต่งตั้งมีอยู่จริง
   ↓
7. บันทึกไฟล์ไปที่ uploads/committee_reports/
   ↓
8. อัปเดตฐานข้อมูล:
   - meeting_report_file = path
   - meeting_report_uploaded_at = NOW()
   - meeting_report_uploaded_by = user_id
   ↓
9. บันทึก Activity Log
   ↓
10. แสดงข้อความสำเร็จและรีโหลดหน้า
    ↓
11. ปุ่มเปลี่ยนเป็น "ดูรายงานสรุป"
    ↓
12. แสดงข้อมูลผู้อัปโหลดและวันที่
```

---

## 📊 ตัวอย่างการใช้งาน

### กรณีที่ 1: อัปโหลดรายงานครั้งแรก

```
1. เข้าหน้า http://localhost/parcel/committee.php
2. เห็นคำสั่งแต่งตั้งปี 2567
3. คลิกปุ่ม "อัปโหลดรายงานสรุป" (สีเขียว)
4. เลือกไฟล์: รายงานสรุปการประชุม_2567.pdf (2.5 MB)
5. คลิก "อัปโหลด"
6. ✅ แสดงข้อความ "อัปโหลดสำเร็จ!"
7. หน้ารีโหลด
8. เห็นข้อความสีเขียว:
   "✅ รายงานสรุปการประชุมอัปโหลดแล้ว
    โดย สมชาย ใจดี เมื่อ 10 ก.พ. 2567"
9. ปุ่มเปลี่ยนเป็น "ดูรายงานสรุป"
```

### กรณีที่ 2: ดูรายงานที่อัปโหลดแล้ว

```
1. เข้าหน้า http://localhost/parcel/committee.php
2. เห็นคำสั่งที่มีรายงานแล้ว
3. คลิกปุ่ม "ดูรายงานสรุป" (สีเขียว)
4. เปิดหน้าต่าง PDF Viewer
5. ดูเนื้อหารายงานได้ทันที
```

---

## 🎨 UI/UX Features

### สถานะก่อนอัปโหลด

```
┌─────────────────────────┐
│ รายงานสรุปการประชุม     │
│ ยังไม่อัปโหลด           │
└─────────────────────────┘

[📤 อัปโหลดรายงานสรุป]  ← สีเขียว
```

### สถานะหลังอัปโหลด

```
┌─────────────────────────┐
│ รายงานสรุปการประชุม     │
│ 📄 ดูรายงาน             │
└─────────────────────────┘

┌─────────────────────────┐
│ ✅ รายงานสรุปการประชุม │
│    อัปโหลดแล้ว          │
│ โดย: สมชาย ใจดี         │
│ เมื่อ: 10 ก.พ. 2567     │
└─────────────────────────┘

[📄 ดูรายงานสรุป]  ← สีเขียว
```

### ฟอร์มอัปโหลด

```
┌──────────────────────────────┐
│ 📤 อัปโหลดรายงานสรุปฯ      │
├──────────────────────────────┤
│ ℹ️ ข้อมูล:                  │
│ ┌────────────────────────┐  │
│ │ • PDF เท่านั้น         │  │
│ │ • ≤ 10 MB              │  │
│ │ • หลังตรวจสอบเสร็จ     │  │
│ └────────────────────────┘  │
│                              │
│ 📄 เลือกไฟล์ PDF            │
│ [เลือกไฟล์...]              │
│                              │
│ [ยกเลิก] [📤 อัปโหลด]      │
└──────────────────────────────┘
```

---

## ✅ Validation

### 1. ประเภทไฟล์

```javascript
if (file.type !== "application/pdf") {
  Swal.showValidationMessage("กรุณาเลือกไฟล์ PDF เท่านั้น");
}
```

### 2. ขนาดไฟล์

```javascript
if (file.size > 10 * 1024 * 1024) {
  Swal.showValidationMessage("ขนาดไฟล์ต้องไม่เกิน 10 MB");
}
```

### 3. ไฟล์ต้องมี

```javascript
if (!file) {
  Swal.showValidationMessage("กรุณาเลือกไฟล์");
}
```

### 4. คำสั่งต้องมีอยู่

```php
$stmt = $pdo->prepare("SELECT id FROM committee_orders WHERE id = ?");
if (!$stmt->fetch()) {
    throw new Exception('ไม่พบคำสั่งแต่งตั้ง');
}
```

---

## 📁 โครงสร้างไฟล์

### ไฟล์ที่อัปโหลด

```
uploads/
└── committee_reports/
    ├── meeting_report_1_1707577200_abc123.pdf
    ├── meeting_report_2_1707577300_def456.pdf
    └── meeting_report_3_1707577400_ghi789.pdf
```

### รูปแบบชื่อไฟล์

```
meeting_report_{order_id}_{timestamp}_{uniqid}.pdf
```

**ตัวอย่าง:**

```
meeting_report_5_1707577200_65c7f1d0a1234.pdf
                │     │           │
                │     │           └─ Unique ID
                │     └─ Unix Timestamp
                └─ Order ID
```

---

## 🔐 ความปลอดภัย

### 1. Authentication

- ✅ ต้องเข้าสู่ระบบก่อน (`requireLogin()`)
- ✅ ต้องมีสิทธิ์ admin หรือ supply_officer

### 2. File Validation

- ✅ ตรวจสอบประเภทไฟล์ (PDF เท่านั้น)
- ✅ ตรวจสอบขนาดไฟล์ (≤ 10 MB)
- ✅ ใช้ชื่อไฟล์ที่ปลอดภัย (ไม่ใช้ชื่อเดิม)

### 3. Directory Security

- ✅ สร้างโฟลเดอร์อัตโนมัติถ้าไม่มี
- ✅ ตั้งค่า permission 0755

### 4. Activity Logging

- ✅ บันทึกทุกครั้งที่อัปโหลด
- ✅ เก็บข้อมูลผู้อัปโหลดและเวลา

---

## 📝 การทดสอบ

### ขั้นตอนทดสอบ

1. **เข้าหน้าคณะกรรมการ**

   ```
   http://localhost/parcel/committee.php
   ```

2. **ตรวจสอบการแสดงผล**
   - ✅ เห็นคอลัมน์ "รายงานสรุปการประชุม"
   - ✅ แสดง "ยังไม่อัปโหลด" ถ้าไม่มีไฟล์
   - ✅ มีปุ่ม "อัปโหลดรายงานสรุป"

3. **ทดสอบอัปโหลด**
   - คลิกปุ่ม "อัปโหลดรายงานสรุป"
   - เลือกไฟล์ PDF
   - คลิก "อัปโหลด"
   - ✅ แสดงข้อความสำเร็จ
   - ✅ หน้ารีโหลด
   - ✅ แสดงสถานะอัปโหลดแล้ว

4. **ทดสอบ Validation**
   - ลองอัปโหลดไฟล์ที่ไม่ใช่ PDF → ❌ แสดงข้อผิดพลาด
   - ลองอัปโหลดไฟล์ใหญ่เกิน 10 MB → ❌ แสดงข้อผิดพลาด
   - ไม่เลือกไฟล์ → ❌ แสดงข้อผิดพลาด

5. **ทดสอบดูรายงาน**
   - คลิกปุ่ม "ดูรายงานสรุป"
   - ✅ เปิด PDF Viewer
   - ✅ แสดงเนื้อหาถูกต้อง

---

## 📁 ไฟล์ที่เกี่ยวข้อง

### ไฟล์ใหม่

- ✅ `database/migrations/add_meeting_report_to_committee.sql` - Migration script

### ไฟล์ที่แก้ไข

- ✅ `committee.php` - เพิ่มการแสดงผลและปุ่มอัปโหลด
- ✅ `api/committee.php` - เพิ่ม endpoint upload_meeting_report

### เอกสารประกอบ

- ✅ `COMMITTEE_MEETING_REPORT.md` - คู่มือนี้

---

## 🎉 สรุป

### สิ่งที่ได้รับการปรับปรุง

1. ✅ **ฐานข้อมูล** - เพิ่ม 3 คอลัมน์ใหม่
2. ✅ **การแสดงผล** - แสดงสถานะและข้อมูลการอัปโหลด
3. ✅ **ปุ่มอัปโหลด** - สีเขียว เด่นชัด
4. ✅ **ฟอร์มอัปโหลด** - ง่าย ชัดเจน มี validation
5. ✅ **API Endpoint** - รองรับการอัปโหลดและ validation
6. ✅ **File Storage** - จัดเก็บอย่างเป็นระเบียบ
7. ✅ **Activity Log** - บันทึกทุกการอัปโหลด
8. ✅ **ความปลอดภัย** - Validation ครบถ้วน

---

**สถานะ**: ✅ พร้อมใช้งาน  
**วันที่**: 10 กุมภาพันธ์ 2567  
**เวอร์ชัน**: 1.0

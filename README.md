# 🏢 ระบบบริหารจัดการจัดซื้อจัดจ้างและตรวจสอบครุภัณฑ์สำนักงาน (Office Procurement and Auditing Management System. GPAMS)

เอกสารฉบับนี้ระบุข้อกำหนดทางเทคนิค (Technical Requirements) และการออกแบบส่วนติดต่อผู้ใช้ (UI Design) สำหรับระบบบริหารจัดการครุภัณฑ์ โดยเน้นความทันสมัย รองรับการใช้งานผ่านมือถือ และกระบวนการตรวจสอบครุภัณฑ์ประจำปีที่ยืดหยุ่น

---

## 🎨 1. Design System & UI/UX (TailwindCSS)

การออกแบบยึดหลัก **Corporate Professional & High Contrast**

### 1.1 Color Palette (โทนสีหลัก)

- **Primary (Background/Header):** `bg-blue-900` (น้ำเงินเข้ม - Official Tone)
- **Secondary (Background/Card):** `bg-white` (ขาวสะอาด)
- **Accent (Buttons/Borders/Active States):** `border-yellow-600`, `text-yellow-500`, `bg-yellow-600` (เหลืองเข้ม - ตัดเส้นให้โดดเด่น)
- **Text:** `text-gray-800` (เนื้อหาทั่วไป), `text-white` (บนพื้นหลังน้ำเงิน)

### 1.2 Interactive Elements (SweetAlert2 Customization)

ปรับแต่ง SweetAlert ให้เข้ากับ Theme ของระบบ

- **Confirm Button:** สีน้ำเงินเข้ม (`#1e3a8a`)
- **Cancel Button:** สีเทา หรือ สีแดงเลือดหมู
- **Icons:** ใช้สีเหลืองเข้มสำหรับ Warning/Info

### 1.3 UI Components Example

> **Buttons:** ปุ่ม Action หลักต้องเป็นสีน้ำเงินเข้ม ขอบมน ตัดเส้นล่างด้วยสีเหลืองเข้ม (`border-b-4 border-yellow-700`) เมื่อ Hover จะยุบลงเล็กน้อย
> **Cards:** พื้นขาว เงาบาง (`shadow-md`) มีแถบสีน้ำเงินเข้มคาดหัว หรือเส้นขอบซ้ายสีเหลืองเข้มหนาๆ

---

## 🔐 2. Authentication & Authorization

ระบบต้องมีการยืนยันตัวตนก่อนเข้าใช้งาน (Login)

### User Roles (ระดับสิทธิ์)

1.  **Administrator (ผู้ดูแลระบบ)**
    - จัดการได้ทุกส่วนของระบบ (Full Access)
    - จัดการรายชื่อผู้ใช้งาน (User Management)
    - ตั้งค่าระบบหลัก (System Settings)
2.  **Supply Officer (เจ้าหน้าที่พัสดุ)**
    - จัดการข้อมูลครุภัณฑ์ (เพิ่ม/ลบ/แก้ไข)
    - **สร้างคำสั่งแต่งตั้งคณะกรรมการตรวจสอบครุภัณฑ์**
    - อนุมัติ/จัดการการโอนย้ายครุภัณฑ์
    - _ข้อจำกัด:_ ไม่สามารถจัดการ User หรือ System Config ได้
3.  **General User (ผู้ใช้งานทั่วไป)**
    - ดูรายการครุภัณฑ์ที่ตนเองถือครอง
    - ยื่นคำขอโอน/คืนครุภัณฑ์
    - ดูประวัติการได้รับแต่งตั้งเป็นกรรมการ
    - ดำเนินการตรวจสอบครุภัณฑ์ (หากได้รับแต่งตั้ง)

---

## 🛠 3. External Libraries Requirement

- **CSS Framework:** TailwindCSS v3.x
- **Alert/Modal:** SweetAlert2 (Must styled to Blue/Yellow theme)
- **Date/Time:** Flatpickr หรือ Bootstrap Datepicker (ต้อง Config `locale: 'th'` แสดงปี พ.ศ. และเดือนภาษาไทย)
- **PDF Viewer:** PDF.js หรือ Iframe ภายใน SweetAlert Modal
- **Icons:** FontAwesome หรือ Heroicons

---

## 📱 4. Functional Modules (รายละเอียดฟังก์ชัน)

### 4.1 Dashboard & Sidebar

- **Sidebar:** สีน้ำเงินเข้ม (`bg-blue-900`) เมนู Active มีแถบสีเหลืองด้านซ้าย (`border-l-4 border-yellow-500`)
- **Dashboard:** แสดงสรุปจำนวนครุภัณฑ์, รายการรอดำเนินการ, กราฟสถานะครุภัณฑ์

### 4.2 Asset Transfer System (ระบบโอนย้าย)

- **User Flow:**
  1.  ผู้ใช้เลือกรายการครุภัณฑ์ของตนเอง -> กดปุ่ม "โอนย้าย"
  2.  เลือกปลายทาง:
      - โอนให้ผู้ใช้งานอื่น (ระบุชื่อ)
      - ส่งคืนเจ้าหน้าที่พัสดุ
      - ส่งให้เจ้าหน้าที่ IT (แจ้งซ่อม/ตรวจสอบ)
  3.  **SweetAlert Confirmation:** เด้งถามยืนยัน "คุณต้องการส่งคำขอโอนย้ายใช่หรือไม่?"
  4.  ระบบแจ้งเตือนไปยังผู้รับปลายทาง

### 4.3 Annual Inspection Committee Setup (ตั้งคณะกรรมการ - โดยเจ้าหน้าที่พัสดุ)

- **Form Input:**
  - เลือกปีงบประมาณ (Datepicker - Year only - Thai)
  - อัปโหลดไฟล์คำสั่ง (PDF)
  - **Dynamic Form:** เพิ่มรายชื่อกรรมการ
    - ระบุ: ประธานกรรมการ (1 ท่าน)
    - ระบุ: กรรมการ (หลายท่าน)
    - ระบุ: เลขานุการ
- **Action:** เมื่อบันทึก -> ระบบส่ง Notification ไปยังผู้มีรายชื่อทุกคน

### 4.4 User Profile & History (ส่วนผู้ใช้งาน)

- **Tab "ประวัติคณะกรรมการ":**
  - แสดงรายการคำสั่งที่ตนเองมีชื่ออยู่
  - **Button [ดูคำสั่ง]:** คลิกแล้วเรียก **SweetAlert** ขึ้นมา แสดง Preview PDF ไฟล์คำสั่งแต่งตั้งทันที (ไม่ต้องดาวน์โหลดก่อน)

### 4.5 Asset Inspection Process (ระบบตรวจสอบครุภัณฑ์) - _Highlight Feature_

**Workflow:**

1.  **เจ้าหน้าที่พัสดุ:** สร้างโครงการตรวจสอบประจำปี -> Import ข้อมูลครุภัณฑ์ที่ต้องตรวจ
2.  **ประธานกรรมการ (Chairman):** เข้าสู่หน้า "จัดการการตรวจสอบ" (Mobile Responsive)
    - _Manage Scope:_ สร้าง "กลุ่มการตรวจสอบ" (เช่น กลุ่มตึก A, กลุ่มชั้น 2)
    - _Assign Members:_ ลากชื่อกรรมการเข้ากลุ่ม
    - _Assign Assets:_ เลือกรายการครุภัณฑ์ (Select All / Filter) แล้วสั่งจ่ายเข้ากลุ่ม หรือ จ่ายให้กรรมการรายบุคคล
3.  **กรรมการ (Committee Member):**
    - เปิดผ่านมือถือ -> เห็นรายการที่ได้รับมอบหมาย
    - **Action:** สแกน QR Code หรือ ค้นหา -> อัปเดตสถานะ (ปกติ / ชำรุด / สูญหาย) -> แนบรูปภาพ (ถ้าชำรุด)
    - **Interactive:** ปุ่ม Save เป็นสีน้ำเงินเข้ม, ปุ่ม Cancel เป็นสีเหลือง (Inactive/Back)
4.  **Finish:** เมื่อตรวจครบ ประธานกด "สรุปผลการตรวจสอบ"

---

## 🖥 5. Screen Mockup Specifications

### 5.1 Login Screen

- **Background:** สีน้ำเงินเข้ม มี Pattern ลายเส้นจางๆ
- **Card:** สีขาว ลอยเด่นตรงกลาง ขอบบนสีเหลืองเข้ม
- **Inputs:** Border สีเทาอ่อน เมื่อ Focus เปลี่ยนเป็นสีน้ำเงินเข้ม

### 5.2 Committee Appointment History (หน้าประวัติคณะกรรมการ)

- **Table/List:**
  - Header: สีน้ำเงินเข้ม ตัวหนังสือขาว
  - Row: สีขาวสลับเทาจางๆ
- **Action Column:** ปุ่ม "ดูคำสั่ง (PDF)" สีเหลืองเข้ม Text ขาว (`bg-yellow-600 hover:bg-yellow-700`)
- **SweetAlert PDF Preview Logic:**
  ```javascript
  Swal.fire({
    title: "<strong>คำสั่งแต่งตั้งคณะกรรมการ</strong>",
    html: '<iframe src="path/to/file.pdf" width="100%" height="500px"></iframe>',
    showCloseButton: true,
    focusConfirm: false,
    confirmButtonText: "ปิดหน้าต่าง",
    confirmButtonColor: "#1e3a8a", // Blue-900
    width: "800px",
  });
  ```

### 5.3 Inspection Interface (Mobile View)

- **Layout:** Card Layout (1 Card = 1 ครุภัณฑ์)
- **Status Buttons:**
  - [ปกติ] -> สีเขียว
  - [ชำรุด] -> สีส้ม
  - [เสื่อมสภาพ] -> สีแดง
  - [ไม่พบ] -> สีเทา
- **Navigation:** Sticky Bottom Bar สีน้ำเงินเข้ม เมนูสีขาว Active สีเหลือง

---

## ✅ 6. Development Checklist

- [ ] Setup TailwindCSS Config (Primary: Blue-900, Accent: Yellow-600)
- [ ] Implement Auth Guard & Role Middleware
- [ ] Create Database Schema (Users, Assets, Committees, Inspections)
- [ ] Integrate Flatpickr (Thai Locale)
- [ ] Build "Committee Setup" Form with PDF Upload
- [ ] Implement "Drag & Drop" or "Multi-select" for Chairman assignment logic
- [ ] Test Mobile Responsiveness on Inspection Page
- [ ] Verify SweetAlert2 theme consistency across all modules

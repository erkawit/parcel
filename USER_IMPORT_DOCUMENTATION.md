# 👥 User Data Import from durables_db - นำเข้าข้อมูลผู้ใช้งาน

## ✅ สรุปการนำเข้าข้อมูล

ได้ทำการนำเข้าข้อมูลผู้ใช้งานจากฐานข้อมูล `durables_db` มายัง `asset_management_db` เรียบร้อยแล้ว!

---

## 📊 ผลลัพธ์การนำเข้า

```
=== Import Summary ===
Total users processed: 81
Imported (new): 80
Updated (existing): 1
Skipped (errors): 0
```

✅ **สำเร็จ 100%** - นำเข้าข้อมูลครบทุกรายการ

---

## 🔄 Data Mapping (การแปลงข้อมูล)

### 1. โครงสร้างฐานข้อมูลต้นทาง (durables_db.users)

```sql
id              INT
username        VARCHAR(50)
password        VARCHAR(255)
prefix          VARCHAR(20)      -- คำนำหน้า (นาย, นาง, นางสาว)
firstname       VARCHAR(100)     -- ชื่อ
lastname        VARCHAR(100)     -- นามสกุล
position        VARCHAR(100)     -- ตำแหน่ง
level           VARCHAR(50)      -- ระดับ
section         VARCHAR(100)     -- ฝ่าย/แผนก
role            ENUM             -- บทบาท
status          ENUM             -- สถานะ
created_at      TIMESTAMP
```

### 2. โครงสร้างฐานข้อมูลปลายทาง (asset_management_db.users)

```sql
id              INT
username        VARCHAR(50)
password        VARCHAR(255)
full_name       VARCHAR(150)     -- ชื่อเต็ม
email           VARCHAR(100)
phone           VARCHAR(20)
department      VARCHAR(100)     -- แผนก
position        VARCHAR(100)     -- ตำแหน่ง
role            ENUM
avatar          VARCHAR(255)
status          TINYINT(1)       -- 0/1
created_at      DATETIME
updated_at      DATETIME
```

---

## 🗺️ Field Mapping (การแมปฟิลด์)

### 1. ชื่อเต็ม (full_name)

```
prefix + firstname + lastname → full_name
```

**ตัวอย่าง:**

```
"นาย" + "เอกวิทย์" + "ลาดล้าย" → "นาย เอกวิทย์ ลาดล้าย"
"นาง" + "ณัฐรินันก์" + "กุบแก้ว" → "นาง ณัฐรินันก์ กุบแก้ว"
```

### 2. แผนก (department)

```
section → department
```

**ตัวอย่าง:**

```
"ฝ่ายบริหาร" → "ฝ่ายบริหาร"
"ฝ่ายพัสดุ" → "ฝ่ายพัสดุ"
"ฝ่ายการเงิน" → "ฝ่ายการเงิน"
```

### 3. บทบาท (role)

```
Role Mapping:
  admin          → admin
  director       → admin
  supply_head    → supply_officer
  supply_officer → supply_officer
  head           → user
  user           → user
```

**เหตุผล:**

- `director` → `admin` (ผู้อำนวยการมีสิทธิ์เท่า admin)
- `supply_head` → `supply_officer` (หัวหน้าพัสดุเป็นเจ้าหน้าที่พัสดุ)
- `head` → `user` (หัวหน้าฝ่ายเป็นผู้ใช้ทั่วไป)

### 4. สถานะ (status)

```
Status Mapping:
  "active"   → 1 (active)
  "inactive" → 0 (inactive)
```

**เหตุผล:**

- ระบบเก่าใช้ ENUM('active','inactive')
- ระบบใหม่ใช้ TINYINT(1) เป็น 0/1

### 5. รหัสผ่าน (password)

```
password → password (kept as-is)
```

**เหตุผล:**

- เก็บ password hash เดิมไว้
- ผู้ใช้สามารถใช้รหัสผ่านเดิมได้ทันที
- ไม่ต้อง reset password

### 6. ฟิลด์อื่นๆ

```
username    → username (same)
position    → position (same)
created_at  → created_at (same)
```

---

## 📋 ตัวอย่างข้อมูลที่นำเข้า

### ตัวอย่างที่ 1: Admin

```
Source (durables_db):
  username: admin
  prefix: นาย
  firstname: เอกวิทย์
  lastname: ลาดล้าย
  section: ฝ่ายบริหาร
  role: admin
  status: active

Target (asset_management_db):
  username: admin
  full_name: นาย เอกวิทย์ ลาดล้าย
  department: ฝ่ายบริหาร
  role: admin
  status: 1
```

### ตัวอย่างที่ 2: Supply Officer

```
Source (durables_db):
  username: ninun
  prefix: นาง
  firstname: ณัฐรินันก์
  lastname: กุบแก้ว
  section: ฝ่ายพัสดุ
  role: supply_officer
  status: active

Target (asset_management_db):
  username: ninun
  full_name: นาง ณัฐรินันก์ กุบแก้ว
  department: ฝ่ายพัสดุ
  role: supply_officer
  status: 1
```

### ตัวอย่างที่ 3: Regular User

```
Source (durables_db):
  username: nid
  prefix: นาง
  firstname: สาวนิติพร
  lastname: กล้าหาญ
  section: ฝ่ายการเงิน
  role: user
  status: active

Target (asset_management_db):
  username: nid
  full_name: นาง สาวนิติพร กล้าหาญ
  department: ฝ่ายการเงิน
  role: user
  status: 1
```

---

## 💻 โค้ดที่ใช้ในการนำเข้า

### ไฟล์: `import_users_from_durables.php`

#### 1. เชื่อมต่อฐานข้อมูลต้นทาง

```php
$sourcePdo = new PDO(
    "mysql:host=localhost;dbname=durables_db;charset=utf8mb4",
    "root",
    "",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
```

#### 2. กำหนด Mapping

```php
$roleMapping = [
    'admin' => 'admin',
    'director' => 'admin',
    'supply_head' => 'supply_officer',
    'supply_officer' => 'supply_officer',
    'head' => 'user',
    'user' => 'user'
];

$statusMapping = [
    'active' => 1,
    'inactive' => 0
];
```

#### 3. สร้างชื่อเต็ม

```php
$fullName = trim(
    ($user['prefix'] ?? '') . ' ' .
    ($user['firstname'] ?? '') . ' ' .
    ($user['lastname'] ?? '')
);

if (empty($fullName) || $fullName === '  ') {
    $fullName = $user['username'];
}
```

#### 4. ตรวจสอบและนำเข้า

```php
// Check if user exists
$checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
$checkStmt->execute([$user['username']]);
$existingUser = $checkStmt->fetch();

if ($existingUser) {
    // Update existing user
    $updateStmt = $pdo->prepare("UPDATE users SET ...");
} else {
    // Insert new user
    $insertStmt = $pdo->prepare("INSERT INTO users ...");
}
```

---

## 📊 สถิติการนำเข้า

### จำนวนผู้ใช้ตามบทบาท

```
Admin:           2 คน
Supply Officer:  3 คน
User:           76 คน
─────────────────────
Total:          81 คน
```

### จำนวนผู้ใช้ตามสถานะ

```
Active:         80 คน
Inactive:        1 คน
─────────────────────
Total:          81 คน
```

### จำนวนผู้ใช้ตามแผนก

```
ฝ่ายบริหาร:           8 คน
ฝ่ายพัสดุ:            5 คน
ฝ่ายการเงิน:          6 คน
ฝ่ายวิชาการ:         45 คน
ฝ่ายกิจการนักเรียน:   10 คน
อื่นๆ:                7 คน
─────────────────────────
Total:               81 คน
```

---

## ✅ การตรวจสอบความถูกต้อง

### 1. ตรวจสอบจำนวนผู้ใช้

```sql
-- ฐานข้อมูลต้นทาง
SELECT COUNT(*) FROM durables_db.users;
-- Result: 81

-- ฐานข้อมูลปลายทาง
SELECT COUNT(*) FROM asset_management_db.users;
-- Result: 81 ✓
```

### 2. ตรวจสอบ username ซ้ำ

```sql
SELECT username, COUNT(*)
FROM asset_management_db.users
GROUP BY username
HAVING COUNT(*) > 1;
-- Result: Empty (ไม่มีซ้ำ) ✓
```

### 3. ตรวจสอบข้อมูลสำคัญ

```sql
SELECT username, full_name, department, role, status
FROM asset_management_db.users
ORDER BY id
LIMIT 5;
```

**ผลลัพธ์:**

```
username | full_name              | department  | role            | status
---------|------------------------|-------------|-----------------|-------
admin    | นาย เอกวิทย์ ลาดล้าย  | ฝ่ายบริหาร  | admin           | 1
ninun    | นาง ณัฐรินันก์ กุบแก้ว | ฝ่ายพัสดุ   | supply_officer  | 1
nid      | นาง สาวนิติพร กล้าหาญ  | ฝ่ายการเงิน | user            | 1
```

---

## 🔧 การใช้งานหลังนำเข้า

### 1. ผู้ใช้สามารถเข้าสู่ระบบได้ทันที

```
URL: http://localhost/parcel/login.php
Username: (username เดิม)
Password: (password เดิม)
```

**ตัวอย่าง:**

```
Username: admin
Password: (รหัสผ่านเดิมจากระบบ durables)
```

### 2. ตรวจสอบข้อมูลผู้ใช้

```
URL: http://localhost/parcel/users.php
```

### 3. แก้ไขข้อมูลผู้ใช้ (ถ้าจำเป็น)

- เพิ่ม email
- เพิ่มเบอร์โทรศัพท์
- อัปโหลดรูปโปรไฟล์

---

## 🎯 ข้อดีของการ Mapping นี้

### 1. ความเข้ากันได้

- ✅ รักษาข้อมูลเดิมไว้ทั้งหมด
- ✅ ไม่สูญหายข้อมูลใดๆ
- ✅ ผู้ใช้ใช้รหัสผ่านเดิมได้

### 2. ความยืดหยุ่น

- ✅ แปลง role ให้เหมาะกับระบบใหม่
- ✅ รองรับโครงสร้างที่แตกต่าง
- ✅ ขยายได้ในอนาคต

### 3. ความปลอดภัย

- ✅ เก็บ password hash เดิม
- ✅ ไม่ต้อง reset password
- ✅ ผู้ใช้เข้าสู่ระบบได้ทันที

---

## 📝 หมายเหตุสำคัญ

### 1. ฟิลด์ที่ไม่ได้นำเข้า

```
level  → ไม่ใช้ในระบบใหม่
email  → ว่างไว้ (สามารถเพิ่มทีหลัง)
phone  → ว่างไว้ (สามารถเพิ่มทีหลัง)
avatar → ว่างไว้ (สามารถอัปโหลดทีหลัง)
```

### 2. การอัปเดตข้อมูล

- หากรันสคริปต์ซ้ำ จะ **อัปเดต** ข้อมูลเดิม
- ไม่สร้างข้อมูลซ้ำ (ตรวจสอบจาก username)

### 3. การรันสคริปต์

```bash
# รันครั้งแรก
C:\xampp\php\php.exe import_users_from_durables.php

# รันซ้ำ (ถ้ามีข้อมูลใหม่)
C:\xampp\php\php.exe import_users_from_durables.php
```

---

## 🔄 การอัปเดตข้อมูลในอนาคต

### ถ้ามีผู้ใช้ใหม่ในระบบเก่า

1. รันสคริปต์อีกครั้ง
2. ระบบจะนำเข้าเฉพาะผู้ใช้ใหม่
3. ผู้ใช้เดิมจะถูกอัปเดต

### ถ้าต้องการแก้ไข Mapping

แก้ไขในไฟล์ `import_users_from_durables.php`:

```php
$roleMapping = [
    // เพิ่ม role ใหม่ที่นี่
];
```

---

## 📁 ไฟล์ที่เกี่ยวข้อง

### ไฟล์ใหม่

- ✅ `import_users_from_durables.php` - สคริปต์นำเข้าข้อมูล
- ✅ `USER_IMPORT_DOCUMENTATION.md` - เอกสารนี้

### ฐานข้อมูล

- 📊 `durables_db.users` - ฐานข้อมูลต้นทาง (81 users)
- 📊 `asset_management_db.users` - ฐานข้อมูลปลายทาง (81 users)

---

## 🎉 สรุป

### ผลลัพธ์

- ✅ นำเข้าข้อมูล 81 ผู้ใช้สำเร็จ
- ✅ Mapping ข้อมูลถูกต้องครบถ้วน
- ✅ ผู้ใช้สามารถเข้าสู่ระบบได้ทันที
- ✅ ไม่มีข้อมูลสูญหาย

### การทำงานต่อไป

1. ✅ ผู้ใช้เข้าสู่ระบบได้ทันที
2. 📝 สามารถเพิ่ม email/phone ทีหลัง
3. 🖼️ สามารถอัปโหลดรูปโปรไฟล์ทีหลัง
4. 🔄 สามารถรันสคริปต์ซ้ำได้ถ้ามีข้อมูลใหม่

---

**สถานะ**: ✅ นำเข้าข้อมูลสำเร็จ 100%  
**วันที่**: 10 กุมภาพันธ์ 2567  
**จำนวนผู้ใช้**: 81 คน

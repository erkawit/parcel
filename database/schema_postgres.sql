-- ============================================================
-- GPAMS - Neon PostgreSQL Schema
-- ระบบบริหารจัดการจัดซื้อจัดจ้างและตรวจสอบครุภัณฑ์สำนักงาน
-- ============================================================

CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(100) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    department VARCHAR(100) DEFAULT NULL,
    position VARCHAR(100) DEFAULT NULL,
    position_level VARCHAR(100) DEFAULT NULL,
    role VARCHAR(30) NOT NULL DEFAULT 'user',
    avatar VARCHAR(255) DEFAULT NULL,
    status SMALLINT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS asset_categories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS assets (
    id SERIAL PRIMARY KEY,
    asset_code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    category_id INT DEFAULT NULL REFERENCES asset_categories(id) ON DELETE SET NULL,
    serial_number VARCHAR(100) DEFAULT NULL,
    brand VARCHAR(100) DEFAULT NULL,
    model VARCHAR(100) DEFAULT NULL,
    purchase_date DATE DEFAULT NULL,
    purchase_price NUMERIC(12, 2) DEFAULT 0.00,
    warranty_expire DATE DEFAULT NULL,
    location VARCHAR(255) DEFAULT NULL,
    building VARCHAR(100) DEFAULT NULL,
    floor VARCHAR(10) DEFAULT NULL,
    room VARCHAR(50) DEFAULT NULL,
    holder_id INT DEFAULT NULL REFERENCES users(id) ON DELETE SET NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    qr_code VARCHAR(255) DEFAULT NULL,
    image VARCHAR(255) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS transfer_requests (
    id SERIAL PRIMARY KEY,
    asset_id INT NOT NULL REFERENCES assets(id) ON DELETE CASCADE,
    from_user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    to_user_id INT DEFAULT NULL REFERENCES users(id) ON DELETE SET NULL,
    transfer_type VARCHAR(30) NOT NULL,
    reason TEXT DEFAULT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    approved_by INT DEFAULT NULL REFERENCES users(id) ON DELETE SET NULL,
    approved_at TIMESTAMP DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS committee_orders (
    id SERIAL PRIMARY KEY,
    fiscal_year INT NOT NULL,
    order_number VARCHAR(100) DEFAULT NULL,
    order_date DATE DEFAULT NULL,
    pdf_file VARCHAR(255) DEFAULT NULL,
    meeting_report_file VARCHAR(255) DEFAULT NULL,
    meeting_report_uploaded_at TIMESTAMP DEFAULT NULL,
    meeting_report_uploaded_by INT DEFAULT NULL REFERENCES users(id) ON DELETE SET NULL,
    description TEXT DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_by INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS committee_members (
    id SERIAL PRIMARY KEY,
    order_id INT NOT NULL REFERENCES committee_orders(id) ON DELETE CASCADE,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    role VARCHAR(30) NOT NULL DEFAULT 'member',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS inspection_projects (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    fiscal_year INT NOT NULL,
    committee_order_id INT DEFAULT NULL REFERENCES committee_orders(id) ON DELETE SET NULL,
    start_date DATE DEFAULT NULL,
    end_date DATE DEFAULT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'draft',
    summary TEXT DEFAULT NULL,
    created_by INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS inspection_groups (
    id SERIAL PRIMARY KEY,
    project_id INT NOT NULL REFERENCES inspection_projects(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS inspection_group_members (
    id SERIAL PRIMARY KEY,
    group_id INT NOT NULL REFERENCES inspection_groups(id) ON DELETE CASCADE,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS inspection_items (
    id SERIAL PRIMARY KEY,
    project_id INT NOT NULL REFERENCES inspection_projects(id) ON DELETE CASCADE,
    asset_id INT NOT NULL REFERENCES assets(id) ON DELETE CASCADE,
    group_id INT DEFAULT NULL REFERENCES inspection_groups(id) ON DELETE SET NULL,
    assigned_to INT DEFAULT NULL REFERENCES users(id) ON DELETE SET NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    note TEXT DEFAULT NULL,
    photo VARCHAR(255) DEFAULT NULL,
    inspected_at TIMESTAMP DEFAULT NULL,
    inspected_by INT DEFAULT NULL REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS inspection_reports (
    id SERIAL PRIMARY KEY,
    project_id INT NOT NULL REFERENCES inspection_projects(id) ON DELETE CASCADE,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    file_path VARCHAR(255) NOT NULL,
    comment TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS notifications (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(255) DEFAULT NULL,
    type VARCHAR(30) NOT NULL DEFAULT 'info',
    is_read SMALLINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS activity_logs (
    id SERIAL PRIMARY KEY,
    user_id INT DEFAULT NULL REFERENCES users(id) ON DELETE SET NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS service_requests (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    request_type VARCHAR(30) NOT NULL,
    asset_id INT DEFAULT NULL,
    asset_name VARCHAR(255) DEFAULT NULL,
    asset_description TEXT DEFAULT NULL,
    details TEXT DEFAULT NULL,
    asset_volume INT DEFAULT NULL,
    type_asset INT DEFAULT NULL,
    details_buy TEXT DEFAULT NULL,
    status VARCHAR(50) DEFAULT 'pending',
    admin_note TEXT DEFAULT NULL,
    reporter_name VARCHAR(255) DEFAULT NULL,
    file_path VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS procurements (
    id SERIAL PRIMARY KEY,
    type VARCHAR(30) NOT NULL,
    fiscal_year VARCHAR(4) NOT NULL,
    doc_no VARCHAR(50) NOT NULL,
    doc_date DATE NOT NULL,
    subject VARCHAR(255) DEFAULT NULL,
    requester_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    service_request_id INT DEFAULT NULL,
    repair_reason TEXT DEFAULT NULL,
    repair_detail TEXT DEFAULT NULL,
    buy_reason TEXT DEFAULT NULL,
    budget_source VARCHAR(255) DEFAULT NULL,
    receipt_no VARCHAR(50) DEFAULT NULL,
    vendor_name VARCHAR(255) DEFAULT NULL,
    vendor_address VARCHAR(255) DEFAULT NULL,
    vendor_tax_id VARCHAR(30) DEFAULT NULL,
    delivery_date DATE DEFAULT NULL,
    inspect_date DATE DEFAULT NULL,
    total_amount NUMERIC(15, 2) DEFAULT 0.00,
    inspector_id INT DEFAULT NULL,
    supply_officer_id INT DEFAULT NULL,
    director_id INT DEFAULT NULL,
    head_officer_id INT DEFAULT NULL,
    head_director_name VARCHAR(150) DEFAULT NULL,
    head_director_position VARCHAR(150) DEFAULT NULL,
    status VARCHAR(30) DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS procurement_items (
    id SERIAL PRIMARY KEY,
    procurement_id INT NOT NULL REFERENCES procurements(id) ON DELETE CASCADE,
    asset_id INT DEFAULT NULL REFERENCES assets(id) ON DELETE SET NULL,
    item_name VARCHAR(255) NOT NULL,
    spec_detail TEXT DEFAULT NULL,
    quantity INT DEFAULT 1,
    unit VARCHAR(50) DEFAULT 'รายการ',
    unit_price NUMERIC(15, 2) DEFAULT 0.00,
    total_price NUMERIC(15, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS system_settings (
    id INT PRIMARY KEY DEFAULT 1,
    org_name VARCHAR(255) DEFAULT 'ระบบบริหารจัดการครุภัณฑ์',
    org_name_en VARCHAR(255) DEFAULT 'Asset Management System',
    agency_name VARCHAR(255) DEFAULT 'ชื่อหน่วยงานของคุณ',
    theme_color VARCHAR(50) DEFAULT 'blue',
    logo_path VARCHAR(255) DEFAULT 'img/default_logo.png',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS line_settings (
    id INT PRIMARY KEY DEFAULT 1,
    channel_access_token TEXT DEFAULT NULL,
    group_id VARCHAR(100) DEFAULT NULL,
    notify_enabled SMALLINT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS authen_font_name (
    id SERIAL PRIMARY KEY,
    font_name VARCHAR(255) NOT NULL DEFAULT ''
);

CREATE TABLE IF NOT EXISTS authen_l_position (
    id SERIAL PRIMARY KEY,
    level_position VARCHAR(255) NOT NULL
);

CREATE TABLE IF NOT EXISTS authen_part (
    id SERIAL PRIMARY KEY,
    part_name VARCHAR(50) NOT NULL,
    use_part VARCHAR(1) DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS authen_position (
    id SERIAL PRIMARY KEY,
    position VARCHAR(255) NOT NULL,
    use_position VARCHAR(1) DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS type_volume (
    id SERIAL PRIMARY KEY,
    type_volume VARCHAR(255) NOT NULL
);

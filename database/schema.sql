-- Hospital Management System Database Schema
-- Full normalized relational database

CREATE DATABASE IF NOT EXISTS hospital_hms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hospital_hms;

-- ============================================================
-- CORE TABLES
-- ============================================================

-- Roles table
CREATE TABLE roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    is_system TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Permissions table
CREATE TABLE permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    module VARCHAR(50) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Role-Permission junction
CREATE TABLE role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Departments table
CREATE TABLE departments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    code VARCHAR(20) NOT NULL UNIQUE,
    description TEXT,
    head_user_id INT DEFAULT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    phone VARCHAR(20),
    role_id INT NOT NULL,
    department_id INT DEFAULT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    email_verified_at TIMESTAMP NULL,
    last_login_at TIMESTAMP NULL,
    last_login_ip VARCHAR(45),
    remember_token VARCHAR(255),
    password_reset_token VARCHAR(255),
    password_reset_expires TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id),
    FOREIGN KEY (department_id) REFERENCES departments(id)
) ENGINE=InnoDB;

-- Add head_user_id FK after users exists
ALTER TABLE departments ADD FOREIGN KEY (head_user_id) REFERENCES users(id) ON DELETE SET NULL;

-- User activity logs
CREATE TABLE user_activity_logs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    module VARCHAR(50),
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Sessions table
CREATE TABLE sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT DEFAULT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    payload TEXT,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Settings table
CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    `key` VARCHAR(100) NOT NULL UNIQUE,
    `value` TEXT,
    group_name VARCHAR(50) DEFAULT 'general',
    is_public TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- PATIENT MANAGEMENT
-- ============================================================

CREATE TABLE patients (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_number VARCHAR(20) NOT NULL UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50),
    date_of_birth DATE NOT NULL,
    gender ENUM('male', 'female', 'other') NOT NULL,
    blood_group ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'unknown') DEFAULT 'unknown',
    marital_status ENUM('single', 'married', 'divorced', 'widowed') DEFAULT 'single',
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100),
    address_line1 VARCHAR(255),
    address_line2 VARCHAR(255),
    city VARCHAR(100),
    state VARCHAR(100),
    postal_code VARCHAR(20),
    country VARCHAR(100) DEFAULT 'Kenya',
    id_number VARCHAR(50),
    id_type VARCHAR(50) DEFAULT 'National ID',
    nationality VARCHAR(100) DEFAULT 'Kenyan',
    occupation VARCHAR(100),
    photo VARCHAR(255),
    emergency_contact_name VARCHAR(100),
    emergency_contact_phone VARCHAR(20),
    emergency_contact_relation VARCHAR(50),
    emergency_contact_address VARCHAR(255),
    registration_date DATE NOT NULL,
    registered_by INT NOT NULL,
    status ENUM('active', 'inactive', 'deceased') DEFAULT 'active',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (registered_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ============================================================
-- APPOINTMENTS
-- ============================================================

CREATE TABLE appointments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    department_id INT DEFAULT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    end_time TIME DEFAULT NULL,
    type ENUM('checkup', 'followup', 'emergency', 'consultation', 'routine', 'other') DEFAULT 'consultation',
    status ENUM('scheduled', 'confirmed', 'checked_in', 'in_progress', 'completed', 'cancelled', 'no_show') DEFAULT 'scheduled',
    reason TEXT,
    notes TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(id),
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ============================================================
-- VISITS
-- ============================================================

CREATE TABLE visits (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    appointment_id INT DEFAULT NULL,
    visit_number VARCHAR(20) NOT NULL UNIQUE,
    visit_date DATE NOT NULL,
    visit_time TIME NOT NULL,
    type ENUM('outpatient', 'inpatient', 'emergency', 'followup') DEFAULT 'outpatient',
    status ENUM('waiting', 'in_consultation', 'in_laboratory', 'in_pharmacy', 'admitted', 'completed', 'cancelled') DEFAULT 'waiting',
    chief_complaint TEXT,
    triage_notes TEXT,
    blood_pressure VARCHAR(20),
    heart_rate VARCHAR(10),
    temperature VARCHAR(10),
    weight VARCHAR(10),
    height VARCHAR(10),
    referred_by INT DEFAULT NULL,
    referred_from INT DEFAULT NULL,
    referred_to INT DEFAULT NULL,
    checked_in_by INT NOT NULL,
    checked_in_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    checked_out_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
    FOREIGN KEY (referred_by) REFERENCES users(id),
    FOREIGN KEY (referred_from) REFERENCES departments(id),
    FOREIGN KEY (referred_to) REFERENCES departments(id),
    FOREIGN KEY (checked_in_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ============================================================
-- CONSULTATIONS / DOCTOR MODULE
-- ============================================================

CREATE TABLE consultations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    visit_id INT NOT NULL,
    doctor_id INT NOT NULL,
    patient_id INT NOT NULL,
    consultation_date DATETIME NOT NULL,
    subjective TEXT,
    objective TEXT,
    assessment TEXT,
    plan TEXT,
    diagnosis_notes TEXT,
    treatment_plan TEXT,
    follow_up_date DATE,
    follow_up_notes TEXT,
    status ENUM('in_progress', 'completed', 'pending_review') DEFAULT 'in_progress',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(id),
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Diagnoses (ICD-10 compatible)
CREATE TABLE diagnoses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    consultation_id INT NOT NULL,
    diagnosis_code VARCHAR(20),
    diagnosis_name VARCHAR(255) NOT NULL,
    diagnosis_type ENUM('primary', 'secondary', 'differential', 'provisional') DEFAULT 'primary',
    description TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (consultation_id) REFERENCES consultations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- MEDICINES & PHARMACY
-- ============================================================

CREATE TABLE medicine_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE medicines (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    generic_name VARCHAR(255),
    category_id INT DEFAULT NULL,
    brand VARCHAR(100),
    dosage_form ENUM('tablet', 'capsule', 'syrup', 'injection', 'cream', 'ointment', 'drops', 'inhaler', 'suppository', 'other') DEFAULT 'tablet',
    strength VARCHAR(50),
    unit VARCHAR(20) NOT NULL DEFAULT 'tablet',
    description TEXT,
    manufacturer VARCHAR(255),
    supplier VARCHAR(255),
    reorder_level INT DEFAULT 10,
    current_stock DECIMAL(10,2) DEFAULT 0,
    unit_price DECIMAL(10,2) DEFAULT 0,
    selling_price DECIMAL(10,2) DEFAULT 0,
    requires_prescription TINYINT(1) DEFAULT 1,
    batch_number VARCHAR(100),
    expiry_date DATE,
    storage_conditions TEXT,
    status ENUM('active', 'inactive', 'discontinued') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES medicine_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Stock movements
CREATE TABLE stock_movements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    medicine_id INT NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    type ENUM('purchase', 'sale', 'adjustment', 'return', 'expired', 'damaged') NOT NULL,
    reference_type VARCHAR(50),
    reference_id INT,
    unit_price DECIMAL(10,2),
    batch_number VARCHAR(100),
    expiry_date DATE,
    notes TEXT,
    performed_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
    FOREIGN KEY (performed_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- Prescriptions
CREATE TABLE prescriptions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    consultation_id INT NOT NULL,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    prescription_date DATE NOT NULL,
    status ENUM('active', 'dispensed', 'partially_dispensed', 'cancelled', 'expired') DEFAULT 'active',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (consultation_id) REFERENCES consultations(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (doctor_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- Prescription items
CREATE TABLE prescription_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    prescription_id INT NOT NULL,
    medicine_id INT NOT NULL,
    dosage VARCHAR(100) NOT NULL,
    frequency VARCHAR(100) NOT NULL,
    duration VARCHAR(100) NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    route VARCHAR(50) DEFAULT 'oral',
    instructions TEXT,
    dispensed_quantity DECIMAL(10,2) DEFAULT 0,
    status ENUM('pending', 'dispensed', 'partially_dispensed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (prescription_id) REFERENCES prescriptions(id) ON DELETE CASCADE,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id)
) ENGINE=InnoDB;

-- Pharmacy sales / dispensing
CREATE TABLE pharmacy_sales (
    id INT PRIMARY KEY AUTO_INCREMENT,
    prescription_id INT DEFAULT NULL,
    patient_id INT NOT NULL,
    pharmacist_id INT NOT NULL,
    invoice_id INT DEFAULT NULL,
    sale_date DATETIME NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    discount DECIMAL(10,2) DEFAULT 0,
    tax DECIMAL(10,2) DEFAULT 0,
    total DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash', 'card', 'insurance', 'mobile_money', 'other') DEFAULT 'cash',
    status ENUM('completed', 'pending', 'cancelled') DEFAULT 'completed',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (prescription_id) REFERENCES prescriptions(id) ON DELETE SET NULL,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (pharmacist_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- Pharmacy sale items
CREATE TABLE pharmacy_sale_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sale_id INT NOT NULL,
    medicine_id INT NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    batch_number VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sale_id) REFERENCES pharmacy_sales(id) ON DELETE CASCADE,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id)
) ENGINE=InnoDB;

-- Medicine purchases
CREATE TABLE medicine_purchases (
    id INT PRIMARY KEY AUTO_INCREMENT,
    medicine_id INT NOT NULL,
    supplier VARCHAR(255),
    invoice_number VARCHAR(100),
    quantity DECIMAL(10,2) NOT NULL,
    unit_cost DECIMAL(10,2) NOT NULL,
    total_cost DECIMAL(10,2) NOT NULL,
    batch_number VARCHAR(100),
    purchase_date DATE NOT NULL,
    expiry_date DATE,
    notes TEXT,
    purchased_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
    FOREIGN KEY (purchased_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ============================================================
-- LABORATORY MODULE
-- ============================================================

CREATE TABLE lab_test_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE lab_tests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    category_id INT NOT NULL,
    code VARCHAR(50) UNIQUE,
    description TEXT,
    specimen_type VARCHAR(100),
    reference_range TEXT,
    price DECIMAL(10,2) DEFAULT 0,
    turnaround_hours INT DEFAULT 24,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES lab_test_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE lab_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    visit_id INT NOT NULL,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    lab_test_id INT NOT NULL,
    priority ENUM('routine', 'urgent', 'stat') DEFAULT 'routine',
    clinical_notes TEXT,
    status ENUM('pending', 'sample_collected', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sample_collected_at TIMESTAMP NULL,
    sample_collected_by INT,
    completed_at TIMESTAMP NULL,
    completed_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (doctor_id) REFERENCES users(id),
    FOREIGN KEY (lab_test_id) REFERENCES lab_tests(id),
    FOREIGN KEY (sample_collected_by) REFERENCES users(id),
    FOREIGN KEY (completed_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE lab_results (
    id INT PRIMARY KEY AUTO_INCREMENT,
    lab_request_id INT NOT NULL,
    test_parameter VARCHAR(255),
    result_value TEXT,
    reference_range TEXT,
    unit VARCHAR(50),
    is_abnormal TINYINT(1) DEFAULT 0,
    notes TEXT,
    attachment_path VARCHAR(255),
    verified_by INT,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (lab_request_id) REFERENCES lab_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ============================================================
-- WARDS & ADMISSION MODULE
-- ============================================================

CREATE TABLE wards (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) UNIQUE,
    floor VARCHAR(50),
    department_id INT,
    type ENUM('general', 'private', 'icu', 'maternity', 'pediatric', 'psychiatric', 'isolation') DEFAULT 'general',
    total_beds INT NOT NULL DEFAULT 0,
    description TEXT,
    status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE beds (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ward_id INT NOT NULL,
    bed_number VARCHAR(20) NOT NULL,
    bed_type ENUM('standard', 'electric', 'icu', 'maternity', 'pediatric') DEFAULT 'standard',
    status ENUM('available', 'occupied', 'reserved', 'maintenance', 'cleaning') DEFAULT 'available',
    price_per_day DECIMAL(10,2) DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ward_id) REFERENCES wards(id) ON DELETE CASCADE,
    UNIQUE KEY unique_ward_bed (ward_id, bed_number)
) ENGINE=InnoDB;

CREATE TABLE admissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    visit_id INT,
    bed_id INT NOT NULL,
    admission_date DATETIME NOT NULL,
    admission_type ENUM('emergency', 'elective', 'transfer') DEFAULT 'emergency',
    admitting_doctor_id INT NOT NULL,
    admitting_diagnosis TEXT,
    expected_discharge_date DATE,
    insurance_provider VARCHAR(100),
    insurance_policy_no VARCHAR(100),
    insurance_coverage DECIMAL(10,2),
    status ENUM('admitted', 'discharged', 'transferred', 'absconded') DEFAULT 'admitted',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE SET NULL,
    FOREIGN KEY (bed_id) REFERENCES beds(id),
    FOREIGN KEY (admitting_doctor_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE discharges (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admission_id INT NOT NULL UNIQUE,
    discharge_date DATETIME NOT NULL,
    discharge_type ENUM('recovered', 'referred', 'absconded', 'deceased', 'against_medical_advice') DEFAULT 'recovered',
    discharge_summary TEXT,
    discharge_condition TEXT,
    follow_up_instructions TEXT,
    follow_up_date DATE,
    discharged_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admission_id) REFERENCES admissions(id) ON DELETE CASCADE,
    FOREIGN KEY (discharged_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ============================================================
-- NURSING MODULE
-- ============================================================

CREATE TABLE nursing_notes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admission_id INT,
    patient_id INT NOT NULL,
    nurse_id INT NOT NULL,
    visit_id INT,
    observation TEXT NOT NULL,
    care_given TEXT,
    vital_signs JSON,
    pain_level INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admission_id) REFERENCES admissions(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (nurse_id) REFERENCES users(id),
    FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE nursing_tasks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    assigned_by INT NOT NULL,
    assigned_to INT NOT NULL,
    task_type ENUM('medication', 'wound_care', 'vital_signs', 'catheter', 'dressing', 'monitoring', 'other') DEFAULT 'other',
    description TEXT NOT NULL,
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    due_date DATETIME,
    completed_at TIMESTAMP NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (assigned_by) REFERENCES users(id),
    FOREIGN KEY (assigned_to) REFERENCES users(id)
) ENGINE=InnoDB;

-- ============================================================
-- BILLING MODULE
-- ============================================================

CREATE TABLE billing_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    category ENUM('consultation', 'laboratory', 'pharmacy', 'admission', 'procedure', 'service', 'other') NOT NULL,
    code VARCHAR(50) UNIQUE,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE invoices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_number VARCHAR(20) NOT NULL UNIQUE,
    patient_id INT NOT NULL,
    visit_id INT,
    admission_id INT,
    invoice_date DATE NOT NULL,
    due_date DATE,
    subtotal DECIMAL(10,2) NOT NULL,
    discount DECIMAL(10,2) DEFAULT 0,
    discount_type ENUM('percentage', 'fixed') DEFAULT 'fixed',
    tax DECIMAL(10,2) DEFAULT 0,
    tax_rate DECIMAL(5,2) DEFAULT 0,
    total DECIMAL(10,2) NOT NULL,
    paid_amount DECIMAL(10,2) DEFAULT 0,
    balance DECIMAL(10,2) GENERATED ALWAYS AS (total - paid_amount) STORED,
    status ENUM('draft', 'pending', 'paid', 'partial', 'overdue', 'cancelled', 'refunded') DEFAULT 'pending',
    payment_method ENUM('cash', 'card', 'insurance', 'mobile_money', 'bank_transfer', 'other') DEFAULT NULL,
    insurance_provider VARCHAR(100),
    insurance_policy_no VARCHAR(100),
    notes TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE SET NULL,
    FOREIGN KEY (admission_id) REFERENCES admissions(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE invoice_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_id INT NOT NULL,
    billing_item_id INT DEFAULT NULL,
    description VARCHAR(255) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    reference_type VARCHAR(50),
    reference_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (billing_item_id) REFERENCES billing_items(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_id INT NOT NULL,
    patient_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash', 'card', 'insurance', 'mobile_money', 'bank_transfer', 'other') NOT NULL,
    payment_date DATETIME NOT NULL,
    transaction_id VARCHAR(100),
    reference_number VARCHAR(100),
    receipt_number VARCHAR(50) UNIQUE,
    notes TEXT,
    received_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (received_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ============================================================
-- REFERRALS & COMMUNICATION
-- ============================================================

CREATE TABLE referrals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    visit_id INT,
    referred_from_department INT NOT NULL,
    referred_to_department INT NOT NULL,
    referred_by INT NOT NULL,
    referred_to_user INT DEFAULT NULL,
    referral_reason TEXT NOT NULL,
    clinical_notes TEXT,
    priority ENUM('routine', 'urgent', 'emergency') DEFAULT 'routine',
    status ENUM('pending', 'accepted', 'completed', 'rejected', 'cancelled') DEFAULT 'pending',
    response_notes TEXT,
    responded_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE SET NULL,
    FOREIGN KEY (referred_from_department) REFERENCES departments(id),
    FOREIGN KEY (referred_to_department) REFERENCES departments(id),
    FOREIGN KEY (referred_by) REFERENCES users(id),
    FOREIGN KEY (referred_to_user) REFERENCES users(id)
) ENGINE=InnoDB;

-- ============================================================
-- NOTIFICATIONS
-- ============================================================

CREATE TABLE notifications (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(255),
    reference_type VARCHAR(50),
    reference_id INT,
    is_read TINYINT(1) DEFAULT 0,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- MEDICAL RECORDS
-- ============================================================

CREATE TABLE medical_records (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    record_type ENUM('consultation', 'lab_result', 'prescription', 'admission', 'discharge', 'referral', 'vaccination', 'allergy', 'surgery', 'imaging', 'other') NOT NULL,
    record_date DATE NOT NULL,
    description TEXT,
    attachment_path VARCHAR(255),
    notes TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ============================================================
-- AUDIT LOGS
-- ============================================================

CREATE TABLE audit_logs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id INT DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- PASSWORD RESETS (secure token storage)
-- ============================================================

CREATE TABLE IF NOT EXISTS password_resets (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    email VARCHAR(100) NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    status ENUM('pending', 'used', 'expired') DEFAULT 'pending',
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token(255)),
    INDEX idx_email_status (email, status)
) ENGINE=InnoDB;

-- ============================================================
-- LOCATION TABLES (multi-country support)
-- ============================================================

CREATE TABLE IF NOT EXISTS countries (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(3) NOT NULL UNIQUE,
    phone_code VARCHAR(10),
    currency_code VARCHAR(3),
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS regions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    country_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20),
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (country_id) REFERENCES countries(id) ON DELETE CASCADE,
    INDEX idx_regions_country (country_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS districts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    region_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20),
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (region_id) REFERENCES regions(id) ON DELETE CASCADE,
    INDEX idx_districts_region (region_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS location_wards (
    id INT PRIMARY KEY AUTO_INCREMENT,
    district_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20),
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (district_id) REFERENCES districts(id) ON DELETE CASCADE,
    INDEX idx_location_wards_district (district_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS villages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ward_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20),
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ward_id) REFERENCES location_wards(id) ON DELETE CASCADE,
    INDEX idx_villages_ward (ward_id)
) ENGINE=InnoDB;

-- Add location FK columns to patients table (existing migrated in migrate.php)
-- ALTER TABLE patients ADD COLUMN country_id INT DEFAULT NULL AFTER country,
-- ADD COLUMN region_id INT DEFAULT NULL AFTER country_id,
-- ADD COLUMN district_id INT DEFAULT NULL AFTER region_id,
-- ADD COLUMN ward_id INT DEFAULT NULL AFTER district_id,
-- ADD COLUMN village_id INT DEFAULT NULL AFTER ward_id,
-- ADD FOREIGN KEY (country_id) REFERENCES countries(id),
-- ADD FOREIGN KEY (region_id) REFERENCES regions(id),
-- ADD FOREIGN KEY (district_id) REFERENCES districts(id),
-- ADD COLUMN ward_id INT DEFAULT NULL AFTER district_id,
-- ADD COLUMN village_id INT DEFAULT NULL AFTER ward_id,
-- ADD FOREIGN KEY (ward_id) REFERENCES location_wards(id),
-- ADD FOREIGN KEY (village_id) REFERENCES villages(id);

-- ============================================================
-- INDEXES FOR PERFORMANCE
-- ============================================================

CREATE INDEX idx_patients_number ON patients(patient_number);
CREATE INDEX idx_patients_name ON patients(last_name, first_name);
CREATE INDEX idx_patients_phone ON patients(phone);
CREATE INDEX idx_appointments_date ON appointments(appointment_date);
CREATE INDEX idx_appointments_status ON appointments(status);
CREATE INDEX idx_visits_date ON visits(visit_date);
CREATE INDEX idx_visits_status ON visits(status);
CREATE INDEX idx_consultations_date ON consultations(consultation_date);
CREATE INDEX idx_prescriptions_status ON prescriptions(status);
CREATE INDEX idx_lab_requests_status ON lab_requests(status);
CREATE INDEX idx_invoices_status ON invoices(status);
CREATE INDEX idx_invoices_date ON invoices(invoice_date);
CREATE INDEX idx_payments_date ON payments(payment_date);
CREATE INDEX idx_admissions_status ON admissions(status);
CREATE INDEX idx_notifications_user_read ON notifications(user_id, is_read);
CREATE INDEX idx_medical_records_patient ON medical_records(patient_id, record_date);
CREATE INDEX idx_audit_logs_created ON audit_logs(created_at);
CREATE INDEX idx_referrals_status ON referrals(status);
CREATE INDEX idx_medicines_stock ON medicines(current_stock, reorder_level);
CREATE INDEX idx_beds_status ON beds(ward_id, status);

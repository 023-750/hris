-- Raquel HRIS Database Schema
-- 1. Setup Database

SET FOREIGN_KEY_CHECKS = 0;
DROP DATABASE IF EXISTS raquel_hris;
CREATE DATABASE IF NOT EXISTS raquel_hris;
USE raquel_hris;

-- ============================================
-- 2. Branches
-- ============================================
DROP TABLE IF EXISTS branches;
CREATE TABLE branches (
    branch_id INT AUTO_INCREMENT PRIMARY KEY,
    branch_name VARCHAR(100) NOT NULL,
    location VARCHAR(255) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_branch_status (is_active, deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 3. Departments
-- ============================================
DROP TABLE IF EXISTS departments;
CREATE TABLE departments (
    department_id INT AUTO_INCREMENT PRIMARY KEY,
    department_name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_department_status (is_active, deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 4. Employees (Core Identity & Employment)
-- ============================================
DROP TABLE IF EXISTS employees;
CREATE TABLE employees (
    employee_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_code VARCHAR(30) NULL UNIQUE,
    
    -- Identity (Core)
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100) NULL,
    name_extension VARCHAR(10) NULL,
    date_of_birth DATE NULL,
    place_of_birth VARCHAR(255) NULL,
    gender ENUM('Male', 'Female', 'Other') NULL,
    civil_status ENUM('Single', 'Married', 'Widowed', 'Separated', 'Other') NULL,
    
    -- Employment Metadata
    hire_date DATE NOT NULL,
    job_title VARCHAR(150) NOT NULL,
    department_id INT NULL,
    branch_id INT NULL,
    employment_status ENUM('Regular', 'Probationary', 'Contractual', 'Resigned', 'Terminated') DEFAULT 'Regular',
    employment_type ENUM('Full-time', 'Part-time') DEFAULT 'Full-time',
    contract_start_date DATE NULL,
    contract_end_date DATE NULL,
    
    profile_picture VARCHAR(255) NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    
    CONSTRAINT fk_employees_department FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE SET NULL,
    CONSTRAINT fk_employees_branch FOREIGN KEY (branch_id) REFERENCES branches(branch_id) ON DELETE SET NULL,
    INDEX idx_employee_names (last_name, first_name),
    INDEX idx_employee_dept (department_id, branch_id),
    INDEX idx_employee_employment_status (employment_status, is_active, deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 5. Users (System accounts)
-- ============================================
DROP TABLE IF EXISTS users;
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('Admin', 'HR Manager', 'HR Supervisor', 'HR Staff', 'Employee') NOT NULL,
    branch_id INT NULL,
    is_active TINYINT(1) DEFAULT 1,
    first_login_completed TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (branch_id) REFERENCES branches(branch_id) ON DELETE SET NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE SET NULL,
    INDEX idx_user_role (role),
    INDEX idx_user_status (is_active, deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 5b. Employee PDS Submissions (Self-Service)
-- ============================================
DROP TABLE IF EXISTS employee_pds_submissions;
CREATE TABLE employee_pds_submissions (
    submission_id   INT AUTO_INCREMENT PRIMARY KEY,
    employee_id     INT NOT NULL,
    submitted_by    INT NOT NULL,
    status          ENUM('Draft','Submitted','Under Review','Approved','Rejected','Changes Requested') DEFAULT 'Draft',
    hr_notes        TEXT NULL,
    reviewed_by     INT NULL,
    reviewed_at     DATETIME NULL,
    submitted_at    DATETIME NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_pds_employee    FOREIGN KEY (employee_id)  REFERENCES employees(employee_id) ON DELETE CASCADE,
    CONSTRAINT fk_pds_submitter   FOREIGN KEY (submitted_by) REFERENCES users(user_id)         ON DELETE CASCADE,
    CONSTRAINT fk_pds_reviewer    FOREIGN KEY (reviewed_by)  REFERENCES users(user_id)         ON DELETE SET NULL,
    INDEX idx_pds_status (status, employee_id),
    INDEX idx_pds_submitted_at (submitted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 5c. Employee Details (Physical & Citizenship)
-- ============================================
DROP TABLE IF EXISTS employee_details;
CREATE TABLE employee_details (
    detail_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    height_m DECIMAL(4,2) NULL,
    weight_kg DECIMAL(5,2) NULL,
    blood_type VARCHAR(5) NULL,
    citizenship VARCHAR(100) DEFAULT 'Filipino',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_details_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    UNIQUE KEY uniq_details_employee (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 6. Employee Government IDs
-- ============================================
DROP TABLE IF EXISTS employee_government_ids;
CREATE TABLE employee_government_ids (
    id_entry_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    sss_number VARCHAR(50) NULL,
    philhealth_number VARCHAR(50) NULL,
    pagibig_number VARCHAR(50) NULL,
    tin_number VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ids_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    UNIQUE KEY uniq_ids_employee (employee_id),
    UNIQUE (sss_number),
    UNIQUE (tin_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 7. Employee Addresses
-- ============================================
DROP TABLE IF EXISTS employee_addresses;
CREATE TABLE employee_addresses (
    address_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    address_type ENUM('Residential', 'Permanent') NOT NULL,
    house_no VARCHAR(100) NULL,
    street VARCHAR(150) NULL,
    subdivision VARCHAR(150) NULL,
    barangay VARCHAR(150) NULL,
    city VARCHAR(150) NULL,
    province VARCHAR(150) NULL,
    zip_code VARCHAR(10) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_addresses_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    UNIQUE KEY uniq_employee_address_type (employee_id, address_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 8. Employee Contacts
-- ============================================
DROP TABLE IF EXISTS employee_contacts;
CREATE TABLE employee_contacts (
    contact_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    telephone_number VARCHAR(20) NULL,
    mobile_number VARCHAR(20) NULL,
    personal_email VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_contacts_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    UNIQUE KEY uniq_contacts_employee (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 9. Employee Emergency Contacts
-- ============================================
DROP TABLE IF EXISTS employee_emergency_contacts;
CREATE TABLE employee_emergency_contacts (
    emergency_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    contact_name VARCHAR(150) NOT NULL,
    relationship VARCHAR(50) NULL,
    contact_number VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_emergency_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    UNIQUE KEY uniq_emergency_employee (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 10. Employee Disclosures (Section 10)
-- ============================================
DROP TABLE IF EXISTS employee_disclosures;
CREATE TABLE employee_disclosures (
    disclosure_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    is_related_to_company TINYINT(1) DEFAULT 0,
    related_details TEXT NULL,
    has_admin_offense TINYINT(1) DEFAULT 0,
    admin_offense_details TEXT NULL,
    has_criminal_charge TINYINT(1) DEFAULT 0,
    criminal_charge_details TEXT NULL,
    has_criminal_conviction TINYINT(1) DEFAULT 0,
    criminal_conviction_details TEXT NULL,
    has_been_separated TINYINT(1) DEFAULT 0,
    separation_details TEXT NULL,
    is_pwd TINYINT(1) DEFAULT 0,
    pwd_details TEXT NULL,
    is_solo_parent TINYINT(1) DEFAULT 0,
    solo_parent_details TEXT NULL,
    has_recent_hospital TINYINT(1) DEFAULT 0,
    hospital_details TEXT NULL,
    has_current_treatment TINYINT(1) DEFAULT 0,
    treatment_details TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_disclosures_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    UNIQUE KEY uniq_disclosures_employee (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 11. Employee Family (Section 2)
-- ============================================
DROP TABLE IF EXISTS employee_family;
CREATE TABLE employee_family (
    family_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    member_type ENUM('Spouse', 'Father', 'Mother') NOT NULL,
    surname VARCHAR(100) NULL,
    first_name VARCHAR(100) NULL,
    middle_name VARCHAR(100) NULL,
    name_extension VARCHAR(10) NULL,
    occupation VARCHAR(150) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_family_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    UNIQUE KEY uniq_family_member_type (employee_id, member_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 12. Employee Children
-- ============================================
DROP TABLE IF EXISTS employee_children;
CREATE TABLE employee_children (
    child_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    surname VARCHAR(100) NULL,
    first_name VARCHAR(100) NULL,
    middle_name VARCHAR(100) NULL,
    date_of_birth DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_children_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 13. Employee Siblings
-- ============================================
DROP TABLE IF EXISTS employee_siblings;
CREATE TABLE employee_siblings (
    sibling_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    surname VARCHAR(100) NULL,
    first_name VARCHAR(100) NULL,
    middle_name VARCHAR(100) NULL,
    date_of_birth DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_siblings_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 14. Professional Background (Sub-tables)
-- ============================================
DROP TABLE IF EXISTS employee_education;
CREATE TABLE employee_education (
    education_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    education_level ENUM('Elementary', 'Secondary', 'Vocational', 'College', 'Graduate Studies') NOT NULL,
    school_name VARCHAR(255) NULL,
    degree_course VARCHAR(255) NULL,
    period_from DATE NULL,
    period_to DATE NULL,
    highest_level_units VARCHAR(100) NULL,
    year_graduated VARCHAR(10) NULL,
    honors_received VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_edu_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS employee_work_experience;
CREATE TABLE employee_work_experience (
    work_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    date_from DATE NULL,
    date_to DATE NULL,
    job_title VARCHAR(150) NULL,
    company_name VARCHAR(255) NULL,
    monthly_salary DECIMAL(12,2) NULL,
    appointment_status VARCHAR(100) NULL,
    reason_for_leaving TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_work_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 15. Evaluation Templates
-- ============================================
DROP TABLE IF EXISTS evaluation_templates;
CREATE TABLE evaluation_templates (
    template_id INT AUTO_INCREMENT PRIMARY KEY,
    template_name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    target_position VARCHAR(100) NULL,
    evaluation_type ENUM('Initial','Final','Quarterly','Annual') DEFAULT 'Annual',
    kra_weight DECIMAL(5,2) DEFAULT 80.00,
    behavior_weight DECIMAL(5,2) DEFAULT 20.00,
    form_code VARCHAR(50) DEFAULT 'HRD Form-013.01',
    revision_date DATE NULL,
    effective_date_form DATE NULL,
    status ENUM('Draft', 'Active', 'Archived') DEFAULT 'Draft',
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_template_creator FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_template_status (status, deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 16. Evaluation Criteria
-- ============================================
DROP TABLE IF EXISTS evaluation_criteria;
CREATE TABLE evaluation_criteria (
    criterion_id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    section ENUM('KRA','Behavior') DEFAULT 'KRA',
    criterion_name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    kpi_description TEXT NULL,
    weight DECIMAL(5,2) NOT NULL,
    scoring_method ENUM('Scale_1_5', 'Scale_1_10', 'Percentage', 'Scale_1_4') DEFAULT 'Scale_1_4',
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_criteria_template FOREIGN KEY (template_id) REFERENCES evaluation_templates(template_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 17. Evaluations
-- ============================================
DROP TABLE IF EXISTS evaluations;
CREATE TABLE evaluations (
    evaluation_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    template_id INT NOT NULL,
    evaluation_type ENUM('Initial','Final','Quarterly','Annual') DEFAULT 'Annual',
    evaluation_period_start DATE NULL,
    evaluation_period_end DATE NULL,
    submitted_by INT NULL,
    endorsed_by INT NULL,
    approved_by INT NULL,
    status ENUM('Draft', 'Pending Supervisor', 'Pending Manager', 'Approved', 'Rejected', 'Returned') DEFAULT 'Draft',
    total_score DECIMAL(5,2) NULL,
    kra_subtotal DECIMAL(5,2) NULL,
    behavior_average DECIMAL(5,2) NULL,
    performance_level VARCHAR(50) NULL,
    submitted_date DATETIME NULL,
    endorsed_date DATETIME NULL,
    approved_date DATETIME NULL,
    staff_comments TEXT NULL,
    supervisor_comments TEXT NULL,
    manager_comments TEXT NULL,
    evaluator_comments TEXT NULL,
    current_position VARCHAR(150) NULL,
    months_in_position INT NULL,
    desired_position VARCHAR(150) NULL,
    target_date DATE NULL,
    career_growth_suited TINYINT(1) DEFAULT 0,
    career_growth_details TEXT NULL,
    hr_received_date DATE NULL,
    hr_received_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_eval_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    CONSTRAINT fk_eval_template FOREIGN KEY (template_id) REFERENCES evaluation_templates(template_id) ON DELETE CASCADE,
    CONSTRAINT fk_eval_submitter FOREIGN KEY (submitted_by) REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT fk_eval_endorser FOREIGN KEY (endorsed_by) REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT fk_eval_approver FOREIGN KEY (approved_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_eval_status (status, deleted_at),
    INDEX idx_eval_date (approved_date, submitted_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 18. Evaluation Scores
-- ============================================
DROP TABLE IF EXISTS evaluation_scores;
CREATE TABLE evaluation_scores (
    score_id INT AUTO_INCREMENT PRIMARY KEY,
    evaluation_id INT NOT NULL,
    criterion_id INT NOT NULL,
    score_value DECIMAL(5,2) NOT NULL,
    weighted_score DECIMAL(5,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_score_eval FOREIGN KEY (evaluation_id) REFERENCES evaluations(evaluation_id) ON DELETE CASCADE,
    CONSTRAINT fk_score_criteria FOREIGN KEY (criterion_id) REFERENCES evaluation_criteria(criterion_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 19. Evaluation Dev Plans
-- ============================================
DROP TABLE IF EXISTS evaluation_dev_plans;
CREATE TABLE evaluation_dev_plans (
    plan_id INT AUTO_INCREMENT PRIMARY KEY,
    evaluation_id INT NOT NULL,
    improvement_area TEXT NULL,
    support_needed TEXT NULL,
    time_frame VARCHAR(100) NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_devplan_eval FOREIGN KEY (evaluation_id) REFERENCES evaluations(evaluation_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 19. Career Movements
-- ============================================
DROP TABLE IF EXISTS career_movements;
CREATE TABLE career_movements (
    movement_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    movement_type ENUM('Promotion', 'Transfer', 'Demotion', 'Role Change') NOT NULL,
    previous_position VARCHAR(100) NULL,
    new_position VARCHAR(100) NOT NULL,
    previous_branch_id INT NULL,
    new_branch_id INT NULL,
    effective_date DATE NOT NULL,
    reason TEXT NULL,
    logged_by INT NULL,
    approved_by INT NULL,
    approval_status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    is_applied TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_movement_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    CONSTRAINT fk_movement_logger FOREIGN KEY (logged_by) REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT fk_movement_approver FOREIGN KEY (approved_by) REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT fk_movement_prev_branch FOREIGN KEY (previous_branch_id) REFERENCES branches(branch_id) ON DELETE SET NULL,
    CONSTRAINT fk_movement_new_branch FOREIGN KEY (new_branch_id) REFERENCES branches(branch_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 20. Notifications
-- ============================================
DROP TABLE IF EXISTS notifications;
CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(255) NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 21. Audit Logs
-- ============================================
DROP TABLE IF EXISTS audit_logs;
CREATE TABLE audit_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action_type VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 22. System Settings
-- ============================================
DROP TABLE IF EXISTS system_settings;
CREATE TABLE system_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 23. Additional PDS Tables (to keep employees table lean)
-- Note: Remaining Sections 5, 7, 8, 9 from previous schema are kept as separate tables below

DROP TABLE IF EXISTS employee_trainings;
CREATE TABLE employee_trainings (
    training_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    date_from DATE NULL,
    date_to DATE NULL,
    training_title VARCHAR(255) NULL,
    training_type VARCHAR(100) NULL,
    no_of_hours DECIMAL(7,2) NULL,
    conducted_by VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_train_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS employee_voluntary_work;
CREATE TABLE employee_voluntary_work (
    voluntary_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    date_from DATE NULL,
    date_to DATE NULL,
    organization_name VARCHAR(255) NULL,
    organization_address TEXT NULL,
    no_of_hours DECIMAL(7,2) NULL,
    position_nature VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_vol_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS employee_eligibility;
CREATE TABLE employee_eligibility (
    eligibility_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    license_title VARCHAR(255) NULL,
    date_from DATE NULL,
    date_to DATE NULL,
    license_number VARCHAR(100) NULL,
    date_of_exam DATE NULL,
    place_of_exam VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_elig_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS employee_skills;
CREATE TABLE employee_skills (
    skill_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    skill_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_skill_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS employee_recognitions;
CREATE TABLE employee_recognitions (
    recognition_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    recognition_title VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_recog_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS employee_memberships;
CREATE TABLE employee_memberships (
    membership_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    organization_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_member_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS employee_real_properties;
CREATE TABLE employee_real_properties (
    property_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    description VARCHAR(255) NULL,
    kind VARCHAR(100) NULL,
    exact_location TEXT NULL,
    assessed_value DECIMAL(14,2) NULL,
    market_value DECIMAL(14,2) NULL,
    acquisition_year_mode VARCHAR(100) NULL,
    acquisition_cost DECIMAL(14,2) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_real_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS employee_personal_properties;
CREATE TABLE employee_personal_properties (
    property_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    description VARCHAR(255) NULL,
    year_acquired VARCHAR(10) NULL,
    acquisition_cost DECIMAL(14,2) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pers_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS employee_liabilities;
CREATE TABLE employee_liabilities (
    liability_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    nature_of_liability VARCHAR(255) NULL,
    creditor_name VARCHAR(255) NULL,
    outstanding_balance DECIMAL(14,2) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_liab_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS employee_references;
CREATE TABLE employee_references (
    reference_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    reference_name VARCHAR(200) NULL,
    reference_address TEXT NULL,
    reference_telephone VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ref_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 23. Login Attempts (Brute Force Protection)
-- ============================================
DROP TABLE IF EXISTS login_attempts;
CREATE TABLE login_attempts (
    attempt_id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    identifier VARCHAR(100) NOT NULL,
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_time (ip_address, attempt_time),
    INDEX idx_identifier_time (identifier, attempt_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

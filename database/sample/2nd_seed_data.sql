SET FOREIGN_KEY_CHECKS = 0;
USE raquel_hris;

-- ============================================
-- 1. CLEANUP
-- ============================================
DELETE FROM `employee_references`; ALTER TABLE `employee_references` AUTO_INCREMENT = 1;
DELETE FROM `employee_liabilities`; ALTER TABLE `employee_liabilities` AUTO_INCREMENT = 1;
DELETE FROM `employee_personal_properties`; ALTER TABLE `employee_personal_properties` AUTO_INCREMENT = 1;
DELETE FROM `employee_real_properties`; ALTER TABLE `employee_real_properties` AUTO_INCREMENT = 1;
DELETE FROM `employee_memberships`; ALTER TABLE `employee_memberships` AUTO_INCREMENT = 1;
DELETE FROM `employee_recognitions`; ALTER TABLE `employee_recognitions` AUTO_INCREMENT = 1;
DELETE FROM `employee_skills`; ALTER TABLE `employee_skills` AUTO_INCREMENT = 1;
DELETE FROM `employee_eligibility`; ALTER TABLE `employee_eligibility` AUTO_INCREMENT = 1;
DELETE FROM `employee_voluntary_work`; ALTER TABLE `employee_voluntary_work` AUTO_INCREMENT = 1;
DELETE FROM `employee_trainings`; ALTER TABLE `employee_trainings` AUTO_INCREMENT = 1;
DELETE FROM `employee_work_experience`; ALTER TABLE `employee_work_experience` AUTO_INCREMENT = 1;
DELETE FROM `employee_education`; ALTER TABLE `employee_education` AUTO_INCREMENT = 1;
DELETE FROM `employee_siblings`; ALTER TABLE `employee_siblings` AUTO_INCREMENT = 1;
DELETE FROM `employee_children`; ALTER TABLE `employee_children` AUTO_INCREMENT = 1;
DELETE FROM `employee_family`; ALTER TABLE `employee_family` AUTO_INCREMENT = 1;
DELETE FROM `employee_disclosures`; ALTER TABLE `employee_disclosures` AUTO_INCREMENT = 1;
DELETE FROM `employee_government_ids`; ALTER TABLE `employee_government_ids` AUTO_INCREMENT = 1;
DELETE FROM `employee_details`; ALTER TABLE `employee_details` AUTO_INCREMENT = 1;
DELETE FROM `employee_emergency_contacts`; ALTER TABLE `employee_emergency_contacts` AUTO_INCREMENT = 1;
DELETE FROM `employee_contacts`; ALTER TABLE `employee_contacts` AUTO_INCREMENT = 1;
DELETE FROM `employee_addresses`; ALTER TABLE `employee_addresses` AUTO_INCREMENT = 1;
DELETE FROM `users`; ALTER TABLE `users` AUTO_INCREMENT = 1;
DELETE FROM `employees`; ALTER TABLE `employees` AUTO_INCREMENT = 1;
DELETE FROM `departments`; ALTER TABLE `departments` AUTO_INCREMENT = 1;
DELETE FROM `branches`; ALTER TABLE `branches` AUTO_INCREMENT = 1;

-- ============================================
-- 2. BRANCHES
-- ============================================
INSERT INTO `branches` (`branch_id`, `branch_name`, `location`, `is_active`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Raquel Pawnshop Main Office', 'San Diego St., Tayabas City, Quezon', 1, NOW(), NOW(), NULL),
(2, 'Paracale Branch', 'Sta Cruz St. Purok Narra, Barangay Poblacion Norte, Paracale, Camarines Norte', 1, NOW(), NOW(), NULL),
(3, 'San Pascual Branch', 'Aquino Avenue, Brgy. Poblacion San Pascual, Batangas', 1, NOW(), NOW(), NULL),
(4, 'Laurel Branch', 'Poblacion Tres, Laurel, Batangas', 1, NOW(), NOW(), NULL),
(5, 'San Andres Branch', 'Fernandez St. Brgy. Poblacion, San Andres, Quezon', 1, NOW(), NOW(), NULL);

-- ============================================
-- 3. DEPARTMENTS
-- ============================================
INSERT INTO `departments` (`department_id`, `department_name`, `description`, `is_active`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Human Resources', 'HR Management and Recruitment', 1, NOW(), NOW(), NULL),
(2, 'Accounting', 'Financial Documentation and Bookkeeping', 1, NOW(), NOW(), NULL),
(3, 'Operations', 'Daily Pawnshop Operations and Branch Management', 1, NOW(), NOW(), NULL),
(4, 'IT', 'Information Technology and Systems Support', 1, NOW(), NOW(), NULL),
(5, 'Marketing', 'Promotions and Brand Management', 1, NOW(), NOW(), NULL),
(6, 'Sales', 'Customer Acquisition and Sales Strategy', 1, NOW(), NOW(), NULL),
(7, 'Customer Service', 'Client Inquiry and Problem Resolution', 1, NOW(), NOW(), NULL),
(8, 'Finance', 'Financial Planning and Analysis', 1, NOW(), NOW(), NULL),
(9, 'Legal', 'Legal Compliance and Contracts', 1, NOW(), NOW(), NULL),
(10, 'Research and Development', 'System Innovation and Process Improvement', 1, NOW(), NOW(), NULL);

-- ============================================
-- 4. BUILT-IN SYSTEM ADMIN
-- ============================================
-- Default password: password
INSERT INTO `users` (`user_id`, `employee_id`, `username`, `email`, `password_hash`, `full_name`, `role`, `branch_id`, `is_active`, `first_login_completed`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, NULL, 'admin', 'admin@raquel.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Raquel HR Admin', 'Admin', 1, 1, 1, NOW(), NOW(), NULL);


-- ============================================
-- 5. SEEDED HR MANAGER
-- ============================================
INSERT INTO `employees` (
    `employee_id`, `employee_code`, `first_name`, `last_name`, `middle_name`,
    `date_of_birth`, `place_of_birth`, `gender`, `civil_status`,
    `hire_date`, `job_title`, `department_id`, `branch_id`,
    `employment_status`, `employment_type`, `profile_picture`, `is_active`,
    `created_at`, `updated_at`
) VALUES (
    1, '026-001', 'Elena', 'Santos', 'Reyes',
    '1990-05-15', 'Tayabas, Quezon', 'Female', 'Married',
    '2024-01-15', 'HR Manager', 1, 1,
    'Regular', 'Full-time', NULL, 1,
    NOW(), NOW()
);

INSERT INTO `employee_contacts` (`employee_id`, `telephone_number`, `mobile_number`, `personal_email`) VALUES
(1, NULL, '09171234567', 'elena.santos@email.com');

INSERT INTO `users` (`user_id`, `employee_id`, `username`, `email`, `password_hash`, `full_name`, `role`, `branch_id`, `is_active`, `first_login_completed`, `created_at`, `updated_at`, `deleted_at`) VALUES
(2, 1, 'elena.santos', 'elena.santos@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Elena Santos', 'HR Manager', 1, 1, 1, NOW(), NOW(), NULL);

SET FOREIGN_KEY_CHECKS = 1;

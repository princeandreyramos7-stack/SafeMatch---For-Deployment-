-- =====================================================================
-- SafeMatch - Mother-Baby Identification System
-- Database schema + default seed data
--
-- Import with:  mysql -u root < database.sql
-- (or phpMyAdmin > Import). Creates and uses its own `safematch_php` database.
-- Safe by design: there are no DROP statements, so importing into a database
-- that already has these tables fails instead of overwriting data.
-- To reset a development install:  DROP DATABASE safematch_php;  then re-import.
-- =====================================================================


SET NAMES utf8mb4;

-- 1. users -------------------------------------------------------------
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'healthcare_personnel') DEFAULT 'healthcare_personnel',
    contact_number VARCHAR(11) NOT NULL,
    position VARCHAR(255) NOT NULL,
    profile_picture VARCHAR(255) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. mothers -----------------------------------------------------------
CREATE TABLE mothers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    mother_id VARCHAR(50) UNIQUE NOT NULL,
    first_name VARCHAR(255) NOT NULL,
    middle_name VARCHAR(255),
    last_name VARCHAR(255) NOT NULL,
    date_of_birth DATE NOT NULL,
    address TEXT NOT NULL,
    contact_number VARCHAR(11) NOT NULL,
    emergency_contact VARCHAR(255),
    emergency_contact_number VARCHAR(11),
    blood_type VARCHAR(5),
    admission_date DATETIME NOT NULL,
    room_number VARCHAR(50),
    attending_physician VARCHAR(255),
    medical_history TEXT,
    allergies TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_mothers_admission (admission_date),
    INDEX idx_mothers_name (last_name, first_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. newborns ----------------------------------------------------------
CREATE TABLE newborns (
    id INT PRIMARY KEY AUTO_INCREMENT,
    newborn_id VARCHAR(50) UNIQUE NOT NULL,
    mother_id INT NOT NULL,
    first_name VARCHAR(255),
    middle_name VARCHAR(255),
    last_name VARCHAR(255) NOT NULL,
    date_of_birth DATETIME NOT NULL,
    time_of_birth TIME NOT NULL,
    sex ENUM('Male', 'Female') NOT NULL,
    weight DECIMAL(5,2) NOT NULL COMMENT 'in kg',
    length DECIMAL(5,2) NOT NULL COMMENT 'in cm',
    head_circumference DECIMAL(5,2) COMMENT 'in cm',
    apgar_score_1min INT,
    apgar_score_5min INT,
    delivery_type ENUM('Normal', 'Cesarean') NOT NULL,
    birth_complications TEXT,
    pediatrician VARCHAR(255),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (mother_id) REFERENCES mothers(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_newborns_dob (date_of_birth)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. qr_codes ----------------------------------------------------------
CREATE TABLE qr_codes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    mother_id INT NOT NULL,
    newborn_id INT NOT NULL,
    mother_qr_code TEXT NOT NULL COMMENT 'Stored as base64 image',
    newborn_qr_code TEXT NOT NULL COMMENT 'Stored as base64 image',
    mother_qr_data TEXT NOT NULL COMMENT 'JSON data',
    newborn_qr_data TEXT NOT NULL COMMENT 'JSON data',
    generated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    generated_by INT,
    FOREIGN KEY (mother_id) REFERENCES mothers(id) ON DELETE CASCADE,
    FOREIGN KEY (newborn_id) REFERENCES newborns(id) ON DELETE CASCADE,
    FOREIGN KEY (generated_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. qr_verifications --------------------------------------------------
CREATE TABLE qr_verifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    mother_id INT NOT NULL,
    newborn_id INT NOT NULL,
    verified_by INT NOT NULL,
    verification_result ENUM('match', 'mismatch') NOT NULL,
    verification_notes TEXT,
    verified_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (mother_id) REFERENCES mothers(id),
    FOREIGN KEY (newborn_id) REFERENCES newborns(id),
    FOREIGN KEY (verified_by) REFERENCES users(id),
    INDEX idx_verifications_date (verified_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. newborn_screening_types -------------------------------------------
CREATE TABLE newborn_screening_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. newborn_screenings ------------------------------------------------
CREATE TABLE newborn_screenings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    newborn_id INT NOT NULL,
    screening_type_id INT NOT NULL,
    screening_date DATETIME NOT NULL,
    result ENUM('Normal', 'Abnormal', 'Pending') NOT NULL,
    notes TEXT,
    performed_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (newborn_id) REFERENCES newborns(id) ON DELETE CASCADE,
    FOREIGN KEY (screening_type_id) REFERENCES newborn_screening_types(id),
    FOREIGN KEY (performed_by) REFERENCES users(id),
    INDEX idx_screenings_date (screening_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. maternal_assessment_types -----------------------------------------
CREATE TABLE maternal_assessment_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. maternal_assessments ----------------------------------------------
CREATE TABLE maternal_assessments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    mother_id INT NOT NULL,
    assessment_type_id INT NOT NULL,
    assessment_date DATETIME NOT NULL,
    result ENUM('Normal', 'Abnormal', 'Needs Attention') NOT NULL,
    notes TEXT,
    assessed_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (mother_id) REFERENCES mothers(id) ON DELETE CASCADE,
    FOREIGN KEY (assessment_type_id) REFERENCES maternal_assessment_types(id),
    FOREIGN KEY (assessed_by) REFERENCES users(id),
    INDEX idx_assessments_date (assessment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. discharge_requirements -------------------------------------------
CREATE TABLE discharge_requirements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    category ENUM('mother', 'newborn', 'both') NOT NULL,
    is_mandatory BOOLEAN DEFAULT TRUE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. discharge_records ------------------------------------------------
CREATE TABLE discharge_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    mother_id INT NOT NULL,
    newborn_id INT NOT NULL,
    discharge_date DATETIME,
    discharge_status ENUM('ready', 'not_ready', 'discharged') DEFAULT 'not_ready',
    discharge_notes TEXT,
    discharged_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (mother_id) REFERENCES mothers(id),
    FOREIGN KEY (newborn_id) REFERENCES newborns(id),
    FOREIGN KEY (discharged_by) REFERENCES users(id),
    INDEX idx_discharge_status (discharge_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. discharge_decisions ----------------------------------------------
CREATE TABLE discharge_decisions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    discharge_record_id INT NOT NULL,
    requirement_id INT NOT NULL,
    is_met BOOLEAN DEFAULT FALSE,
    checked_at DATETIME,
    checked_by INT,
    notes TEXT,
    FOREIGN KEY (discharge_record_id) REFERENCES discharge_records(id) ON DELETE CASCADE,
    FOREIGN KEY (requirement_id) REFERENCES discharge_requirements(id),
    FOREIGN KEY (checked_by) REFERENCES users(id),
    UNIQUE KEY uq_decision (discharge_record_id, requirement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. activity_log -----------------------------------------------------
CREATE TABLE activity_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_activity_created (created_at),
    INDEX idx_activity_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. notifications ----------------------------------------------------
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    related_type VARCHAR(50),
    related_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notifications_unread (user_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- Seed data
-- =====================================================================

-- Default users (passwords: admin123 / midwifery123). CHANGE AFTER FIRST LOGIN.
INSERT INTO users (name, email, password, role, contact_number, position) VALUES
('System Administrator', 'admin@safematch.com',
 '$2y$10$28xMIe/5SPfMdOGHtCCdB.5h/9jkQFh9NBYgrZKEMlxGHtOgZ32C6', 'admin', '09170000001', 'System Administrator'),
('Midwifery Staff', 'midwifery@safematch.com',
 '$2y$10$YCntmWGWUEWosT/K0k0./OQBr9ejtJRPxN3yU3peGLvJT2IQaeUGe', 'healthcare_personnel', '09170000002', 'Midwife');

INSERT INTO newborn_screening_types (name, description) VALUES
('Hearing Screening', 'Otoacoustic emissions / automated ABR hearing test'),
('Newborn Blood Screening', 'Heel-prick blood test for metabolic and genetic disorders'),
('Pulse Oximetry Screening', 'Screening for critical congenital heart disease'),
('Physical Examination', 'Complete head-to-toe newborn physical examination');

INSERT INTO maternal_assessment_types (name, description) VALUES
('Vital Signs Check', 'Blood pressure, pulse, temperature and respiratory rate'),
('Postpartum Assessment', 'Fundal height, lochia, perineum/incision and pain assessment'),
('Breastfeeding Assessment', 'Latch, positioning and milk transfer evaluation'),
('Mental Health Check', 'Screening for postpartum depression and anxiety');

INSERT INTO discharge_requirements (name, description, category, is_mandatory) VALUES
('Vital signs stable for 24 hours', 'Maternal vital signs within normal limits for at least 24 hours', 'mother', 1),
('No excessive bleeding', 'Lochia within normal amount; no signs of hemorrhage', 'mother', 1),
('Able to ambulate', 'Mother can walk independently', 'mother', 1),
('Breastfeeding established', 'Effective latch and feeding observed', 'mother', 1),
('Post-delivery education completed', 'Newborn care, warning signs and self-care teaching completed', 'mother', 1),
('All newborn screenings completed', 'Required newborn screenings performed and documented', 'newborn', 1),
('Feeding well', 'Baby is feeding adequately', 'newborn', 1),
('Normal vital signs', 'Newborn vital signs within normal limits', 'newborn', 1),
('No jaundice or managed', 'No significant jaundice, or jaundice is being managed', 'newborn', 1),
('Car seat test passed', 'Infant car seat safety check passed', 'newborn', 1),
('Birth certificate paperwork completed', 'Birth registration documents submitted', 'newborn', 1),
('Follow-up appointments scheduled', 'Postpartum and newborn follow-up visits booked', 'both', 1),
('Emergency contact information provided', 'Family has hospital and emergency contact numbers', 'both', 1),
('Discharge medications reviewed', 'Take-home medications explained to the mother', 'both', 1);

CREATE DATABASE IF NOT EXISTS bhirms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bhirms;
CREATE TABLE IF NOT EXISTS households (
 household_id INT AUTO_INCREMENT PRIMARY KEY,
 household_head VARCHAR(150) NOT NULL,
 address VARCHAR(255) NOT NULL,
 sitio_zone VARCHAR(100) NOT NULL,
 contact_number VARCHAR(30)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS users (
 user_id INT AUTO_INCREMENT PRIMARY KEY,
 username VARCHAR(60) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 full_name VARCHAR(150) NOT NULL,
 role ENUM('admin','midwife','bhw') NOT NULL DEFAULT 'bhw',
 contact_number VARCHAR(30)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS patients (
 patient_id INT AUTO_INCREMENT PRIMARY KEY,
 household_id INT NOT NULL,
 first_name VARCHAR(100) NOT NULL,
 last_name VARCHAR(100) NOT NULL,
 birthdate DATE NOT NULL,
 sex ENUM('Male','Female') NOT NULL,
 contact_number VARCHAR(30),
 FOREIGN KEY (household_id) REFERENCES households(household_id) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS visits (
 visit_id INT AUTO_INCREMENT PRIMARY KEY,
 patient_id INT NOT NULL,
 user_id INT NOT NULL,
 visit_date DATETIME NOT NULL,
 diagnosis VARCHAR(255) NOT NULL,
 notes TEXT,
 FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE RESTRICT,
 FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS sessions (
 session_id INT AUTO_INCREMENT PRIMARY KEY,
 patient_id INT NOT NULL,
 user_id INT NOT NULL,
 session_date DATETIME NOT NULL,
 session_type VARCHAR(100) NOT NULL,
 outcome VARCHAR(255),
 session_status ENUM('Completed','Follow-up','Referred') NOT NULL DEFAULT 'Completed',
 notes TEXT,
 FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE RESTRICT,
 FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS immunizations (
 immunization_id INT AUTO_INCREMENT PRIMARY KEY,
 patient_id INT NOT NULL,
 administered_by INT NOT NULL,
 vaccine_name VARCHAR(150) NOT NULL,
 dose_number INT NOT NULL,
 date_administered DATE NOT NULL,
 next_due_date DATE DEFAULT NULL,
 FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE RESTRICT,
 FOREIGN KEY (administered_by) REFERENCES users(user_id) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS medicines (
 medicine_id INT AUTO_INCREMENT PRIMARY KEY,
 medicine_name VARCHAR(150) NOT NULL,
 unit VARCHAR(40) NOT NULL,
 stock_quantity INT NOT NULL DEFAULT 0,
 expiration_date DATE NOT NULL,
 CHECK (stock_quantity >= 0)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS medicine_dispensed (
 dispense_id INT AUTO_INCREMENT PRIMARY KEY,
 visit_id INT NOT NULL,
 medicine_id INT NOT NULL,
 quantity INT NOT NULL,
 date_dispensed DATE NOT NULL,
 FOREIGN KEY (visit_id) REFERENCES visits(visit_id) ON DELETE RESTRICT,
 FOREIGN KEY (medicine_id) REFERENCES medicines(medicine_id) ON DELETE RESTRICT,
 CHECK (quantity > 0)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS transactions (
 transaction_id INT AUTO_INCREMENT PRIMARY KEY,
 transaction_date DATE NOT NULL,
 transaction_type ENUM('service','medicine_dispense','medicine_restock') NOT NULL,
 patient_id INT DEFAULT NULL,
 user_id INT NOT NULL,
 medicine_id INT DEFAULT NULL,
 quantity INT DEFAULT NULL,
 description VARCHAR(255) NOT NULL,
 amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
 payment_status ENUM('Paid','Unpaid','Not applicable') NOT NULL DEFAULT 'Not applicable',
 FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE RESTRICT,
 FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE RESTRICT,
 FOREIGN KEY (medicine_id) REFERENCES medicines(medicine_id) ON DELETE RESTRICT,
 CHECK (amount >= 0),
 CHECK (quantity IS NULL OR quantity > 0),
 CHECK (transaction_type NOT IN ('medicine_dispense','medicine_restock') OR (medicine_id IS NOT NULL AND quantity IS NOT NULL)),
 CHECK (transaction_type <> 'service' OR patient_id IS NOT NULL)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS appointments (
 appointment_id INT AUTO_INCREMENT PRIMARY KEY,
 patient_id INT NOT NULL,
 user_id INT NOT NULL,
 appointment_date DATETIME NOT NULL,
 purpose VARCHAR(255) NOT NULL,
 status ENUM('Scheduled','Completed','Cancelled') NOT NULL DEFAULT 'Scheduled',
 FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE RESTRICT,
 FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS activity_logs (
 log_id INT AUTO_INCREMENT PRIMARY KEY,
 user_id INT NOT NULL,
 action VARCHAR(255) NOT NULL,
 timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

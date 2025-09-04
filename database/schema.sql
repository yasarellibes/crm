-- HVAC Service Management System - MySQL Schema
-- cPanel Compatible Database Structure

-- Companies table
CREATE TABLE companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(255),
    address TEXT,
    city VARCHAR(100),
    district VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Branches table
CREATE TABLE branches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(255),
    address TEXT,
    city VARCHAR(100),
    district VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Personnel table (users)
CREATE TABLE personnel (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT,
    branch_id INT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    role ENUM('super_admin', 'company_admin', 'branch_manager', 'technician') NOT NULL DEFAULT 'technician',
    status ENUM('active', 'inactive') DEFAULT 'active',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customers table
CREATE TABLE customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT,
    branch_id INT,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(255),
    address TEXT,
    city VARCHAR(100),
    district VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Services table
CREATE TABLE services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT,
    branch_id INT,
    customer_id INT NOT NULL,
    personnel_id INT,
    device VARCHAR(255),
    brand VARCHAR(255),
    model VARCHAR(255),
    complaint TEXT,
    operation_status VARCHAR(100),
    service_date DATE,
    description TEXT,
    price DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (personnel_id) REFERENCES personnel(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Device types table
CREATE TABLE devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT,
    branch_id INT,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Brands table
CREATE TABLE brands (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT,
    branch_id INT,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Models table
CREATE TABLE models (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT,
    branch_id INT,
    brand_id INT,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL,
    FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Complaints table
CREATE TABLE complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT,
    branch_id INT,
    description TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System settings table
CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(255) UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity logs table
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES personnel(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Indexes for better performance
CREATE INDEX idx_services_customer_id ON services(customer_id);
CREATE INDEX idx_services_personnel_id ON services(personnel_id);
CREATE INDEX idx_services_service_date ON services(service_date);
CREATE INDEX idx_services_company_branch ON services(company_id, branch_id);
CREATE INDEX idx_customers_company_branch ON customers(company_id, branch_id);
CREATE INDEX idx_personnel_company_branch ON personnel(company_id, branch_id);
CREATE INDEX idx_personnel_email ON personnel(email);

-- Demo data insertion
-- Companies
INSERT INTO companies (name, phone, email, address, city, district) VALUES
('Ana Şirket', '0212 555 0001', 'info@anasirket.com', 'Merkez Mahallesi, Ana Cadde No:1', 'İstanbul', 'Kadıköy'),
('Şube Şirketi', '0312 555 0002', 'info@sube.com', 'Çankaya Mahallesi, Şube Sokak No:2', 'Ankara', 'Çankaya');

-- Branches
INSERT INTO branches (company_id, name, phone, email, address, city, district) VALUES
(1, 'İstanbul Merkez', '0212 555 0101', 'istanbul@anasirket.com', 'Merkez Mah. İstanbul Cad. No:10', 'İstanbul', 'Kadıköy'),
(1, 'İstanbul Anadolu', '0216 555 0102', 'anadolu@anasirket.com', 'Anadolu Mah. Şube Cad. No:20', 'İstanbul', 'Üsküdar'),
(2, 'Ankara Merkez', '0312 555 0201', 'ankara@sube.com', 'Çankaya Mah. Ankara Cad. No:30', 'Ankara', 'Çankaya');

-- Personnel (password is bcrypt hash of 'admin123')
INSERT INTO personnel (company_id, branch_id, name, email, password, phone, role, status) VALUES
(NULL, NULL, 'Süper Admin', 'super_admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0500 000 0001', 'super_admin', 'active'),
(1, NULL, 'Şirket Admin', 'company_admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0500 000 0002', 'company_admin', 'active'),
(1, 1, 'Şube Müdürü', 'branch_manager@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0500 000 0003', 'branch_manager', 'active'),
(1, 1, 'Teknisyen', 'technician@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0500 000 0004', 'technician', 'active');

-- Sample customers
INSERT INTO customers (company_id, branch_id, name, phone, email, address, city, district) VALUES
(1, 1, 'Ahmet Yılmaz', '0532 123 4567', 'ahmet@email.com', 'Örnek Mahallesi, 1. Sokak No:5', 'İstanbul', 'Kadıköy'),
(1, 1, 'Ayşe Demir', '0533 234 5678', 'ayse@email.com', 'Test Mahallesi, 2. Cadde No:10', 'İstanbul', 'Kadıköy'),
(1, 2, 'Mehmet Kaya', '0534 345 6789', 'mehmet@email.com', 'Deneme Mahallesi, 3. Sokak No:15', 'İstanbul', 'Üsküdar');

-- Sample definitions
INSERT INTO devices (company_id, branch_id, name) VALUES
(1, 1, 'Split Klima'),
(1, 1, 'VRV Sistem'),
(1, 1, 'Kaset Klima'),
(1, 1, 'Şömine');

INSERT INTO brands (company_id, branch_id, name) VALUES
(1, 1, 'Daikin'),
(1, 1, 'Mitsubishi'),
(1, 1, 'LG'),
(1, 1, 'Samsung'),
(1, 1, 'Arçelik');

INSERT INTO complaints (company_id, branch_id, description) VALUES
(1, 1, 'Soğutmuyor'),
(1, 1, 'Gürültülü çalışıyor'),
(1, 1, 'Su akıyor'),
(1, 1, 'Açılmıyor'),
(1, 1, 'Remote çalışmıyor');

-- Sample services
INSERT INTO services (company_id, branch_id, customer_id, personnel_id, device, brand, complaint, operation_status, service_date, description, price) VALUES
(1, 1, 1, 4, 'Split Klima', 'Daikin', 'Soğutmuyor', 'Tamamlandı', '2024-08-15', 'Gaz dolumu yapıldı', 350.00),
(1, 1, 2, 4, 'VRV Sistem', 'Mitsubishi', 'Gürültülü çalışıyor', 'Devam Ediyor', '2024-08-18', 'Fan arızası tespit edildi', 450.00),
(1, 2, 3, 4, 'Kaset Klima', 'LG', 'Su akıyor', 'Beklemede', '2024-08-20', 'Drenaj temizliği gerekli', 200.00);
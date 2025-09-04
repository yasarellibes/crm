-- HVAC System SQLite Schema for Replit Demo

-- Companies table
CREATE TABLE companies (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(255),
    address TEXT,
    city VARCHAR(100),
    district VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Branches table
CREATE TABLE branches (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_id INTEGER NOT NULL,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(255),
    address TEXT,
    city VARCHAR(100),
    district VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);

-- Personnel table
CREATE TABLE personnel (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_id INTEGER,
    branch_id INTEGER,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    role TEXT NOT NULL DEFAULT 'technician' CHECK (role IN ('super_admin', 'company_admin', 'branch_manager', 'technician')),
    status TEXT DEFAULT 'active' CHECK (status IN ('active', 'inactive')),
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
);

-- Customers table
CREATE TABLE customers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_id INTEGER,
    branch_id INTEGER,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(255),
    address TEXT,
    city VARCHAR(100),
    district VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
);

-- Services table
CREATE TABLE services (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_id INTEGER,
    branch_id INTEGER,
    customer_id INTEGER NOT NULL,
    personnel_id INTEGER,
    device VARCHAR(255),
    brand VARCHAR(255),
    model VARCHAR(255),
    complaint TEXT,
    operation_status VARCHAR(100),
    service_date DATE,
    description TEXT,
    price DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (personnel_id) REFERENCES personnel(id) ON DELETE SET NULL
);

-- Devices table
CREATE TABLE devices (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_id INTEGER,
    branch_id INTEGER,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
);

-- Brands table
CREATE TABLE brands (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_id INTEGER,
    branch_id INTEGER,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
);

-- Models table
CREATE TABLE models (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_id INTEGER,
    branch_id INTEGER,
    brand_id INTEGER,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL,
    FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE
);

-- Complaints table
CREATE TABLE complaints (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_id INTEGER,
    branch_id INTEGER,
    description TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
);

-- Activity logs table
CREATE TABLE activity_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES personnel(id) ON DELETE SET NULL
);

-- Demo data insertion
INSERT INTO companies (name, phone, email, address, city, district) VALUES
('Ana Şirket', '0212 555 0001', 'info@anasirket.com', 'Merkez Mahallesi, Ana Cadde No:1', 'İstanbul', 'Kadıköy'),
('Şube Şirketi', '0312 555 0002', 'info@sube.com', 'Çankaya Mahallesi, Şube Sokak No:2', 'Ankara', 'Çankaya');

INSERT INTO branches (company_id, name, phone, email, address, city, district) VALUES
(1, 'İstanbul Merkez', '0212 555 0101', 'istanbul@anasirket.com', 'Merkez Mah. İstanbul Cad. No:10', 'İstanbul', 'Kadıköy'),
(1, 'İstanbul Anadolu', '0216 555 0102', 'anadolu@anasirket.com', 'Anadolu Mah. Şube Cad. No:20', 'İstanbul', 'Üsküdar'),
(2, 'Ankara Merkez', '0312 555 0201', 'ankara@sube.com', 'Çankaya Mah. Ankara Cad. No:30', 'Ankara', 'Çankaya');

-- Personnel with password hash for 'admin123'
INSERT INTO personnel (company_id, branch_id, name, email, password, phone, role, status) VALUES
(NULL, NULL, 'Süper Admin', 'super_admin@example.com', '$2y$10$UJMm9IMhA8pEttqP07L77u0zvH.uxLYft9c34ZcKJ.Z.fH7F9FfUa', '0500 000 0001', 'super_admin', 'active'),
(1, NULL, 'Şirket Admin', 'company_admin@example.com', '$2y$10$UJMm9IMhA8pEttqP07L77u0zvH.uxLYft9c34ZcKJ.Z.fH7F9FfUa', '0500 000 0002', 'company_admin', 'active'),
(1, 1, 'Şube Müdürü', 'branch_manager@example.com', '$2y$10$UJMm9IMhA8pEttqP07L77u0zvH.uxLYft9c34ZcKJ.Z.fH7F9FfUa', '0500 000 0003', 'branch_manager', 'active'),
(1, 1, 'Teknisyen', 'technician@example.com', '$2y$10$UJMm9IMhA8pEttqP07L77u0zvH.uxLYft9c34ZcKJ.Z.fH7F9FfUa', '0500 000 0004', 'technician', 'active');

-- Sample customers
INSERT INTO customers (company_id, branch_id, name, phone, email, address, city, district) VALUES
(1, 1, 'Ahmet Yılmaz', '05321234567', 'ahmet@email.com', 'Örnek Mahallesi, 1. Sokak No:5', 'İstanbul', 'Kadıköy'),
(1, 1, 'Ayşe Demir', '05332345678', 'ayse@email.com', 'Test Mahallesi, 2. Cadde No:10', 'İstanbul', 'Kadıköy'),
(1, 2, 'Mehmet Kaya', '05343456789', 'mehmet@email.com', 'Deneme Mahallesi, 3. Sokak No:15', 'İstanbul', 'Üsküdar');

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
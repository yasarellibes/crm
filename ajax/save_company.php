<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek']);
    exit;
}

try {
    $name = trim($_POST['name'] ?? '');
    $taxNumber = trim($_POST['tax_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validation
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Şirket adı gereklidir']);
        exit;
    }
    
    if (empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Şifre gereklidir']);
        exit;
    }
    
    // Check if company name already exists
    $existing = fetchOne("SELECT id FROM companies WHERE name = ?", [$name]);
    if ($existing) {
        echo json_encode(['success' => false, 'message' => 'Bu şirket adı zaten kayıtlı']);
        exit;
    }
    
    $pdo = getConnection();
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert company
    $stmt = $pdo->prepare("
        INSERT INTO companies (name, tax_number, email, phone, city, district, address, website, password, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
    ");
    
    $result = $stmt->execute([
        $name, $taxNumber, $email, $phone, $city, $district, $address, $website, $hashedPassword
    ]);
    
    if ($result) {
        // Get the ID of the newly created company
        $companyId = $pdo->lastInsertId();
        
        // Create default data for the new company
        createDefaultCompanyData($pdo, $companyId);
        
        echo json_encode(['success' => true, 'message' => 'Şirket başarıyla eklendi']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Şirket eklenirken hata oluştu']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Hata: ' . $e->getMessage()]);
}

/**
 * Create default data for a new company
 * This replicates the Flask system behavior where default entities are created automatically
 */
function createDefaultCompanyData($pdo, $companyId) {
    try {
        // 1. Create default branch: "Merkez"
        $stmt = $pdo->prepare("
            INSERT INTO branches (name, company_id, password, created_at) 
            VALUES ('Merkez', ?, ?, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$companyId, password_hash('merkez123', PASSWORD_DEFAULT)]);
        $branchId = $pdo->lastInsertId();
        
        // 2. Create default personnel: "Personel" or "Teknisyen"
        $stmt = $pdo->prepare("
            INSERT INTO personnel (name, company_id, branch_id, role, password, created_at) 
            VALUES ('Teknisyen', ?, ?, 'technician', ?, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$companyId, $branchId, password_hash('teknisyen123', PASSWORD_DEFAULT)]);
        
        // 3. Create default complaint: "Çalışmıyor"
        $stmt = $pdo->prepare("
            INSERT INTO complaints (name, company_id, created_at) 
            VALUES ('Çalışmıyor', ?, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$companyId]);
        
        // 4. Create default devices
        $defaultDevices = ['Kombi', 'Klima', 'Çamaşır Makinesi', 'Bulaşık Makinesi'];
        foreach ($defaultDevices as $deviceName) {
            $stmt = $pdo->prepare("
                INSERT INTO devices (name, company_id, created_at) 
                VALUES (?, ?, CURRENT_TIMESTAMP)
            ");
            $stmt->execute([$deviceName, $companyId]);
        }
        
        // 5. Create default brands
        $defaultBrands = ['Baymak', 'Demirdöküm', 'Arçelik', 'Vestel'];
        $brandIds = [];
        foreach ($defaultBrands as $brandName) {
            $stmt = $pdo->prepare("
                INSERT INTO brands (name, company_id, created_at) 
                VALUES (?, ?, CURRENT_TIMESTAMP)
            ");
            $stmt->execute([$brandName, $companyId]);
            $brandIds[] = $pdo->lastInsertId();
        }
        
        // 6. Create default "Bilinmiyor" model for each brand
        foreach ($brandIds as $brandId) {
            $stmt = $pdo->prepare("
                INSERT INTO models (name, brand_id, company_id, created_at) 
                VALUES ('Bilinmiyor', ?, ?, CURRENT_TIMESTAMP)
            ");
            $stmt->execute([$brandId, $companyId]);
        }
        
        // 7. Create default operation (if operations table exists)
        try {
            $stmt = $pdo->prepare("
                INSERT INTO operations (name, company_id, created_at) 
                VALUES ('Bakım', ?, CURRENT_TIMESTAMP)
            ");
            $stmt->execute([$companyId]);
        } catch (Exception $e) {
            // Operations table might not exist, ignore error
        }
        
    } catch (Exception $e) {
        // Log error but don't fail the company creation
        error_log("Error creating default company data: " . $e->getMessage());
    }
}
?>
<?php
require_once '../config/auth.php';
require_once '../includes/functions.php';

requireLogin();

header('Content-Type: application/json');

// Check permissions
if ($_SESSION['role'] == 'technician') {
    echo json_encode(['success' => false, 'message' => 'Yetkiniz yok']);
    exit;
}

$action = $_POST['action'] ?? '';

if (!in_array($action, ['add', 'edit'])) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz işlem']);
    exit;
}

// Validate required fields
$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$city = trim($_POST['city'] ?? '');
$district = trim($_POST['district'] ?? '');

if (!$name || !$phone || !$city || !$district) {
    echo json_encode(['success' => false, 'message' => 'Ad, telefon, il ve ilçe alanları gerekli']);
    exit;
}

// Validate phone number (11 digits)
if (!preg_match('/^[0-9]{11}$/', $phone)) {
    echo json_encode(['success' => false, 'message' => 'Telefon numarası 11 haneli olmalıdır']);
    exit;
}

// Check if phone exists (for add or different personnel in edit)
$phoneCheckQuery = "SELECT id FROM personnel WHERE phone = ?";
$phoneCheckParams = [$phone];

if ($action == 'edit') {
    $personnelId = intval($_POST['personnel_id'] ?? 0);
    if (!$personnelId) {
        echo json_encode(['success' => false, 'message' => 'Personel ID gerekli']);
        exit;
    }
    $phoneCheckQuery .= " AND id != ?";
    $phoneCheckParams[] = $personnelId;
}

$existingPhone = fetchOne($phoneCheckQuery, $phoneCheckParams);
if ($existingPhone) {
    echo json_encode(['success' => false, 'message' => 'Bu telefon numarası zaten kullanılıyor']);
    exit;
}

// Check if email exists (for add or different personnel in edit)
$email = trim($_POST['email'] ?? '');
if (!empty($email)) {
    $emailCheckQuery = "SELECT id FROM personnel WHERE email = ?";
    $emailCheckParams = [$email];
    
    if ($action == 'edit') {
        $emailCheckQuery .= " AND id != ?";
        $emailCheckParams[] = $personnelId;
    }
    
    $existingEmail = fetchOne($emailCheckQuery, $emailCheckParams);
    if ($existingEmail) {
        echo json_encode(['success' => false, 'message' => 'Bu email adresi zaten kullanılıyor']);
        exit;
    }
}

try {
    $email = trim($_POST['email'] ?? '');
    $specialization = trim($_POST['specialization'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    
    // Company and branch handling
    $companyId = null;
    $branchId = null;
    
    if ($_SESSION['role'] == 'super_admin') {
        $companyId = intval($_POST['company_id'] ?? 0);
        if (!$companyId) {
            echo json_encode(['success' => false, 'message' => 'Şirket seçimi gerekli']);
            exit;
        }
    } else {
        $companyId = $_SESSION['company_id'];
    }
    
    $branchId = intval($_POST['branch_id'] ?? 0);
    if (!$branchId) {
        $branchId = null;
    }
    
    if ($action == 'add') {
        // Add new personnel
        $query = "
            INSERT INTO personnel (name, email, phone, specialization, city, district, address, password, company_id, branch_id, is_active, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ";
        
        $hashedPassword = $password ? password_hash($password, PASSWORD_DEFAULT) : null;
        
        $params = [
            $name, $email, $phone, $specialization, $city, $district, $address, 
            $hashedPassword, $companyId, $branchId, $isActive
        ];
        
        $result = executeQuery($query, $params);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Personel başarıyla eklendi']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Personel eklenemedi']);
        }
        
    } else {
        // Edit existing personnel
        $personnelId = intval($_POST['personnel_id']);
        
        // Check if personnel exists and user has permission to edit
        list($baseQuery, $baseParams) = applyDataFilter(
            "SELECT id", 
            [], 
            'p'
        );
        
        $checkQuery = "SELECT id FROM personnel p WHERE p.id = ? AND " . substr($baseQuery, strpos($baseQuery, 'WHERE') + 6);
        $checkParams = array_merge([$personnelId], $baseParams);
        $existingPersonnel = fetchOne($checkQuery, $checkParams);
        
        if (!$existingPersonnel) {
            echo json_encode(['success' => false, 'message' => 'Personel bulunamadı veya düzenleme yetkiniz yok']);
            exit;
        }
        
        // Update personnel
        $updateFields = [
            'name = ?', 'email = ?', 'phone = ?', 'specialization = ?', 
            'city = ?', 'district = ?', 'address = ?', 'company_id = ?', 
            'branch_id = ?', 'is_active = ?'
        ];
        
        $params = [
            $name, $email, $phone, $specialization, $city, $district, $address, 
            $companyId, $branchId, $isActive
        ];
        
        // Add password update if provided
        if ($password) {
            $updateFields[] = 'password = ?';
            $params[] = password_hash($password, PASSWORD_DEFAULT);
        }
        
        $params[] = $personnelId;
        
        $query = "UPDATE personnel SET " . implode(', ', $updateFields) . " WHERE id = ?";
        
        $result = executeQuery($query, $params);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Personel başarıyla güncellendi']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Personel güncellenemedi']);
        }
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Veritabanı hatası: ' . $e->getMessage()]);
}
?>
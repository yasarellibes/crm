<?php
/**
 * Definitions Management Page - cPanel HVAC System
 * Manages branches, personnel, complaints, devices, brands, models, operations
 */

require_once 'config/database_production.php';
require_once 'includes/functions.php';

session_start();

// Get PDO connection from standard config
$pdo = getDB();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Handle AJAX requests for tab refresh
if (isset($_GET['ajax']) && $_GET['ajax'] === 'refresh' && isset($_GET['type'])) {
    $type = $_GET['type'];
    include 'includes/tab_content.php';
    exit;
}

// Handle operation color update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_operation_color') {
    header('Content-Type: application/json');
    
    $operationId = intval($_POST['operation_id'] ?? 0);
    $color = trim($_POST['color'] ?? '#6c757d');
    
    // Validate color format
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
        echo json_encode(['success' => false, 'message' => 'Geçersiz renk formatı']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE operations SET color = ? WHERE id = ? AND company_id = ?");
        $result = $stmt->execute([$color, $operationId, $_SESSION['company_id']]);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Renk güncellendi']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Güncelleme başarısız']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Veritabanı hatası']);
    }
    exit;
}

// Handle AJAX GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    
    if ($action === 'get_brands') {
        try {
            $stmt = $pdo->prepare("SELECT id, name FROM brands WHERE company_id = ? ORDER BY name ASC");
            $stmt->execute([$_SESSION['company_id'] ?? 11]);
            $brands = $stmt->fetchAll();
            echo json_encode(['success' => true, 'brands' => $brands]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        exit;
    }
    
    if ($action === 'get_branches') {
        try {
            $stmt = $pdo->prepare("SELECT id, name FROM branches WHERE company_id = ? ORDER BY name ASC");
            $stmt->execute([$_SESSION['company_id'] ?? 11]);
            $branches = $stmt->fetchAll();
            echo json_encode(['success' => true, 'branches' => $branches]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        exit;
    }
    
    if ($action === 'get_company_info') {
        try {
            $stmt = $pdo->prepare("SELECT name, city, district FROM companies WHERE id = ?");
            $stmt->execute([$_SESSION['company_id'] ?? 11]);
            $company = $stmt->fetch();
            echo json_encode(['success' => true, 'company' => $company]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $type = $_POST['type'] ?? '';
    $response = ['success' => false, 'message' => ''];
    
    switch ($action) {
        case 'add':
        case 'edit':
            $response = handleDefinitionSubmit($type, $_POST);
            break;
        case 'delete':
            $response = handleDeleteDefinition($type, $_POST['id'] ?? 0);
            break;
        default:
            $response = ['success' => false, 'message' => 'Geçersiz işlem'];
    }
    
    echo json_encode($response);
    exit;
}

// Get current user for role-based access
$currentUser = $_SESSION;

// Get all definition data
$branches = getBranchesDefinitions();
$personnel = getPersonnelDefinitions();
$complaints = getDefinitions('complaints');
$devices = getDefinitions('devices');
$brands = getDefinitions('brands');
$models = getDefinitionsWithBrand('models');
$operations = getDefinitions('operations');

/**
 * Get branches with role-based filtering
 */
function getBranchesDefinitions() {
    global $pdo;
    $currentUser = $_SESSION;
    
    // Super admin sees ALL branches across all companies
    if ($currentUser['role'] === 'super_admin') {
        $sql = "SELECT id, name, phone, email, city, district, address 
                FROM branches 
                ORDER BY name ASC";
        $params = [];
    } else {
        $sql = "SELECT id, name, phone, email, city, district, address 
                FROM branches 
                WHERE company_id = ?
                ORDER BY name ASC";
        $params = [$currentUser['company_id'] ?? 11];
    }
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error in getBranchesDefinitions: " . $e->getMessage());
        return [];
    }
}

/**
 * Get definitions with role-based filtering
 */
function getDefinitions($table) {
    global $pdo;
    $currentUser = $_SESSION;
    
    // Super admin sees ALL data across all companies
    if ($currentUser['role'] === 'super_admin') {
        $sql = "SELECT * FROM {$table} ORDER BY name";
        $params = [];
    }
    // Different filtering based on user role
    elseif ($currentUser['role'] === 'branch_manager' || $currentUser['role'] === 'technician') {
        // Branch users see ONLY company definitions (branch_id IS NULL)
        $sql = "SELECT * FROM {$table} WHERE company_id = ? AND branch_id IS NULL ORDER BY name";
        $params = [$currentUser['company_id'] ?? 11];
    } else {
        // Company admins see ALL definitions (company + all branches)
        $sql = "SELECT * FROM {$table} WHERE company_id = ? ORDER BY name";
        $params = [$currentUser['company_id'] ?? 11];
    }
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error in getDefinitions: " . $e->getMessage());
        return [];
    }
}

/**
 * Get models with brand names
 */
function getDefinitionsWithBrand($table) {
    global $pdo;
    $currentUser = $_SESSION;
    
    // Super admin sees ALL models across all companies
    if ($currentUser['role'] === 'super_admin') {
        $sql = "SELECT m.*, b.name as brand_name 
                FROM {$table} m 
                LEFT JOIN brands b ON m.brand_id = b.id 
                ORDER BY m.name";
        $params = [];
    }
    // Different filtering for models based on user role
    elseif ($currentUser['role'] === 'branch_manager' || $currentUser['role'] === 'technician') {
        // Branch users see ONLY company models (branch_id IS NULL)
        $sql = "SELECT m.*, b.name as brand_name 
                FROM {$table} m 
                LEFT JOIN brands b ON m.brand_id = b.id 
                WHERE m.company_id = ? AND m.branch_id IS NULL 
                ORDER BY m.name";
        $params = [$currentUser['company_id'] ?? 11];
    } else {
        // Company admins see ALL models (company + all branches)
        $sql = "SELECT m.*, b.name as brand_name 
                FROM {$table} m 
                LEFT JOIN brands b ON m.brand_id = b.id 
                WHERE m.company_id = ? 
                ORDER BY m.name";
        $params = [$currentUser['company_id'] ?? 11];
    }
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error in getDefinitionsWithBrand: " . $e->getMessage());
        return [];
    }
}

/**
 * Get personnel with role-based filtering
 */
function getPersonnelDefinitions() {
    global $pdo;
    $currentUser = $_SESSION;
    
    // Super admin sees ALL personnel across all companies
    if ($currentUser['role'] === 'super_admin') {
        $sql = "SELECT p.id, p.name, p.phone, p.email, p.city, p.district, p.branch_id,
                       b.name as branch_name, c.name as company_name
                FROM personnel p
                LEFT JOIN branches b ON p.branch_id = b.id
                LEFT JOIN companies c ON p.company_id = c.id
                ORDER BY p.name";
        $params = [];
    }
    // Branch managers see only their branch personnel
    elseif ($currentUser['role'] === 'branch_manager') {
        $sql = "SELECT p.id, p.name, p.phone, p.email, p.city, p.district, p.branch_id,
                       b.name as branch_name, c.name as company_name
                FROM personnel p
                LEFT JOIN branches b ON p.branch_id = b.id
                LEFT JOIN companies c ON p.company_id = c.id
                WHERE p.company_id = ? AND p.branch_id = ?
                ORDER BY p.name";
        $params = [$currentUser['company_id'] ?? 11, $currentUser['branch_id'] ?? null];
    } else {
        $sql = "SELECT p.id, p.name, p.phone, p.email, p.city, p.district, p.branch_id,
                       b.name as branch_name, c.name as company_name
                FROM personnel p
                LEFT JOIN branches b ON p.branch_id = b.id
                LEFT JOIN companies c ON p.company_id = c.id
                WHERE p.company_id = ?
                ORDER BY p.name";
        $params = [$currentUser['company_id'] ?? 11];
    }
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error in getPersonnelDefinitions: " . $e->getMessage());
        return [];
    }
}

/**
 * Handle definition submission (add/edit)
 */
function handleDefinitionSubmit($type, $data) {
    global $pdo;
    $currentUser = $_SESSION;
    $isEdit = !empty($data['id']);
    
    try {
        switch ($type) {
            case 'branches':
                if ($isEdit) {
                    // Update password only if provided
                    if (!empty($data['password'])) {
                        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
                        $sql = "UPDATE branches SET name = ?, phone = ?, email = ?, city = ?, district = ?, address = ?, password = ? WHERE id = ? AND company_id = ?";
                        $params = [$data['name'], $data['phone'] ?? '', $data['email'] ?? '', $data['city'] ?? '', $data['district'] ?? '', $data['address'] ?? '', $hashedPassword, $data['id'], $currentUser['company_id']];
                    } else {
                        $sql = "UPDATE branches SET name = ?, phone = ?, email = ?, city = ?, district = ?, address = ? WHERE id = ? AND company_id = ?";
                        $params = [$data['name'], $data['phone'] ?? '', $data['email'] ?? '', $data['city'] ?? '', $data['district'] ?? '', $data['address'] ?? '', $data['id'], $currentUser['company_id']];
                    }
                } else {
                    // Hash password for new branch
                    $hashedPassword = password_hash($data['password'] ?? 'password123', PASSWORD_DEFAULT);
                    $sql = "INSERT INTO branches (name, phone, email, city, district, address, password, company_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $params = [$data['name'], $data['phone'] ?? '', $data['email'] ?? '', $data['city'] ?? '', $data['district'] ?? '', $data['address'] ?? '', $hashedPassword, $currentUser['company_id']];
                }
                break;
                
            case 'personnel':
                if ($isEdit) {
                    // Update password only if provided
                    if (!empty($data['password'])) {
                        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
                        $sql = "UPDATE personnel SET name = ?, phone = ?, email = ?, city = ?, district = ?, branch_id = ?, password = ? WHERE id = ? AND company_id = ?";
                        $params = [$data['name'], $data['phone'] ?? '', $data['email'] ?? '', $data['city'] ?? '', $data['district'] ?? '', 
                                  !empty($data['branch_id']) ? (int)$data['branch_id'] : null, $hashedPassword, $data['id'], $currentUser['company_id']];
                    } else {
                        $sql = "UPDATE personnel SET name = ?, phone = ?, email = ?, city = ?, district = ?, branch_id = ? WHERE id = ? AND company_id = ?";
                        $params = [$data['name'], $data['phone'] ?? '', $data['email'] ?? '', $data['city'] ?? '', $data['district'] ?? '', 
                                  !empty($data['branch_id']) ? (int)$data['branch_id'] : null, $data['id'], $currentUser['company_id']];
                    }
                } else {
                    // Hash password for new personnel
                    $hashedPassword = password_hash($data['password'] ?? 'password123', PASSWORD_DEFAULT);
                    
                    // Branch managers can only add personnel to their own branch
                    if ($currentUser['role'] === 'branch_manager') {
                        $branchId = $currentUser['branch_id'];
                    } else {
                        $branchId = !empty($data['branch_id']) ? (int)$data['branch_id'] : null;
                    }
                    
                    $sql = "INSERT INTO personnel (name, phone, email, city, district, branch_id, password, company_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $params = [$data['name'], $data['phone'] ?? '', $data['email'] ?? '', $data['city'] ?? '', $data['district'] ?? '', 
                               $branchId, $hashedPassword, $currentUser['company_id']];
                }
                break;
                
            case 'models':
                if ($isEdit) {
                    $sql = "UPDATE models SET name = ?, brand_id = ? WHERE id = ? AND company_id = ?";
                    $params = [$data['name'], $data['brand_id'], $data['id'], $currentUser['company_id']];
                } else {
                    // All new models become company-level (branch_id NULL) so company can see them
                    $sql = "INSERT INTO models (name, brand_id, company_id, branch_id) VALUES (?, ?, ?, ?)";
                    $params = [$data['name'], $data['brand_id'], $currentUser['company_id'], null];
                }
                break;
                
            default: // complaints, devices, brands, operations
                if ($isEdit) {
                    $sql = "UPDATE {$type} SET name = ? WHERE id = ? AND company_id = ?";
                    $params = [$data['name'], $data['id'], $currentUser['company_id']];
                } else {
                    // All new definitions become company-level (branch_id NULL) so company can see them
                    $sql = "INSERT INTO {$type} (name, company_id, branch_id) VALUES (?, ?, ?)";
                    $params = [$data['name'], $currentUser['company_id'], null];
                }
                break;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return ['success' => true, 'message' => $isEdit ? 'Güncellendi' : 'Eklendi'];
        
    } catch (PDOException $e) {
        error_log("Database error in handleDefinitionSubmit: " . $e->getMessage());
        return ['success' => false, 'message' => 'Veritabanı hatası'];
    }
}

/**
 * Handle delete operations
 */
function handleDeleteDefinition($type, $id) {
    global $pdo;
    $currentUser = $_SESSION;
    
    try {
        // Special handling for branches - check for dependencies
        if ($type === 'branches') {
            // Check if branch has personnel
            $checkStmt = $pdo->prepare("SELECT COUNT(*) as count FROM personnel WHERE branch_id = ?");
            $checkStmt->execute([$id]);
            $personnelResult = $checkStmt->fetch();
            
            // Check if branch has users  
            $userCheckStmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE branch_id = ?");
            $userCheckStmt->execute([$id]);
            $userResult = $userCheckStmt->fetch();
            
            // Check if branch has services
            $serviceCheckStmt = $pdo->prepare("SELECT COUNT(*) as count FROM services WHERE branch_id = ?");
            $serviceCheckStmt->execute([$id]);
            $serviceResult = $serviceCheckStmt->fetch();
            
            // Check if branch has customers
            $customerCheckStmt = $pdo->prepare("SELECT COUNT(*) as count FROM customers WHERE branch_id = ?");
            $customerCheckStmt->execute([$id]);
            $customerResult = $customerCheckStmt->fetch();
            
            $dependencies = [];
            if ($personnelResult['count'] > 0) {
                $dependencies[] = $personnelResult['count'] . ' personel';
            }
            if ($userResult['count'] > 0) {
                $dependencies[] = $userResult['count'] . ' kullanıcı';
            }
            if ($serviceResult['count'] > 0) {
                $dependencies[] = $serviceResult['count'] . ' servis';
            }
            if ($customerResult['count'] > 0) {
                $dependencies[] = $customerResult['count'] . ' müşteri';
            }
            
            if (!empty($dependencies)) {
                $dependencyText = implode(', ', $dependencies);
                return ['success' => false, 'message' => "Bu şubeye bağlı kayıtlar var: {$dependencyText}. Önce bu kayıtları silin veya başka şubeye taşıyın."];
            }
        }
        
        // Special handling for personnel - check for dependencies
        if ($type === 'personnel') {
            // Check if personnel has services assigned (using personnel_id instead of technician_id)
            $serviceCheckStmt = $pdo->prepare("SELECT COUNT(*) as count FROM services WHERE personnel_id = ?");
            $serviceCheckStmt->execute([$id]);
            $serviceResult = $serviceCheckStmt->fetch();
            
            if ($serviceResult['count'] > 0) {
                return ['success' => false, 'message' => 'Bu personele atanmış servisler var. Önce servisleri başka personele atayın.'];
            }
        }
        
        $sql = "DELETE FROM {$type} WHERE id = ? AND company_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id, $currentUser['company_id']]);
        
        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'Başarıyla silindi'];
        } else {
            return ['success' => false, 'message' => 'Silinecek kayıt bulunamadı'];
        }
        
    } catch (PDOException $e) {
        error_log("Database error in handleDeleteDefinition: " . $e->getMessage());
        
        // More specific error messages
        if (strpos($e->getMessage(), 'Foreign key violation') !== false) {
            return ['success' => false, 'message' => 'Bu kayda bağlı başka veriler var. Önce bağlı verileri silin.'];
        }
        
        return ['success' => false, 'message' => 'Veritabanı hatası oluştu'];
    }
}

/**
 * Check if user can edit definitions
 */
function canEditDefinition($type) {
    $role = $_SESSION['role'] ?? '';
    
    if ($role === 'super_admin') {
        return true;
    }
    
    if ($role === 'company_admin') {
        return true;
    }
    
    if ($role === 'branch_manager') {
        return in_array($type, ['personnel', 'brands', 'models', 'devices', 'complaints', 'operations']);
    }
    
    return false;
}

$pageTitle = 'Tanımlamalar - Serviso';
require_once 'includes/header.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="page-header">
                    <h2 class="mb-0">
                        <i class="fas fa-cogs me-2"></i>
                        Tanımlamalar
                    </h2>
                    <p class="page-subtitle">Sistem tanımları ve yapılandırma seçenekleri</p>
                </div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">
                    <i class="fas fa-list"></i>
                    Tanımlamalar
                </div>
                <p style="color: var(--text-secondary); margin: 0; font-size: 0.9rem;">Sistem tanımları ve ayarlarını yönetin</p>
            </div>
            
            <div class="nav-tabs-modern">
                <ul class="nav definition-tabs-compact" id="definitionTabs" role="tablist">
            <?php if ($currentUser && $currentUser['role'] != 'branch_manager'): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="branches-tab" data-bs-toggle="pill" data-bs-target="#branches" type="button" role="tab">
                    <i class="fas fa-building me-1"></i>Şubeler
                </button>
            </li>
            <?php endif; ?>
            
            <?php if ($currentUser && ($currentUser['role'] != 'technician')): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= ($currentUser && $currentUser['role'] == 'branch_manager') ? 'active' : '' ?>" id="personnel-tab" data-bs-toggle="pill" data-bs-target="#personnel" type="button" role="tab">
                    <i class="fas fa-users me-1"></i>Personel
                </button>
            </li>
            <?php endif; ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= ($currentUser && $currentUser['role'] == 'branch_manager' && $currentUser['role'] != 'personnel') ? '' : '' ?>" id="complaints-tab" data-bs-toggle="pill" data-bs-target="#complaints" type="button" role="tab">
                    <i class="fas fa-exclamation-triangle me-1"></i>Şikayetler
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="devices-tab" data-bs-toggle="pill" data-bs-target="#devices" type="button" role="tab">
                    <i class="fas fa-microchip me-1"></i>Cihazlar
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="brands-tab" data-bs-toggle="pill" data-bs-target="#brands" type="button" role="tab">
                    <i class="fas fa-tag me-1"></i>Markalar
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="models-tab" data-bs-toggle="pill" data-bs-target="#models" type="button" role="tab">
                    <i class="fas fa-cube me-1"></i>Modeller
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="operations-tab" data-bs-toggle="pill" data-bs-target="#operations" type="button" role="tab">
                    <i class="fas fa-tasks me-1"></i>Operasyonlar
                </button>
            </li>
                </ul>
            </div>

            <!-- Tab Content -->
            <div class="tab-content" id="definitionTabsContent">
            <!-- Branches Tab -->
            <?php if ($currentUser && $currentUser['role'] != 'branch_manager'): ?>
            <div class="tab-pane fade show active" id="branches" role="tabpanel">
                <?php 
                $_GET['type'] = 'branches';
                include 'includes/tab_content.php';
                ?>
            </div>

            <?php endif; ?>
            
            <!-- Personnel Tab -->
            <?php if ($currentUser && ($currentUser['role'] != 'technician')): ?>
            <div class="tab-pane fade <?= ($currentUser && $currentUser['role'] == 'branch_manager') ? 'show active' : '' ?>" id="personnel" role="tabpanel">
                <?php 
                $_GET['type'] = 'personnel';
                include 'includes/tab_content.php';
                ?>
            </div>
            <?php endif; ?>

            <!-- Complaints Tab -->
            <div class="tab-pane fade <?= ($currentUser && $currentUser['role'] == 'branch_manager') ? '' : '' ?>" id="complaints" role="tabpanel">
                <?php 
                $_GET['type'] = 'complaints';
                include 'includes/tab_content.php';
                ?>
            </div>

            <!-- Devices Tab -->
            <div class="tab-pane fade" id="devices" role="tabpanel">
                <?php 
                $_GET['type'] = 'devices';
                include 'includes/tab_content.php';
                ?>
            </div>

            <!-- Brands Tab -->
            <div class="tab-pane fade" id="brands" role="tabpanel">
                <?php 
                $_GET['type'] = 'brands';
                include 'includes/tab_content.php';
                ?>
            </div>

            <!-- Models Tab -->
            <div class="tab-pane fade" id="models" role="tabpanel">
                <?php 
                $_GET['type'] = 'models';
                include 'includes/tab_content.php';
                ?>
            </div>

            <!-- Operations Tab -->
            <div class="tab-pane fade" id="operations" role="tabpanel">
                <?php 
                $_GET['type'] = 'operations';
                include 'includes/tab_content.php';
                ?>
            </div>
        </div>
    </div>
</div>

<!-- Universal Add/Edit Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addItemModalLabel">Yeni Öğe</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="itemForm" onsubmit="submitItemForm(event)">
                <div class="modal-body">
                    <input type="hidden" id="itemType" name="type">
                    <input type="hidden" id="itemId" name="id">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="itemName" class="form-label">Ad</label>
                        <input type="text" class="form-control" id="itemName" name="name" required>
                    </div>
                    
                    <!-- Additional fields for different types -->
                    <div id="branchFields" style="display: none;">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="itemPhone" class="form-label">Telefon</label>
                                    <input type="text" class="form-control" id="itemPhone" name="phone">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="itemEmail" class="form-label">E-posta</label>
                                    <input type="email" class="form-control" id="itemEmail" name="email">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="itemPassword" class="form-label">Şifre</label>
                                    <input type="password" class="form-control" id="itemPassword" name="password" minlength="6">
                                    <div class="form-text">Boş bırakılırsa mevcut şifre korunur</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <!-- Empty column for alignment -->
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="itemCity" class="form-label">Şehir</label>
                                    <select class="form-select" id="itemCity" name="city">
                                        <option value="">Şehir seçin...</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="itemDistrict" class="form-label">İlçe</label>
                                    <select class="form-select" id="itemDistrict" name="district">
                                        <option value="">İlçe seçin...</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="itemAddress" class="form-label">Adres</label>
                            <textarea class="form-control" id="itemAddress" name="address" rows="2"></textarea>
                        </div>
                    </div>
                    
                    <div id="personnelFields" style="display: none;">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="branchSelectPersonnel" class="form-label">Şube</label>
                                    <select class="form-select" id="branchSelectPersonnel" name="branch_id">
                                        <option value="">Şube seçin...</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="personnelPassword" class="form-label">Şifre</label>
                                    <input type="password" class="form-control" id="personnelPassword" name="password">
                                    <div class="form-text" id="passwordHelp">Boş bırakılırsa mevcut şifre korunur</div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="personnelPhone" class="form-label">Telefon</label>
                                    <input type="text" class="form-control" id="personnelPhone" name="phone">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="personnelEmail" class="form-label">E-posta</label>
                                    <input type="email" class="form-control" id="personnelEmail" name="email">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="personnelCity" class="form-label">Şehir</label>
                                    <select class="form-select" id="personnelCity" name="city">
                                        <option value="">İl seçin...</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="personnelDistrict" class="form-label">İlçe</label>
                                    <select class="form-select" id="personnelDistrict" name="district">
                                        <option value="">İlçe seçin...</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div id="modelFields" style="display: none;">
                        <div class="mb-3">
                            <label for="brandSelect" class="form-label">Marka</label>
                            <select class="form-select" id="brandSelect" name="brand_id">
                                <option value="">Marka seçin...</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="assets/js/definitions.js"></script>
<script>
// Enhanced JavaScript for all CRUD operations
function openAddModal(type) {
    const modalTitles = {
        'branches': 'Yeni Şube',
        'personnel': 'Yeni Personel', 
        'complaints': 'Yeni Şikayet Türü',
        'devices': 'Yeni Cihaz Türü',
        'brands': 'Yeni Marka',
        'models': 'Yeni Model',
        'operations': 'Yeni Operasyon Türü'
    };
    
    // Reset form
    document.getElementById('itemForm').reset();
    document.querySelector('input[name="action"]').value = 'add';
    
    // Set modal title and type
    document.getElementById('addItemModalLabel').textContent = modalTitles[type] || 'Yeni Öğe';
    document.getElementById('itemType').value = type;
    document.getElementById('itemId').value = '';
    
    // Show/hide specific fields
    document.getElementById('branchFields').style.display = type === 'branches' ? 'block' : 'none';
    document.getElementById('personnelFields').style.display = type === 'personnel' ? 'block' : 'none';
    document.getElementById('modelFields').style.display = type === 'models' ? 'block' : 'none';
    
    // Set password field requirements for add/edit
    const passwordField = document.getElementById('personnelPassword');
    const passwordHelp = document.getElementById('passwordHelp');
    if (type === 'personnel') {
        passwordField.setAttribute('required', 'required');
        passwordField.placeholder = 'Şifre girin (zorunlu)';
        passwordHelp.textContent = 'En az 6 karakter gerekli';
    }
    
    // Set required attribute for brand_id only when it's for models
    const brandSelect = document.getElementById('brandSelect');
    if (type === 'models') {
        brandSelect.setAttribute('required', 'required');
    } else {
        brandSelect.removeAttribute('required');
    }
    
    // Load brands for models
    if (type === 'models') {
        loadBrands();
    }
    
    // Load branches for personnel
    if (type === 'personnel') {
        loadBranches();
        // Initialize city/district system like customer add page
        setTimeout(() => {
            if (typeof setupCityDistrict === 'function') {
                console.log('Setting up city/district for new personnel');
                setupCityDistrict('personnelCity', 'personnelDistrict', 'Samsun', 'Atakum');
            } else {
                console.error('setupCityDistrict function not found');
            }
        }, 200);
    }
    
    // Load brands for models
    if (type === 'models') {
        loadBrands();
    }
    
    new bootstrap.Modal(document.getElementById('addItemModal')).show();
}

function editItem(type, id, name, data = {}) {
    openAddModal(type);
    document.getElementById('addItemModalLabel').textContent = 'Düzenle';
    document.querySelector('input[name="action"]').value = 'edit';
    document.getElementById('itemId').value = id;
    document.getElementById('itemName').value = name;
    
    // Fill additional fields based on type
    if (type === 'branches' && data) {
        document.getElementById('itemPhone').value = data.phone || '';
        document.getElementById('itemEmail').value = data.email || '';
        document.getElementById('itemCity').value = data.city || '';
        document.getElementById('itemDistrict').value = data.district || '';
        document.getElementById('itemAddress').value = data.address || '';
    }
    
    if (type === 'personnel' && data) {
        loadBranches().then(() => {
            document.getElementById('branchSelectPersonnel').value = data.branch_id || '';
        });
        document.getElementById('personnelPhone').value = data.phone || '';
        document.getElementById('personnelEmail').value = data.email || '';
        
        // Initialize city/district system and set values
        setTimeout(() => {
            if (typeof setupCityDistrict === 'function') {
                console.log('Setting up city/district for edit with:', data.city, data.district);
                setupCityDistrict('personnelCity', 'personnelDistrict', data.city || 'Samsun', data.district || 'Atakum');
            } else {
                console.error('setupCityDistrict function not found');
            }
        }, 200);
        
        // Don't populate password field for security
        // Change password field requirements for edit
        const passwordField = document.getElementById('personnelPassword');
        const passwordHelp = document.getElementById('passwordHelp');
        passwordField.removeAttribute('required');
        passwordField.placeholder = 'Yeni şifre (opsiyonel)';
        passwordHelp.textContent = 'Boş bırakılırsa mevcut şifre korunur';
    }
    
    if (type === 'models' && data && data.brand_id) {
        loadBrands().then(() => {
            document.getElementById('brandSelect').value = data.brand_id;
        });
    }
}

function submitItemForm(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const type = formData.get('type');
    
    fetch('definitions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('addItemModal')).hide();
            showAlert(data.message || 'İşlem başarılı', 'success');
            // Refresh the current tab
            switchTab(type);
        } else {
            showAlert(data.message || 'İşlem başarısız', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('İşlem sırasında hata oluştu', 'error');
    });
}

function deleteItem(type, id, name) {
    if (!confirm(`"${name}" öğesini silmek istediğinizden emin misiniz?`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('type', type);
    formData.append('id', id);
    
    fetch('definitions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message || 'Silme işlemi başarılı', 'success');
            switchTab(type);
        } else {
            showAlert(data.message || 'Silme işlemi başarısız', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Silme işlemi sırasında hata oluştu', 'error');
    });
}

function loadBrands() {
    return fetch('definitions.php?action=get_brands')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('brandSelect');
                select.innerHTML = '<option value="">Marka seçin...</option>';
                data.brands.forEach(brand => {
                    select.innerHTML += `<option value="${brand.id}">${brand.name}</option>`;
                });
            }
        });
}

function loadBranches() {
    return fetch('definitions.php?action=get_branches')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('branchSelectPersonnel');
                if (select) {
                    select.innerHTML = '<option value="">Şube seçin...</option>';
                    data.branches.forEach(branch => {
                        select.innerHTML += `<option value="${branch.id}">${branch.name}</option>`;
                    });
                }
            }
        });
}

// Personnel city/district system now uses cities_complete.js like customer forms

function editPersonnel(id, name, phone, email, city, district, branchName, branchId) {
    console.log('editPersonnel called with:', {id, name, phone, email, city, district, branchName, branchId});
    
    // Populate basic fields
    document.getElementById('itemId').value = id;
    document.getElementById('itemName').value = name;
    document.getElementById('personnelPhone').value = phone || '';
    document.getElementById('personnelEmail').value = email || '';
    
    // Load branches and set branch
    loadBranches().then(() => {
        if (branchId) {
            document.getElementById('branchSelectPersonnel').value = branchId;
        }
    });
    
    // Setup city/district with actual values
    setTimeout(() => {
        if (typeof setupCityDistrict === 'function') {
            console.log('Setting up city/district with:', city, district);
            setupCityDistrict('personnelCity', 'personnelDistrict', city || 'Samsun', district || 'Atakum');
        } else {
            console.error('setupCityDistrict function not found');
        }
    }, 200);
    
    // Update modal for edit mode
    document.getElementById('addItemModalLabel').textContent = 'Personel Düzenle';
    document.querySelector('input[name="action"]').value = 'edit';
    document.getElementById('itemType').value = 'personnel';
    
    // Show personnel fields
    document.getElementById('personnelFields').style.display = 'block';
    document.getElementById('branchFields').style.display = 'none';
    document.getElementById('modelFields').style.display = 'none';
    
    // Show modal
    new bootstrap.Modal(document.getElementById('addItemModal')).show();
}

function switchTab(tabType) {
    // Update active tab
    document.querySelectorAll('.definition-tabs-compact .nav-link').forEach(link => {
        link.classList.remove('active');
    });
    const targetTab = document.querySelector(`#${tabType}-tab`);
    if (targetTab) {
        targetTab.classList.add('active');
    }
    
    // Load tab content via AJAX
    fetch(`definitions.php?ajax=refresh&type=${tabType}`)
        .then(response => response.text())
        .then(html => {
            const targetPane = document.querySelector(`#${tabType}`);
            if (targetPane) {
                targetPane.innerHTML = html;
                document.querySelectorAll('.tab-pane').forEach(pane => {
                    pane.classList.remove('show', 'active');
                });
                targetPane.classList.add('show', 'active');
            }
        })
        .catch(error => {
            console.error('Error loading tab:', error);
            showAlert('Sekme yüklenirken hata oluştu', 'error');
        });
}

function showAlert(message, type) {
    // Simple alert implementation
    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    const alertHTML = `
        <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    // Find or create alert container
    let alertContainer = document.querySelector('.alert-container');
    if (!alertContainer) {
        alertContainer = document.createElement('div');
        alertContainer.className = 'alert-container position-fixed top-0 start-50 translate-middle-x mt-3';
        alertContainer.style.zIndex = '9999';
        document.body.appendChild(alertContainer);
    }
    
    alertContainer.innerHTML = alertHTML;
    
    // Auto-hide after 3 seconds
    setTimeout(() => {
        const alert = alertContainer.querySelector('.alert');
        if (alert) {
            alert.remove();
        }
    }, 3000);
}
</script>
<!-- Branch Modal -->
<div class="modal fade" id="branchModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="branchModalLabel">Yeni Şube Ekle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="branchForm" action="definitions.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="type" value="branches">
                    <input type="hidden" name="action" value="add" id="branchAction">
                    <input type="hidden" id="branchId" name="id">
                    
                    <!-- Şube Adı -->
                    <div class="mb-3">
                        <label for="branchName" class="form-label">Şube Adı</label>
                        <input type="text" class="form-control" id="branchName" name="name" required>
                    </div>
                    
                    <!-- İl - İlçe -->
<div class="row">
    <div class="col-md-6 mb-3">
        <label for="branchCity" class="form-label">İl</label>
        <select class="form-select" id="branchCity" name="city" required>
            <option value="">İl seçin...</option>
        </select>
    </div>
    <div class="col-md-6 mb-3">
        <label for="branchDistrict" class="form-label">İlçe</label>
        <select class="form-select" id="branchDistrict" name="district">
            <option value="">İlçe seçin...</option>
        </select>
    </div>
</div>

<!-- JS -->
<script src="assets/js/cities_complete.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const branchModal = document.getElementById('branchModal');

    // Modal açıldığında city/district dropdownlarını hazırla
    branchModal.addEventListener('show.bs.modal', function() {
        const action = document.getElementById('branchAction').value;
        if (action === 'add') {
            fetch('definitions.php?action=get_company_info')
                .then(response => response.json())
                .then(data => {
                    let defaultCity = '';
                    let defaultDistrict = '';
                    if (data.success && data.company) {
                        defaultCity = data.company.city || '';
                        defaultDistrict = data.company.district || '';
                    }
                    // cities_complete.js'deki setupCityDistrict fonksiyonunu kullan
                    setupCityDistrict('branchCity', 'branchDistrict', defaultCity, defaultDistrict);
                })
                .catch(() => {
                    // Eğer fetch başarısız olursa dropdownları boş başlat
                    setupCityDistrict('branchCity', 'branchDistrict');
                });
        }
    });
});
</script>
                    
                    <!-- Adres -->
                    <div class="mb-3">
                        <label for="branchAddress" class="form-label">Adres</label>
                        <textarea class="form-control" id="branchAddress" name="address" rows="3"></textarea>
                    </div>
                    
                    <!-- Telefon - Email -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="branchPhone" class="form-label">Telefon</label>
                            <input type="tel" class="form-control" id="branchPhone" name="phone">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="branchEmail" class="form-label">Email</label>
                            <input type="email" class="form-control" id="branchEmail" name="email">
                        </div>
                    </div>
                    
                    <!-- Şifre -->
                    <div class="mb-3">
                        <label for="branchPassword" class="form-label">Şifre</label>
                        <input type="password" class="form-control" id="branchPassword" name="password" minlength="6" required>
                        <div class="form-text" id="passwordHelp">En az 6 karakter gerekli</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const citySelect = document.getElementById('branchCity');
    const districtSelect = document.getElementById('branchDistrict');
    const branchModal = document.getElementById('branchModal');

    // City-District setup using existing cities_complete.js
    function initializeBranchModal(defaultCity = '', defaultDistrict = '') {
        // setupCityDistrict(cityId, districtId, defaultCity, defaultDistrict)
        setupCityDistrict('branchCity', 'branchDistrict', defaultCity, defaultDistrict);
    }

    // Modal show event - load company city as default for new branches
    branchModal.addEventListener('show.bs.modal', function() {
        const action = document.getElementById('branchAction').value;
        if (action === 'add') {
            fetch('definitions.php?action=get_company_info')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.company) {
                        const defaultCity = data.company.city || '';
                        const defaultDistrict = data.company.district || '';
                        initializeBranchModal(defaultCity, defaultDistrict);
                    }
                })
                .catch(() => {
                    // Eğer fetch başarısız olursa sadece dropdownları boş başlat
                    initializeBranchModal();
                });
        }
    });
});

    
    // Form submit
    branchForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        fetch('definitions.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(branchModal).hide();
                showAlert(data.message || 'İşlem başarılı', 'success');
                location.reload();
            } else {
                showAlert(data.message || 'İşlem başarısız', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('İşlem sırasında hata oluştu', 'error');
        });
    });
    
    // Reset form on modal close
    branchModal.addEventListener('hidden.bs.modal', function() {
        branchForm.reset();
        document.getElementById('branchModalLabel').textContent = 'Yeni Şube Ekle';
        document.getElementById('branchAction').value = 'add';
        document.getElementById('branchId').value = '';
        document.getElementById('branchPassword').required = true;
        document.getElementById('passwordHelp').textContent = 'En az 6 karakter gerekli';
    });

// Edit branch function
function editBranch(id, name, phone, email, city, district, address) {
    const modal = document.getElementById('branchModal');
    const form = document.getElementById('branchForm');
    
    // Set form to edit mode
    document.getElementById('branchModalLabel').textContent = 'Şube Düzenle';
    document.getElementById('branchAction').value = 'edit';
    document.getElementById('branchId').value = id;
    
    // Fill form fields
    document.getElementById('branchName').value = name;
    document.getElementById('branchPhone').value = phone;
    document.getElementById('branchEmail').value = email;
    document.getElementById('branchAddress').value = address;
    
    // Set city and load districts
    const citySelect = document.getElementById('branchCity');
    const districtSelect = document.getElementById('branchDistrict');
    
    citySelect.value = city;
    loadDistricts(city, district);
    
    // Password is optional for edit
    const passwordField = document.getElementById('branchPassword');
    passwordField.required = false;
    passwordField.value = '';
    document.getElementById('passwordHelp').textContent = 'Boş bırakılırsa mevcut şifre korunur';
    
    // Show modal
    new bootstrap.Modal(modal).show();
}


</script>

<?php require_once 'includes/footer.php'; ?>
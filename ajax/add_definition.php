<?php
session_start();
require_once '../config/auth.php';
require_once '../includes/functions.php';

// Check if user is logged in
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek']);
    exit;
}

$type = $_POST['type'] ?? '';
$name = trim($_POST['name'] ?? '');
$brandId = $_POST['brand_id'] ?? null;

if (empty($type) || empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Tür ve isim gereklidir']);
    exit;
}

try {
    $companyId = $_SESSION['company_id'];
    $branchId = $_SESSION['branch_id'];
    
    // Determine table name
    $tableName = '';
    switch ($type) {
        case 'device':
            $tableName = 'devices';
            break;
        case 'brand':
            $tableName = 'brands';
            break;
        case 'model':
            $tableName = 'models';
            break;
        case 'complaint':
            $tableName = 'complaints';
            break;
        case 'operation':
            $tableName = 'operations';
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Geçersiz tür']);
            exit;
    }
    
    // Check if name already exists
    $existing = fetchOne("SELECT id FROM $tableName WHERE name = ? AND company_id = ?", [$name, $companyId]);
    if ($existing) {
        echo json_encode(['success' => false, 'message' => 'Bu isim zaten mevcut']);
        exit;
    }
    
    // CRITICAL: All definitions must be company-level (branch_id NULL) per user requirements
    // This ensures branches can see all company definitions but cannot create separate ones
    
    // Debug logging
    error_log("Add definition - Type: $type, Name: $name, Brand ID: " . ($brandId ?? 'null') . ", Company ID: $companyId");
    
    // Prepare insert query
    if ($type === 'model' && $brandId) {
        // For models, include brand_id but branch_id is NULL for company-level
        $query = "INSERT INTO $tableName (name, company_id, branch_id, brand_id, created_at) VALUES (?, ?, NULL, ?, NOW())";
        $params = [$name, $companyId, $brandId];
    } else {
        // For other types, branch_id is NULL for company-level
        $query = "INSERT INTO $tableName (name, company_id, branch_id, created_at) VALUES (?, ?, NULL, NOW())";
        $params = [$name, $companyId];
    }
    
    $result = executeQuery($query, $params);
    
    if ($result) {
        // Get the last insert ID using database connection
        $newId = null;
        try {
            $pdo = getDB();
            if ($pdo) {
                $newId = $pdo->lastInsertId();
            }
        } catch (Exception $e) {
            // Fallback - query the record we just inserted
            $lastRecord = fetchOne("SELECT id FROM $tableName WHERE name = ? AND company_id = ? ORDER BY id DESC LIMIT 1", [$name, $companyId]);
            $newId = $lastRecord['id'] ?? null;
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Başarıyla eklendi',
            'id' => $newId
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Ekleme işlemi başarısız']);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Hata oluştu: ' . $e->getMessage()
    ]);
}
?>
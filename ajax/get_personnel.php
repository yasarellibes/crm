<?php
require_once '../config/auth.php';
require_once '../includes/functions.php';

requireLogin();

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Personel ID gerekli']);
    exit;
}

$personnelId = intval($_GET['id']);

try {
    // Build query with role-based filtering
    $whereConditions = ['p.id = ?'];
    $queryParams = [$personnelId];

    // Apply role-based filtering
    if ($_SESSION['role'] == 'company_admin') {
        $whereConditions[] = 'p.company_id = ?';
        $queryParams[] = $_SESSION['company_id'];
    } elseif ($_SESSION['role'] == 'branch_manager') {
        $whereConditions[] = 'p.branch_id = ?';
        $queryParams[] = $_SESSION['branch_id'];
    }

    $whereClause = implode(' AND ', $whereConditions);
    
    $query = "
        SELECT p.*, c.name as company_name, b.name as branch_name
        FROM personnel p
        LEFT JOIN companies c ON p.company_id = c.id
        LEFT JOIN branches b ON p.branch_id = b.id
        WHERE $whereClause";
    
    $personnel = fetchOne($query, $queryParams);
    
    if (!$personnel) {
        echo json_encode(['success' => false, 'message' => 'Personel bulunamadı']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'personnel' => $personnel
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Veritabanı hatası: ' . $e->getMessage()]);
}
?>
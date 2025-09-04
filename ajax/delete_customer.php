<?php
/**
 * AJAX endpoint for deleting customers
 */

header('Content-Type: application/json');
session_start();

require_once '../config/auth.php';
require_once '../includes/functions.php';

// Check authentication and permissions
requirePermission(['super_admin', 'company_admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$customerId = intval($input['customer_id'] ?? 0);

if (!$customerId) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz müşteri ID']);
    exit;
}

try {
    // Check if customer has services
    $serviceCount = fetchOne(
        "SELECT COUNT(*) as count FROM services WHERE customer_id = ?", 
        [$customerId]
    )['count'];
    
    if ($serviceCount > 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'Bu müşterinin servis kayıtları bulunuyor. Önce servisleri silin.'
        ]);
        exit;
    }
    
    // Apply role-based filtering to ensure user can delete this customer
    $query = "SELECT id FROM customers WHERE id = ?";
    $params = [$customerId];
    list($query, $params) = applyDataFilter($query, $params, 'customers');
    
    $customer = fetchOne($query, $params);
    
    if (!$customer) {
        echo json_encode(['success' => false, 'message' => 'Müşteri bulunamadı veya silme yetkiniz yok']);
        exit;
    }
    
    // Delete the customer
    executeQuery("DELETE FROM customers WHERE id = ?", [$customerId]);
    
    // Log activity
    logActivity('Customer Deleted', "Customer ID: $customerId");
    
    echo json_encode([
        'success' => true, 
        'message' => 'Müşteri başarıyla silindi'
    ]);
    
} catch (Exception $e) {
    error_log("Error deleting customer: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Müşteri silinirken bir hata oluştu'
    ]);
}
?>
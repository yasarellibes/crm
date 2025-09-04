<?php
/**
 * AJAX endpoint for deleting services
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
$serviceId = intval($input['service_id'] ?? 0);

if (!$serviceId) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz servis ID']);
    exit;
}

try {
    // Apply role-based filtering to ensure user can delete this service
    $query = "SELECT id FROM services WHERE id = ?";
    $params = [$serviceId];
    list($query, $params) = applyDataFilter($query, $params, 'services');
    
    $service = fetchOne($query, $params);
    
    if (!$service) {
        echo json_encode(['success' => false, 'message' => 'Servis bulunamadı veya silme yetkiniz yok']);
        exit;
    }
    
    // Delete the service
    executeQuery("DELETE FROM services WHERE id = ?", [$serviceId]);
    
    // Log activity
    logActivity('Service Deleted', "Service ID: $serviceId");
    
    echo json_encode([
        'success' => true, 
        'message' => 'Servis başarıyla silindi'
    ]);
    
} catch (Exception $e) {
    error_log("Error deleting service: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Servis silinirken bir hata oluştu'
    ]);
}
?>
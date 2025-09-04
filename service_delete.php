<?php
// Include the header to load authentication and database functions
require_once 'config/auth.php';
require_once 'includes/functions.php';

// Check if user is logged in
requireLogin();

// Check if user has permission to delete services (only super_admin, company_admin, branch_manager)
requirePermission(['super_admin', 'company_admin', 'branch_manager']);

// Get service ID from URL
$serviceId = $_GET['id'] ?? null;
if (!$serviceId) {
    header('Location: services.php?error=service_not_found');
    exit;
}

// Get service data with filters applied
$serviceQuery = "
    SELECT s.*, c.name as customer_name
    FROM services s
    JOIN customers c ON s.customer_id = c.id
    WHERE s.id = ?
";

// Apply data filter based on user role
list($serviceQuery, $serviceParams) = applyDataFilter($serviceQuery, [$serviceId], 's');

$service = fetchOne($serviceQuery, $serviceParams);

if (!$service) {
    header('Location: services.php?error=service_not_found');
    exit;
}

// Check if user can access this service data
if (!canAccessData(null, $service['company_id'], $service['branch_id'])) {
    header('Location: services.php?error=no_permission');
    exit;
}

try {
    // Use the database connection from config
    require_once 'config/database.php';
    $pdo = getPDO();
    
    // Delete the service
    $stmt = $pdo->prepare("DELETE FROM services WHERE id = ?");
    $stmt->execute([$serviceId]);
    
    header('Location: services.php?success=service_deleted');
    exit;
    
} catch (Exception $e) {
    header('Location: services.php?error=delete_failed');
    exit;
}
?>
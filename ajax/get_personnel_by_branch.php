<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database and auth functions
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

// Check if user is logged in
requireLogin();

// Set JSON header
header('Content-Type: application/json');

// Get current user information
$currentUser = getCurrentUser();
$companyId = $currentUser['company_id'];

// Get branch ID from request
$branchId = $_GET['branch_id'] ?? null;

try {
    // Get personnel for the selected branch or all company personnel if no branch
    if ($branchId && is_numeric($branchId)) {
        // Get personnel for specific branch
        $personnel = fetchAll(
            "SELECT id, name FROM personnel WHERE branch_id = ? AND company_id = ? ORDER BY name",
            [$branchId, $companyId]
        );
    } else {
        // Get all personnel for company
        $personnel = fetchAll(
            "SELECT id, name FROM personnel WHERE company_id = ? ORDER BY name",
            [$companyId]
        );
    }
    
    echo json_encode([
        'success' => true,
        'personnel' => $personnel
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Personel listesi alınırken hata oluştu: ' . $e->getMessage()
    ]);
}
?>
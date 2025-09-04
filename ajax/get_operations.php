<?php
session_start();
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    // Get available statuses from operations table (dynamic based on company)
    $statusQuery = "SELECT DISTINCT name, color FROM operations WHERE company_id = ? AND branch_id IS NULL ORDER BY name";
    $statusParams = [];

    // Apply role-based filtering for operations
    if ($_SESSION['role'] === 'super_admin') {
        // Super admin sees all statuses
        $statusQuery = "SELECT DISTINCT name, color FROM operations ORDER BY name";
    } elseif ($_SESSION['role'] === 'company_admin') {
        $statusParams = [$_SESSION['company_id']];
    } elseif ($_SESSION['role'] === 'branch_manager') {
        $statusParams = [$_SESSION['company_id']];
    } elseif ($_SESSION['role'] === 'technician') {
        $statusParams = [$_SESSION['company_id']];
    }

    $operations = fetchAll($statusQuery, $statusParams);

    echo json_encode([
        'success' => true,
        'operations' => $operations
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Operasyonlar alınırken hata oluştu: ' . $e->getMessage()]);
}
?>
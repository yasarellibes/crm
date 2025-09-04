<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$date = $_GET['date'] ?? '';
if (!$date || !strtotime($date)) {
    echo json_encode([]);
    exit;
}

$currentUser = $_SESSION;

// Build role-based query
$whereConditions = ['DATE(s.service_date) = ?'];
$queryParams = [$date];

// Apply role-based filtering
if ($currentUser['role'] === 'company_admin') {
    $whereConditions[] = 's.company_id = ?';
    $queryParams[] = $currentUser['company_id'];
} elseif ($currentUser['role'] === 'branch_manager') {
    $whereConditions[] = 's.company_id = ?';
    $whereConditions[] = 's.branch_id = ?';
    $queryParams[] = $currentUser['company_id'];
    $queryParams[] = $currentUser['branch_id'];
} elseif ($currentUser['role'] === 'technician') {
    $whereConditions[] = 's.personnel_id = (SELECT id FROM personnel WHERE email = ? LIMIT 1)';
    $queryParams[] = $currentUser['email'];
}

$whereClause = implode(' AND ', $whereConditions);

// Get services for the specific date
$query = "
    SELECT s.id, s.device, s.complaint, s.operation_status, s.description,
           c.name as customer_name, c.phone, c.address, c.city, c.district,
           p.name as personnel_name, b.name as brand_name
    FROM services s
    LEFT JOIN customers c ON s.customer_id = c.id
    LEFT JOIN personnel p ON s.personnel_id = p.id
    LEFT JOIN brands b ON s.brand::integer = b.id
    WHERE $whereClause
    ORDER BY s.created_at DESC
";

$services = fetchAll($query, $queryParams);

echo json_encode($services);
?>
<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$year = intval($_GET['year'] ?? date('Y'));
$month = intval($_GET['month'] ?? date('n'));
$currentUser = $_SESSION;

// Build role-based query
$whereConditions = ['1=1'];
$queryParams = [];

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

// Date range for the month
$startDate = date('Y-m-01', mktime(0, 0, 0, $month, 1, $year));
$endDate = date('Y-m-t', mktime(0, 0, 0, $month, 1, $year));

$whereConditions[] = 's.service_date >= ?';
$whereConditions[] = 's.service_date <= ?';
$queryParams[] = $startDate;
$queryParams[] = $endDate;

$whereClause = implode(' AND ', $whereConditions);

// Get service counts by date
$query = "
    SELECT DATE(s.service_date) as service_date, COUNT(*) as service_count
    FROM services s
    WHERE $whereClause
    GROUP BY DATE(s.service_date)
    ORDER BY DATE(s.service_date)
";

$results = fetchAll($query, $queryParams);

// Convert to associative array with date strings as keys
$serviceCounts = [];
foreach ($results as $row) {
    $serviceCounts[$row['service_date']] = intval($row['service_count']);
}

echo json_encode($serviceCounts);
?>
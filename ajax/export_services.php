<?php
session_start();
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('Yetkisiz erişim');
}

// Check if XLSX export is requested
$isXlsxExport = isset($_GET['export']) && $_GET['export'] === 'xlsx';

// Get filter parameters
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$personnelFilter = trim($_GET['personnel'] ?? '');
$quickDate = trim($_GET['quick_date'] ?? '');

// Build query with improved search and filtering
$baseQuery = "
    SELECT s.id, s.customer_id, s.device, s.brand, s.model,
           s.complaint, s.description, s.price, s.service_date, s.operation_status,
           s.personnel_id, s.branch_id, s.company_id, s.created_at,
           c.name as customer_name, c.phone as customer_phone, c.address as customer_address,
           c.city as customer_city, c.district as customer_district,
           p.name as personnel_name,
           b.name as branch_name,
           COALESCE(brand_table.name, s.brand) as brand_name,
           COALESCE(model_table.name, s.model) as model_name
    FROM services s
    LEFT JOIN customers c ON s.customer_id = c.id
    LEFT JOIN personnel p ON s.personnel_id = p.id
    LEFT JOIN branches b ON s.branch_id = b.id
    LEFT JOIN brands brand_table ON (CASE WHEN s.brand ~ '^[0-9]+$' THEN CAST(s.brand AS INTEGER) ELSE NULL END) = brand_table.id AND s.company_id = brand_table.company_id
    LEFT JOIN models model_table ON (CASE WHEN s.model ~ '^[0-9]+$' THEN CAST(s.model AS INTEGER) ELSE NULL END) = model_table.id AND s.company_id = model_table.company_id
    WHERE 1=1
";

$params = [];

// Apply role-based filtering
list($baseQuery, $params) = applyDataFilter($baseQuery, $params, 's');

// Apply improved search filter
if ($search) {
    $baseQuery .= " AND (
        c.name ILIKE ? OR 
        c.phone LIKE ? OR 
        s.device ILIKE ? OR 
        s.brand ILIKE ? OR 
        s.model ILIKE ? OR 
        COALESCE(brand_table.name, s.brand) ILIKE ? OR 
        COALESCE(model_table.name, s.model) ILIKE ? OR 
        s.complaint ILIKE ?
    )";
    $searchParam = '%' . $search . '%';
    $params = array_merge($params, array_fill(0, 8, $searchParam));
}

// Apply status filter
if ($statusFilter) {
    $baseQuery .= " AND s.operation_status = ?";
    $params[] = $statusFilter;
}

// Apply personnel filter
if ($personnelFilter) {
    $baseQuery .= " AND s.personnel_id = ?";
    $params[] = $personnelFilter;
}

// Apply date filters
if ($quickDate) {
    $today = date('Y-m-d');
    switch ($quickDate) {
        case 'yesterday':
            $date = date('Y-m-d', strtotime('-1 day'));
            $baseQuery .= " AND DATE(s.service_date) = ?";
            $params[] = $date;
            break;
        case 'today':
            $baseQuery .= " AND DATE(s.service_date) = ?";
            $params[] = $today;
            break;
        case 'tomorrow':
            $date = date('Y-m-d', strtotime('+1 day'));
            $baseQuery .= " AND DATE(s.service_date) = ?";
            $params[] = $date;
            break;
    }
}

// Get services
$servicesQuery = $baseQuery . " ORDER BY s.service_date DESC, s.created_at DESC";
$services = fetchAll($servicesQuery, $params);

if ($isXlsxExport) {
    // Generate HTML-based Excel file
    $filename = 'servisler_' . date('Y-m-d_H-i-s') . '.xls';
    
    // Set headers for Excel download
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');
    
    // Output Excel-compatible HTML
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">' . "\n";
    echo '<head>' . "\n";
    echo '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">' . "\n";
    echo '<meta name="ProgId" content="Excel.Sheet">' . "\n";
    echo '<meta name="Generator" content="Microsoft Excel 15">' . "\n";
    echo '<style>' . "\n";
    echo 'table { border-collapse: collapse; width: 100%; }' . "\n";
    echo 'th { background-color: #E6E6FA; font-weight: bold; border: 1px solid #000; padding: 8px; text-align: left; }' . "\n";
    echo 'td { border: 1px solid #000; padding: 8px; text-align: left; }' . "\n";
    echo '</style>' . "\n";
    echo '</head>' . "\n";
    echo '<body>' . "\n";
    echo '<table>' . "\n";
    
    // Headers
    echo '<tr>' . "\n";
    echo '<th>Müşteri</th>' . "\n";
    echo '<th>Cihaz/Marka/Model</th>' . "\n";
    echo '<th>Şikayet</th>' . "\n";
    echo '<th>Durum</th>' . "\n";
    echo '<th>Personel</th>' . "\n";
    echo '<th>Tarih</th>' . "\n";
    echo '<th>Fiyat</th>' . "\n";
    echo '</tr>' . "\n";
    
    // Data rows
    foreach ($services as $service) {
        $deviceInfo = $service['device'];
        if ($service['brand_name'] || $service['model_name']) {
            $deviceInfo .= ' - ' . ($service['brand_name'] ?: $service['brand']) . ' / ' . ($service['model_name'] ?: $service['model']);
        }
        
        echo '<tr>' . "\n";
        echo '<td>' . htmlspecialchars($service['customer_name'] ?: 'N/A', ENT_QUOTES, 'UTF-8') . '</td>' . "\n";
        echo '<td>' . htmlspecialchars($deviceInfo, ENT_QUOTES, 'UTF-8') . '</td>' . "\n";
        echo '<td>' . htmlspecialchars($service['complaint'] ?: 'N/A', ENT_QUOTES, 'UTF-8') . '</td>' . "\n";
        echo '<td>' . htmlspecialchars($service['operation_status'] ?: 'N/A', ENT_QUOTES, 'UTF-8') . '</td>' . "\n";
        echo '<td>' . htmlspecialchars($service['personnel_name'] ?: 'Atanmamış', ENT_QUOTES, 'UTF-8') . '</td>' . "\n";
        echo '<td>' . htmlspecialchars($service['service_date'] ? date('d.m.Y', strtotime($service['service_date'])) : 'N/A', ENT_QUOTES, 'UTF-8') . '</td>' . "\n";
        echo '<td>' . htmlspecialchars($service['price'] ? number_format($service['price'], 2, ',', '.') . ' ₺' : 'N/A', ENT_QUOTES, 'UTF-8') . '</td>' . "\n";
        echo '</tr>' . "\n";
    }
    
    echo '</table>' . "\n";
    echo '</body>' . "\n";
    echo '</html>' . "\n";
    
} else {
    // Generate CSV file (fallback)
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="servisler_' . date('Y-m-d_H-i-s') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Output UTF-8 BOM for proper Turkish character support in Excel
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // CSV Headers
    $headers = [
        'Müşteri',
        'Cihaz/Marka/Model', 
        'Şikayet',
        'Durum',
        'Personel',
        'Tarih',
        'Fiyat'
    ];
    
    fputcsv($output, $headers, ';');
    
    // Output data rows
    foreach ($services as $service) {
        $deviceInfo = $service['device'];
        if ($service['brand_name'] || $service['model_name']) {
            $deviceInfo .= ' - ' . ($service['brand_name'] ?: $service['brand']) . ' / ' . ($service['model_name'] ?: $service['model']);
        }
        
        $row = [
            $service['customer_name'] ?: 'N/A',
            $deviceInfo,
            $service['complaint'] ?: 'N/A',
            $service['operation_status'] ?: 'N/A',
            $service['personnel_name'] ?: 'Atanmamış',
            $service['service_date'] ? date('d.m.Y', strtotime($service['service_date'])) : 'N/A',
            $service['price'] ? number_format($service['price'], 2) . ' ₺' : 'N/A'
        ];
        
        fputcsv($output, $row, ';');
    }
    
    fclose($output);
}
?>
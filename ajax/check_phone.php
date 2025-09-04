<?php
// Start session first
session_start();

// Include required files
require_once '../config/database.php';

// Debug session for troubleshooting
$sessionDebug = [
    'session_id' => session_id(),
    'logged_in_isset' => isset($_SESSION['logged_in']),
    'logged_in_value' => $_SESSION['logged_in'] ?? 'not_set',
    'company_id_isset' => isset($_SESSION['company_id']),
    'company_id_value' => $_SESSION['company_id'] ?? 'not_set'
];

// Check if user is logged in (AJAX friendly - no redirect)
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['company_id'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'Oturum bulunamadı. Lütfen giriş yapınız.',
        'debug' => $sessionDebug
    ]);
    exit;
}

// Get company ID from session
$companyId = $_SESSION['company_id'];
$userId = $_SESSION['user_id'];

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$phone = trim($_GET['phone'] ?? '');

if (empty($phone)) {
    echo json_encode(['success' => false, 'message' => 'Phone number is required']);
    exit;
}

// Clean phone number (remove spaces and non-numeric chars except leading 0)
$cleanPhone = preg_replace('/[^0-9]/', '', $phone);

// Validate phone format (same as Flask system)
if (!preg_match('/^0[5-9]\d{9}$/', $cleanPhone)) {
    echo json_encode([
        'success' => false, 
        'message' => 'Geçerli bir Türkiye telefon numarası giriniz.',
        'valid' => false
    ]);
    exit;
}

// Use cleaned phone for database queries
$phone = $cleanPhone;

try {
    // Log the search parameters for debugging
    error_log("Phone check - Phone: $phone, Company ID: $companyId");
    
    // Check if customer exists with this phone within the same company only
    // This ensures company data isolation - each company can only see their own customers
    $existingCustomer = fetchOne("SELECT id, name, city, district, address FROM customers WHERE phone = ? AND company_id = ?", [$phone, $companyId]);
    
    // Debug: Log the SQL result
    error_log("Phone check result: " . json_encode($existingCustomer));
    
    if ($existingCustomer) {
        echo json_encode([
            'success' => true,
            'exists' => true,
            'customer' => $existingCustomer,
            'message' => 'Bu telefon numarasına kayıtlı müşteri bulundu. Bilgiler otomatik doldurulacak.'
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'exists' => false,
            'valid' => true,
            'message' => 'Telefon numarası geçerli. Yeni müşteri olarak kaydedilecek.'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
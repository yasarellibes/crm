<?php
/**
 * Common utility functions for HVAC System
 */

/**
 * Escape HTML output
 * @param mixed $value Value to escape
 * @return string Escaped value
 */
function e($value) {
    if (is_null($value)) {
        return '';
    }
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Format phone number for display
 * @param string $phone Phone number
 * @return string Formatted phone number
 */
function formatPhone($phone) {
    if (empty($phone)) return '';
    
    // Remove all non-numeric characters
    $clean = preg_replace('/[^0-9]/', '', $phone);
    
    // Return clean numeric format: 05321234567
    if (strlen($clean) == 11 && substr($clean, 0, 1) == '0') {
        return $clean;
    }
    
    return $phone;
}

/**
 * Format date for display
 * @param string $date Date string
 * @param string $format Output format
 * @return string Formatted date
 */
function formatDate($date, $format = 'd.m.Y') {
    if (empty($date)) return '';
    
    try {
        $dateObj = new DateTime($date);
        return $dateObj->format($format);
    } catch (Exception $e) {
        return $date;
    }
}

/**
 * Format currency amount
 * @param float $amount Amount
 * @return string Formatted amount
 */
function formatCurrency($amount) {
    return number_format($amount, 2, ',', '.') . ' ₺';
}

/**
 * Generate pagination HTML
 * @param int $currentPage Current page number
 * @param int $totalPages Total number of pages
 * @param string $baseUrl Base URL for pagination links
 * @return string Pagination HTML
 */
function generatePagination($currentPage, $totalPages, $baseUrl) {
    if ($totalPages <= 1) return '';
    
    $html = '<nav aria-label="Sayfa navigasyonu"><ul class="pagination justify-content-center">';
    
    // Previous button
    if ($currentPage > 1) {
        $prevUrl = $baseUrl . ($currentPage - 1);
        $html .= '<li class="page-item"><a class="page-link" href="' . $prevUrl . '">Önceki</a></li>';
    }
    
    // Page numbers
    $start = max(1, $currentPage - 2);
    $end = min($totalPages, $currentPage + 2);
    
    if ($start > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '1">1</a></li>';
        if ($start > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }
    
    for ($i = $start; $i <= $end; $i++) {
        $class = ($i == $currentPage) ? 'page-item active' : 'page-item';
        $html .= '<li class="' . $class . '"><a class="page-link" href="' . $baseUrl . $i . '">' . $i . '</a></li>';
    }
    
    if ($end < $totalPages) {
        if ($end < $totalPages - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . $totalPages . '">' . $totalPages . '</a></li>';
    }
    
    // Next button
    if ($currentPage < $totalPages) {
        $nextUrl = $baseUrl . ($currentPage + 1);
        $html .= '<li class="page-item"><a class="page-link" href="' . $nextUrl . '">Sonraki</a></li>';
    }
    
    $html .= '</ul></nav>';
    
    return $html;
}

/**
 * Add flash message to session
 * @param string $message Message text
 * @param string $type Message type (success, error, warning, info)
 */
function addFlashMessage($message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

/**
 * Get and clear flash message
 * @return array|null Flash message data
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $flash = [
            'message' => $_SESSION['flash_message'],
            'type' => $_SESSION['flash_type']
        ];
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        return $flash;
    }
    return null;
}

/**
 * Redirect with message
 * @param string $url Redirect URL
 * @param string $message Flash message
 * @param string $type Message type
 */
function redirectWithMessage($url, $message, $type = 'info') {
    addFlashMessage($message, $type);
    header('Location: ' . $url);
    exit;
}

/**
 * Get current page name from URL
 * @return string Page name
 */
function getCurrentPage() {
    $page = basename($_SERVER['PHP_SELF'], '.php');
    return $page;
}

/**
 * Check if current page matches given page(s)
 * @param string|array $pages Page name(s) to check
 * @return bool
 */
function isCurrentPage($pages) {
    $currentPage = getCurrentPage();
    
    if (is_array($pages)) {
        return in_array($currentPage, $pages);
    }
    
    return $currentPage === $pages;
}

/**
 * Apply role-based data filtering to queries (alternative implementation)
 * @param string $query Base SQL query
 * @param array $params Query parameters
 * @param string $tableAlias Table alias (s for services, c for customers, etc.)
 * @return array [filtered query, parameters]
 */
function applyRoleFilter($query, $params = [], $tableAlias = '') {
    if (!isset($_SESSION['role']) || !isset($_SESSION['user_type'])) {
        return [$query, $params];
    }
    
    $role = $_SESSION['role'];
    $userType = $_SESSION['user_type'] ?? 'user';
    $alias = $tableAlias ? $tableAlias . '.' : '';
    
    // Super admin sees everything
    if ($role === 'super_admin') {
        return [$query, $params];
    }
    
    // Company admin sees only their company data
    if ($role === 'company_admin' && $userType === 'company' && isset($_SESSION['company_id'])) {
        $query .= " AND {$alias}company_id = ?";
        $params[] = $_SESSION['company_id'];
    }
    // Branch manager sees only their branch data
    elseif ($role === 'branch_manager' && isset($_SESSION['company_id']) && isset($_SESSION['branch_id'])) {
        $query .= " AND {$alias}company_id = ? AND {$alias}branch_id = ?";
        $params[] = $_SESSION['company_id'];
        $params[] = $_SESSION['branch_id'];
    }
    // Technician sees only their assigned services
    elseif ($role === 'technician' && isset($_SESSION['user_id'])) {
        $query .= " AND {$alias}personnel_id = ?";
        $params[] = $_SESSION['user_id'];
    }
    
    return [$query, $params];
}

/**
 * Get dashboard statistics for current user
 * @return array Statistics data
 */
function getDashboardStats() {
    $baseQuery = "SELECT COUNT(*) as count FROM ";
    $params = [];
    
    // Use auth.php applyDataFilter function
    // Customers count
    list($customersQuery, $customersParams) = applyDataFilter($baseQuery . "customers c WHERE 1=1", [], 'c');
    $customersCount = fetchOne($customersQuery, $customersParams)['count'];
    
    // Services count
    list($servicesQuery, $servicesParams) = applyDataFilter($baseQuery . "services s WHERE 1=1", [], 's');
    $servicesCount = fetchOne($servicesQuery, $servicesParams)['count'];
    
    // Active services (not completed)
    list($activeQuery, $activeParams) = applyDataFilter($baseQuery . "services s WHERE s.operation_status != 'Tamamlandı'", [], 's');
    $activeServices = fetchOne($activeQuery, $activeParams)['count'];
    
    // Pending services (Beklemede, Devam Ediyor)
    list($pendingQuery, $pendingParams) = applyDataFilter($baseQuery . "services s WHERE s.operation_status IN ('Beklemede', 'Devam Ediyor')", [], 's');
    $pendingServices = fetchOne($pendingQuery, $pendingParams)['count'];
    
    // Today's services
    list($todayQuery, $todayParams) = applyDataFilter($baseQuery . "services s WHERE DATE(s.service_date) = CURRENT_DATE", [], 's');
    $todayServices = fetchOne($todayQuery, $todayParams)['count'];
    
    // Total revenue
    list($revenueQuery, $revenueParams) = applyDataFilter("SELECT COALESCE(SUM(s.price), 0) as total FROM services s WHERE s.operation_status = 'Tamamlandı'", [], 's');
    $totalRevenue = fetchOne($revenueQuery, $revenueParams)['total'];
    
    return [
        'customers' => $customersCount,
        'services' => $servicesCount,
        'active_services' => $activeServices,
        'pending_services' => $pendingServices,
        'today_services' => $todayServices,
        'total_revenue' => floatval($totalRevenue)
    ];
}

/**
 * Log user activity
 * @param string $action Action description
 * @param string $details Additional details
 */
function logActivity($action, $details = '') {
    if (!isLoggedIn()) return;
    
    executeQuery(
        "INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)",
        [$_SESSION['user_id'], $action, $details]
    );
}

/**
 * Get operation status options
 * @return array Status options
 */
function getOperationStatuses() {
    return [
        'Beklemede',
        'Devam Ediyor',
        'Tamamlandı',
        'İptal Edildi',
        'Ertelendi'
    ];
}

/**
 * Generate Google Maps URL from address
 * @param string $address Full address
 * @return string Google Maps URL
 */
function getGoogleMapsUrl($address) {
    return 'https://maps.google.com/maps?q=' . urlencode($address);
}

/**
 * Clean and validate phone number
 * @param string $phone Phone number
 * @return string|false Clean phone or false if invalid
 */
function cleanPhoneNumber($phone) {
    $clean = preg_replace('/[^0-9]/', '', $phone);
    
    // Turkish mobile number validation
    if (preg_match('/^(05[0-9]{9})$/', $clean)) {
        return '0' . substr($clean, 1);
    }
    
    return false;
}

/**
 * Validate email address
 * @param string $email Email address
 * @return bool Valid email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Get system settings
 */
function getSystemSettings() {
    static $systemSettings = null;
    
    if ($systemSettings === null) {
        try {
            require_once __DIR__ . '/../config/database.php';
            $settings = fetchAll("SELECT setting_key, setting_value FROM system_settings", []);
            $systemSettings = [];
            foreach ($settings as $setting) {
                $systemSettings[$setting['setting_key']] = $setting['setting_value'];
            }
            
            // Default values if not set
            $defaults = [
                'system_name' => 'Serviso HVAC Yönetim Sistemi',
                'company_name' => 'Serviso',
                'company_phone' => '05320528000',
                'company_email' => 'info@serviso.com.tr',
                'company_website' => 'serviso.com.tr'
            ];
            
            foreach ($defaults as $key => $value) {
                if (!isset($systemSettings[$key])) {
                    $systemSettings[$key] = $value;
                }
            }
            
        } catch (Exception $e) {
            // Fallback defaults if database error
            $systemSettings = [
                'system_name' => 'Serviso HVAC Yönetim Sistemi',
                'company_name' => 'Serviso',
                'company_phone' => '05320528000',
                'company_email' => 'info@serviso.com.tr',
                'company_website' => 'serviso.com.tr'
            ];
        }
    }
    
    return $systemSettings;
}

/**
 * Get default city and district from current user's company
 * @return array Default city and district
 */
function getCompanyDefaults() {
    global $currentUser;
    if (!$currentUser || !$currentUser['company_id']) {
        return ['city' => '', 'district' => ''];
    }
    
    $company = fetchOne("SELECT city, district FROM companies WHERE id = ?", [$currentUser['company_id']]);
    
    return [
        'city' => $company['city'] ?? '',
        'district' => $company['district'] ?? ''
    ];
}



/**
 * Generate secure password hash
 * @param string $password Plain password
 * @return string Hashed password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Truncate text to specified length
 * @param string $text Text to truncate
 * @param int $length Maximum length
 * @param string $suffix Suffix for truncated text
 * @return string Truncated text
 */
function truncateText($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    
    return substr($text, 0, $length) . $suffix;
}

/**
 * Create demo data when a new company is created (like Flask system)
 * @param int $companyId Company ID
 * @param int $branchId Default branch ID
 */
function createCompanyDemoData($companyId, $branchId) {
    require_once __DIR__ . '/../config/database.php';
    $pdo = getPDO();
    
    try {
        $pdo->beginTransaction();
        
        // Create default devices
        $devices = ['Kombi', 'Klima', 'Çamaşır Makinesi', 'Bulaşık Makinesi', 'Şofben'];
        foreach ($devices as $device) {
            $stmt = $pdo->prepare("INSERT INTO devices (name, company_id, branch_id, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$device, $companyId, $branchId]);
        }
        
        // Create default brands with "Bilinmiyor" as first option
        $brands = ['Bilinmiyor', 'Arçelik', 'Bosch', 'Siemens', 'Vaillant', 'Baxi', 'Demirdöküm'];
        $brandIds = [];
        foreach ($brands as $brand) {
            $stmt = $pdo->prepare("INSERT INTO brands (name, company_id, branch_id, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$brand, $companyId, $branchId]);
            $brandIds[$brand] = $pdo->lastInsertId();
        }
        
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}



/**
 * Get today's services count
 */
function getTodayServicesCount() {
    $query = "SELECT COUNT(*) as count FROM services s WHERE DATE(service_date) = CURRENT_DATE";
    $params = [];
    
    list($query, $params) = applyDataFilter($query, $params, 's');
    
    $result = fetchOne($query, $params);
    return intval($result['count'] ?? 0);
}

/**
 * Format price with Turkish Lira currency
 * @param float $price Price amount
 * @return string Formatted price
 */
function formatPrice($price) {
    if (empty($price) || $price == 0) return '0,00 ₺';
    return number_format($price, 2, ',', '.') . ' ₺';
}

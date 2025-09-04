<?php
/**
 * Authentication and Authorization System
 * Equivalent to Flask auth.py functionality
 */

// Session will be started by the calling script
require_once __DIR__ . '/database.php';

/**
 * Login user with email and password
 * @param string $email User email
 * @param string $password User password
 * @param bool $remember Whether to remember the user
 * @return bool Login success
 */
function loginUser($email, $password, $remember = false) {
    try {
        // First check companies table
        $company = fetchOne("SELECT * FROM companies WHERE email = ?", [$email]);
        
        if ($company && !empty($company['password']) && password_verify($password, $company['password'])) {
            // Check if company service is expired (super admin check by email)
            $userRole = ($email === 'admin@serviso.com') ? 'super_admin' : 'company_admin';
            if ($userRole !== 'super_admin' && $company['service_end_date'] && $company['service_end_date'] < date('Y-m-d')) {
                error_log("Login blocked - Company service expired: " . $email);
                return false;
            }
            
            // Company login
            $_SESSION['user_id'] = $company['id'];
            $_SESSION['name'] = $company['name'];
            $_SESSION['email'] = $company['email'];
            $_SESSION['role'] = $userRole;
            $_SESSION['company_id'] = $company['id'];
            $_SESSION['company_name'] = $company['name'];
            $_SESSION['branch_id'] = null;
            $_SESSION['logged_in'] = true;
            $_SESSION['user_type'] = 'company';
            $_SESSION['service_end_date'] = $company['service_end_date'];
            
            // Set remember me cookie if requested
            if ($remember) {
                $rememberToken = bin2hex(random_bytes(32));
                setcookie('remember_token', $rememberToken, time() + (30 * 24 * 60 * 60), '/', '', false, true); // 30 days
                setcookie('remember_email', $email, time() + (30 * 24 * 60 * 60), '/', '', false, true);
            }
            
            error_log("Company login successful: " . $email);
            return true;
        }
        
        // Then try branches table
        $branch = fetchOne("SELECT * FROM branches WHERE email = ?", [$email]);
        
        if ($branch && !empty($branch['password']) && password_verify($password, $branch['password'])) {
            // Check if parent company service is expired
            if ($branch['company_id']) {
                $companyData = fetchOne("SELECT service_end_date, name FROM companies WHERE id = ?", [$branch['company_id']]);
                if ($companyData && $companyData['service_end_date'] && $companyData['service_end_date'] < date('Y-m-d')) {
                    error_log("Login blocked - Parent company service expired: " . $email);
                    return false;
                }
                $_SESSION['company_name'] = $companyData['name'] ?? 'Bilinmiyor';
                $_SESSION['service_end_date'] = $companyData['service_end_date'];
            }
            
            // Branch login
            $_SESSION['user_id'] = $branch['id'];
            $_SESSION['name'] = $branch['name'];
            $_SESSION['email'] = $branch['email'];
            $_SESSION['role'] = 'branch_manager';
            $_SESSION['company_id'] = $branch['company_id'];
            $_SESSION['branch_id'] = $branch['id'];
            $_SESSION['logged_in'] = true;
            $_SESSION['user_type'] = 'branch';
            
            // Set remember me cookie if requested
            if ($remember) {
                $rememberToken = bin2hex(random_bytes(32));
                setcookie('remember_token', $rememberToken, time() + (30 * 24 * 60 * 60), '/', '', false, true); // 30 days
                setcookie('remember_email', $email, time() + (30 * 24 * 60 * 60), '/', '', false, true);
            }
            
            error_log("Branch login successful: " . $email);
            return true;
        }
        
        // Then try users table
        $user = fetchOne(
            "SELECT id, CONCAT(first_name, ' ', last_name) as name, email, password_hash as password, role, company_id, branch_id, status 
             FROM users 
             WHERE email = ? AND status = 'active'", 
            [$email]
        );
        
        // If not found in users, try personnel table
        if (!$user) {
            $user = fetchOne(
                "SELECT id, name, email, password, role, company_id, branch_id, status 
                 FROM personnel 
                 WHERE email = ? AND status = 'active'", 
                [$email]
            );
        }
        
        if ($user && !empty($user['password']) && password_verify($password, $user['password'])) {
            // Check if parent company service is expired
            if ($user['company_id']) {
                $companyData = fetchOne("SELECT service_end_date, name FROM companies WHERE id = ?", [$user['company_id']]);
                if ($companyData && $companyData['service_end_date'] && $companyData['service_end_date'] < date('Y-m-d')) {
                    error_log("Login blocked - Parent company service expired for user: " . $email);
                    return false;
                }
                $_SESSION['company_name'] = $companyData['name'] ?? 'Bilinmiyor';
                $_SESSION['service_end_date'] = $companyData['service_end_date'];
            }
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['company_id'] = $user['company_id'];
            $_SESSION['branch_id'] = $user['branch_id'];
            $_SESSION['logged_in'] = true;
            $_SESSION['user_type'] = 'user';
            
            // Set remember me cookie if requested
            if ($remember) {
                $rememberToken = bin2hex(random_bytes(32));
                setcookie('remember_token', $rememberToken, time() + (30 * 24 * 60 * 60), '/', '', false, true); // 30 days
                setcookie('remember_email', $email, time() + (30 * 24 * 60 * 60), '/', '', false, true);
            }
            
            error_log("User login successful: " . $email);
            return true;
        }
        
        error_log("Login failed for: " . $email);
        return false;
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        return false;
    }
}

/**
 * Logout user
 */
function logoutUser() {
    // Clear remember me cookies
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/');
    }
    if (isset($_COOKIE['remember_email'])) {
        setcookie('remember_email', '', time() - 3600, '/');
    }
    
    session_unset();
    session_destroy();
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
}

/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Get current user data
 * @return array|null
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['name'],
        'email' => $_SESSION['email'],
        'role' => $_SESSION['role'],
        'company_id' => $_SESSION['company_id'],
        'branch_id' => $_SESSION['branch_id']
    ];
}

/**
 * Require login - redirect to login page if not authenticated
 */
function requireLogin() {
    if (!isLoggedIn()) {
        // For AJAX requests, return JSON error instead of redirect
        if (isset($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') || 
            strpos($_SERVER['REQUEST_URI'] ?? '', 'ajax/') !== false) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Oturum süresi dolmuş', 'redirect' => 'login.php']);
            exit;
        }
        header('Location: login.php');
        exit;
    }
    
    // Check service expiration for non-super admins
    if ($_SESSION['role'] !== 'super_admin' && isset($_SESSION['company_id'])) {
        $company = fetchOne("SELECT service_end_date FROM companies WHERE id = ?", [$_SESSION['company_id']]);
        if ($company && $company['service_end_date'] && strtotime($company['service_end_date']) < time()) {
            // Service expired - redirect to expired page
            if (basename($_SERVER['PHP_SELF']) !== 'service_expired.php') {
                header('Location: service_expired.php');
                exit;
            }
        }
    }
}

/**
 * Check user permission for specific role
 * @param array $allowedRoles Array of allowed roles
 * @return bool
 */
function hasPermission($allowedRoles = []) {
    if (!isLoggedIn()) {
        return false;
    }
    
    if (empty($allowedRoles)) {
        return true;
    }
    
    return in_array($_SESSION['role'], $allowedRoles);
}

/**
 * Require specific permission - redirect if not authorized
 * @param array $allowedRoles Array of allowed roles
 */
function requirePermission($allowedRoles = []) {
    requireLogin();
    
    if (!hasPermission($allowedRoles)) {
        header('Location: dashboard.php?error=no_permission');
        exit;
    }
}

/**
 * Apply role-based data filtering to queries
 * @param string $baseQuery Base SQL query
 * @param array $params Current parameters
 * @param string $tableAlias Table alias (default: 's' for services)
 * @return array [modified_query, modified_params]
 */
function applyDataFilter($baseQuery, $params = [], $tableAlias = 's') {
    if (!isLoggedIn()) {
        return [$baseQuery, $params];
    }
    
    $role = $_SESSION['role'];
    $companyId = $_SESSION['company_id'];
    $branchId = $_SESSION['branch_id'];
    
    switch ($role) {
        case 'super_admin':
            // Super admin sees all data across all companies and branches
            // No additional filters applied
            break;
            
        case 'company_admin':
            // Company admin sees only their company's data (all branches within company)
            if ($companyId) {
                // For tables with direct company_id column
                if (in_array($tableAlias, ['s', 'c', 'p'])) { // services, customers, personnel
                    $baseQuery .= " AND {$tableAlias}.company_id = ?";
                    $params[] = $companyId;
                } else {
                    // For other tables, join with companies table if needed
                    $baseQuery .= " AND {$tableAlias}.company_id = ?";
                    $params[] = $companyId;
                }
            }
            break;
            
        case 'branch_manager':
            // Branch manager sees company-level data with some branch restrictions
            if ($companyId && $branchId) {
                // Branches table - only their specific branch
                if ($tableAlias === 'b' && strpos($baseQuery, 'branches') !== false) {
                    $baseQuery .= " AND {$tableAlias}.company_id = ? AND {$tableAlias}.id = ?";
                    $params[] = $companyId;
                    $params[] = $branchId;
                } 
                // Customers table - all customers of their company (shared across branches)
                elseif ($tableAlias === 'c' && strpos($baseQuery, 'customers') !== false) {
                    $baseQuery .= " AND {$tableAlias}.company_id = ?";
                    $params[] = $companyId;
                } 
                // Services table - only their branch services
                elseif ($tableAlias === 's' && strpos($baseQuery, 'services') !== false) {
                    $baseQuery .= " AND {$tableAlias}.company_id = ? AND {$tableAlias}.branch_id = ?";
                    $params[] = $companyId;
                    $params[] = $branchId;
                } 
                // Other tables - company and branch specific
                else {
                    $baseQuery .= " AND {$tableAlias}.company_id = ? AND {$tableAlias}.branch_id = ?";
                    $params[] = $companyId;
                    $params[] = $branchId;
                }
            } elseif ($companyId) {
                // Fallback to company-only filter if branch not set
                $baseQuery .= " AND {$tableAlias}.company_id = ?";
                $params[] = $companyId;
            }
            break;
            
        case 'technician':
            // Technician sees only services assigned to them personally
            // Plus they can see customers related to their services
            if ($tableAlias === 's') {
                // For services table - only assigned services
                $baseQuery .= " AND {$tableAlias}.personnel_id = ?";
                $params[] = $_SESSION['user_id'];
            } elseif ($tableAlias === 'c') {
                // For customers table - only customers with services assigned to this technician
                $baseQuery .= " AND {$tableAlias}.id IN (
                    SELECT DISTINCT customer_id FROM services WHERE personnel_id = ?
                )";
                $params[] = $_SESSION['user_id'];
            } else {
                // For other tables, filter by company at minimum
                if ($companyId) {
                    $baseQuery .= " AND {$tableAlias}.company_id = ?";
                    $params[] = $companyId;
                }
            }
            break;
    }
    
    return [$baseQuery, $params];
}

/**
 * Get company filter for current user
 * @return int|null Company ID or null
 */
function getCompanyFilter() {
    return $_SESSION['company_id'] ?? null;
}

/**
 * Get branch filter for current user
 * @return int|null Branch ID or null
 */
function getBranchFilter() {
    return $_SESSION['branch_id'] ?? null;
}

/**
 * Get personnel list filtered by current user's permissions
 * @return array Personnel list
 */
function getFilteredPersonnel() {
    if (!isLoggedIn()) {
        return [];
    }
    
    $role = $_SESSION['role'];
    $companyId = $_SESSION['company_id'];
    $branchId = $_SESSION['branch_id'];
    
    $query = "SELECT id, name FROM personnel WHERE status = 'active'";
    $params = [];
    
    switch ($role) {
        case 'super_admin':
            // Super admin sees all personnel across all companies
            break;
            
        case 'company_admin':
            // Company admin sees only personnel from their company
            if ($companyId) {
                $query .= " AND company_id = ?";
                $params[] = $companyId;
            }
            break;
            
        case 'branch_manager':
            if ($companyId && $branchId) {
                $query .= " AND company_id = ? AND branch_id = ?";
                $params[] = $companyId;
                $params[] = $branchId;
            }
            break;
            
        case 'technician':
            // Technicians only see themselves
            $query .= " AND id = ?";
            $params[] = $_SESSION['user_id'];
            break;
    }
    
    $query .= " ORDER BY name";
    
    return fetchAll($query, $params);
}

/**
 * Check if user can access specific data based on hierarchy
 * @param string $targetRole Target role to check against
 * @param int|null $targetCompanyId Target company ID
 * @param int|null $targetBranchId Target branch ID
 * @return bool
 */
function canAccessData($targetRole = null, $targetCompanyId = null, $targetBranchId = null) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $currentRole = $_SESSION['role'];
    $currentCompanyId = $_SESSION['company_id'];
    $currentBranchId = $_SESSION['branch_id'];
    
    switch ($currentRole) {
        case 'super_admin':
            // Super admin can access everything
            return true;
            
        case 'company_admin':
            // Company admin can access their company data
            if ($targetCompanyId && $targetCompanyId != $currentCompanyId) {
                return false;
            }
            // Can manage branch managers and technicians within their company
            return in_array($targetRole, ['company_admin', 'branch_manager', 'technician']) || is_null($targetRole);
            
        case 'branch_manager':
            // Branch manager can only access their branch data
            if ($targetCompanyId && $targetCompanyId != $currentCompanyId) {
                return false;
            }
            if ($targetBranchId && $targetBranchId != $currentBranchId) {
                return false;
            }
            // Can manage technicians within their branch
            return in_array($targetRole, ['branch_manager', 'technician']) || is_null($targetRole);
            
        case 'technician':
            // Technician can only access their own data
            return $targetRole == 'technician' || is_null($targetRole);
            
        default:
            return false;
    }
}

/**
 * Check if user can manage (add/edit/delete) specific role
 * @param string $targetRole Target role to manage
 * @return bool
 */
function canManageRole($targetRole) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $currentRole = $_SESSION['role'];
    
    $hierarchy = [
        'super_admin' => ['super_admin', 'company_admin', 'branch_manager', 'technician'],
        'company_admin' => ['branch_manager', 'technician'],
        'branch_manager' => ['technician'],
        'technician' => []
    ];
    
    return isset($hierarchy[$currentRole]) && in_array($targetRole, $hierarchy[$currentRole]);
}

/**
 * Get allowed roles for current user to create/manage
 * @return array
 */
function getAllowedRoles() {
    if (!isLoggedIn()) {
        return [];
    }
    
    $currentRole = $_SESSION['role'];
    
    switch ($currentRole) {
        case 'super_admin':
            return [
                'super_admin' => 'Süper Admin',
                'company_admin' => 'Şirket Admin',
                'branch_manager' => 'Şube Müdürü',
                'technician' => 'Teknisyen'
            ];
            
        case 'company_admin':
            return [
                'branch_manager' => 'Şube Müdürü',
                'technician' => 'Teknisyen'
            ];
            
        case 'branch_manager':
            return [
                'technician' => 'Teknisyen'
            ];
            
        case 'technician':
        default:
            return [];
    }
}

/**
 * Check if current user can view system-wide analytics
 * @return bool
 */
function canViewSystemAnalytics() {
    return hasPermission(['super_admin']);
}

/**
 * Check if current user can manage companies
 * @return bool
 */
function canManageCompanies() {
    return hasPermission(['super_admin']);
}

/**
 * Check if current user can manage branches
 * @return bool
 */
function canManageBranches() {
    return hasPermission(['super_admin', 'company_admin']);
}

/**
 * Check if current user can manage personnel
 * @return bool
 */
function canManagePersonnel() {
    return hasPermission(['super_admin', 'company_admin', 'branch_manager']);
}

/**
 * Get role display name
 * @param string $role
 * @return string
 */
function getRoleDisplayName($role) {
    $roleNames = [
        'super_admin' => 'Süper Admin',
        'company_admin' => 'Şirket Admin',
        'branch_manager' => 'Şube Müdürü',
        'technician' => 'Teknisyen'
    ];
    
    return $roleNames[$role] ?? ucfirst($role);
}
?>
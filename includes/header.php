<?php
/**
 * Common header template for all pages
 * Equivalent to Flask base.html functionality
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/functions.php';

// Require login for all pages except login
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
if ($currentPage !== 'login') {
    requireLogin();
}

// Get current user
$currentUser = getCurrentUser();
$flash = getFlashMessage();

// Get system settings for dynamic content
$systemSettings = getSystemSettings();

// Get page title if not set
if (!isset($pageTitle)) {
    $pageTitle = $systemSettings['system_name'] ?? 'Serviso';
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) : e($systemSettings['system_name'] ?? 'Serviso') ?></title>
    
    <!-- Inter Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Font Awesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!-- Bootstrap 5 CSS (for utilities only) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Modern Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <!-- Additional CSS for specific pages -->
    <?php if (isset($additionalCSS) && !empty($additionalCSS)): ?>
        <?= $additionalCSS ?>
    <?php endif; ?>
</head>
<body>
    <!-- Alert container for JavaScript alerts -->
    <div id="alert-container" style="position: fixed; top: 20px; right: 20px; z-index: 1060;"></div>
    
    <div class="app-container">
        <!-- Modern Sidebar -->
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <a href="dashboard.php" class="sidebar-logo">
                    <?php if (!empty($systemSettings['company_logo'])): ?>
                        <img src="<?= e($systemSettings['company_logo']) ?>" alt="Logo" style="width: 24px; height: 24px; object-fit: contain;">
                    <?php else: ?>
                        <i class="fas fa-tools"></i>
                    <?php endif; ?>
                    <span><?= e($systemSettings['system_name'] ?? 'Serviso') ?></span>
                </a>
            </div>
            
            <!-- Navigation Menu -->
            <div class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Ana Menü</div>
                    
                    <!-- Dashboard - Not for technicians -->
                    <?php if ($currentUser && $currentUser['role'] != 'technician'): ?>
                    <div class="nav-item">
                        <a class="nav-link <?= isCurrentPage('dashboard') ? 'active' : '' ?>" href="dashboard.php">
                            <i class="fas fa-home"></i>
                            <span>Ana Sayfa</span>
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Services -->
                    <div class="nav-item">
                        <a class="nav-link <?= isCurrentPage(['services', 'service_add', 'service_edit']) ? 'active' : '' ?>" href="services.php">
                            <i class="fas fa-wrench"></i>
                            <span>Servisler</span>
                        </a>
                    </div>
                    
                    <!-- Customers -->
                    <div class="nav-item">
                        <a class="nav-link <?= isCurrentPage(['customers', 'customer_add', 'customer_edit', 'customer_services']) ? 'active' : '' ?>" href="customers.php">
                            <i class="fas fa-users"></i>
                            <span>Müşteriler</span>
                        </a>
                    </div>
                    
                    <!-- Definitions (not for technicians) -->
                    <?php if ($currentUser && $currentUser['role'] != 'technician'): ?>
                    <div class="nav-item">
                        <a class="nav-link <?= isCurrentPage('definitions') ? 'active' : '' ?>" href="definitions.php">
                            <i class="fas fa-list"></i>
                            <span>Tanımlamalar</span>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Management Section -->
                <?php if ($currentUser && in_array($currentUser['role'], ['super_admin', 'company_admin'])): ?>
                <div class="nav-section">
                    <div class="nav-section-title">Yönetim</div>
                    
                    <?php if ($currentUser['role'] == 'super_admin'): ?>
                    <div class="nav-item">
                        <a class="nav-link <?= isCurrentPage('companies') ? 'active' : '' ?>" href="companies.php">
                            <i class="fas fa-building"></i>
                            <span>Şirketler</span>
                        </a>
                    </div>
                    
                    <div class="nav-item">
                        <a class="nav-link <?= isCurrentPage('settings') ? 'active' : '' ?>" href="settings.php">
                            <i class="fas fa-cogs"></i>
                            <span>Sistem Ayarları</span>
                        </a>
                    </div>
                    
                    <div class="nav-item">
                        <a class="nav-link <?= isCurrentPage('pages') ? 'active' : '' ?>" href="pages.php">
                            <i class="fas fa-file-alt"></i>
                            <span>Sayfalar</span>
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($currentUser['role'] === 'company_admin'): ?>
                    <div class="nav-item">
                        <a class="nav-link <?= isCurrentPage('company_settings') ? 'active' : '' ?>" href="company_settings.php">
                            <i class="fas fa-building-user"></i>
                            <span>Şirket Ayarları</span>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <!-- User Section -->
                <div class="nav-section">
                    <div class="nav-section-title">Hesap</div>
                    
                    <div class="nav-item">
                        <a class="nav-link <?= isCurrentPage('profile') ? 'active' : '' ?>" href="profile.php">
                            <i class="fas fa-user"></i>
                            <span>Profil</span>
                        </a>
                    </div>
                    
                    <div class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Çıkış</span>
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar User Info -->
            <div class="sidebar-user">
                <div class="d-flex align-items-center">
                    <div class="user-avatar">
                        <?= strtoupper(substr($currentUser['name'] ?? 'U', 0, 1)) ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?= e($currentUser['name'] ?? 'Kullanıcı') ?></div>
                        <div class="user-role">
                            <?php
                            $roleNames = [
                                'super_admin' => 'Süper Admin',
                                'company_admin' => 'Şirket Admin',
                                'branch_manager' => 'Şube Müdürü',
                                'technician' => 'Teknisyen'
                            ];
                            echo $roleNames[$currentUser['role']] ?? $currentUser['role'];
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </nav>
        
        <!-- Sidebar Overlay -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        
        <!-- Modern Header -->
        <header class="main-header">
            <div class="header-left">
                <button class="sidebar-toggle" id="sidebarToggle" type="button" aria-label="Menü">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <h1 class="page-title"><?= isset($pageTitle) ? str_replace(' - ' . ($systemSettings['system_name'] ?? 'Serviso'), '', e($pageTitle)) : 'Dashboard' ?></h1>
                </div>
            </div>
            
            <div class="header-right">
                <a href="profile.php" class="header-user">
                    <div class="user-avatar">
                        <?= strtoupper(substr($currentUser['name'] ?? 'U', 0, 1)) ?>
                    </div>
                    <div class="user-details">
                        <div class="user-name"><?= e($currentUser['name'] ?? 'Kullanıcı') ?></div>
                        <div class="user-role"><?= e(ucfirst($currentUser['role'] ?? 'guest')) ?></div>
                    </div>
                </a>
                
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Çıkış</span>
                </a>
            </div>
        </header>

        <!-- Content Wrapper -->
        <div class="content-wrapper">
            <!-- Flash Messages -->
            <?php if ($flash): ?>
            <div class="alert-modern alert-<?= $flash['type'] == 'error' ? 'danger' : e($flash['type']) ?>">
                <i class="fas fa-<?= $flash['type'] == 'error' ? 'exclamation-triangle' : ($flash['type'] == 'success' ? 'check-circle' : 'info-circle') ?>"></i>
                <?= e($flash['message']) ?>
            </div>
            <?php endif; ?>
            
            <!-- Page Content -->
            <div class="page-content">
                <!-- Content will be included here -->
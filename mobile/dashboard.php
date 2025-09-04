<?php
require_once '../includes/header.php';
require_once '../includes/functions.php';

requireLogin();

// Only technicians can access mobile interface
if ($_SESSION['role'] !== 'technician') {
    header('Location: ../dashboard.php');
    exit;
}

$currentUser = $_SESSION;

// Get technician stats
$personnelId = fetchOne("SELECT id FROM personnel WHERE email = ?", [$currentUser['email']]);
$personnelId = $personnelId['id'] ?? null;

$stats = [
    'today_services' => 0,
    'active_services' => 0,
    'completed_services' => 0,
    'total_services' => 0
];

if ($personnelId) {
    // Today's services
    $todayResult = fetchOne("
        SELECT COUNT(*) as count FROM services 
        WHERE personnel_id = ? AND DATE(service_date) = CURRENT_DATE
    ", [$personnelId]);
    $stats['today_services'] = $todayResult['count'] ?? 0;
    
    // Active services (not completed)
    $activeResult = fetchOne("
        SELECT COUNT(*) as count FROM services 
        WHERE personnel_id = ? AND operation_status != 'Tamamlandı'
    ", [$personnelId]);
    $stats['active_services'] = $activeResult['count'] ?? 0;
    
    // Completed services
    $completedResult = fetchOne("
        SELECT COUNT(*) as count FROM services 
        WHERE personnel_id = ? AND operation_status = 'Tamamlandı'
    ", [$personnelId]);
    $stats['completed_services'] = $completedResult['count'] ?? 0;
    
    // Total services
    $totalResult = fetchOne("
        SELECT COUNT(*) as count FROM services WHERE personnel_id = ?
    ", [$personnelId]);
    $stats['total_services'] = $totalResult['count'] ?? 0;
}

$pageTitle = 'Teknisyen Paneli - Serviso';
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/mobile.css" rel="stylesheet">
</head>
<body class="mobile-interface">
    <!-- Mobile Header -->
    <div class="mobile-header">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center">
                <div class="header-title">
                    <h5 class="mb-0">
                        <i class="fas fa-tools me-2"></i>
                        Serviso
                    </h5>
                </div>
                <div class="header-actions">
                    <a href="../profile.php" class="btn btn-outline-light btn-sm">
                        <i class="fas fa-user"></i>
                    </a>
                    <a href="../logout.php" class="btn btn-outline-light btn-sm ms-2">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Content -->
    <div class="mobile-content">
        <div class="container-fluid">
            <!-- Welcome Section -->
            <div class="welcome-section mb-4">
                <h4>Merhaba, <?= e($currentUser['name']) ?></h4>
                <p class="text-muted"><?= date('d F Y, l') ?></p>
            </div>

            <!-- Quick Stats -->
            <div class="row mb-4">
                <div class="col-6 mb-3">
                    <div class="stat-card bg-primary text-white">
                        <div class="stat-number"><?= number_format($stats['today_services']) ?></div>
                        <div class="stat-label">Bugünkü Servisler</div>
                    </div>
                </div>
                <div class="col-6 mb-3">
                    <div class="stat-card bg-warning text-white">
                        <div class="stat-number"><?= number_format($stats['active_services']) ?></div>
                        <div class="stat-label">Aktif Servisler</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="stat-card bg-success text-white">
                        <div class="stat-number"><?= number_format($stats['completed_services']) ?></div>
                        <div class="stat-label">Tamamlanan</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="stat-card bg-info text-white">
                        <div class="stat-number"><?= number_format($stats['total_services']) ?></div>
                        <div class="stat-label">Toplam Servis</div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions mb-4">
                <h6 class="fw-bold mb-3">Hızlı İşlemler</h6>
                <div class="row">
                    <div class="col-4">
                        <a href="services.php" class="action-btn">
                            <i class="fas fa-list"></i>
                            <span>Servisler</span>
                        </a>
                    </div>
                    <div class="col-4">
                        <a href="customers.php" class="action-btn">
                            <i class="fas fa-users"></i>
                            <span>Müşteriler</span>
                        </a>
                    </div>
                    <div class="col-4">
                        <a href="scanner.php" class="action-btn">
                            <i class="fas fa-qrcode"></i>
                            <span>QR Tarama</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Recent Services -->
            <?php if ($personnelId): ?>
            <?php
            $recentServices = fetchAll("
                SELECT s.*, c.name as customer_name, c.phone, c.city, c.district
                FROM services s
                LEFT JOIN customers c ON s.customer_id = c.id
                WHERE s.personnel_id = ?
                ORDER BY s.service_date DESC, s.created_at DESC
                LIMIT 5
            ", [$personnelId]);
            ?>
            
            <?php if (!empty($recentServices)): ?>
            <div class="recent-services">
                <h6 class="fw-bold mb-3">Son Servisler</h6>
                <div class="service-list">
                    <?php foreach ($recentServices as $service): ?>
                    <div class="service-item" onclick="location.href='service_detail.php?id=<?= $service['id'] ?>'">
                        <div class="service-info">
                            <div class="service-customer fw-bold"><?= e($service['customer_name']) ?></div>
                            <div class="service-details">
                                <small class="text-muted"><?= e($service['device']) ?> • <?= e($service['complaint']) ?></small>
                            </div>
                            <div class="service-meta">
                                <span class="service-date"><?= formatDate($service['service_date']) ?></span>
                                <span class="service-status badge bg-<?= 
                                    $service['operation_status'] == 'Tamamlandı' ? 'success' : 
                                    ($service['operation_status'] == 'Devam Ediyor' ? 'info' : 'warning')
                                ?>"><?= e($service['operation_status']) ?></span>
                            </div>
                        </div>
                        <div class="service-actions">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="text-center mt-3">
                    <a href="services.php" class="btn btn-outline-primary">Tüm Servisleri Gör</a>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Mobile Navigation -->
    <div class="mobile-nav">
        <div class="container-fluid">
            <div class="nav-items">
                <a href="dashboard.php" class="nav-item active">
                    <i class="fas fa-home"></i>
                    <span>Ana Sayfa</span>
                </a>
                <a href="services.php" class="nav-item">
                    <i class="fas fa-wrench"></i>
                    <span>Servisler</span>
                </a>
                <a href="customers.php" class="nav-item">
                    <i class="fas fa-users"></i>
                    <span>Müşteriler</span>
                </a>
                <a href="scanner.php" class="nav-item">
                    <i class="fas fa-qrcode"></i>
                    <span>QR Tarama</span>
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/mobile.js"></script>
</body>
</html>
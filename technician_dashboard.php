<?php
/**
 * Technician Dashboard - Simple view for field technicians
 */

session_start();

// Check if user is logged in and is a technician
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'personnel') {
    header('Location: personnel_login.php');
    exit;
}

// Database connection
try {
    $host = $_ENV['PGHOST'] ?? 'localhost';
    $port = $_ENV['PGPORT'] ?? '5432';
    $dbname = $_ENV['PGDATABASE'] ?? 'main';
    $username = $_ENV['PGUSER'] ?? 'replit';
    $password = $_ENV['PGPASSWORD'] ?? '';

    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed");
}

$pageTitle = 'Teknisyen Paneli - Serviso';
require_once 'includes/functions.php';

// Get technician's assigned services
$services = [];
try {
    $sql = "SELECT s.*, c.name as customer_name, c.phone as customer_phone,
                   co.name as complaint_name, d.name as device_name, 
                   b.name as brand_name, m.name as model_name,
                   o.name as operation_name
            FROM services s
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN complaints co ON s.complaint_id = co.id
            LEFT JOIN devices d ON s.device_id = d.id
            LEFT JOIN brands b ON s.brand_id = b.id
            LEFT JOIN models m ON s.model_id = m.id
            LEFT JOIN operations o ON s.operation_id = o.id
            WHERE s.technician_id = ? 
            AND s.service_date >= CURRENT_DATE - INTERVAL '30 days'
            ORDER BY s.service_date DESC, s.id DESC
            LIMIT 50";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_SESSION['user_id']]);
    $services = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Services query error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar {
            background: linear-gradient(45deg, #667eea, #764ba2) !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .service-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s;
            margin-bottom: 1rem;
        }
        .service-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        .service-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-completed { background-color: #d4edda; color: #155724; }
        .status-cancelled { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="technician_dashboard.php">
                <i class="fas fa-tools me-2"></i>
                <strong>Serviso</strong>
                <small class="ms-2">Teknisyen Paneli</small>
            </a>
            
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user me-1"></i>
                        <?= e($_SESSION['user_name']) ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><h6 class="dropdown-header">
                            <i class="fas fa-building me-1"></i>
                            <?= e($_SESSION['branch_name'] ?? 'Genel') ?>
                        </h6></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php">
                            <i class="fas fa-sign-out-alt me-1"></i>
                            Çıkış Yap
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Main Content -->
    <div class="container mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <h2>
                    <i class="fas fa-clipboard-list text-primary me-2"></i>
                    Atanan Servisler
                </h2>
                <p class="text-muted mb-0">Size atanan servis talepleri ve durumları</p>
            </div>
        </div>
        
        <!-- Services List -->
        <div class="row">
            <?php if (!empty($services)): ?>
                <?php foreach ($services as $service): ?>
                <div class="col-lg-6 col-xl-4">
                    <div class="card service-card">
                        <div class="card-body">
                            <!-- Service Header -->
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-hashtag text-muted me-1"></i>
                                    <?= e($service['id']) ?>
                                </h6>
                                <span class="service-status status-<?= $service['status'] === 'completed' ? 'completed' : ($service['status'] === 'cancelled' ? 'cancelled' : 'pending') ?>">
                                    <?= $service['status'] === 'completed' ? 'Tamamlandı' : ($service['status'] === 'cancelled' ? 'İptal' : 'Bekliyor') ?>
                                </span>
                            </div>
                            
                            <!-- Customer Info -->
                            <div class="mb-3">
                                <h6 class="text-primary mb-1">
                                    <i class="fas fa-user me-1"></i>
                                    <?= e($service['customer_name']) ?>
                                </h6>
                                <?php if ($service['customer_phone']): ?>
                                <a href="tel:<?= e($service['customer_phone']) ?>" class="text-decoration-none text-success small">
                                    <i class="fas fa-phone me-1"></i>
                                    <?= e($service['customer_phone']) ?>
                                </a>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Service Details -->
                            <div class="mb-3">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <small class="text-muted d-block">Tarih</small>
                                        <small><strong><?= formatDate($service['service_date']) ?></strong></small>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Saat</small>
                                        <small><strong><?= e($service['service_time'] ?? '-') ?></strong></small>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Device Info -->
                            <div class="mb-3">
                                <small class="text-muted d-block">Cihaz</small>
                                <small>
                                    <strong>
                                        <?= e($service['device_name'] ?? 'Belirtilmemiş') ?>
                                        <?php if ($service['brand_name']): ?>
                                            - <?= e($service['brand_name']) ?>
                                        <?php endif; ?>
                                        <?php if ($service['model_name']): ?>
                                            <?= e($service['model_name']) ?>
                                        <?php endif; ?>
                                    </strong>
                                </small>
                            </div>
                            
                            <!-- Complaint -->
                            <?php if ($service['complaint_name']): ?>
                            <div class="mb-3">
                                <small class="text-muted d-block">Şikayet</small>
                                <small><strong><?= e($service['complaint_name']) ?></strong></small>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Address -->
                            <?php if ($service['address']): ?>
                            <div class="mb-3">
                                <small class="text-muted d-block">Adres</small>
                                <small><?= e($service['address']) ?></small>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Actions -->
                            <div class="d-flex gap-2">
                                <a href="service_detail.php?id=<?= $service['id'] ?>" class="btn btn-primary btn-sm flex-fill">
                                    <i class="fas fa-eye me-1"></i>
                                    Detay
                                </a>
                                <?php if ($service['customer_phone']): ?>
                                <a href="tel:<?= e($service['customer_phone']) ?>" class="btn btn-success btn-sm">
                                    <i class="fas fa-phone"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="text-center py-5">
                        <i class="fas fa-clipboard-list fa-4x text-muted mb-3"></i>
                        <h4 class="text-muted">Henüz Atanan Servis Yok</h4>
                        <p class="text-muted">Size atanan servis talepleri burada görünecek.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
// Start session first
session_start();

require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'includes/functions.php';

requireLogin();

$customerId = $_GET['customer_id'] ?? null;
if (!$customerId) {
    header('Location: customers.php?error=customer_required');
    exit;
}

// Build role-based customer query
$whereConditions = ['c.id = ?'];
$queryParams = [$customerId];

if ($_SESSION['role'] === 'company_admin') {
    $whereConditions[] = 'c.company_id = ?';
    $queryParams[] = $_SESSION['company_id'];
} elseif ($_SESSION['role'] === 'branch_manager') {
    // Branch managers can access all customers of their company
    $whereConditions[] = 'c.company_id = ?';
    $queryParams[] = $_SESSION['company_id'];
} elseif ($_SESSION['role'] === 'technician') {
    // Technicians can only see customers from their assigned services
    $whereConditions[] = 'EXISTS (
        SELECT 1 FROM services s2 
        INNER JOIN personnel p ON s2.personnel_id = p.id 
        WHERE s2.customer_id = c.id AND p.email = ?
    )';
    $queryParams[] = $_SESSION['email'];
}

$whereClause = implode(' AND ', $whereConditions);

// Get customer info with role-based access
$customer = fetchOne("
    SELECT c.*, co.name as company_name, b.name as branch_name
    FROM customers c
    LEFT JOIN companies co ON c.company_id = co.id
    LEFT JOIN branches b ON c.branch_id = b.id
    WHERE $whereClause
", $queryParams);

if (!$customer) {
    header('Location: customers.php?error=customer_not_found');
    exit;
}

// Page title for header
$pageTitle = "Müşteri Servisleri - " . e($customer['name']);
require_once 'includes/header.php';

// Get services for this customer with role-based filtering
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$whereConditions = ['s.customer_id = ?'];
$queryParams = [$customerId];

// Apply role-based filtering
if ($_SESSION['role'] === 'company_admin') {
    $whereConditions[] = 's.company_id = ?';
    $queryParams[] = $_SESSION['company_id'];
} elseif ($_SESSION['role'] === 'branch_manager') {
    $whereConditions[] = 's.company_id = ?';
    $whereConditions[] = 's.branch_id = ?';
    $queryParams[] = $_SESSION['company_id'];
    $queryParams[] = $_SESSION['branch_id'];
} elseif ($_SESSION['role'] === 'technician') {
    $whereConditions[] = 's.personnel_id = (SELECT id FROM personnel WHERE email = ? LIMIT 1)';
    $queryParams[] = $_SESSION['email'];
}



$whereClause = implode(' AND ', $whereConditions);

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM services s WHERE $whereClause";
$totalResult = fetchOne($countQuery, $queryParams);
$totalCount = $totalResult['total'] ?? 0;
$totalPages = ceil($totalCount / $perPage);

// Get services with brand names - some fields are IDs that need to be resolved
$query = "
    SELECT s.*, 
           p.name as personnel_name,
           COALESCE(b.name, s.brand) as brand_name
    FROM services s
    LEFT JOIN personnel p ON s.personnel_id = p.id
    LEFT JOIN brands b ON (
        CASE 
            WHEN s.brand ~ '^[0-9]+$' AND LENGTH(s.brand) < 10 
            THEN s.brand::integer = b.id 
            ELSE FALSE 
        END
    )
    WHERE $whereClause
    ORDER BY s.service_date DESC, s.created_at DESC
    LIMIT $perPage OFFSET $offset
";

$services = fetchAll($query, $queryParams);

$pageTitle = 'Müşteri Servisleri - ' . e($customer['name']) . ' - Serviso';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="page-header">
                    <h2 class="mb-0">
                        <i class="fas fa-user me-2"></i><?= e($customer['name']) ?>
                    </h2>
                </div>
            </div>
            <div class="col-lg-4 text-lg-end">
                <a href="customers.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Müşterilere Dön
                </a>
            </div>
        </div>

        <!-- Customer Info Card -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="row">
                                    <div class="col-sm-6">
                                        <strong>Telefon:</strong><br>
                                        <a href="tel:<?= e($customer['phone']) ?>" class="phone-link"><?= formatPhone($customer['phone']) ?></a>
                                    </div>
                                    <div class="col-sm-6">
                                        <strong>Adres:</strong><br>
                                        <a href="#" onclick="openGoogleMaps('<?= e($customer['address'] . ', ' . $customer['district'] . ', ' . $customer['city']) ?>')" class="text-decoration-none">
                                            <?= e($customer['address']) ?>
                                        </a>
                                        <br>
                                        <small class="text-muted"><?= e($customer['city']) ?>, <?= e($customer['district']) ?></small>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-sm-6">
                                        <strong>Kayıt Tarihi:</strong><br>
                                        <span class="text-muted"><?= formatDate($customer['created_at']) ?></span>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>



        <!-- Services List -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Servis Kayıtları</h5>
                        <small class="text-muted"><?= number_format($totalCount) ?> kayıt bulundu</small>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($services)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-tools fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">Henüz servis kaydı yok</h5>
                            <p class="text-muted">Bu müşteri için henüz servis kaydı bulunmuyor.</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Servis Tarihi</th>
                                        <th>Cihaz & Şikayet</th>
                                        <th>Teknisyen</th>
                                        <th>Durum</th>
                                        <th>Fiyat</th>
                                        <th width="120">İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($services as $service): ?>
                                    <tr>
                                        <td>
                                            <strong><?= formatDate($service['service_date']) ?></strong><br>
                                            <small class="text-muted"><?= formatDate($service['created_at']) ?></small>
                                        </td>
                                        <td>
                                            <div class="service-info">
                                                <strong><?= e($service['device']) ?></strong>
                                                <?php if ($service['brand_name']): ?>
                                                <span class="text-muted">- <?= e($service['brand_name']) ?></span>
                                                <?php endif; ?>
                                                <?php if ($service['model']): ?>
                                                <span class="text-muted"> <?= e($service['model']) ?></span>
                                                <?php endif; ?>
                                                <br>
                                                <small class="text-muted"><?= e($service['complaint']) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-muted"><?= e($service['personnel_name'] ?? 'Atanmamış') ?></span>
                                        </td>
                                        <td>
                                            <?php
                                            $statusClass = '';
                                            switch ($service['operation_status']) {
                                                case 'Tamamlandı':
                                                    $statusClass = 'success';
                                                    break;
                                                case 'Devam Ediyor':
                                                    $statusClass = 'warning';
                                                    break;
                                                case 'Beklemede':
                                                    $statusClass = 'info';
                                                    break;
                                                case 'İptal':
                                                    $statusClass = 'danger';
                                                    break;
                                                default:
                                                    $statusClass = 'secondary';
                                            }
                                            ?>
                                            <span class="badge bg-<?= $statusClass ?>"><?= e($service['operation_status']) ?></span>
                                        </td>
                                        <td>
                                            <strong><?= formatPrice($service['price']) ?></strong>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="service_view.php?id=<?= $service['id'] ?>" class="btn btn-outline-primary btn-sm" title="Görüntüle">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if ($_SESSION['role'] !== 'technician'): ?>
                                                <a href="service_print.php?id=<?= $service['id'] ?>" class="btn btn-outline-secondary btn-sm" title="Yazdır" target="_blank">
                                                    <i class="fas fa-print"></i>
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                        <div class="card-footer">
                            <nav aria-label="Sayfa navigasyonu">
                                <ul class="pagination pagination-sm justify-content-center mb-0">
                                    <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?customer_id=<?= $customerId ?>&page=<?= $page - 1 ?>">Önceki</a>
                                    </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?customer_id=<?= $customerId ?>&page=<?= $i ?>"><?= $i ?></a>
                                    </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?customer_id=<?= $customerId ?>&page=<?= $page + 1 ?>">Sonraki</a>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
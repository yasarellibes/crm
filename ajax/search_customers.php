<?php
session_start();
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get search parameters
$search = trim($_POST['search'] ?? '');
$page = max(1, intval($_POST['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

try {
    // Build query similar to customers.php
    $baseQuery = "
        SELECT c.id, c.name, c.phone, c.address, c.city, c.district, c.created_at,
               COUNT(s.id) as service_count,
               MAX(s.service_date) as last_service_date
        FROM customers c
        LEFT JOIN services s ON c.id = s.customer_id
        WHERE 1=1
    ";

    $params = [];

    // Apply role-based filtering
    if ($_SESSION['role'] === 'company_admin') {
        $baseQuery .= " AND c.company_id = ?";
        $params[] = $_SESSION['company_id'];
    } elseif ($_SESSION['role'] === 'branch_manager') {
        $baseQuery .= " AND c.branch_id = ?";
        $params[] = $_SESSION['branch_id'];
    } elseif ($_SESSION['role'] === 'technician') {
        // Technicians can only see customers they have serviced
        $baseQuery .= " AND c.id IN (
            SELECT DISTINCT s.customer_id 
            FROM services s 
            WHERE s.personnel_id = ?
        )";
        $params[] = $_SESSION['user_id'];
    }

    // Apply search filter
    if ($search) {
        $baseQuery .= " AND (c.name ILIKE ? OR c.phone LIKE ? OR c.city ILIKE ? OR c.district ILIKE ?)";
        $searchParam = '%' . $search . '%';
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
    }

    // Group by customer
    $baseQuery .= " GROUP BY c.id, c.name, c.phone, c.address, c.city, c.district, c.created_at";

    // Get total count for pagination
    $countQuery = "SELECT COUNT(DISTINCT c.id) FROM customers c WHERE 1=1";
    $countParams = [];

    // Apply same filters for count
    if ($_SESSION['role'] === 'company_admin') {
        $countQuery .= " AND c.company_id = ?";
        $countParams[] = $_SESSION['company_id'];
    } elseif ($_SESSION['role'] === 'branch_manager') {
        $countQuery .= " AND c.branch_id = ?";
        $countParams[] = $_SESSION['branch_id'];
    } elseif ($_SESSION['role'] === 'technician') {
        $countQuery .= " AND c.id IN (
            SELECT DISTINCT s.customer_id 
            FROM services s 
            WHERE s.personnel_id = ?
        )";
        $countParams[] = $_SESSION['user_id'];
    }

    if ($search) {
        $countQuery .= " AND (c.name ILIKE ? OR c.phone LIKE ? OR c.city ILIKE ? OR c.district ILIKE ?)";
        $countParams = array_merge($countParams, [$searchParam, $searchParam, $searchParam, $searchParam]);
    }

    // Execute count query
    $db = getDB();
    $countStmt = $db->prepare($countQuery);
    $countStmt->execute($countParams);
    $totalCount = $countStmt->fetchColumn();
    $totalPages = ceil($totalCount / $limit);

    // Add ordering and pagination
    $baseQuery .= " ORDER BY c.name ASC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    // Execute main query
    $stmt = $db->prepare($baseQuery);
    $stmt->execute($params);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Generate HTML for customers list
    ob_start();
    ?>
    <?php if (!empty($customers)): ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead class="table-light">
                    <tr>
                        <th>Müşteri Bilgileri</th>
                        <th class="d-none d-lg-table-cell">Adres</th>
                        <th class="d-none d-md-table-cell">Servis Sayısı</th>
                        <th class="d-none d-lg-table-cell">Kayıt Tarihi</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $customer): ?>
                    <tr>
                        <td>
                            <div class="customer-info">
                                <div class="fw-bold text-primary"><?= e($customer['name']) ?></div>
                                <div class="text-muted small">
                                    <?php if ($customer['phone']): ?>
                                    <a href="tel:<?= e($customer['phone']) ?>" class="phone-link">
                                        <?= formatPhone($customer['phone']) ?>
                                    </a>
                                    <?php else: ?>
                                    <span class="text-muted small">Telefon yok</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-muted small">
                                    <?php if ($customer['city'] || $customer['district']): ?>
                                        <?= e($customer['city']) ?><?= $customer['district'] ? ' / ' . e($customer['district']) : '' ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="d-none d-lg-table-cell">
                            <div class="text-wrap" style="max-width: 200px;">
                                <?php if ($customer['address']): ?>
                                    <small><?= e($customer['address']) ?></small>
                                <?php else: ?>
                                    <span class="text-muted small">Adres bilgisi yok</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="d-none d-md-table-cell">
                            <div class="text-center">
                                <?php if ($customer['service_count'] > 0): ?>
                                    <a href="customer_services.php?customer_id=<?= $customer['id'] ?>" class="text-decoration-none">
                                        <span class="badge bg-info"><?= number_format($customer['service_count']) ?> Servis</span>
                                    </a>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Henüz servis yok</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="d-none d-lg-table-cell">
                            <span class="text-muted small"><?= formatDate($customer['created_at']) ?></span>
                        </td>
                        <td>
                            <div class="action-buttons d-none d-md-flex">
                                <a href="customer_services.php?customer_id=<?= $customer['id'] ?>" class="btn btn-sm btn-outline-info" title="Servisleri">
                                    <i class="fas fa-tools"></i>
                                </a>
                                <a href="customer_edit.php?id=<?= $customer['id'] ?>" class="btn btn-sm btn-outline-primary" title="Düzenle">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="customer_services.php?customer_id=<?= $customer['id'] ?>" class="btn btn-sm btn-outline-success" title="Servisleri">
                                    <i class="fas fa-tools"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                        onclick="deleteCustomer(<?= $customer['id'] ?>)" title="Sil">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                            
                            <!-- Mobile Action Buttons -->
                            <div class="mobile-action-grid d-md-none">
                                <div class="mobile-action-row">
                                    <a href="customer_services.php?customer_id=<?= $customer['id'] ?>" class="mobile-action-btn mobile-action-view">
                                        <i class="fas fa-tools"></i>
                                        <span>Servisleri</span>
                                    </a>
                                    <a href="customer_edit.php?id=<?= $customer['id'] ?>" class="mobile-action-btn mobile-action-edit">
                                        <i class="fas fa-edit"></i>
                                        <span>Düzenle</span>
                                    </a>
                                </div>
                                <div class="mobile-action-row">
                                    <a href="customer_services.php?customer_id=<?= $customer['id'] ?>" class="mobile-action-btn mobile-action-print">
                                        <i class="fas fa-tools"></i>
                                        <span>Servisleri</span>
                                    </a>
                                    <button type="button" class="mobile-action-btn mobile-action-delete"
                                            onclick="deleteCustomer(<?= $customer['id'] ?>)">
                                        <i class="fas fa-trash"></i>
                                        <span>Sil</span>
                                    </button>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination-container mt-3">
            <div class="d-flex justify-content-between align-items-center">
                <small class="text-muted">
                    Toplam <?= number_format($totalCount) ?> kayıt, Sayfa <?= $page ?>/<?= $totalPages ?>
                </small>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link search-page-link" href="#" data-page="<?= $page - 1 ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php 
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);
                        for ($i = $start; $i <= $end; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link search-page-link" href="#" data-page="<?= $i ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link search-page-link" href="#" data-page="<?= $page + 1 ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="text-center py-5">
            <i class="fas fa-users fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">Müşteri bulunamadı</h5>
            <p class="text-muted">
                <?php if ($search): ?>
                    "<strong><?= e($search) ?></strong>" araması için müşteri bulunamadı.
                <?php else: ?>
                    Henüz sistemde müşteri kaydı bulunmuyor.
                <?php endif; ?>
            </p>
            <?php if ($_SESSION && $_SESSION['role'] != 'technician'): ?>
            <a href="customer_add.php" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i>
                İlk Müşteriyi Ekle
            </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php
    $html = ob_get_clean();

    // Return JSON response
    echo json_encode([
        'success' => true,
        'html' => $html,
        'totalCount' => $totalCount,
        'currentPage' => $page,
        'totalPages' => $totalPages
    ]);

} catch (Exception $e) {
    error_log("Customer search error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Arama sırasında hata oluştu.']);
}
?>
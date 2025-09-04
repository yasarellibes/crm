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
$statusFilter = trim($_POST['status'] ?? '');
$personnelFilter = trim($_POST['personnel'] ?? '');
$quickDate = trim($_POST['quick_date'] ?? '');
$page = max(1, intval($_POST['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

try {
    // Build query with improved search
    $baseQuery = "
        SELECT s.id, s.customer_id, s.device, s.brand, s.model,
               s.complaint, s.description, s.price, s.service_date, s.operation_status,
               s.personnel_id, s.branch_id, s.company_id, s.created_at,
               c.name as customer_name, c.phone as customer_phone, c.address as customer_address,
               c.city as customer_city, c.district as customer_district,
               p.name as personnel_name,
               b.name as branch_name,
               o.color as operation_color,
               COALESCE(brand_table.name, s.brand) as brand_name,
               COALESCE(model_table.name, s.model) as model_name
        FROM services s
        LEFT JOIN customers c ON s.customer_id = c.id
        LEFT JOIN personnel p ON s.personnel_id = p.id
        LEFT JOIN branches b ON s.branch_id = b.id
        LEFT JOIN operations o ON s.operation_status = o.name AND s.company_id = o.company_id
        LEFT JOIN brands brand_table ON (CASE WHEN s.brand ~ '^[0-9]+$' THEN CAST(s.brand AS INTEGER) ELSE NULL END) = brand_table.id AND s.company_id = brand_table.company_id
        LEFT JOIN models model_table ON (CASE WHEN s.model ~ '^[0-9]+$' THEN CAST(s.model AS INTEGER) ELSE NULL END) = model_table.id AND s.company_id = model_table.company_id
        WHERE 1=1
    ";

    $params = [];

    // Apply role-based filtering
    if ($_SESSION['role'] === 'technician') {
        // Technician sees only services assigned to them personally
        $baseQuery .= " AND s.personnel_id = ?";
        $params[] = $_SESSION['user_id'];
    } else {
        list($baseQuery, $params) = applyDataFilter($baseQuery, $params, 's');
    }

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

    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM (" . $baseQuery . ") as count_query";
    $totalCount = fetchOne($countQuery, $params)['total'];
    $totalPages = ceil($totalCount / $limit);

    // Get services with pagination
    $servicesQuery = $baseQuery . " ORDER BY s.service_date DESC, s.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $services = fetchAll($servicesQuery, $params);

    // Generate HTML for services table
    ob_start();
    ?>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead class="table-light">
                <tr>
                    <th>Müşteri</th>
                    <th>Cihaz/Marka/Model</th>
                    <th>Şikayet</th>
                    <th>Durum</th>
                    <th>Personel</th>
                    <th>Tarih</th>
                    <th>Fiyat</th>
                    <th>İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($services)): ?>
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">
                        <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                        Arama kriterlerinize uygun servis kaydı bulunamadı.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($services as $service): ?>
                <tr>
                    <td>
                        <div class="d-flex flex-column">
                            <strong><?= e($service['customer_name']) ?></strong>
                            <small class="text-muted">
                                <a href="tel:<?= e($service['customer_phone']) ?>" class="phone-link">
                                    <?= e($service['customer_phone']) ?>
                                </a>
                            </small>
                        </div>
                    </td>
                    <td>
                        <div class="d-flex flex-column">
                            <span><?= e($service['device']) ?></span>
                            <small class="text-muted">
                                <?= e($service['brand_name'] ?? $service['brand'] ?? 'Belirtilmemiş') ?> - 
                                <?= e($service['model_name'] ?? $service['model'] ?? 'Belirtilmemiş') ?>
                            </small>
                        </div>
                    </td>
                    <td>
                        <span class="text-truncate d-inline-block" style="max-width: 150px;" title="<?= e($service['complaint']) ?>">
                            <?= e($service['complaint']) ?>
                        </span>
                    </td>
                    <td>
                        <?php 
                        $backgroundColor = $service['operation_color'] ?? '#6c757d';
                        ?>
                        <span class="badge" style="background-color: <?= e($backgroundColor) ?>; color: white;"><?= e($service['operation_status']) ?></span>
                    </td>
                    <td>
                        <?= $service['personnel_name'] ? e($service['personnel_name']) : '<span class="text-muted">Atanmamış</span>' ?>
                    </td>
                    <td>
                        <span><?= date('d.m.Y', strtotime($service['service_date'])) ?></span>
                        <small class="text-muted d-block"><?= date('H:i', strtotime($service['created_at'])) ?></small>
                    </td>
                    <td>
                        <?php if ($service['price']): ?>
                        <span class="text-success"><?= number_format($service['price'], 2) ?> ₺</span>
                        <?php else: ?>
                        <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <!-- Desktop View -->
                        <div class="btn-group btn-group-sm d-none d-md-flex">
                            <a href="service_view.php?id=<?= $service['id'] ?>" class="btn btn-outline-primary btn-sm" title="Görüntüle">
                                <i class="fas fa-eye"></i>
                            </a>
                            <?php if ($_SESSION && $_SESSION['role'] != 'technician'): ?>
                            <a href="service_edit.php?id=<?= $service['id'] ?>" class="btn btn-outline-warning btn-sm" title="Düzenle">
                                <i class="fas fa-edit"></i>
                            </a>
                            <?php endif; ?>
                            <a href="service_print.php?id=<?= $service['id'] ?>" target="_blank" class="btn btn-outline-info btn-sm" title="Yazdır">
                                <i class="fas fa-print"></i>
                            </a>
                        </div>
                        
                        <!-- Mobile View - 2x2 Grid -->
                        <div class="mobile-action-grid d-md-none">
                            <div class="mobile-action-row">
                                <!-- Görüntüle -->
                                <a href="service_view.php?id=<?= $service['id'] ?>" 
                                   class="mobile-action-btn mobile-action-view" title="Görüntüle">
                                    <i class="fas fa-eye"></i>
                                    <span>Görüntüle</span>
                                </a>
                                
                                <!-- Düzenle -->
                                <?php if ($_SESSION && $_SESSION['role'] != 'technician'): ?>
                                <a href="service_edit.php?id=<?= $service['id'] ?>" 
                                   class="mobile-action-btn mobile-action-edit" title="Düzenle">
                                    <i class="fas fa-edit"></i>
                                    <span>Düzenle</span>
                                </a>
                                <?php else: ?>
                                <div class="mobile-action-btn mobile-action-disabled">
                                    <i class="fas fa-lock"></i>
                                    <span>-</span>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mobile-action-row">
                                <!-- Yazdır -->
                                <a href="service_print.php?id=<?= $service['id'] ?>" 
                                   target="_blank" class="mobile-action-btn mobile-action-print" title="Yazdır">
                                    <i class="fas fa-print"></i>
                                    <span>Yazdır</span>
                                </a>
                                
                                <!-- Sil (sadece yetkili roller için) -->
                                <?php if ($_SESSION && in_array($_SESSION['role'], ['super_admin', 'company_admin', 'branch_manager'])): ?>
                                <button type="button" class="mobile-action-btn mobile-action-delete" 
                                        title="Sil" onclick="deleteService(<?= $service['id'] ?>)">
                                    <i class="fas fa-trash"></i>
                                    <span>Sil</span>
                                </button>
                                <?php else: ?>
                                <div class="mobile-action-btn mobile-action-disabled">
                                    <i class="fas fa-lock"></i>
                                    <span>-</span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <nav aria-label="Sayfa navigasyonu">
        <ul class="pagination justify-content-center">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link search-page-link" href="#" data-page="<?= $page - 1 ?>">Önceki</a>
            </li>
            
            <?php
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            
            for ($i = $startPage; $i <= $endPage; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link search-page-link" href="#" data-page="<?= $i ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
            
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link search-page-link" href="#" data-page="<?= $page + 1 ?>">Sonraki</a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>
    <?php
    $html = ob_get_clean();

    // Get operations for status filter update
    $statusQuery = "SELECT DISTINCT name, color FROM operations WHERE company_id = ? AND branch_id IS NULL ORDER BY name";
    $statusParams = [];

    if ($_SESSION['role'] === 'super_admin') {
        $statusQuery = "SELECT DISTINCT name, color FROM operations ORDER BY name";
    } elseif ($_SESSION['role'] === 'company_admin') {
        $statusParams = [$_SESSION['company_id']];
    } elseif ($_SESSION['role'] === 'branch_manager') {
        $statusParams = [$_SESSION['company_id']];
    } elseif ($_SESSION['role'] === 'technician') {
        $statusParams = [$_SESSION['company_id']];
    }

    $operations = fetchAll($statusQuery, $statusParams);
    
    // Return JSON response
    echo json_encode([
        'success' => true,
        'html' => $html,
        'total' => $totalCount,
        'current_page' => $page,
        'total_pages' => $totalPages,
        'operations' => $operations
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Arama işlemi sırasında bir hata oluştu: ' . $e->getMessage()]);
}
?>
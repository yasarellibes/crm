<?php
/**
 * Services Page - List all services
 */

$pageTitle = 'Servisler';
require_once 'includes/header.php';

// Get page parameters
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Search and filter parameters with debug logging
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$personnelFilter = trim($_GET['personnel'] ?? '');
$quickDate = trim($_GET['quick_date'] ?? '');
$startDate = trim($_GET['start_date'] ?? '');
$endDate = trim($_GET['end_date'] ?? '');

// Debug: Log filter parameters (temporarily)
if (!empty($search) || !empty($statusFilter) || !empty($personnelFilter) || !empty($quickDate) || !empty($startDate) || !empty($endDate)) {
    error_log("Services page filters - search: '{$search}', status: '{$statusFilter}', personnel: '{$personnelFilter}', quickDate: '{$quickDate}', startDate: '{$startDate}', endDate: '{$endDate}'");
}

// Build query
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
} elseif ($startDate && $endDate) {
    $baseQuery .= " AND DATE(s.service_date) BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
} elseif ($startDate) {
    $baseQuery .= " AND DATE(s.service_date) >= ?";
    $params[] = $startDate;
} elseif ($endDate) {
    $baseQuery .= " AND DATE(s.service_date) <= ?";
    $params[] = $endDate;
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

// Get available statuses from operations table (dynamic based on company)
$statusQuery = "SELECT DISTINCT name FROM operations WHERE company_id = ? AND branch_id IS NULL ORDER BY name";
$statusParams = [];

// Apply role-based filtering for operations
if ($_SESSION['role'] === 'super_admin') {
    // Super admin sees all statuses
    $statusQuery = "SELECT DISTINCT name FROM operations ORDER BY name";
} elseif ($_SESSION['role'] === 'company_admin') {
    $statusParams = [$_SESSION['company_id']];
} elseif ($_SESSION['role'] === 'branch_manager') {
    $statusParams = [$_SESSION['company_id']];
} elseif ($_SESSION['role'] === 'technician') {
    $statusParams = [$_SESSION['company_id']];
}

$availableStatuses = fetchAll($statusQuery, $statusParams);
$personnel = getFilteredPersonnel();

// Handle Excel export
if (isset($_GET['export']) && $_GET['export'] === 'xlsx') {
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
    exit;
}
?>


<!-- Success Messages -->
<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="fas fa-check-circle me-2"></i>
    <?php 
    switch($_GET['success']) {
        case 'service_updated':
            echo 'Servis kaydı başarıyla güncellendi.';
            break;
        case 'service_and_customer_updated':
            echo 'Servis kaydı ve müşteri bilgileri başarıyla güncellendi.';
            break;
        case 'service_added':
            echo 'Yeni servis kaydı başarıyla eklendi.';
            break;
        case 'service_deleted':
            echo 'Servis kaydı başarıyla silindi.';
            break;
        default:
            echo 'İşlem başarıyla tamamlandı.';
    }
    ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($_SESSION && $_SESSION['role'] != 'technician'): ?>
<div class="mb-4 text-end">
    <a href="service_add.php" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i>
        Yeni Servis Ekle
    </a>
</div>
<?php endif; ?>

<!-- Modern Search and Filters -->
<div class="services-filter-section">
    <div class="services-filter-card">
        <form id="filterForm" class="services-filter-form">
            <!-- Hidden inputs for quick date filtering -->
            <input type="hidden" name="quick_date" id="quickDateInput" value="<?= e($quickDate) ?>">
            
            <!-- Desktop Layout -->
            <div class="desktop-filters">
                <div class="filter-row-1">
                    <div class="search-field">
                        <div class="search-input-group">
                            <span class="search-icon"><i class="fas fa-search"></i></span>
                            <input type="text" class="search-input" name="search" id="searchInput"
                                   value="<?= e($search) ?>" placeholder="Müşteri, telefon, cihaz, marka, model ile arama yapın...">
                        </div>
                    </div>
                    
                    <div class="status-field">
                        <select class="filter-select" name="status" id="statusFilter">
                            <option value="">Tüm Durumlar</option>
                            <?php foreach ($availableStatuses as $status): ?>
                            <option value="<?= e($status['name']) ?>" <?= $status['name'] === $statusFilter ? 'selected' : '' ?>>
                                <?= e($status['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <?php if ($_SESSION && $_SESSION['role'] != 'technician'): ?>
                    <div class="personnel-field">
                        <select class="filter-select" name="personnel" id="personnelFilter">
                            <option value="">Tüm Personel</option>
                            <?php foreach ($personnel as $person): ?>
                            <option value="<?= e($person['id']) ?>" <?= $person['id'] == $personnelFilter ? 'selected' : '' ?>>
                                <?= e($person['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="action-buttons">
                        <button type="button" class="filter-btn filter-btn-light" onclick="clearFilters()">
                            <i class="fas fa-refresh"></i>
                            Sıfırla
                        </button>
                        <button type="button" class="filter-btn filter-btn-success" onclick="exportToExcel()">
                            <i class="fas fa-file-excel"></i>
                            Excel
                        </button>
                    </div>
                </div>
                
                <div class="filter-row-2">
                    <div class="date-buttons">
                        <button type="button" class="date-btn <?= $quickDate === 'yesterday' ? 'date-btn-active' : '' ?>" 
                                data-date="yesterday">
                            <i class="fas fa-calendar"></i>
                            Dün
                        </button>
                        <button type="button" class="date-btn <?= $quickDate === 'today' ? 'date-btn-active date-btn-primary' : '' ?>" 
                                data-date="today">
                            <i class="fas fa-calendar-day"></i>
                            Bugün
                        </button>
                        <button type="button" class="date-btn <?= $quickDate === 'tomorrow' ? 'date-btn-active date-btn-success' : '' ?>" 
                                data-date="tomorrow">
                            <i class="fas fa-calendar-plus"></i>
                            Yarın
                        </button>
                        <button type="button" class="date-btn date-btn-range" onclick="toggleDateRange()">
                            <i class="fas fa-calendar-range"></i>
                            Tarih Aralığı
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Mobile Layout - Simple Bootstrap -->
            <div class="mobile-filters">
                <div class="row mb-2">
                    <div class="col-12">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" name="search" id="searchInputMobile"
                                   value="<?= e($search) ?>" placeholder="Müşteri ara...">
                        </div>
                    </div>
                </div>
                
                <div class="row mb-2">
                    <div class="col-6">
                        <select class="form-select" name="status" id="statusFilterMobile">
                            <option value="">Tüm Durumlar</option>
                            <?php foreach ($availableStatuses as $status): ?>
                            <option value="<?= e($status['name']) ?>" <?= $status['name'] === $statusFilter ? 'selected' : '' ?>>
                                <?= e($status['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <?php if ($_SESSION && $_SESSION['role'] != 'technician'): ?>
                    <div class="col-6">
                        <select class="form-select" name="personnel" id="personnelFilterMobile">
                            <option value="">Tüm Personel</option>
                            <?php foreach ($personnel as $person): ?>
                            <option value="<?= e($person['id']) ?>" <?= $person['id'] == $personnelFilter ? 'selected' : '' ?>>
                                <?= e($person['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="row mb-2">
                    <div class="col-12">
                        <div class="d-flex gap-1 justify-content-between">
                            <button type="button" class="btn <?= $quickDate === 'yesterday' ? 'btn-secondary' : 'btn-outline-secondary' ?> mobile-quick-btn flex-fill" 
                                    data-date="yesterday">
                                <i class="fas fa-calendar"></i> Dün
                            </button>
                            <button type="button" class="btn <?= $quickDate === 'today' ? 'btn-primary' : 'btn-outline-primary' ?> mobile-quick-btn flex-fill" 
                                    data-date="today">
                                <i class="fas fa-calendar-day"></i> Bugün
                            </button>
                            <button type="button" class="btn <?= $quickDate === 'tomorrow' ? 'btn-success' : 'btn-outline-success' ?> mobile-quick-btn flex-fill" 
                                    data-date="tomorrow">
                                <i class="fas fa-calendar-plus"></i> Yarın
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-12">
                        <div class="d-flex gap-1 justify-content-between">
                            <button type="button" class="btn btn-info flex-fill" onclick="toggleDateRange()">
                                <i class="fas fa-calendar-range"></i> Tarih
                            </button>
                            <button type="button" class="btn btn-light flex-fill" onclick="clearFilters()">
                                <i class="fas fa-refresh"></i> Sıfırla
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Date Range Section (Hidden by default) -->
<div id="dateRangeSection" class="row mb-4" style="display: none;">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h6 class="card-title">
                    <i class="fas fa-calendar-range me-2"></i>
                    Tarih Aralığı Seçin
                </h6>
                <form method="GET" action="services.php" class="row g-3">
                    <!-- Keep existing filters -->
                    <input type="hidden" name="search" value="<?= e($search) ?>">
                    <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
                    <input type="hidden" name="personnel" value="<?= e($personnelFilter) ?>">
                    
                    <div class="col-md-4">
                        <label class="form-label">Başlangıç Tarihi</label>
                        <input type="date" class="form-control" name="start_date" 
                               value="<?= e($startDate) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Bitiş Tarihi</label>
                        <input type="date" class="form-control" name="end_date" 
                               value="<?= e($endDate) ?>" required>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-filter me-1"></i>
                            Uygula
                        </button>
                        <button type="button" class="btn btn-outline-danger" onclick="clearDateRange()">
                            <i class="fas fa-times me-1"></i>
                            Temizle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Services List -->
<div class="row mb-4 mt-4">
    <div class="col-12">
        <div id="servicesContainer">
        <?php if ($services): ?>
        <div class="clean-table-container">
            <?php if (!empty($services)): ?>
            <div class="clean-table">
                <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Müşteri Adı</th>
                        <th>Telefon</th>
                        <th>Adres</th>
                        <th>Cihaz Bilgileri</th>
                        <th>Şube</th>
                        <th>Personel</th>
                        <th>Tarih</th>
                        <th>Durum</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $service): ?>
                    <tr>
                        <td class="customer-name-cell">
                            <strong>
                                <a href="customer_services.php?customer_id=<?= e($service['customer_id']) ?>" class="customer-link" 
                                   title="<?= e($service['customer_name'] ?: 'N/A') ?>">
                                    <?= e($service['customer_name'] ?: 'N/A') ?>
                                </a>
                            </strong>
                        </td>
                        <td>
                            <?php if ($service['customer_phone']): ?>
                            <a href="tel:<?= e($service['customer_phone']) ?>" class="phone-link">
                                <?= e($service['customer_phone']) ?>
                            </a>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="address-info">
                                <div class="text-primary fw-medium address-link" 
                                     onclick="openGoogleMaps('<?= e($service['customer_address']) ?>', '<?= e($service['customer_district']) ?>', '<?= e($service['customer_city']) ?>')"
                                     style="cursor: pointer; text-decoration: underline;">
                                    <?= e($service['customer_address']) ?>
                                </div>
                                <small class="text-muted"><?= e($service['customer_city']) ?>, <?= e($service['customer_district']) ?></small>
                            </div>
                        </td>
                        <td>
                            <strong><?= e($service['device']) ?></strong>
                            <div class="text-muted small">
                                <?= e($service['brand_name'] ?: $service['brand']) ?> - <?= e($service['model_name'] ?: $service['model']) ?>
                            </div>
                            <span class="badge bg-light text-dark"><?= e($service['complaint']) ?></span>
                        </td>
                        <td><?= e($service['branch_name'] ?: '-') ?></td>
                        <td><?= e($service['personnel_name'] ?: 'Atanmamış') ?></td>
                        <td>
                            <div><?= date('d.m.Y', strtotime($service['service_date'])) ?></div>
                            <small class="text-muted"><?= date('H:i', strtotime($service['service_date'])) ?></small>
                        </td>
                        <td>
                            <?php
                            $backgroundColor = $service['operation_color'] ?? '#6c757d';
                            ?>
                            <span class="badge" style="background-color: <?= e($backgroundColor) ?>; color: white;"><?= e($service['operation_status']) ?></span>
                        </td>
                        <td>
                            <!-- Desktop View -->
                            <div class="btn-group btn-group-sm d-none d-md-flex">
                                <!-- Görüntüle - Tüm roller için -->
                                <a href="service_view.php?id=<?= e($service['id']) ?>" 
                                   class="btn btn-outline-info" title="Görüntüle">
                                    <i class="fas fa-eye"></i>
                                </a>
                                
                                <!-- Düzenle - Tüm roller için -->
                                <a href="service_edit.php?id=<?= e($service['id']) ?>" 
                                   class="btn btn-outline-primary" title="Düzenle">
                                    <i class="fas fa-edit"></i>
                                </a>
                                
                                <!-- Sil - Sadece süper admin, şirket admin, şube müdürü için -->
                                <?php if ($_SESSION && in_array($_SESSION['role'], ['super_admin', 'company_admin', 'branch_manager'])): ?>
                                <button type="button" class="btn btn-outline-danger" 
                                        title="Sil" onclick="deleteService(<?= e($service['id']) ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                                
                                <!-- Yazdır - Tüm roller için -->
                                <a href="service_print.php?id=<?= e($service['id']) ?>" 
                                   target="_blank" class="btn btn-outline-success" title="Yazdır">
                                    <i class="fas fa-print"></i>
                                </a>
                            </div>
                            
                            <!-- Mobile View - 2x2 Grid -->
                            <div class="mobile-action-grid d-md-none">
                                <div class="mobile-action-row">
                                    <!-- Görüntüle -->
                                    <a href="service_view.php?id=<?= e($service['id']) ?>" 
                                       class="mobile-action-btn mobile-action-view" title="Görüntüle">
                                        <i class="fas fa-eye"></i>
                                        <span>Görüntüle</span>
                                    </a>
                                    
                                    <!-- Düzenle -->
                                    <a href="service_edit.php?id=<?= e($service['id']) ?>" 
                                       class="mobile-action-btn mobile-action-edit" title="Düzenle">
                                        <i class="fas fa-edit"></i>
                                        <span>Düzenle</span>
                                    </a>
                                </div>
                                
                                <div class="mobile-action-row">
                                    <!-- Yazdır -->
                                    <a href="service_print.php?id=<?= e($service['id']) ?>" 
                                       target="_blank" class="mobile-action-btn mobile-action-print" title="Yazdır">
                                        <i class="fas fa-print"></i>
                                        <span>Yazdır</span>
                                    </a>
                                    
                                    <!-- Sil (sadece yetkili roller için) -->
                                    <?php if ($_SESSION && in_array($_SESSION['role'], ['super_admin', 'company_admin', 'branch_manager'])): ?>
                                    <button type="button" class="mobile-action-btn mobile-action-delete" 
                                            title="Sil" onclick="deleteService(<?= e($service['id']) ?>)">
                                        <i class="fas fa-trash"></i>
                                        <span>Sil</span>
                                    </button>
                                    <?php else: ?>
                                    <!-- Boş alan (silme yetkisi olmayan kullanıcılar için) -->
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
                </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state text-center py-5">
                <i class="fas fa-wrench fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Henüz servis kaydı yok</h5>
                <p class="text-muted">İlk servis kaydını oluşturmak için "Yeni Servis Ekle" butonunu kullanın.</p>
                <?php if ($_SESSION && $_SESSION['role'] != 'technician'): ?>
                <a href="service_add.php" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>
                    Yeni Servis Ekle
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination-container mt-4">
            <nav aria-label="Sayfa navigasyonu">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php
                    $start = max(1, $page - 2);
                    $end = min($totalPages, $page + 2);
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                            <?= $i ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
            
            <div class="pagination-info text-center mt-2">
                <small class="text-muted">
                    Toplam <?= number_format($totalCount) ?> kayıt
                </small>
            </div>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        <div class="empty-state text-center py-5">
            <i class="fas fa-wrench fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">
                <?php if ($_SESSION['role'] === 'technician'): ?>
                    Size atanan servis bulunamadı
                <?php else: ?>
                    Servis kaydı bulunamadı
                <?php endif; ?>
            </h5>
            <p class="text-muted">
                <?php if ($search): ?>
                    Arama kriterlerinize uygun servis bulunamadı.
                <?php elseif ($_SESSION['role'] === 'technician'): ?>
                    Henüz size atanan herhangi bir servis bulunmuyor.
                <?php else: ?>
                    Henüz hiç servis kaydı bulunmuyor.
                <?php endif; ?>
            </p>
            <?php if ($_SESSION && $_SESSION['role'] != 'technician'): ?>
            <a href="service_add.php" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i>
                İlk Servisi Ekle
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        </div>
    </div>
</div>

<script>
// AJAX Search System
let searchTimeout;
let currentPage = 1;

function performSearch(page = 1) {
    currentPage = page;
    
    const formData = new FormData();
    
    // Get search value from both desktop and mobile inputs
    const searchInput = document.getElementById('searchInput');
    const searchInputMobile = document.getElementById('searchInputMobile');
    let searchValue = '';
    if (searchInput && searchInput.value) {
        searchValue = searchInput.value;
    } else if (searchInputMobile && searchInputMobile.value) {
        searchValue = searchInputMobile.value;
    }
    formData.append('search', searchValue);
    
    // Get status value from both desktop and mobile selects
    const statusFilter = document.getElementById('statusFilter');
    const statusFilterMobile = document.getElementById('statusFilterMobile');
    let statusValue = '';
    if (statusFilter && statusFilter.value) {
        statusValue = statusFilter.value;
    } else if (statusFilterMobile && statusFilterMobile.value) {
        statusValue = statusFilterMobile.value;
    }
    formData.append('status', statusValue);
    
    // Get personnel value from both desktop and mobile selects
    const personnelFilter = document.getElementById('personnelFilter');
    const personnelFilterMobile = document.getElementById('personnelFilterMobile');
    let personnelValue = '';
    if (personnelFilter && personnelFilter.value) {
        personnelValue = personnelFilter.value;
    } else if (personnelFilterMobile && personnelFilterMobile.value) {
        personnelValue = personnelFilterMobile.value;
    }
    if (personnelValue) {
        formData.append('personnel', personnelValue);
    }
    
    const quickDateInput = document.getElementById('quickDateInput');
    if (quickDateInput) {
        formData.append('quick_date', quickDateInput.value);
    }
    
    formData.append('page', page);

    // Show loading
    const container = document.getElementById('servicesContainer');
    container.innerHTML = '<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x"></i><br>Aranıyor...</div>';

    fetch('ajax/search_services.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            container.innerHTML = data.html;
            
            // Update status filter if operations data is available
            if (data.operations) {
                updateStatusFilter(data.operations);
            }
            
            // Add event listeners to pagination links
            document.querySelectorAll('.search-page-link').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const page = parseInt(this.dataset.page);
                    performSearch(page);
                });
            });
        } else {
            container.innerHTML = '<div class="alert alert-danger">Arama sırasında hata oluştu: ' + (data.error || 'Bilinmeyen hata') + '</div>';
        }
    })
    .catch(error => {
        console.error('Search error:', error);
        container.innerHTML = '<div class="alert alert-danger">Arama sırasında bir hata oluştu.</div>';
    });
}

function updateStatusFilter(operations) {
    const statusFilter = document.getElementById('statusFilter');
    const currentValue = statusFilter.value;
    
    // Clear existing options except "Tüm Durumlar"
    statusFilter.innerHTML = '<option value="">Tüm Durumlar</option>';
    
    // Add dynamic operations
    operations.forEach(operation => {
        const option = document.createElement('option');
        option.value = operation.name;
        option.textContent = operation.name;
        if (operation.name === currentValue) {
            option.selected = true;
        }
        statusFilter.appendChild(option);
    });
}

// Real-time search with debounce
function initializeAjaxSearch() {
    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    const personnelFilter = document.getElementById('personnelFilter');
    
    // Mobile inputs
    const searchInputMobile = document.getElementById('searchInputMobile');
    const statusFilterMobile = document.getElementById('statusFilterMobile');
    const personnelFilterMobile = document.getElementById('personnelFilterMobile');
    
    // Desktop search input with 300ms debounce
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                performSearch(1);
            }, 300);
        });
    }
    
    // Mobile search input with 300ms debounce
    if (searchInputMobile) {
        searchInputMobile.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                performSearch(1);
            }, 300);
        });
    }
    
    // Desktop filter changes
    if (statusFilter) {
        statusFilter.addEventListener('change', function() {
            performSearch(1);
        });
    }
    
    // Mobile filter changes
    if (statusFilterMobile) {
        statusFilterMobile.addEventListener('change', function() {
            performSearch(1);
        });
    }
    
    if (personnelFilter) {
        personnelFilter.addEventListener('change', function() {
            performSearch(1);
        });
    }
    
    if (personnelFilterMobile) {
        personnelFilterMobile.addEventListener('change', function() {
            performSearch(1);
        });
    }
    
    // Desktop date buttons
    document.querySelectorAll('.date-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            // Clear all active states from desktop buttons
            document.querySelectorAll('.date-btn').forEach(b => {
                b.classList.remove('date-btn-active', 'date-btn-primary', 'date-btn-success');
            });
            
            // Set active state for clicked button
            const dateType = this.dataset.date;
            this.classList.add('date-btn-active');
            if (dateType === 'today') {
                this.classList.add('date-btn-primary');
            } else if (dateType === 'tomorrow') {
                this.classList.add('date-btn-success');
            }
            
            // Set quick date value
            const quickDateInput = document.getElementById('quickDateInput');
            if (quickDateInput) {
                quickDateInput.value = this.dataset.date;
            }
            
            // Perform search
            performSearch(1);
        });
    });
    
    // Mobile date buttons  
    document.querySelectorAll('.mobile-quick-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            // Clear all active states from mobile buttons
            document.querySelectorAll('.mobile-quick-btn').forEach(b => {
                const dateType = b.dataset.date;
                b.classList.remove('btn-secondary', 'btn-primary', 'btn-success');
                if (dateType === 'yesterday') {
                    b.classList.add('btn-outline-secondary');
                } else if (dateType === 'today') {
                    b.classList.add('btn-outline-primary');
                } else if (dateType === 'tomorrow') {
                    b.classList.add('btn-outline-success');
                }
            });
            
            // Set active state for clicked button
            const dateType = this.dataset.date;
            if (dateType === 'yesterday') {
                this.classList.remove('btn-outline-secondary');
                this.classList.add('btn-secondary');
            } else if (dateType === 'today') {
                this.classList.remove('btn-outline-primary');
                this.classList.add('btn-primary');
            } else if (dateType === 'tomorrow') {
                this.classList.remove('btn-outline-success');
                this.classList.add('btn-success');
            }
            
            // Set quick date value
            const quickDateInput = document.getElementById('quickDateInput');
            if (quickDateInput) {
                quickDateInput.value = this.dataset.date;
            }
            
            // Perform search
            performSearch(1);
        });
    });
}

// Clear all filters
function clearFilters() {
    // Clear desktop inputs
    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    const personnelFilter = document.getElementById('personnelFilter');
    
    if (searchInput) searchInput.value = '';
    if (statusFilter) statusFilter.value = '';
    if (personnelFilter) personnelFilter.value = '';
    
    // Clear mobile inputs
    const searchInputMobile = document.getElementById('searchInputMobile');
    const statusFilterMobile = document.getElementById('statusFilterMobile');
    const personnelFilterMobile = document.getElementById('personnelFilterMobile');
    
    if (searchInputMobile) searchInputMobile.value = '';
    if (statusFilterMobile) statusFilterMobile.value = '';
    if (personnelFilterMobile) personnelFilterMobile.value = '';
    
    // Clear quick date
    const quickDateInput = document.getElementById('quickDateInput');
    if (quickDateInput) {
        quickDateInput.value = '';
    }
    
    // Clear active desktop date buttons
    document.querySelectorAll('.date-btn').forEach(btn => {
        const dateType = btn.dataset.date;
        btn.classList.remove('date-btn-active', 'date-btn-primary', 'date-btn-success');
    });
    
    // Clear active mobile date buttons
    document.querySelectorAll('.mobile-quick-btn').forEach(btn => {
        const dateType = btn.dataset.date;
        btn.classList.remove('btn-secondary', 'btn-primary', 'btn-success');
        if (dateType === 'yesterday') {
            btn.classList.add('btn-outline-secondary');
        } else if (dateType === 'today') {
            btn.classList.add('btn-outline-primary');
        } else if (dateType === 'tomorrow') {
            btn.classList.add('btn-outline-success');
        }
    });
    
    // Perform search with cleared filters
    performSearch(1);
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initializeAjaxSearch();
});

function exportToExcel() {
    // Create URL with current filter parameters
    const params = new URLSearchParams();
    params.append('export', 'xlsx');
    params.append('search', document.getElementById('searchInput').value);
    params.append('status', document.getElementById('statusFilter').value);
    
    const personnelFilter = document.getElementById('personnelFilter');
    if (personnelFilter) {
        params.append('personnel', personnelFilter.value);
    }
    
    const quickDateInput = document.getElementById('quickDateInput');
    if (quickDateInput) {
        params.append('quick_date', quickDateInput.value);
    }
    
    // Direct download approach - use current page URL
    const exportUrl = `services.php?${params.toString()}`;
    
    // Open in new window to trigger download
    window.open(exportUrl, '_blank');
    
    showAlert('Excel dosyası indiriliyor...', 'success');
}
</script>

<?php require_once 'includes/footer.php'; ?>
<?php
/**
 * Customers Page - List all customers with search functionality
 * Equivalent to Flask customers.html functionality
 */

$pageTitle = 'Müşteriler';
require_once 'includes/header.php';

// Current user info for role-based actions
$currentUser = ['role' => $_SESSION['role'] ?? ''];

// Get page parameters
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Search parameter
$search = trim($_GET['search'] ?? '');

// Build base query
$baseQuery = "
    SELECT c.id, c.name, c.phone, c.email, c.city, c.district, c.address, c.created_at,
           COUNT(s.id) as service_count
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
    // Branch managers can see all customers of their company (company-level sharing)
    $baseQuery .= " AND c.company_id = ?";
    $params[] = $_SESSION['company_id'];
} elseif ($_SESSION['role'] === 'technician') {
    // Technicians can only see customers from their assigned services
    $baseQuery .= " AND EXISTS (
        SELECT 1 FROM services s2 
        WHERE s2.customer_id = c.id AND s2.personnel_id = ?
    )";
    $params[] = $_SESSION['user_id'];
}

// Apply search filter
if ($search) {
    $baseQuery .= " AND (c.name LIKE ? OR c.phone LIKE ? OR c.city LIKE ? OR c.district LIKE ?)";
    $searchParam = '%' . $search . '%';
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
}

// Group by customer
$baseQuery .= " GROUP BY c.id";

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM (" . $baseQuery . ") as count_query";
$totalCount = fetchOne($countQuery, $params)['total'];
$totalPages = ceil($totalCount / $limit);

// Get customers with pagination
$customersQuery = $baseQuery . " ORDER BY c.name ASC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$customers = fetchAll($customersQuery, $params);
?>

<div class="page-header">
    <div class="row align-items-center">
        <div class="col-lg-8">
            <h2 class="text-dark mb-1">Müşteriler</h2>
            <p class="text-muted mb-0">Müşteri bilgilerini görüntüleyin ve yönetin</p>
        </div>
    </div>
</div>

<div class="mb-4 text-end">
    <a href="customer_add.php" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i>
        Yeni Müşteri Ekle
    </a>
</div>

<!-- Modern Search and Filters -->
<div class="services-filter-section">
    <div class="services-filter-card">
        <div class="services-filter-form">
            
            <!-- Desktop Layout -->
            <div class="desktop-filters">
                <div class="filter-row-1">
                    <div class="search-field">
                        <div class="search-input-group">
                            <span class="search-icon"><i class="fas fa-search"></i></span>
                            <input type="text" class="search-input" name="search" id="searchInput"
                                   value="<?= e($search) ?>" placeholder="Müşteri adı, telefon, şehir, ilçe ile arama yapın...">
                        </div>
                    </div>
                    
                    <div class="action-buttons">
                        <button type="button" class="btn btn-outline-primary" onclick="clearSearch()">
                            <i class="fas fa-times me-1"></i>
                            Temizle
                        </button>
                        <?php if ($search): ?>
                        <a href="customers.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i>
                            Temizle
                        </a>
                        <?php endif; ?>
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
                
                <div class="row">
                    <div class="col-12">
                        <div class="d-flex gap-1 justify-content-between">
                            <button type="button" class="btn btn-outline-secondary flex-fill" onclick="clearSearch()">
                                <i class="fas fa-times"></i> Temizle
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Customers Table -->
<div class="row">
    <div class="col-12">
        <div class="clean-table-container" id="customersContainer">
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
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="customer_edit.php?id=<?= $customer['id'] ?>" class="btn btn-sm btn-outline-primary" title="Düzenle">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="customer_services.php?customer_id=<?= $customer['id'] ?>" class="btn btn-sm btn-outline-success" title="Servisleri">
                                        <i class="fas fa-tools"></i>
                                    </a>
                                    <?php if ($customer['service_count'] == 0 && $currentUser['role'] != 'branch_manager'): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                            onclick="deleteCustomer(<?= $customer['id'] ?>)" title="Sil">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
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
                                        <?php if ($customer['service_count'] == 0 && $currentUser['role'] != 'branch_manager'): ?>
                                        <button type="button" class="mobile-action-btn mobile-action-delete"
                                                onclick="deleteCustomer(<?= $customer['id'] ?>)">
                                            <i class="fas fa-trash"></i>
                                            <span>Sil</span>
                                        </button>
                                        <?php else: ?>
                                        <div class="mobile-action-btn mobile-action-disabled">
                                            <i class="fas fa-ban"></i>
                                            <span>Silinmez</span>
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
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="card-footer">
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted">
                        Toplam <?= number_format($totalCount) ?> kayıt, Sayfa <?= $page ?>/<?= $totalPages ?>
                    </small>
                    <?= generatePagination($page, $totalPages, 'customers.php?' . ($search ? 'search=' . urlencode($search) . '&' : '') . 'page=') ?>
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
                <a href="customer_add.php" class="btn btn-primary mt-2">
                    <i class="fas fa-plus me-2"></i>
                    İlk Müşteriyi Ekle
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// AJAX Search System for Customers
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
    formData.append('page', page);

    // Show loading
    const container = document.getElementById('customersContainer');
    container.innerHTML = '<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x"></i><br>Aranıyor...</div>';

    fetch('ajax/search_customers.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            container.innerHTML = data.html;
            
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
        container.innerHTML = '<div class="alert alert-danger">Arama sırasında hata oluştu.</div>';
    });
}

// Real-time search with debounce
function initializeAjaxSearch() {
    const searchInput = document.getElementById('searchInput');
    const searchInputMobile = document.getElementById('searchInputMobile');
    
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
}

function clearSearch() {
    // Clear desktop inputs
    const searchInput = document.getElementById('searchInput');
    const searchInputMobile = document.getElementById('searchInputMobile');
    
    if (searchInput) searchInput.value = '';
    if (searchInputMobile) searchInputMobile.value = '';
    
    // Perform search with cleared filters
    performSearch(1);
}

function deleteCustomer(customerId) {
    if (confirm('Bu müşteriyi silmek istediğinizden emin misiniz?')) {
        fetch('ajax/delete_customer.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({id: customerId})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                performSearch(currentPage);
            } else {
                alert('Silme işlemi başarısız: ' + (data.error || 'Bilinmeyen hata'));
            }
        })
        .catch(error => {
            console.error('Delete error:', error);
            alert('Silme işlemi sırasında hata oluştu.');
        });
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initializeAjaxSearch();
});
</script>

<?php require_once 'includes/footer.php'; ?>
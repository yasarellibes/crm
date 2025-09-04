<?php
// Handle AJAX requests FIRST - before any includes
if (isset($_GET['ajax'])) {
    ob_start(); // Catch any unwanted output
    require_once 'config/database.php';
    require_once 'includes/functions.php';
    session_start();
    header('Content-Type: application/json');
    
    try {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $action = $_GET['action'] ?? '';
            
            if ($action === 'get_company') {
                $companyId = intval($_GET['id'] ?? 0);
                $company = fetchOne("SELECT * FROM companies WHERE id = ?", [$companyId]);
                
                if ($company) {
                    echo json_encode(['success' => true, 'company' => $company]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Şirket bulunamadı']);
                }
                exit;
            }
        } else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';
            
            if ($action === 'add') {
                $name = trim($_POST['name'] ?? '');
                $password = trim($_POST['password'] ?? '');
                
                if (empty($name)) {
                    echo json_encode(['success' => false, 'message' => 'Şirket adı gereklidir']);
                    exit;
                }
                
                if (empty($password)) {
                    echo json_encode(['success' => false, 'message' => 'Şifre gereklidir']);
                    exit;
                }
                
                $insertQuery = "INSERT INTO companies (name, tax_number, email, phone, city, district, website, address, service_start_date, service_end_date, password, created_at) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
                
                $params = [
                    $name,
                    $_POST['tax_number'] ?? '',
                    $_POST['email'] ?? '',
                    $_POST['phone'] ?? '',
                    $_POST['city'] ?? '',
                    $_POST['district'] ?? '',
                    $_POST['website'] ?? '',
                    $_POST['address'] ?? '',
                    $_POST['service_start_date'] ?: null,
                    $_POST['service_end_date'] ?: null,
                    password_hash($password, PASSWORD_DEFAULT)
                ];
                
                try {
                    if (executeQuery($insertQuery, $params)) {
                        // Get the newly created company ID
                        $newCompanyResult = fetchOne("SELECT id FROM companies WHERE name = ? ORDER BY created_at DESC LIMIT 1", [$name]);
                        $newCompanyId = $newCompanyResult['id'];
                        
                        // Create default data for the new company
                        createDefaultCompanyData($newCompanyId, $name, $_POST['city'] ?? '', $_POST['district'] ?? '', $_POST['address'] ?? '');
                        
                        ob_clean(); // Clear any output buffer
                        echo json_encode(['success' => true, 'message' => 'Şirket ve varsayılan veriler başarıyla oluşturuldu']);
                    } else {
                        ob_clean(); // Clear any output buffer
                        echo json_encode(['success' => false, 'message' => 'Şirket eklenemedi']);
                    }
                } catch (Exception $e) {
                    ob_clean(); // Clear any output buffer
                    echo json_encode(['success' => false, 'message' => 'Veritabanı hatası: ' . $e->getMessage()]);
                }
                exit;
            }
            
            if ($action === 'delete') {
                $companyId = intval($_POST['id'] ?? 0);
                
                if ($companyId <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Geçersiz şirket ID']);
                    exit;
                }
                
                try {
                    // Check if company has services (prevent deletion if has active services)
                    $serviceCount = fetchOne("SELECT COUNT(*) as count FROM services WHERE company_id = ?", [$companyId]);
                    if ($serviceCount['count'] > 0) {
                        echo json_encode(['success' => false, 'message' => 'Bu şirkette aktif servisler bulunmaktadır. Önce servisleri silin.']);
                        exit;
                    }
                    
                    // Delete in correct order (foreign key constraints)
                    executeQuery("DELETE FROM models WHERE company_id = ?", [$companyId]);
                    executeQuery("DELETE FROM brands WHERE company_id = ?", [$companyId]);
                    executeQuery("DELETE FROM devices WHERE company_id = ?", [$companyId]);
                    executeQuery("DELETE FROM complaints WHERE company_id = ?", [$companyId]);
                    executeQuery("DELETE FROM operations WHERE company_id = ?", [$companyId]);
                    executeQuery("DELETE FROM customers WHERE company_id = ?", [$companyId]);
                    executeQuery("DELETE FROM users WHERE company_id = ?", [$companyId]);
                    executeQuery("DELETE FROM branches WHERE company_id = ?", [$companyId]);
                    executeQuery("DELETE FROM companies WHERE id = ?", [$companyId]);
                    
                    ob_clean();
                    echo json_encode(['success' => true, 'message' => 'Şirket ve tüm bağlı veriler silindi']);
                } catch (Exception $e) {
                    ob_clean();
                    echo json_encode(['success' => false, 'message' => 'Silme hatası: ' . $e->getMessage()]);
                }
                exit;
            }
            
            if ($action === 'update') {
                $companyId = intval($_POST['id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                
                if (empty($name)) {
                    echo json_encode(['success' => false, 'message' => 'Şirket adı gereklidir']);
                    exit;
                }
                
                $updateQuery = "UPDATE companies SET 
                    name = ?, tax_number = ?, email = ?, phone = ?, 
                    city = ?, district = ?, website = ?, address = ?,
                    service_start_date = ?, service_end_date = ?";
                
                $params = [
                    $name,
                    $_POST['tax_number'] ?? '',
                    $_POST['email'] ?? '',
                    $_POST['phone'] ?? '',
                    $_POST['city'] ?? '',
                    $_POST['district'] ?? '',
                    $_POST['website'] ?? '',
                    $_POST['address'] ?? '',
                    $_POST['service_start_date'] ?: null,
                    $_POST['service_end_date'] ?: null
                ];
                
                // Password handling for update
                if (!empty($_POST['password'])) {
                    $updateQuery .= ", password = ?";
                    $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
                }
                
                $updateQuery .= " WHERE id = ?";
                $params[] = $companyId;
                
                try {
                    if (executeQuery($updateQuery, $params)) {
                        ob_clean(); // Clear any output buffer
                        echo json_encode(['success' => true, 'message' => 'Şirket güncellendi']);
                    } else {
                        ob_clean(); // Clear any output buffer
                        echo json_encode(['success' => false, 'message' => 'Güncelleme başarısız']);
                    }
                } catch (Exception $e) {
                    ob_clean(); // Clear any output buffer
                    echo json_encode(['success' => false, 'message' => 'Veritabanı hatası: ' . $e->getMessage()]);
                }
                exit;
            }
        }
        
        ob_clean(); // Clear any output buffer
        echo json_encode(['success' => false, 'message' => 'Geçersiz istek']);
        exit;
    } catch (Exception $e) {
        error_log("Companies AJAX error: " . $e->getMessage());
        ob_clean(); // Clear any output buffer
        echo json_encode(['success' => false, 'message' => 'Bir hata oluştu: ' . $e->getMessage()]);
        exit;
    }
}

// Function to create default data for new company
function createDefaultCompanyData($companyId, $companyName, $city = '', $district = '') {
    try {
        // No demo branch or personnel creation - company starts empty
        
        // Create specified devices
        $devices = ['Kombi', 'Klima'];
        foreach ($devices as $device) {
            executeQuery("INSERT INTO devices (name, company_id, created_at) VALUES (?, ?, CURRENT_TIMESTAMP)", 
                        [$device, $companyId]);
        }
        
        // Create specified brands
        $brands = ['Arçelik', 'Baymak', 'Vestel', 'Bilinmiyor'];
        foreach ($brands as $brand) {
            executeQuery("INSERT INTO brands (name, company_id, created_at) VALUES (?, ?, CURRENT_TIMESTAMP)", 
                        [$brand, $companyId]);
        }
        
        // Get brand IDs for models
        $brandResults = fetchAll("SELECT id, name FROM brands WHERE company_id = ?", [$companyId]);
        
        // Create "Bilinmiyor" model for each brand (as requested)
        foreach ($brandResults as $brand) {
            executeQuery("INSERT INTO models (name, brand_id, company_id, created_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)", 
                        ['Bilinmiyor', $brand['id'], $companyId]);
        }
        
        // Create specified complaints
        $complaints = ['Çalışmıyor'];
        foreach ($complaints as $complaint) {
            executeQuery("INSERT INTO complaints (name, company_id, created_at) VALUES (?, ?, CURRENT_TIMESTAMP)", 
                        [$complaint, $companyId]);
        }
        
        // Create default operations
        $operations = ['İşlem bekliyor', 'Tamamlandı', 'Parça Bekliyor'];
        foreach ($operations as $operation) {
            executeQuery("INSERT INTO operations (name, company_id, created_at) VALUES (?, ?, CURRENT_TIMESTAMP)", 
                        [$operation, $companyId]);
        }
        
        error_log("Company data created successfully for company ID: " . $companyId);
        return true;
        
    } catch (Exception $e) {
        error_log("Error creating default company data: " . $e->getMessage());
        return false;
    }
}

// Regular page logic starts here
require_once 'includes/header.php';
require_once 'includes/functions.php';

requireLogin();

// Only super admin can manage companies
if ($_SESSION['role'] !== 'super_admin') {
    header('Location: dashboard.php?error=no_permission');
    exit;
}

$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Build search query
$searchQuery = "";
$searchParams = [];

if ($search) {
    $searchQuery = " WHERE name ILIKE ? OR email ILIKE ? OR city ILIKE ?";
    $searchTerm = "%{$search}%";
    $searchParams = [$searchTerm, $searchTerm, $searchTerm];
}

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as total FROM companies" . $searchQuery;
$totalResult = fetchOne($countQuery, $searchParams);
$totalCount = $totalResult['total'] ?? 0;
$totalPages = ceil($totalCount / $perPage);

// Get companies with pagination and service status
$query = "
    SELECT c.*, 
           (SELECT COUNT(*) FROM branches WHERE company_id = c.id) as branch_count,
           CASE 
               WHEN c.service_end_date < CURRENT_DATE THEN 'expired'
               WHEN c.service_end_date <= CURRENT_DATE + INTERVAL '30 days' THEN 'expiring'
               ELSE 'active'
           END as service_status,
           (c.service_end_date - CURRENT_DATE) as days_remaining,
           CASE 
               WHEN c.created_at >= CURRENT_DATE - INTERVAL '7 days' THEN true
               ELSE false
           END as is_new_registration
    FROM companies c 
    $searchQuery
    ORDER BY 
        CASE WHEN c.service_end_date < CURRENT_DATE THEN 1 ELSE 0 END,
        c.name ASC 
    LIMIT $perPage OFFSET $offset
";

$companies = fetchAll($query, $searchParams);

// Get system settings for contact info
$systemSettings = getSystemSettings();

$pageTitle = 'Şirket Yönetimi - ' . $systemSettings['system_name'];
?>

<style>
.contact-mini-btn {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.contact-mini-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
</style>

<div class="main-content">
    <div class="container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h2 class="text-dark mb-1">Şirket Yönetimi</h2>
                    <p class="text-muted mb-0">Sisteme kayıtlı şirketleri yönetin</p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <button class="btn btn-primary" onclick="showAddCompanyModal()">
                        <i class="fas fa-plus me-1"></i>
                        Yeni Şirket
                    </button>
                </div>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-8">
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Şirket adı, email veya şehir..." value="<?= e($search) ?>">
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-outline-primary flex-fill">
                                        <i class="fas fa-search me-1"></i>Ara
                                    </button>
                                    <?php if ($search): ?>
                                    <a href="companies.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Companies Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-building me-2"></i>
                    Şirketler (<?= number_format($totalCount) ?>)
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($companies)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-building fa-3x text-muted mb-3"></i>
                    <h5>Şirket bulunamadı</h5>
                    <p class="text-muted">
                        <?= $search ? 'Arama kriterlerinize uygun şirket bulunamadı.' : 'Henüz hiç şirket eklenmemiş.' ?>
                    </p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Şirket Bilgileri</th>
                                <th>İletişim</th>
                                <th>Hizmet Durumu</th>
                                <th>Hizmet Tarihleri</th>
                                <th>Şubeler</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($companies as $company): ?>
                            <?php 
                                $isExpired = $company['service_status'] === 'expired';
                                $isExpiring = $company['service_status'] === 'expiring';
                                $isNewRegistration = $company['is_new_registration'];
                                $rowClass = $isExpired ? 'table-danger' : ($isExpiring ? 'table-warning' : '');
                            ?>
                            <tr class="<?= $rowClass ?>">
                                <td>
                                    <div class="company-info">
                                        <div class="fw-bold">
                                            <?= e($company['name']) ?>
                                            <?php if ($isNewRegistration): ?>
                                            <span class="badge bg-info ms-2">YENİ KAYIT</span>
                                            <?php endif; ?>
                                            <?php if ($isExpired): ?>
                                            <span class="badge bg-danger ms-2">SÜRESİ BİTTİ</span>
                                            <?php elseif ($isExpiring): ?>
                                            <span class="badge bg-warning text-dark ms-2">SÜRESİ BİTİYOR</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($company['city'] && $company['district']): ?>
                                        <small class="text-muted"><?= e($company['city']) ?>, <?= e($company['district']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="contact-info">
                                        <?php if ($company['phone']): ?>
                                        <div><a href="tel:<?= e($company['phone']) ?>" class="phone-link"><?= e($company['phone']) ?></a></div>
                                        <?php endif; ?>
                                        <?php if ($company['email']): ?>
                                        <div><a href="mailto:<?= e($company['email']) ?>"><?= e($company['email']) ?></a></div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($isExpired): ?>
                                    <?php 
                                        $phone = preg_replace('/[^0-9]/', '', $systemSettings['company_phone']);
                                        $whatsappUrl = "https://wa.me/9{$phone}?text=" . urlencode("Merhaba, şirket hizmet süresini yenilemek istiyorum.");
                                        $emailUrl = "mailto:{$systemSettings['company_email']}?subject=" . urlencode("Hizmet Süresi Yenileme - " . $company['name']) . "&body=" . urlencode("Merhaba, şirketimizin hizmet süresini yenilemek istiyorum.");
                                    ?>
                                    <div class="alert alert-danger py-2 px-3 mb-0 small">
                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                        <strong>HİZMET SÜRESİ BİTTİ</strong><br>
                                        <small>Sistem kullanımı engellenmiştir</small><br>
                                        <hr class="my-1">
                                        <div class="d-grid gap-1 mt-2">
                                            <div class="btn-group" role="group">
                                                <a href="tel:<?= $systemSettings['company_phone'] ?>" class="btn btn-outline-light btn-sm contact-mini-btn" title="Ara: <?= formatPhone($systemSettings['company_phone']) ?>">
                                                    <i class="fas fa-phone me-1"></i>Ara
                                                </a>
                                                <a href="<?= $whatsappUrl ?>" target="_blank" class="btn btn-outline-light btn-sm contact-mini-btn" title="WhatsApp Mesaj">
                                                    <i class="fab fa-whatsapp me-1"></i>WhatsApp
                                                </a>
                                                <a href="<?= $emailUrl ?>" class="btn btn-outline-light btn-sm contact-mini-btn" title="Email Gönder">
                                                    <i class="fas fa-envelope me-1"></i>Email
                                                </a>
                                            </div>
                                        </div>
                                        <div class="mt-1 text-center">
                                            <small><?= formatPhone($systemSettings['company_phone']) ?> | <?= e($systemSettings['company_email']) ?></small>
                                        </div>
                                    </div>
                                    <?php elseif ($isExpiring): ?>
                                    <?php 
                                        $phone = preg_replace('/[^0-9]/', '', $systemSettings['company_phone']);
                                        $whatsappUrl = "https://wa.me/9{$phone}?text=" . urlencode("Merhaba, şirket hizmet süresini yenilemek istiyorum.");
                                        $emailUrl = "mailto:{$systemSettings['company_email']}?subject=" . urlencode("Hizmet Süresi Yenileme - " . $company['name']) . "&body=" . urlencode("Merhaba, şirketimizin hizmet süresini yenilemek istiyorum.");
                                    ?>
                                    <div class="alert alert-warning py-2 px-3 mb-0 small">
                                        <i class="fas fa-clock me-1"></i>
                                        <strong>HİZMET SÜRESİ BİTİYOR</strong><br>
                                        <small><?= abs($company['days_remaining']) ?> gün kaldı</small><br>
                                        <hr class="my-1">
                                        <div class="d-grid gap-1 mt-2">
                                            <div class="btn-group" role="group">
                                                <a href="tel:<?= $systemSettings['company_phone'] ?>" class="btn btn-outline-dark btn-sm contact-mini-btn" title="Ara: <?= formatPhone($systemSettings['company_phone']) ?>">
                                                    <i class="fas fa-phone me-1"></i>Ara
                                                </a>
                                                <a href="<?= $whatsappUrl ?>" target="_blank" class="btn btn-outline-dark btn-sm contact-mini-btn" title="WhatsApp Mesaj">
                                                    <i class="fab fa-whatsapp me-1"></i>WhatsApp
                                                </a>
                                                <a href="<?= $emailUrl ?>" class="btn btn-outline-dark btn-sm contact-mini-btn" title="Email Gönder">
                                                    <i class="fas fa-envelope me-1"></i>Email
                                                </a>
                                            </div>
                                        </div>
                                        <div class="mt-1 text-center">
                                            <small><?= formatPhone($systemSettings['company_phone']) ?> | <?= e($systemSettings['company_email']) ?></small>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                    <span class="badge bg-success fs-6">
                                        <i class="fas fa-check-circle me-1"></i>Aktif
                                    </span>
                                    <?php if ($isNewRegistration): ?>
                                    <div class="mt-2">
                                        <small class="text-success">
                                            <i class="fas fa-star me-1"></i>14 günlük ücretsiz deneme
                                        </small>
                                    </div>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="service-dates">
                                        <small class="text-muted">Başlangıç:</small><br>
                                        <strong><?= $company['service_start_date'] ? date('d.m.Y', strtotime($company['service_start_date'])) : 'Belirtilmemiş' ?></strong><br>
                                        <small class="text-muted">Bitiş:</small><br>
                                        <strong class="<?= $isExpired ? 'text-danger' : ($isExpiring ? 'text-warning' : 'text-success') ?>">
                                            <?= $company['service_end_date'] ? date('d.m.Y', strtotime($company['service_end_date'])) : 'Belirtilmemiş' ?>
                                        </strong>
                                    </div>
                                </td>
                                <td>
                                    <div class="stats-info">
                                        <span class="badge bg-info"><?= number_format($company['branch_count']) ?> Şube</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="btn-group-horizontal" role="group">
                                        <button class="btn btn-outline-primary btn-action-wide" 
                                                onclick="editCompany(<?= $company['id'] ?>)" title="Düzenle">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="branches.php?company_id=<?= $company['id'] ?>" 
                                           class="btn btn-outline-info btn-action-wide" title="Şubeler">
                                            <i class="fas fa-sitemap"></i>
                                        </a>

                                        <button class="btn btn-outline-danger btn-action-wide" 
                                                onclick="deleteCompany(<?= $company['id'] ?>, '<?= e($company['name']) ?>')" title="Şirketi Sil">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="card-footer">
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted">
                        Toplam <?= number_format($totalCount) ?> kayıt, Sayfa <?= $page ?>/<?= $totalPages ?>
                    </small>
                    <?= generatePagination($page, $totalPages, 'companies.php?' . ($search ? 'search=' . urlencode($search) . '&' : '') . 'page=') ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Company Modal -->
<div class="modal fade" id="addCompanyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Yeni Şirket Ekle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addCompanyForm" onsubmit="saveCompany(event)">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Şirket Adı <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Vergi Numarası</label>
                            <input type="text" class="form-control" name="tax_number">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Telefon</label>
                            <input type="tel" class="form-control" name="phone" maxlength="11" placeholder="05320528000" pattern="[0-9]{10,11}" title="Sadece rakam giriniz (10-11 hane)">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">İl</label>
                            <select class="form-select" name="city" id="addCitySelect">
                                <option value="">İl Seçin</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">İlçe</label>
                            <select class="form-select" name="district" id="addDistrictSelect" disabled>
                                <option value="">İlçe Seçin</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Website</label>
                        <input type="url" class="form-control" name="website">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Adres</label>
                        <textarea class="form-control" name="address" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Giriş Şifresi <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="password" required>
                        <div class="form-text">Bu şifre ile şirket hesabına giriş yapılacak</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Company Modal -->
<div class="modal fade" id="editCompanyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Şirket Düzenle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editCompanyForm" onsubmit="updateCompany(event)">
                <input type="hidden" name="id" id="editCompanyId">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Şirket Adı <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" id="editCompanyName" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Vergi Numarası</label>
                            <input type="text" class="form-control" name="tax_number" id="editCompanyTaxNumber">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" id="editCompanyEmail">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Telefon</label>
                            <input type="tel" class="form-control" name="phone" id="editCompanyPhone" maxlength="11" placeholder="05320528000" pattern="[0-9]{10,11}" title="Sadece rakam giriniz (10-11 hane)">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">İl</label>
                            <select class="form-select" name="city" id="editCitySelect">
                                <option value="">İl Seçin</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">İlçe</label>
                            <select class="form-select" name="district" id="editDistrictSelect" disabled>
                                <option value="">İlçe Seçin</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Hizmet Başlangıç</label>
                            <input type="date" class="form-control" name="service_start_date" id="editServiceStartDate">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Hizmet Bitiş</label>
                            <input type="date" class="form-control" name="service_end_date" id="editServiceEndDate">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Website</label>
                        <input type="url" class="form-control" name="website" id="editCompanyWebsite">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Adres</label>
                        <textarea class="form-control" name="address" id="editCompanyAddress" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Şifre (Değiştirmek için doldur)</label>
                        <input type="password" class="form-control" name="password" id="editCompanyPassword">
                        <div class="form-text">Boş bırakılırsa mevcut şifre korunur</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Güncelle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showAddCompanyModal() {
    document.getElementById('addCompanyForm').reset();
    // Initialize city/district dropdowns for add modal
    const citySelect = document.getElementById('addCitySelect');
    const districtSelect = document.getElementById('addDistrictSelect');
    
    if (typeof loadTurkeyCities === 'function') {
        loadTurkeyCities(citySelect);
        
        // Add city change event listener
        citySelect.addEventListener('change', function() {
            loadTurkeyDistricts(districtSelect, this.value);
        });
    }
    
    new bootstrap.Modal(document.getElementById('addCompanyModal')).show();
}

function editCompany(companyId) {
    // Fetch company data and populate edit modal
    fetch(`companies.php?ajax=1&action=get_company&id=${companyId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const company = data.company;
                document.getElementById('editCompanyId').value = company.id;
                document.getElementById('editCompanyName').value = company.name || '';
                document.getElementById('editCompanyTaxNumber').value = company.tax_number || '';
                document.getElementById('editCompanyEmail').value = company.email || '';
                document.getElementById('editCompanyPhone').value = company.phone || '';
                // Initialize city/district dropdowns for edit modal
                const editCitySelect = document.getElementById('editCitySelect');
                const editDistrictSelect = document.getElementById('editDistrictSelect');
                
                if (typeof loadTurkeyCities === 'function') {
                    loadTurkeyCities(editCitySelect, company.city || '');
                    
                    // Add city change event listener
                    editCitySelect.addEventListener('change', function() {
                        loadTurkeyDistricts(editDistrictSelect, this.value);
                    });
                    
                    // Load districts if city is selected
                    if (company.city) {
                        loadTurkeyDistricts(editDistrictSelect, company.city, company.district || '');
                    }
                }
                document.getElementById('editServiceStartDate').value = company.service_start_date || '';
                document.getElementById('editServiceEndDate').value = company.service_end_date || '';
                document.getElementById('editCompanyWebsite').value = company.website || '';
                document.getElementById('editCompanyAddress').value = company.address || '';
                
                new bootstrap.Modal(document.getElementById('editCompanyModal')).show();
            } else {
                showAlert(data.message || 'Şirket bilgileri yüklenemedi', 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Hata oluştu', 'danger');
        });
}

function updateCompany(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    formData.append('action', 'update');
    
    fetch('companies.php?ajax=1', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Şirket başarıyla güncellendi', 'success');
            bootstrap.Modal.getInstance(document.getElementById('editCompanyModal')).hide();
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert(data.message || 'Hata oluştu', 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('İşlem başarısız', 'danger');
    });
}

function deleteCompany(companyId, companyName) {
    if (!confirm(`"${companyName}" şirketini ve tüm bağlı verilerini (şubeler, kullanıcılar, tanımlamalar) silmek istediğinizden emin misiniz?\n\nBu işlem GERİ ALINAMAZ!`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', companyId);
    
    fetch('companies.php?ajax=1', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Şirket başarıyla silindi', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert(data.message || 'Silme işlemi başarısız', 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Silme işlemi başarısız', 'danger');
    });
}

function saveCompany(event) {
    event.preventDefault();
    console.log('Saving company...');
    
    const formData = new FormData(event.target);
    formData.append('action', 'add');
    
    // Debug: log form data
    for (let [key, value] of formData.entries()) {
        console.log(key + ':', value);
    }
    
    fetch('companies.php?ajax=1', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        return response.text(); // First get as text to see what we receive
    })
    .then(text => {
        console.log('Raw response:', text);
        try {
            const data = JSON.parse(text);
            console.log('Parsed JSON:', data);
            if (data.success) {
                showAlert('Şirket başarıyla eklendi', 'success');
                bootstrap.Modal.getInstance(document.getElementById('addCompanyModal')).hide();
                setTimeout(() => location.reload(), 1000);
            } else {
                showAlert(data.message || 'Hata oluştu', 'danger');
            }
        } catch (parseError) {
            console.error('JSON parse error:', parseError);
            console.error('Raw response that failed to parse:', text);
            showAlert('Sunucu yanıt hatası', 'danger');
        }
    })
    .catch(error => {
        console.error('Network error:', error);
        showAlert('Ağ bağlantı hatası', 'danger');
    });
}
</script>

<script src="assets/js/cities_complete.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('Companies page loaded');
    // City/district system now handled by cities_complete.js
    setupCityDistrict('addCitySelect', 'addDistrictSelect', '', '');
    setupCityDistrict('editCitySelect', 'editDistrictSelect', '', '');
});
</script>

<?php require_once 'includes/footer.php'; ?>
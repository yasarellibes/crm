<?php
require_once 'includes/header.php';
require_once 'includes/functions.php';

requireLogin();

// Check permissions
if ($_SESSION['role'] == 'technician') {
    header('Location: dashboard.php?error=no_permission');
    exit;
}

$currentUser = $_SESSION;
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Build query with role-based filtering
$whereConditions = ['1=1'];
$queryParams = [];

// Apply role-based filtering
if ($_SESSION['role'] == 'company_admin') {
    $whereConditions[] = 'p.company_id = ?';
    $queryParams[] = $_SESSION['company_id'];
} elseif ($_SESSION['role'] == 'branch_manager') {
    $whereConditions[] = 'p.branch_id = ?';
    $queryParams[] = $_SESSION['branch_id'];
}

// Add search filter
if ($search) {
    $whereConditions[] = '(p.name ILIKE ? OR p.email ILIKE ? OR p.phone ILIKE ?)';
    $searchTerm = "%{$search}%";
    $queryParams[] = $searchTerm;
    $queryParams[] = $searchTerm;
    $queryParams[] = $searchTerm;
}

$whereClause = implode(' AND ', $whereConditions);
$allParams = $queryParams;

// Get total count
$countQuery = "
    SELECT COUNT(*) as total 
    FROM personnel p
    LEFT JOIN companies c ON p.company_id = c.id
    LEFT JOIN branches b ON p.branch_id = b.id
    WHERE $whereClause
";

$totalResult = fetchOne($countQuery, $allParams);
$totalCount = $totalResult['total'] ?? 0;
$totalPages = ceil($totalCount / $perPage);

// Get personnel
$query = "
    SELECT p.*, c.name as company_name, b.name as branch_name
    FROM personnel p
    LEFT JOIN companies c ON p.company_id = c.id
    LEFT JOIN branches b ON p.branch_id = b.id
    WHERE $whereClause
    ORDER BY p.name ASC
    LIMIT $perPage OFFSET $offset
";

$personnel = fetchAll($query, $allParams);

$pageTitle = 'Personel Yönetimi - Serviso';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="page-header">
                    <h2 class="mb-0">Personel Yönetimi</h2>
                    <p class="page-subtitle">Teknisyen ve personel listesini yönetin</p>
                </div>
            </div>
            <div class="col-lg-4 text-lg-end">
                <button class="btn btn-primary" onclick="showAddPersonnelModal()">
                    <i class="fas fa-user-plus me-1"></i>
                    Yeni Personel
                </button>
            </div>
        </div>

        <!-- Search -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-8">
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Personel adı, email veya telefon..." value="<?= e($search) ?>">
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-outline-primary flex-fill">
                                        <i class="fas fa-search me-1"></i>Ara
                                    </button>
                                    <?php if ($search): ?>
                                    <a href="personnel.php" class="btn btn-outline-secondary">
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

        <!-- Personnel Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-hard-hat me-2"></i>
                    Personel Listesi (<?= number_format($totalCount) ?>)
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($personnel)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-hard-hat fa-3x text-muted mb-3"></i>
                    <h5>Personel bulunamadı</h5>
                    <p class="text-muted">
                        <?= $search ? 'Arama kriterlerinize uygun personel bulunamadı.' : 'Henüz hiç personel eklenmemiş.' ?>
                    </p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Personel</th>
                                <th>İletişim</th>
                                <th>Şirket/Şube</th>
                                <th>Uzmanlık</th>
                                <th>Durum</th>
                                <th>Kayıt Tarihi</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($personnel as $person): ?>
                            <tr>
                                <td>
                                    <div class="personnel-info">
                                        <div class="fw-bold"><?= e($person['name']) ?></div>
                                        <small class="text-muted">ID: #<?= $person['id'] ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <?php if ($person['email']): ?>
                                        <div><a href="mailto:<?= e($person['email']) ?>"><?= e($person['email']) ?></a></div>
                                        <?php endif; ?>
                                        <?php if ($person['phone']): ?>
                                        <div><a href="tel:<?= e($person['phone']) ?>" class="phone-link"><?= formatPhone($person['phone']) ?></a></div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <div><?= e($person['company_name'] ?? 'Şirket yok') ?></div>
                                        <?php if ($person['branch_name']): ?>
                                        <small class="text-muted"><?= e($person['branch_name']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($person['specialization']): ?>
                                    <span class="badge bg-info"><?= e($person['specialization']) ?></span>
                                    <?php else: ?>
                                    <span class="text-muted">Genel</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (isset($person['is_active']) && $person['is_active']): ?>
                                    <span class="badge bg-success">Aktif</span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">Pasif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="text-muted small"><?= formatDate($person['created_at']) ?></span>
                                </td>
                                <td>
                                    <div class="btn-group-horizontal" role="group">
                                        <button class="btn btn-outline-primary btn-action-wide" 
                                                onclick="editPersonnel(<?= $person['id'] ?>)" title="Düzenle">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="services.php?personnel=<?= $person['id'] ?>" 
                                           class="btn btn-outline-info btn-action-wide" title="Servisleri">
                                            <i class="fas fa-wrench"></i>
                                        </a>
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
                    <?= generatePagination($page, $totalPages, 'personnel.php?' . ($search ? 'search=' . urlencode($search) . '&' : '') . 'page=') ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Personnel Modal -->
<div class="modal fade" id="addPersonnelModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Personel Ekle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addPersonnelForm" method="POST" action="ajax/save_personnel.php">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="add_name" class="form-label">Ad Soyad <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="add_name" name="name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="add_email" class="form-label">E-posta</label>
                            <input type="email" class="form-control" id="add_email" name="email">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="add_phone" class="form-label">Telefon <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control phone-input" id="add_phone" name="phone" maxlength="11" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="add_specialization" class="form-label">Uzmanlık</label>
                            <input type="text" class="form-control" id="add_specialization" name="specialization" placeholder="Klima, Kombi, vb.">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="add_city" class="form-label">İl <span class="text-danger">*</span></label>
                            <select class="form-select" id="add_city" name="city" required>
                                <option value="">İl Seçiniz</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="add_district" class="form-label">İlçe <span class="text-danger">*</span></label>
                            <select class="form-select" id="add_district" name="district" required disabled>
                                <option value="">İlçe Seçiniz</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="add_address" class="form-label">Adres</label>
                            <textarea class="form-control" id="add_address" name="address" rows="2"></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="add_password" class="form-label">Şifre</label>
                            <input type="password" class="form-control" id="add_password" name="password" placeholder="Giriş şifresi">
                        </div>
                        <?php if ($_SESSION['role'] == 'super_admin'): ?>
                        <div class="col-md-6 mb-3">
                            <label for="add_company_id" class="form-label">Şirket <span class="text-danger">*</span></label>
                            <select class="form-select" id="add_company_id" name="company_id" required>
                                <option value="">Şirket Seçiniz</option>
                                <?php 
                                $companies = fetchAll("SELECT id, name FROM companies ORDER BY name");
                                foreach ($companies as $company): ?>
                                    <option value="<?= $company['id'] ?>"><?= e($company['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-6 mb-3">
                            <label for="add_branch_id" class="form-label">Şube</label>
                            <select class="form-select" id="add_branch_id" name="branch_id">
                                <option value="">Şube Seçiniz</option>
                            </select>
                        </div>
                        <div class="col-md-12 mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="add_is_active" name="is_active" checked>
                                <label class="form-check-label" for="add_is_active">Aktif</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Personel Ekle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Personnel Modal -->
<div class="modal fade" id="editPersonnelModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Personel Düzenle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editPersonnelForm" method="POST" action="ajax/save_personnel.php">
                <input type="hidden" id="edit_personnel_id" name="personnel_id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_name" class="form-label">Ad Soyad <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_email" class="form-label">E-posta</label>
                            <input type="email" class="form-control" id="edit_email" name="email">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_phone" class="form-label">Telefon <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control phone-input" id="edit_phone" name="phone" maxlength="11" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_specialization" class="form-label">Uzmanlık</label>
                            <input type="text" class="form-control" id="edit_specialization" name="specialization" placeholder="Klima, Kombi, vb.">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_city" class="form-label">İl <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_city" name="city" required>
                                <option value="">İl seçin...</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_district" class="form-label">İlçe <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_district" name="district" required>
                                <option value="">İlçe seçin...</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_address" class="form-label">Adres</label>
                            <textarea class="form-control" id="edit_address" name="address" rows="2"></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_password" class="form-label">Şifre</label>
                            <input type="password" class="form-control" id="edit_password" name="password" placeholder="Değiştirmek için yeni şifre girin">
                        </div>
                        <?php if ($_SESSION['role'] == 'super_admin'): ?>
                        <div class="col-md-6 mb-3">
                            <label for="edit_company_id" class="form-label">Şirket <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_company_id" name="company_id" required>
                                <option value="">Şirket Seçiniz</option>
                                <?php 
                                $companies = fetchAll("SELECT id, name FROM companies ORDER BY name");
                                foreach ($companies as $company): ?>
                                    <option value="<?= $company['id'] ?>"><?= e($company['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-6 mb-3">
                            <label for="edit_branch_id" class="form-label">Şube</label>
                            <select class="form-select" id="edit_branch_id" name="branch_id">
                                <option value="">Şube Seçiniz</option>
                            </select>
                        </div>
                        <div class="col-md-12 mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="edit_is_active" name="is_active">
                                <label class="form-check-label" for="edit_is_active">Aktif</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Personel Güncelle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="assets/js/cities_complete.js"></script>
<script>
// Personel ekleme modalını aç
function showAddPersonnelModal() {
    // Şirket varsayılanlarını al
    <?php $companyDefaults = getCompanyDefaults(); ?>
    
    // Il/ilce dropdownlarını hazırla
    setupCityDistrict('add_city', 'add_district', '<?= addslashes($companyDefaults['city']) ?>', '<?= addslashes($companyDefaults['district']) ?>');
    
    // Modalı göster
    new bootstrap.Modal(document.getElementById('addPersonnelModal')).show();
}

// Personel düzenleme modalını aç
function editPersonnel(personnelId) {
    fetch(`ajax/get_personnel.php?id=${personnelId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const personnel = data.personnel;
                
                // Form alanlarını doldur
                document.getElementById('edit_personnel_id').value = personnel.id;
                document.getElementById('edit_name').value = personnel.name || '';
                document.getElementById('edit_email').value = personnel.email || '';
                document.getElementById('edit_phone').value = personnel.phone || '';
                document.getElementById('edit_specialization').value = personnel.specialization || '';
                document.getElementById('edit_address').value = personnel.address || '';
                document.getElementById('edit_is_active').checked = personnel.is_active == 1;
                
                // Şirket ve şube seçimi
                if (document.getElementById('edit_company_id')) {
                    document.getElementById('edit_company_id').value = personnel.company_id || '';
                }
                if (document.getElementById('edit_branch_id')) {
                    document.getElementById('edit_branch_id').value = personnel.branch_id || '';
                }
                
                // Il/ilce dropdownlarını hazırla - cities_complete.js sistemi kullan
                setTimeout(() => {
                    if (typeof setupCityDistrict === 'function') {
                        setupCityDistrict('edit_city', 'edit_district', personnel.city || 'Samsun', personnel.district || 'Atakum');
                    }
                }, 100);
                
                // Modalı göster
                new bootstrap.Modal(document.getElementById('editPersonnelModal')).show();
            } else {
                showAlert('Personel bilgileri alınamadı: ' + (data.message || 'Bilinmeyen hata'), 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Bir hata oluştu', 'danger');
        });
}

// Form gönderimi
document.getElementById('addPersonnelForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'add');
    
    fetch('ajax/save_personnel.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Personel başarıyla eklendi', 'success');
            bootstrap.Modal.getInstance(document.getElementById('addPersonnelModal')).hide();
            window.location.reload();
        } else {
            showAlert('Hata: ' + (data.message || 'Personel eklenemedi'), 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Bir hata oluştu', 'danger');
    });
});

document.getElementById('editPersonnelForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'edit');
    
    fetch('ajax/save_personnel.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Personel başarıyla güncellendi', 'success');
            bootstrap.Modal.getInstance(document.getElementById('editPersonnelModal')).hide();
            window.location.reload();
        } else {
            showAlert('Hata: ' + (data.message || 'Personel güncellenemedi'), 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Bir hata oluştu', 'danger');
    });
});

// Sayfa yüklendiğinde
document.addEventListener('DOMContentLoaded', function() {
    // Telefon formatlaması
    const phoneInputs = document.querySelectorAll('.phone-input');
    phoneInputs.forEach(input => {
        input.addEventListener('input', function(e) {
            // Sadece rakam kabul et
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    });
});
</script>

<script>
// Personel sayfası için şube bilgisine göre varsayılan il/ilçe
document.addEventListener('DOMContentLoaded', function() {
    <?php 
    // Personel için şube bilgisini varsayılan olarak ayarlama
    $branchInfo = [];
    if (!empty($_SESSION['branch_id'])) {
        $branchInfo = fetchOne("SELECT city, district FROM branches WHERE id = ?", [$_SESSION['branch_id']]);
    }
    ?>
    window.personnelDefaults = {
        city: '<?= e($branchInfo['city'] ?? '') ?>',
        district: '<?= e($branchInfo['district'] ?? '') ?>'
    };
});

// Personel ekleme modalı açıldığında şube varsayılanlarını ayarla
function showAddPersonnelModal() {
    document.getElementById('addPersonnelForm').reset();
    
    // Şube varsayılan il/ilçe değerlerini ayarla
    if (window.personnelDefaults && window.personnelDefaults.city) {
        setupCityDistrict('add_city', 'add_district', 
                         window.personnelDefaults.city, 
                         window.personnelDefaults.district);
    } else {
        setupCityDistrict('add_city', 'add_district', '', '');
    }
    
    new bootstrap.Modal(document.getElementById('addPersonnelModal')).show();
}
</script>

<?php require_once 'includes/footer.php'; ?>
<?php
// Handle AJAX requests FIRST
if (isset($_GET['ajax'])) {
    require_once 'config/database.php';
    require_once 'includes/functions.php';
    session_start();
    header('Content-Type: application/json');
    
    try {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isLoggedIn()) {
                echo json_encode(['success' => false, 'message' => 'Oturum süresi doldu']);
                exit;
            }
            
            $action = $_POST['action'] ?? 'add';
            
            if ($action === 'add') {
                $name = trim($_POST['name'] ?? '');
                $address = trim($_POST['address'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $city = trim($_POST['city'] ?? '');
                $district = trim($_POST['district'] ?? '');
                $password = $_POST['password'] ?? '';
                $companyId = $_SESSION['company_id'] ?? null;
                
                if (empty($name)) {
                    echo json_encode(['success' => false, 'message' => 'Şube adı gereklidir']);
                    exit;
                }
                
                if (empty($password)) {
                    echo json_encode(['success' => false, 'message' => 'Şube şifresi gereklidir']);
                    exit;
                }
                
                if (!$companyId) {
                    echo json_encode(['success' => false, 'message' => 'Şirket bilgisi bulunamadı']);
                    exit;
                }
                
                // Hash password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                $result = executeQuery(
                    "INSERT INTO branches (name, address, phone, email, city, district, password, company_id, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                    [$name, $address, $phone, $email, $city, $district, $hashedPassword, $companyId]
                );
                
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Şube başarıyla eklendi']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Şube eklenirken hata oluştu']);
                }
                exit;
                
            } elseif ($action === 'update') {
                $id = intval($_POST['id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $address = trim($_POST['address'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $city = trim($_POST['city'] ?? '');
                $district = trim($_POST['district'] ?? '');
                $password = $_POST['password'] ?? '';
                $companyId = $_SESSION['company_id'] ?? null;
                
                if (empty($name)) {
                    echo json_encode(['success' => false, 'message' => 'Şube adı gereklidir']);
                    exit;
                }
                
                if (!$companyId || !$id) {
                    echo json_encode(['success' => false, 'message' => 'Şirket veya şube bilgisi bulunamadı']);
                    exit;
                }
                
                // Build update query
                $updateFields = ['name = ?', 'address = ?', 'phone = ?', 'email = ?', 'city = ?', 'district = ?'];
                $updateParams = [$name, $address, $phone, $email, $city, $district];
                
                if (!empty($password)) {
                    $updateFields[] = 'password = ?';
                    $updateParams[] = password_hash($password, PASSWORD_DEFAULT);
                }
                
                $updateParams[] = $id;
                $updateParams[] = $companyId;
                
                $updateQuery = "UPDATE branches SET " . implode(', ', $updateFields) . " WHERE id = ? AND company_id = ?";
                
                $result = executeQuery($updateQuery, $updateParams);
                
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Şube başarıyla güncellendi']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Şube güncellenirken hata oluştu']);
                }
                exit;
            }
        } elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
            if (!isLoggedIn()) {
                echo json_encode(['success' => false, 'message' => 'Oturum süresi doldu']);
                exit;
            }
            
            if ($_GET['action'] === 'get_branch') {
                $id = intval($_GET['id'] ?? 0);
                $companyId = $_SESSION['company_id'] ?? null;
                
                if (!$companyId || !$id) {
                    echo json_encode(['success' => false, 'message' => 'Şirket veya şube bilgisi bulunamadı']);
                    exit;
                }
                
                $branch = fetchOne("SELECT * FROM branches WHERE id = ? AND company_id = ?", [$id, $companyId]);
                
                if ($branch) {
                    echo json_encode(['success' => true, 'branch' => $branch]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Şube bulunamadı']);
                }
                exit;
            }
        }
        
        echo json_encode(['success' => false, 'message' => 'Geçersiz istek']);
        exit;
    } catch (Exception $e) {
        error_log("Branches AJAX error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Bir hata oluştu']);
        exit;
    }
}

require_once 'includes/header.php';
require_once 'includes/functions.php';

requireLogin();

// Check permissions
$currentUser = $_SESSION;
if ($currentUser['role'] == 'technician') {
    header('Location: dashboard.php?error=no_permission');
    exit;
}

// Get company filter
$companyId = $_GET['company_id'] ?? '';

// Apply role-based filtering
if ($currentUser['role'] == 'company_admin') {
    $companyId = $currentUser['company_id'];
} elseif ($currentUser['role'] == 'branch_manager') {
    $companyId = $currentUser['company_id'];
}

if (!$companyId) {
    header('Location: companies.php?error=company_required');
    exit;
}

// Get company info
$company = fetchOne("SELECT * FROM companies WHERE id = ?", [$companyId]);
if (!$company) {
    header('Location: companies.php?error=company_not_found');
    exit;
}

// Get search parameters
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Build search query
$searchQuery = "";
$searchParams = [$companyId];

if ($search) {
    $searchQuery = " AND (b.name ILIKE ? OR b.address ILIKE ?)";
    $searchTerm = "%{$search}%";
    $searchParams[] = $searchTerm;
    $searchParams[] = $searchTerm;
}

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as total FROM branches b WHERE company_id = ?" . $searchQuery;
$totalResult = fetchOne($countQuery, $searchParams);
$totalCount = $totalResult['total'] ?? 0;
$totalPages = ceil($totalCount / $perPage);

// Get branches with user and customer counts
$query = "
    SELECT b.*, 
           (SELECT COUNT(*) FROM users WHERE branch_id = b.id) as user_count,
           (SELECT COUNT(*) FROM customers WHERE branch_id = b.id) as customer_count
    FROM branches b 
    WHERE b.company_id = ?
    $searchQuery
    ORDER BY b.name ASC 
    LIMIT $perPage OFFSET $offset
";

$branches = fetchAll($query, $searchParams);

$pageTitle = 'Şube Yönetimi - Serviso';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="page-header">
                    <h2 class="mb-0">Şube Yönetimi</h2>
                    <p class="page-subtitle"><?= e($company['name']) ?> şubelerini yönetin</p>
                </div>
            </div>
            <div class="col-lg-4 text-lg-end">
                <a href="companies.php" class="btn btn-outline-secondary me-2">
                    <i class="fas fa-arrow-left me-1"></i>Şirketlere Dön
                </a>
                <a href="branch_add.php" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>
                    Yeni Şube
                </a>
            </div>
        </div>

        <!-- Company Info -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="card bg-light">
                    <div class="card-body py-2">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <strong><i class="fas fa-building me-2"></i><?= e($company['name']) ?></strong>
                                <?php if ($company['city']): ?>
                                - <?= e($company['city']) ?>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4 text-md-end">
                                <small class="text-muted">Toplam <?= number_format($totalCount) ?> şube</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search -->
        <?php if ($totalCount > 5): ?>
        <div class="row mb-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <input type="hidden" name="company_id" value="<?= e($companyId) ?>">
                            <div class="col-md-8">
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Şube adı veya adres..." value="<?= e($search) ?>">
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-outline-primary flex-fill">
                                        <i class="fas fa-search me-1"></i>Ara
                                    </button>
                                    <?php if ($search): ?>
                                    <a href="branches.php?company_id=<?= e($companyId) ?>" class="btn btn-outline-secondary">
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
        <?php endif; ?>

        <!-- Branches Grid -->
        <?php if (empty($branches)): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-sitemap fa-3x text-muted mb-3"></i>
                <h5>Şube bulunamadı</h5>
                <p class="text-muted">
                    <?= $search ? 'Arama kriterlerinize uygun şube bulunamadı.' : 'Bu şirket için henüz hiç şube eklenmemiş.' ?>
                </p>
                <button class="btn btn-primary" onclick="showAddBranchModal()">
                    <i class="fas fa-plus me-1"></i>İlk Şubeyi Ekle
                </button>
            </div>
        </div>
        <?php else: ?>
        <div class="row">
            <?php foreach ($branches as $branch): ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-map-marker-alt text-primary me-2"></i>
                            <?= e($branch['name']) ?>
                        </h5>
                        
                        <?php if ($branch['address']): ?>
                        <p class="card-text text-muted small">
                            <i class="fas fa-map me-1"></i>
                            <?= e($branch['address']) ?>
                        </p>
                        <?php endif; ?>

                        <div class="row text-center mt-3">
                            <div class="col-6">
                                <div class="border-end">
                                    <h6 class="text-primary mb-0"><?= number_format($branch['user_count']) ?></h6>
                                    <small class="text-muted">Kullanıcı</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <h6 class="text-success mb-0"><?= number_format($branch['customer_count']) ?></h6>
                                <small class="text-muted">Müşteri</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-footer bg-transparent">
                        <div class="d-flex justify-content-between">
                            <a href="branch_edit.php?branch_id=<?= $branch['id'] ?>" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-edit me-1"></i>Düzenle
                            </a>
                            <a href="users.php?branch_id=<?= $branch['id'] ?>" 
                               class="btn btn-outline-info btn-sm">
                                <i class="fas fa-users me-1"></i>Kullanıcılar
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-center">
            <?= generatePagination($page, $totalPages, 'branches.php?' . http_build_query(['company_id' => $companyId, 'search' => $search, 'page' => ''])) ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Add Branch Modal -->
<div class="modal fade" id="addBranchModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Yeni Şube Ekle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addBranchForm" onsubmit="saveBranch(event)">
                <input type="hidden" name="company_id" value="<?= e($companyId) ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Şube Adı <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Adres</label>
                        <textarea class="form-control" name="address" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Telefon</label>
                            <input type="tel" class="form-control" name="phone" maxlength="11" placeholder="05320528000" pattern="[0-9]{10,11}" title="Sadece rakam giriniz (10-11 hane)">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">İl</label>
                            <select class="form-select" name="city" id="addBranchCitySelect">
                                <option value="">İl Seçiniz</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">İlçe</label>
                            <select class="form-select" name="district" id="addBranchDistrictSelect" disabled>
                                <option value="">İlçe Seçiniz</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Giriş Şifresi <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="password" required>
                        <div class="form-text">Bu şifre ile şube hesabına giriş yapılacak</div>
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

<!-- Edit Branch Modal -->
<div class="modal fade" id="editBranchModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Şube Düzenle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editBranchForm" onsubmit="updateBranch(event)">
                <input type="hidden" name="id" id="editBranchId">
                <input type="hidden" name="company_id" value="<?= e($companyId) ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Şube Adı <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" id="editBranchName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Adres</label>
                        <textarea class="form-control" name="address" id="editBranchAddress" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Telefon</label>
                            <input type="tel" class="form-control" name="phone" id="editBranchPhone" maxlength="11" placeholder="05320528000" pattern="[0-9]{10,11}" title="Sadece rakam giriniz (10-11 hane)">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" id="editBranchEmail">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">İl</label>
                            <select class="form-select" name="city" id="editBranchCitySelect">
                                <option value="">İl Seçiniz</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">İlçe</label>
                            <select class="form-select" name="district" id="editBranchDistrictSelect" disabled>
                                <option value="">İlçe Seçiniz</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Giriş Şifresi</label>
                        <input type="password" class="form-control" name="password">
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
function showAddBranchModal() {
    document.getElementById('addBranchForm').reset();
    
    // Load city/district dropdowns with company defaults
    setupCityDistrict('addBranchCitySelect', 'addBranchDistrictSelect', '<?= e($company['city']) ?>', '<?= e($company['district']) ?>');
    
    new bootstrap.Modal(document.getElementById('addBranchModal')).show();
}

function editBranch(branchId) {
    fetch(`branches.php?ajax=1&action=get_branch&id=${branchId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const branch = data.branch;
                // Fill edit modal
                document.getElementById('editBranchId').value = branch.id;
                document.getElementById('editBranchName').value = branch.name;
                document.getElementById('editBranchAddress').value = branch.address || '';
                document.getElementById('editBranchPhone').value = branch.phone || '';
                document.getElementById('editBranchEmail').value = branch.email || '';
                
                // Load city/district dropdowns and set values
                setupCityDistrict('editBranchCitySelect', 'editBranchDistrictSelect', branch.city, branch.district);
                
                new bootstrap.Modal(document.getElementById('editBranchModal')).show();
            } else {
                showAlert(data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Şube bilgileri yüklenirken hata oluştu', 'danger');
        });
}

function saveBranch(event) {
    event.preventDefault();
    
    const form = document.getElementById('addBranchForm');
    const formData = new FormData(form);
    
    // Show loading
    const submitBtn = event.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Kaydediliyor...';
    submitBtn.disabled = true;
    
    fetch('branches.php?ajax=1', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('addBranchModal')).hide();
            // Reload page to show new branch
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert(data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Şube eklenirken hata oluştu', 'danger');
    })
    .finally(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

function updateBranch(event) {
    event.preventDefault();
    
    const form = document.getElementById('editBranchForm');
    const formData = new FormData(form);
    formData.append('action', 'update');
    
    // Show loading
    const submitBtn = event.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Güncelleniyor...';
    submitBtn.disabled = true;
    
    fetch('branches.php?ajax=1', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('editBranchModal')).hide();
            // Reload page to show updated branch
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert(data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Şube güncellenirken hata oluştu', 'danger');
    })
    .finally(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

// Yeni sistem otomatik çalışıyor - bu fonksiyonlar artık gerekli değil
</script>

<script src="assets/js/cities_complete.js"></script>

<?php require_once 'includes/footer.php'; ?>
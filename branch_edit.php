<?php
// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database and auth functions first (before any output)
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'includes/functions.php';

requireLogin();

// Get branch ID from URL
$branchId = intval($_GET['branch_id'] ?? 0);

if (!$branchId) {
    header('Location: branches.php?error=invalid_branch');
    exit;
}

$currentUser = $_SESSION;
$companyId = $_SESSION['company_id'];

// Only super_admin and company_admin can edit branches
if (!in_array($currentUser['role'], ['super_admin', 'company_admin'])) {
    header('Location: branches.php?error=no_permission');
    exit;
}

// Get branch data with role-based filtering
list($branchQuery, $branchParams) = applyDataFilter(
    "SELECT b.*, c.name as company_name 
     FROM branches b 
     LEFT JOIN companies c ON b.company_id = c.id 
     WHERE b.id = ?", 
    [$branchId], 
    'b'
);

$branch = fetchOne($branchQuery, $branchParams);

if (!$branch) {
    header('Location: branches.php?error=branch_not_found');
    exit;
}

$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    // Validation
    if (empty($name)) $errors[] = 'Şube adı gereklidir.';
    if (empty($phone)) $errors[] = 'Telefon numarası gereklidir.';
    if (empty($city)) $errors[] = 'İl seçimi gereklidir.';
    if (empty($district)) $errors[] = 'İlçe seçimi gereklidir.';
    if (empty($address)) $errors[] = 'Adres gereklidir.';
    
    // Phone validation
    if ($phone && !preg_match('/^0[5-9]\d{9}$/', $phone)) {
        $errors[] = 'Geçerli bir Türkiye telefon numarası giriniz.';
    }
    
    // Check for duplicate phone (excluding current branch)
    if ($phone && $phone !== $branch['phone']) {
        $existingBranch = fetchOne("SELECT id FROM branches WHERE phone = ? AND company_id = ? AND id != ?", [$phone, $companyId, $branchId]);
        if ($existingBranch) {
            $errors[] = 'Bu telefon numarası zaten kayıtlı.';
        }
    }
    
    // Check for duplicate name (excluding current branch)
    if ($name && $name !== $branch['name']) {
        $existingName = fetchOne("SELECT id FROM branches WHERE name = ? AND company_id = ? AND id != ?", [$name, $companyId, $branchId]);
        if ($existingName) {
            $errors[] = 'Bu şube adı zaten kayıtlı.';
        }
    }
    
    // If no errors, update the branch
    if (empty($errors)) {
        try {
            $pdo = getPDO();
            
            // Prepare update query based on whether password is provided
            if (!empty($password)) {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    UPDATE branches 
                    SET name = ?, phone = ?, city = ?, district = ?, address = ?, password = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE id = ? AND company_id = ?
                ");
                $result = $stmt->execute([$name, $phone, $city, $district, $address, $hashedPassword, $branchId, $companyId]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE branches 
                    SET name = ?, phone = ?, city = ?, district = ?, address = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE id = ? AND company_id = ?
                ");
                $result = $stmt->execute([$name, $phone, $city, $district, $address, $branchId, $companyId]);
            }
            
            if ($result) {
                $success = true;
                // Refresh branch data
                $branch = fetchOne($branchQuery, $branchParams);
            } else {
                $errors[] = 'Şube güncellenirken bir hata oluştu.';
            }
        } catch (PDOException $e) {
            error_log("Branch update error: " . $e->getMessage());
            $errors[] = 'Veritabanı hatası: ' . $e->getMessage();
        }
    }
}

// Set page title and include header after all processing
$pageTitle = 'Şube Düzenle - Serviso';
require_once 'includes/header.php';
?>

<div class="main-content">
    <div class="container-fluid">
    <div class="row">
        <?php require_once 'includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-edit me-2"></i>
                    Şube Düzenle: <?= e($branch['name']) ?>
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="branches.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i>
                        Şubelere Dön
                    </a>
                </div>
            </div>

            <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                Şube başarıyla güncellendi!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-building me-2"></i>
                                Şube Bilgileri
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" class="needs-validation" novalidate>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="name" class="form-label">Şube Adı <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?= e($_POST['name'] ?? $branch['name']) ?>" required>
                                        <div class="invalid-feedback">Şube adı gereklidir.</div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="phone" class="form-label">Telefon <span class="text-danger">*</span></label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?= e($_POST['phone'] ?? $branch['phone']) ?>" 
                                               placeholder="05320528000" required maxlength="11" pattern="[0-9]{10,11}" title="Sadece rakam giriniz (10-11 hane)">
                                        <div class="invalid-feedback">Geçerli bir Türkiye telefon numarası giriniz.</div>
                                        <div class="form-text">11 haneli Türkiye telefon numarası formatında giriniz.</div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="city" class="form-label">İl <span class="text-danger">*</span></label>
                                        <select class="form-select" id="city" name="city" required>
                                            <option value="">İl Seçiniz</option>
                                        </select>
                                        <div class="invalid-feedback">İl seçimi gereklidir.</div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="district" class="form-label">İlçe <span class="text-danger">*</span></label>
                                        <select class="form-select" id="district" name="district" required disabled>
                                            <option value="">İlçe Seçiniz</option>
                                        </select>
                                        <div class="invalid-feedback">İlçe seçimi gereklidir.</div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="address" class="form-label">Adres <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="address" name="address" rows="3" required><?= e($_POST['address'] ?? $branch['address']) ?></textarea>
                                    <div class="invalid-feedback">Adres gereklidir.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="password" class="form-label">Yeni Şifre</label>
                                    <input type="password" class="form-control" id="password" name="password">
                                    <div class="form-text">Şifreyi değiştirmek istemiyorsanız boş bırakın.</div>
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <a href="branches.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-1"></i>
                                        İptal
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i>
                                        Güncelle
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Info Card -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                Şube Detayları
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="info-item mb-3">
                                <label class="text-muted small">Şirket:</label>
                                <div><?= e($branch['company_name']) ?></div>
                            </div>
                            
                            <div class="info-item mb-3">
                                <label class="text-muted small">Kayıt Tarihi:</label>
                                <div><?= formatDate($branch['created_at'], 'd.m.Y H:i') ?></div>
                            </div>
                            
                            <?php if ($branch['updated_at']): ?>
                            <div class="info-item mb-3">
                                <label class="text-muted small">Son Güncelleme:</label>
                                <div><?= formatDate($branch['updated_at'], 'd.m.Y H:i') ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <hr>
                            
                            <div class="d-grid gap-2">
                                <a href="branch_services.php?branch_id=<?= $branch['id'] ?>" class="btn btn-outline-info">
                                    <i class="fas fa-wrench me-1"></i>
                                    Şube Servislerini Görüntüle
                                </a>
                                
                                <a href="branch_customers.php?branch_id=<?= $branch['id'] ?>" class="btn btn-outline-success">
                                    <i class="fas fa-users me-1"></i>
                                    Şube Müşterilerini Görüntüle
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
// Form validation
(function() {
    'use strict';
    
    var form = document.querySelector('.needs-validation');
    if (form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    }
})();

// Phone number formatting
document.getElementById('phone').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length > 11) {
        value = value.slice(0, 11);
    }
    e.target.value = value;
});
</script>

<script src="assets/js/cities_complete.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize city/district dropdowns with current branch data
    setupCityDistrict('city', 'district', '<?= addslashes($_POST['city'] ?? $branch['city']) ?>', '<?= addslashes($_POST['district'] ?? $branch['district']) ?>');
});
</script>

<?php require_once 'includes/footer.php'; ?>
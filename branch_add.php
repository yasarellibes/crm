<?php
require_once 'includes/header.php';
require_once 'includes/functions.php';

requireLogin();

$currentUser = $_SESSION;

// Only super_admin and company_admin can add branches
if (!in_array($currentUser['role'], ['super_admin', 'company_admin'])) {
    header('Location: dashboard.php?error=no_permission');
    exit;
}

$companyId = $_SESSION['company_id'];
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
    if (empty($password)) $errors[] = 'Şifre gereklidir.';
    
    // Phone validation
    if ($phone && !preg_match('/^0[5-9]\d{9}$/', $phone)) {
        $errors[] = 'Geçerli bir Türkiye telefon numarası giriniz.';
    }
    
    // Check for duplicate phone
    if ($phone) {
        $existingBranch = fetchOne("SELECT id FROM branches WHERE phone = ? AND company_id = ?", [$phone, $companyId]);
        if ($existingBranch) {
            $errors[] = 'Bu telefon numarası zaten kayıtlı.';
        }
    }
    
    // Check for duplicate name in same company
    if ($name) {
        $existingName = fetchOne("SELECT id FROM branches WHERE name = ? AND company_id = ?", [$name, $companyId]);
        if ($existingName) {
            $errors[] = 'Bu şube adı zaten kayıtlı.';
        }
    }
    
    // If no errors, create the branch
    if (empty($errors)) {
        try {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                INSERT INTO branches (name, phone, city, district, address, password, company_id, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ");
            
            $result = $stmt->execute([$name, $phone, $city, $district, $address, $hashedPassword, $companyId]);
            
            if ($result) {
                $success = true;
                // Clear form data on success
                $name = $phone = $city = $district = $address = $password = '';
            } else {
                $errors[] = 'Şube kaydedilirken bir hata oluştu.';
            }
        } catch (PDOException $e) {
            error_log("Branch creation error: " . $e->getMessage());
            $errors[] = 'Veritabanı hatası: ' . $e->getMessage();
        }
    }
}
?>

<div class="container-fluid">
    <div class="row">
        <?php require_once 'includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-building me-2"></i>
                    Yeni Şube Ekle
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
                Şube başarıyla eklendi!
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
                                               value="<?= e($_POST['name'] ?? '') ?>" required>
                                        <div class="invalid-feedback">Şube adı gereklidir.</div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="phone" class="form-label">Telefon <span class="text-danger">*</span></label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?= e($_POST['phone'] ?? '') ?>" 
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
                                    <textarea class="form-control" id="address" name="address" rows="3" required><?= e($_POST['address'] ?? '') ?></textarea>
                                    <div class="invalid-feedback">Adres gereklidir.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="password" class="form-label">Şifre <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <div class="invalid-feedback">Şifre gereklidir.</div>
                                    <div class="form-text">Bu şifre şube girişi için kullanılacaktır.</div>
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <a href="branches.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-1"></i>
                                        İptal
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i>
                                        Şube Ekle
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
                                Şube Bilgileri
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="info-item mb-3">
                                <label class="text-muted small">Şirket:</label>
                                <div><?= e($currentUser['company_name']) ?></div>
                            </div>
                            
                            <div class="info-item mb-3">
                                <label class="text-muted small">Eklenen Kişi:</label>
                                <div><?= e($currentUser['name']) ?></div>
                            </div>
                            
                            <div class="info-item mb-3">
                                <label class="text-muted small">Rol:</label>
                                <div><?= getRoleDisplayName($currentUser['role']) ?></div>
                            </div>
                            
                            <hr>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-lightbulb me-2"></i>
                                <small>
                                    Yeni şube eklendikten sonra bu şubenin kendi giriş bilgileri olacaktır. 
                                    Şube yöneticileri bu bilgilerle sisteme giriş yapabilir.
                                </small>
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
    // Initialize city/district dropdowns
    setupCityDistrict('city', 'district', '<?= addslashes($_POST['city'] ?? '') ?>', '<?= addslashes($_POST['district'] ?? '') ?>');
});
</script>

<?php require_once 'includes/footer.php'; ?>
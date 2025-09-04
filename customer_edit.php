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

// Get customer ID from URL
$customerId = intval($_GET['customer_id'] ?? 0);

if (!$customerId) {
    header('Location: customers.php?error=invalid_customer');
    exit;
}

$companyId = $_SESSION['company_id'];
$currentUser = $_SESSION;

// Get customer data with role-based filtering
list($customerQuery, $customerParams) = applyDataFilter(
    "SELECT c.*, co.name as company_name, b.name as branch_name 
     FROM customers c 
     LEFT JOIN companies co ON c.company_id = co.id 
     LEFT JOIN branches b ON c.branch_id = b.id 
     WHERE c.id = ?", 
    [$customerId], 
    'c'
);

$customer = fetchOne($customerQuery, $customerParams);

if (!$customer) {
    header('Location: customers.php?error=customer_not_found');
    exit;
}

// Check if user has permission to edit this customer
if ($currentUser['role'] == 'technician') {
    header('Location: customers.php?error=no_permission');
    exit;
}

// Cities will be loaded via JavaScript from cities.js

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    // Get form data
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    // Validation (same as Flask system)
    if (empty($name)) $errors[] = 'Müşteri adı gereklidir.';
    if (empty($phone)) $errors[] = 'Telefon numarası gereklidir.';
    if (empty($city)) $errors[] = 'İl seçimi gereklidir.';
    if (empty($district)) $errors[] = 'İlçe seçimi gereklidir.';
    if (empty($address)) $errors[] = 'Adres gereklidir.';
    
    // Phone validation (same as Flask system)
    if ($phone && !preg_match('/^0[5-9]\d{9}$/', $phone)) {
        $errors[] = 'Geçerli bir Türkiye telefon numarası giriniz.';
    }
    
    // Check for duplicate phone (excluding current customer)
    if ($phone && $phone !== $customer['phone']) {
        $existingCustomer = fetchOne("SELECT id FROM customers WHERE phone = ? AND company_id = ? AND id != ?", [$phone, $companyId, $customerId]);
        if ($existingCustomer) {
            $errors[] = 'Bu telefon numarası zaten kayıtlı.';
        }
    }
    
    if (empty($errors)) {
        try {
            $pdo = getPDO();
            
            // Update customer
            $updateQuery = "
                UPDATE customers 
                SET name = ?, phone = ?, city = ?, district = ?, address = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ? AND company_id = ?
            ";
            $stmt = $pdo->prepare($updateQuery);
            $result = $stmt->execute([$name, $phone, $city, $district, $address, $customerId, $companyId]);
            
            if ($result) {
                header('Location: customers.php?success=customer_updated');
                exit;
            } else {
                $errors[] = 'Müşteri güncellenirken bir hata oluştu.';
            }
            
        } catch (Exception $e) {
            $errors[] = 'Veritabanı hatası: ' . $e->getMessage();
        }
    }
}

// Set page title and include header after all processing
$pageTitle = 'Müşteri Düzenle - Serviso';
require_once 'includes/header.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="page-header">
                    <h2 class="mb-0">Müşteri Düzenle</h2>
                    <p class="page-subtitle"><?= e($customer['name']) ?> müşteri kaydını güncelleyin</p>
                </div>
            </div>
            <div class="col-lg-4 text-lg-end">
                <a href="customers.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>
                    Müşterilere Geri Dön
                </a>
            </div>
        </div>
        
        <!-- Error Messages -->
        <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Edit Form -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-user me-2"></i>
                            Müşteri Bilgileri
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="needs-validation" novalidate>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Müşteri Adı <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?= e($_POST['name'] ?? $customer['name']) ?>" required>
                                    <div class="invalid-feedback">Müşteri adı gereklidir.</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Telefon <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?= e($_POST['phone'] ?? $customer['phone']) ?>" 
                                           placeholder="05320528000" required maxlength="11" pattern="[0-9]{10,11}" title="Sadece rakam giriniz (10-11 hane)" onblur="checkPhone()">
                                    <div class="invalid-feedback" id="phoneError">Geçerli bir Türkiye telefon numarası giriniz.</div>
                                    <div class="form-text" id="phoneMessage">11 haneli Türkiye telefon numarası formatında giriniz.</div>
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
                                <textarea class="form-control" id="address" name="address" rows="3" required><?= e($_POST['address'] ?? $customer['address']) ?></textarea>
                                <div class="invalid-feedback">Adres gereklidir.</div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="customers.php" class="btn btn-secondary">
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
            
            <!-- Customer Info Card -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            Müşteri Detayları
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="info-item mb-3">
                            <label class="text-muted small">Müşteri ID:</label>
                            <div class="fw-bold">#<?= $customer['id'] ?></div>
                        </div>
                        
                        <div class="info-item mb-3">
                            <label class="text-muted small">Şirket:</label>
                            <div><?= e($customer['company_name']) ?></div>
                        </div>
                        
                        <div class="info-item mb-3">
                            <label class="text-muted small">Şube:</label>
                            <div><?= e($customer['branch_name']) ?></div>
                        </div>
                        
                        <div class="info-item mb-3">
                            <label class="text-muted small">Kayıt Tarihi:</label>
                            <div><?= formatDate($customer['created_at'], 'd.m.Y H:i') ?></div>
                        </div>
                        
                        <?php if ($customer['updated_at']): ?>
                        <div class="info-item mb-3">
                            <label class="text-muted small">Son Güncelleme:</label>
                            <div><?= formatDate($customer['updated_at'], 'd.m.Y H:i') ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <hr>
                        
                        <div class="d-grid">
                            <a href="customer_services.php?customer_id=<?= $customer['id'] ?>" class="btn btn-outline-info">
                                <i class="fas fa-wrench me-1"></i>
                                Servislerini Görüntüle
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Phone check function (same as service add)
function checkPhone() {
    const phoneInput = document.getElementById('phone');
    const phoneMessage = document.getElementById('phoneMessage');
    
    const phone = phoneInput.value.trim();
    
    if (!phone) {
        phoneMessage.textContent = '11 haneli Türkiye telefon numarası formatında giriniz.';
        phoneMessage.className = 'form-text';
        return;
    }
    
    // Show checking message
    phoneMessage.textContent = 'Kontrol ediliyor...';
    phoneMessage.className = 'form-text text-info';
    
    fetch(`ajax/check_phone.php?phone=${encodeURIComponent(phone)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.exists) {
                    phoneMessage.textContent = 'Bu telefon numarası kayıtlı. Dikkatli olun!';
                    phoneMessage.className = 'form-text text-warning';
                } else if (data.valid) {
                    phoneMessage.textContent = 'Telefon numarası geçerli.';
                    phoneMessage.className = 'form-text text-success';
                } else {
                    phoneMessage.textContent = data.message;
                    phoneMessage.className = 'form-text text-danger';
                }
            } else {
                phoneMessage.textContent = data.message || 'Telefon kontrol edilemedi.';
                phoneMessage.className = 'form-text text-danger';
            }
        })
        .catch(error => {
            console.error('Phone check error:', error);
            phoneMessage.textContent = 'Telefon kontrol edilemedi.';
            phoneMessage.className = 'form-text text-warning';
        });
}

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

// Show alert function
function showAlert(message, type = 'info') {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    const container = document.querySelector('.container-fluid');
    const existingAlert = container.querySelector('.alert');
    if (existingAlert) {
        existingAlert.remove();
    }
    
    container.insertAdjacentHTML('afterbegin', alertHtml);
}
</script>

<script src="assets/js/cities_complete.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize city/district dropdowns with current customer data
    setupCityDistrict('city', 'district', '<?= addslashes($_POST['city'] ?? $customer['city']) ?>', '<?= addslashes($_POST['district'] ?? $customer['district']) ?>');
});
</script>

<?php require_once 'includes/footer.php'; ?>
<?php
/**
 * Add Customer Page
 * Equivalent to Flask customer_add.html functionality
 */

$pageTitle = 'Yeni Müşteri Ekle';
require_once 'includes/header.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    $errors = [];
    
    // Validation
    if (empty($name)) {
        $errors[] = 'Müşteri adı gereklidir';
    }
    
    if (empty($phone)) {
        $errors[] = 'Telefon numarası gereklidir';
    } elseif (!cleanPhoneNumber($phone)) {
        $errors[] = 'Geçerli bir telefon numarası giriniz';
    } else {
        $phone = cleanPhoneNumber($phone);
        
        // Check if phone already exists
        $existingCustomer = fetchOne("SELECT id FROM customers WHERE phone = ?", [$phone]);
        if ($existingCustomer) {
            $errors[] = 'Bu telefon numarası zaten kayıtlı';
        }
    }
    
    if ($email && !isValidEmail($email)) {
        $errors[] = 'Geçerli bir e-posta adresi giriniz';
    }
    
    if (empty($city)) {
        $errors[] = 'Şehir seçimi gereklidir';
    }
    
    if (empty($district)) {
        $errors[] = 'İlçe seçimi gereklidir';
    }
    
    if (empty($errors)) {
        try {
            // Get company and branch info from current user
            $companyId = getCompanyFilter();
            $branchId = getBranchFilter();
            
            // Insert customer
            $customerId = insertAndGetId("
                INSERT INTO customers (
                    company_id, branch_id, name, phone, email, 
                    city, district, address, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ", [
                $companyId, $branchId, $name, $phone, $email,
                $city, $district, $address, $notes
            ]);
            
            // Log activity
            logActivity('Customer Added', "Customer ID: $customerId, Name: $name");
            
            redirectWithMessage('customers.php', 'Müşteri başarıyla eklendi', 'success');
            
        } catch (Exception $e) {
            error_log("Customer add error: " . $e->getMessage());
            $errors[] = 'Müşteri eklenirken bir hata oluştu';
        }
    }
}
?>

<div class="row mb-4">
    <div class="col-lg-8">
        <div class="page-header">
            <h2 class="mb-0">
                <a href="customers.php" class="text-muted me-2" title="Müşterilere Dön">
                    <i class="fas fa-arrow-left"></i>
                </a>
                Yeni Müşteri Ekle
            </h2>
            <p class="page-subtitle">Yeni müşteri kaydı oluşturun</p>
        </div>
    </div>
</div>

<!-- Display errors -->
<?php if (!empty($errors)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-12">
        <div class="clean-table-container">
            <div class="card-header">
                <h5><i class="fas fa-user-plus me-2"></i>Müşteri Bilgileri</h5>
            </div>
            <div class="p-4">
                <form method="POST" action="">
                    <div class="row">
                        <!-- Name -->
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">
                                <i class="fas fa-user me-1"></i>
                                Müşteri Adı *
                            </label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?= e($_POST['name'] ?? '') ?>" required
                                   placeholder="Ad Soyad">
                        </div>
                        
                        <!-- Phone -->
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">
                                <i class="fas fa-phone me-1"></i>
                                Telefon *
                            </label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?= e($_POST['phone'] ?? '') ?>" required
                                   placeholder="05320528000" maxlength="11" pattern="[0-9]{10,11}" title="Sadece rakam giriniz (10-11 hane)">
                        </div>
                    </div>
                    
                    <div class="row">
                        <!-- Email -->
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">
                                <i class="fas fa-envelope me-1"></i>
                                E-posta
                            </label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?= e($_POST['email'] ?? '') ?>"
                                   placeholder="ornek@email.com">
                        </div>
                        
                        <!-- City -->
                        <div class="col-md-3 mb-3">
                            <label for="city" class="form-label">
                                <i class="fas fa-map-marker-alt me-1"></i>
                                Şehir *
                            </label>
                            <select class="form-select" id="city" name="city" required>
                                <option value="">İl Seçin</option>
                            </select>
                        </div>
                        
                        <!-- District -->
                        <div class="col-md-3 mb-3">
                            <label for="district" class="form-label">
                                <i class="fas fa-map me-1"></i>
                                İlçe *
                            </label>
                            <select class="form-select" id="district" name="district" required disabled>
                                <option value="">İlçe Seçin</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <!-- Address -->
                        <div class="col-12 mb-3">
                            <label for="address" class="form-label">
                                <i class="fas fa-home me-1"></i>
                                Adres Detayı
                            </label>
                            <textarea class="form-control" id="address" name="address" rows="2" 
                                      placeholder="Mahalle, sokak, bina no vb. detaylar..."><?= e($_POST['address'] ?? '') ?></textarea>
                        </div>
                    </div>
                    
                    <div class="row">
                        <!-- Notes -->
                        <div class="col-12 mb-3">
                            <label for="notes" class="form-label">
                                <i class="fas fa-sticky-note me-1"></i>
                                Notlar
                            </label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" 
                                      placeholder="Müşteri hakkında özel notlar..."><?= e($_POST['notes'] ?? '') ?></textarea>
                        </div>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>
                                Müşteriyi Kaydet
                            </button>
                            <a href="customers.php" class="btn btn-secondary ms-2">
                                <i class="fas fa-times me-2"></i>
                                İptal
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Format phone number input
    const phoneInput = document.getElementById('phone');
    phoneInput.addEventListener('input', function(e) {
        formatPhoneNumber(e.target);
    });
    
    // City-District integration could be added here
    // For now, keeping it simple with manual input
});
</script>

<script src="assets/js/cities_complete.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Get default city from company
    <?php 
    $defaults = getCompanyDefaults();
    $defaultCity = $_POST['city'] ?? $defaults['city'];
    $defaultDistrict = $_POST['district'] ?? $defaults['district'];
    ?>
    // Initialize city/district dropdowns with company defaults
    setupCityDistrict('city', 'district', '<?= e($defaultCity) ?>', '<?= e($defaultDistrict) ?>');
});
</script>

<?php require_once 'includes/footer.php'; ?>
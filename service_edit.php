<?php
// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database and auth functions first (before any output)
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'includes/functions.php';

// Check if user is logged in
requireLogin();

// Get current user information
$currentUser = getCurrentUser();

// Get service ID from URL - ignore other parameters
$serviceId = null;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $serviceId = (int)$_GET['id'];
} else {
    // Check if this is a form submission for adding definitions
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || 
        (isset($_GET['name']) && isset($_GET['type']))) {
        // This is not a service edit request, redirect to avoid confusion
        header('Location: services.php');
        exit;
    }
}

if (!$serviceId) {
    header('Location: services.php?error=service_not_found');
    exit;
}

// Get service data with filters applied
$serviceQuery = "
    SELECT s.*, c.name as customer_name, c.phone as customer_phone, 
           c.city as customer_city, c.district as customer_district, c.address as customer_address,
           p.name as personnel_name, p.id as personnel_id, b.name as branch_name, co.name as company_name
    FROM services s
    JOIN customers c ON s.customer_id = c.id
    LEFT JOIN personnel p ON s.personnel_id = p.id
    LEFT JOIN branches b ON s.branch_id = b.id
    LEFT JOIN companies co ON s.company_id = co.id
    WHERE s.id = ?
";

// Apply data filter based on user role
list($serviceQuery, $serviceParams) = applyDataFilter($serviceQuery, [$serviceId], 's');

$service = fetchOne($serviceQuery, $serviceParams);

if (!$service) {
    header('Location: services.php?error=service_not_found');
    exit;
}

// Check if user can access this service data
if (!canAccessData(null, $service['company_id'], $service['branch_id'])) {
    header('Location: services.php?error=no_permission');
    exit;
}

// Get form data for dropdowns with brand-model relationship
try {
    $devices = fetchAll("SELECT id, name FROM devices WHERE company_id = ? ORDER BY name", [$service['company_id']]);
    $brands = fetchAll("SELECT id, name FROM brands WHERE company_id = ? ORDER BY name", [$service['company_id']]);
    
    // Get current brand ID and name - service might store brand ID or brand name
    $currentBrandId = null;
    $currentBrandName = null;
    
    if (!empty($service['brand'])) {
        if (is_numeric($service['brand'])) {
            // Service stores brand ID
            $currentBrandId = (int)$service['brand'];
            $brandData = fetchOne("SELECT name FROM brands WHERE id = ? AND company_id = ?", [$currentBrandId, $service['company_id']]);
            $currentBrandName = $brandData['name'] ?? null;
        } else {
            // Service stores brand name
            $currentBrandName = $service['brand'];
            $brandData = fetchOne("SELECT id FROM brands WHERE name = ? AND company_id = ?", [$service['brand'], $service['company_id']]);
            $currentBrandId = $brandData['id'] ?? null;
        }
    }
    
    // Get models for current brand or all models as fallback
    if ($currentBrandId) {
        $models = fetchAll("SELECT id, name FROM models WHERE brand_id = ? AND company_id = ? ORDER BY name", [$currentBrandId, $service['company_id']]);
    } else {
        $models = fetchAll("SELECT id, name FROM models WHERE company_id = ? ORDER BY name", [$service['company_id']]);
    }
    
    $complaints = fetchAll("SELECT id, name FROM complaints WHERE company_id = ? ORDER BY name", [$service['company_id']]);
    $operations = fetchAll("SELECT id, name FROM operations WHERE company_id = ? ORDER BY name", [$service['company_id']]);
} catch (Exception $e) {
    // Fallback data if tables don't exist
    $devices = [['id' => 1, 'name' => 'Kombi'], ['id' => 2, 'name' => 'Klima']];
    $brands = [['id' => 1, 'name' => 'Arçelik'], ['id' => 2, 'name' => 'Bosch'], ['id' => 3, 'name' => 'Bilinmiyor']];
    $models = [['id' => 1, 'name' => 'Bilinmiyor']];
    $complaints = [['id' => 1, 'name' => 'Çalışmıyor'], ['id' => 2, 'name' => 'Ses yapıyor']];
    $operations = [['id' => 1, 'name' => 'Beklemede'], ['id' => 2, 'name' => 'Tamamlandı']];
    $currentBrandId = null;
}

// Get branches for current company
$branches = fetchAll("SELECT id, name FROM branches WHERE company_id = ? ORDER BY name", [$service['company_id']]);

// Get personnel (role-based filtering) - will be updated via AJAX based on branch selection
$personnel = getFilteredPersonnel();

// Get cities
// Cities will be loaded via JavaScript from cities_complete.js

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updateCustomerInfo = isset($_POST['update_customer_info']) && $_POST['update_customer_info'] == '1';
    $canUpdateCustomer = in_array($currentUser['role'], ['super_admin', 'company_admin', 'branch_manager']);
    $errors = [];
    
    // Get form data
    $customerName = trim($_POST['customer_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $device = trim($_POST['device'] ?? '');
    $brandId = trim($_POST['brand'] ?? '');
    $model = trim($_POST['model'] ?? '');
    
    // Convert brand ID to name for consistent storage
    $brandName = '';
    if ($brandId && is_numeric($brandId)) {
        $brandData = fetchOne("SELECT name FROM brands WHERE id = ? AND company_id = ?", [$brandId, $service['company_id']]);
        $brandName = $brandData['name'] ?? '';
    }
    $complaint = trim($_POST['complaint'] ?? '');
    $serviceDate = trim($_POST['service_date'] ?? '');
    $selectedBranchId = trim($_POST['branch_id'] ?? '') ?: null;
    $description = trim($_POST['description'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $operationStatus = trim($_POST['operation_status'] ?? '');
    $personnelId = trim($_POST['personnel_id'] ?? '') ?: null;
    
    // Validation
    if (empty($customerName)) $errors[] = 'Müşteri adı gereklidir.';
    if (empty($phone)) $errors[] = 'Telefon numarası gereklidir.';
    if (empty($city)) $errors[] = 'İl seçimi gereklidir.';
    if (empty($district)) $errors[] = 'İlçe seçimi gereklidir.';
    if (empty($address)) $errors[] = 'Adres gereklidir.';
    if (empty($device)) $errors[] = 'Cihaz seçimi gereklidir.';
    if (empty($brandId)) $errors[] = 'Marka seçimi gereklidir.';
    if (empty($model)) $errors[] = 'Model seçimi gereklidir.';
    if (empty($complaint)) $errors[] = 'Arıza seçimi gereklidir.';
    if (empty($serviceDate)) $errors[] = 'Servis tarihi gereklidir.';
    if (empty($operationStatus)) $errors[] = 'İşlem durumu seçimi gereklidir.';
    
    // Phone validation
    if ($phone && !preg_match('/^0[5-9]\d{9}$/', $phone)) {
        $errors[] = 'Geçerli bir telefon numarası giriniz (05XXXXXXXXX).';
    }
    
    // Price validation
    if ($price && !is_numeric($price)) {
        $errors[] = 'Fiyat sayısal bir değer olmalıdır.';
    }
    
    if (empty($errors)) {
        try {
            // Use the database connection
            $pdo = getPDO();
            $pdo->beginTransaction();
            
            // Update customer information only if authorized and checkbox is checked
            if ($updateCustomerInfo && $canUpdateCustomer) {
                $updateCustomerQuery = "
                    UPDATE customers 
                    SET name = ?, phone = ?, city = ?, district = ?, address = ?
                    WHERE id = ?
                ";
                $stmt = $pdo->prepare($updateCustomerQuery);
                $stmt->execute([$customerName, $phone, $city, $district, $address, $service['customer_id']]);
            }
            
            // Update service information - include branch_id
            $serviceBranchId = $selectedBranchId ?: $service['branch_id'];
            $updateServiceQuery = "
                UPDATE services 
                SET device = ?, brand = ?, model = ?, complaint = ?, 
                    service_date = ?, description = ?, price = ?, 
                    operation_status = ?, personnel_id = ?, branch_id = ?
                WHERE id = ?
            ";
            $stmt = $pdo->prepare($updateServiceQuery);
            $stmt->execute([
                $device, $brandName ?: $brandId, $model, $complaint, 
                $serviceDate, $description, $price ? floatval($price) : null, 
                $operationStatus, $personnelId, $serviceBranchId, $serviceId
            ]);
            
            $pdo->commit();
            
            $successMessage = 'service_updated';
            if ($updateCustomerInfo && $canUpdateCustomer) {
                $successMessage = 'service_and_customer_updated';
            }
            
            header('Location: services.php?success=' . $successMessage);
            exit;
            
        } catch (Exception $e) {
            if (isset($pdo)) $pdo->rollBack();
            $errors[] = 'Servis güncellenirken bir hata oluştu: ' . $e->getMessage();
        }
    }
}

// Set page title and include header after all processing
$pageTitle = 'Servis Düzenle - Serviso';
require_once 'includes/header.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="page-header">
                    <h2 class="mb-0">Servis Düzenle</h2>
                    <p class="page-subtitle">Servis kaydını güncelleyin</p>
                </div>
            </div>
            <div class="col-lg-4 text-lg-end">
                <a href="services.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>
                    Servislere Geri Dön
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
        <form method="POST" class="needs-validation" novalidate>
            <div class="row">
                <!-- Customer Information -->
                <div class="col-lg-6">
                    <div class="form-section">
                        <h5 class="section-title">
                            <i class="fas fa-user me-2"></i>
                            Müşteri Bilgileri
                            <?php if (in_array($currentUser['role'], ['super_admin', 'company_admin', 'branch_manager'])): ?>
                            <div class="form-check form-switch d-inline ms-3">
                                <input class="form-check-input" type="checkbox" id="updateCustomerInfo" name="update_customer_info" value="1">
                                <label class="form-check-label small text-muted" for="updateCustomerInfo">
                                    Müşteri bilgilerini de güncelle
                                </label>
                            </div>
                            <?php endif; ?>
                        </h5>
                        
                        <?php if ($currentUser['role'] == 'technician'): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <small>Teknisyen olarak sadece servis detaylarını güncelleyebilirsiniz. Müşteri bilgileri sadece görüntülenebilir.</small>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="customer_name" class="form-label">Müşteri Adı <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="customer_name" name="customer_name" 
                                   value="<?= e($service['customer_name']) ?>" required
                                   <?= $currentUser['role'] == 'technician' ? 'readonly' : '' ?>
                                   onchange="toggleCustomerUpdateWarning()">
                            <div class="invalid-feedback">Müşteri adı gereklidir.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="phone" class="form-label">Telefon <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?= e($service['customer_phone']) ?>" 
                                   placeholder="05320528000" required maxlength="11" pattern="[0-9]{10,11}" title="Sadece rakam giriniz (10-11 hane)"
                                   onblur="checkPhone()" onchange="toggleCustomerUpdateWarning()"
                                   <?= $currentUser['role'] == 'technician' ? 'readonly' : '' ?>>
                            <div class="invalid-feedback" id="phoneError">Geçerli bir Türkiye telefon numarası giriniz (05XXXXXXXXX).</div>
                            <div class="form-text" id="phoneMessage">11 haneli Türkiye telefon numarası formatında giriniz.</div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="city" class="form-label">İl <span class="text-danger">*</span></label>
                                <select class="form-select" id="city" name="city" required
                                       onchange="toggleCustomerUpdateWarning()"
                                       <?= $currentUser['role'] == 'technician' ? 'disabled' : '' ?>>
                                    <option value="">İl Seçiniz</option>

                                </select>
                                <div class="invalid-feedback">İl seçimi gereklidir.</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="district" class="form-label">İlçe <span class="text-danger">*</span></label>
                                <select class="form-select" id="district" name="district" required disabled
                                       onchange="toggleCustomerUpdateWarning()"
                                       <?= $currentUser['role'] == 'technician' ? 'disabled' : '' ?>>
                                    <option value="">İlçe Seçiniz</option>
                                </select>
                                <div class="invalid-feedback">İlçe seçimi gereklidir.</div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">Adres <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="address" name="address" rows="3" required
                                     onchange="toggleCustomerUpdateWarning()"
                                     <?= $currentUser['role'] == 'technician' ? 'readonly' : '' ?>><?= e($service['customer_address']) ?></textarea>
                            <div class="invalid-feedback">Adres gereklidir.</div>
                        </div>
                        
                        <?php if (in_array($currentUser['role'], ['super_admin', 'company_admin', 'branch_manager'])): ?>
                        <div class="alert alert-warning d-none" id="customerUpdateWarning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <small>Müşteri bilgilerini değiştirdiniz. Bu değişikliklerin müşteri veritabanına kaydedilmesi için yukarıdaki "Müşteri bilgilerini de güncelle" seçeneğini işaretleyin.</small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Service Information -->
                <div class="col-lg-6">
                    <div class="form-section">
                        <h5 class="section-title">
                            <i class="fas fa-wrench me-2"></i>
                            Servis Bilgileri
                        </h5>
                        
                        <div class="mb-3">
                            <label for="device" class="form-label">Cihaz <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <select class="form-select" id="device" name="device" required
                                       <?= $currentUser['role'] == 'technician' ? 'disabled' : '' ?>>
                                    <option value="">Cihaz Seçiniz</option>
                                    <?php foreach ($devices as $device): ?>
                                    <option value="<?= e($device['name']) ?>" <?= $device['name'] == $service['device'] ? 'selected' : '' ?>>
                                        <?= e($device['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($currentUser['role'] != 'technician'): ?>
                                <button type="button" class="btn btn-outline-primary" onclick="addNewItem('device')">
                                    <i class="fas fa-plus"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="invalid-feedback">Cihaz seçimi gereklidir.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="brand" class="form-label">Marka <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <select class="form-select" id="brand" name="brand" required onchange="updateModelsByBrand()"
                                       <?= $currentUser['role'] == 'technician' ? 'disabled' : '' ?>>
                                    <option value="">Marka Seçiniz</option>
                                    <?php foreach ($brands as $brand): ?>
                                    <option value="<?= e($brand['id']) ?>" data-name="<?= e($brand['name']) ?>" <?= ($brand['id'] == $currentBrandId || $brand['name'] == $currentBrandName) ? 'selected' : '' ?>>
                                        <?= e($brand['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($currentUser['role'] != 'technician'): ?>
                                <button type="button" class="btn btn-outline-primary" onclick="addNewItem('brand')">
                                    <i class="fas fa-plus"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="invalid-feedback">Marka seçimi gereklidir.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="model" class="form-label">Model <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <select class="form-select" id="model" name="model" required
                                       <?= $currentUser['role'] == 'technician' ? 'disabled' : '' ?>>
                                    <option value="">Model Seçiniz</option>
                                    <?php foreach ($models as $model): ?>
                                    <option value="<?= e($model['name']) ?>" <?= ($model['name'] == $service['model'] || $model['id'] == $service['model']) ? 'selected' : '' ?>>
                                        <?= e($model['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($currentUser['role'] != 'technician'): ?>
                                <button type="button" class="btn btn-outline-primary" onclick="addNewItem('model')">
                                    <i class="fas fa-plus"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="invalid-feedback">Model seçimi gereklidir.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="complaint" class="form-label">Arıza <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <select class="form-select" id="complaint" name="complaint" required
                                       <?= $currentUser['role'] == 'technician' ? 'disabled' : '' ?>>
                                    <option value="">Arıza Seçiniz</option>
                                    <?php foreach ($complaints as $complaint): ?>
                                    <option value="<?= e($complaint['name']) ?>" <?= $complaint['name'] == $service['complaint'] ? 'selected' : '' ?>>
                                        <?= e($complaint['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($currentUser['role'] != 'technician'): ?>
                                <button type="button" class="btn btn-outline-primary" onclick="addNewItem('complaint')">
                                    <i class="fas fa-plus"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="invalid-feedback">Arıza seçimi gereklidir.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="service_date" class="form-label">Servis Tarihi <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="service_date" name="service_date" 
                                   value="<?= e(date('Y-m-d', strtotime($service['service_date']))) ?>" required
                                   <?= $currentUser['role'] == 'technician' ? 'readonly' : '' ?>>
                            <div class="invalid-feedback">Servis tarihi gereklidir.</div>
                        </div>
                        
                        <!-- Branch Selection -->
                        <div class="mb-3">
                            <label for="branch_id" class="form-label">Şube</label>
                            <select class="form-select" id="branch_id" name="branch_id" onchange="updatePersonnelByBranch()"
                                   <?= $currentUser['role'] == 'technician' ? 'disabled' : '' ?>>
                                <option value="">Şube Seçiniz</option>
                                <?php if (!empty($branches)): ?>
                                    <?php foreach ($branches as $branch): ?>
                                    <option value="<?= e($branch['id']) ?>" <?= ($branch['id'] == $service['branch_id']) ? 'selected' : '' ?>>
                                        <?= e($branch['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <small class="form-text text-muted">Mevcut şube: <?= e($service['branch_name'] ?? 'Atanmamış') ?></small>
                        </div>
                        
                        <!-- Personnel Selection -->
                        <div class="mb-3">
                            <label for="personnel_id" class="form-label">Personel</label>
                            <select class="form-select" id="personnel_id" name="personnel_id"
                                   <?= $currentUser['role'] == 'technician' ? 'disabled' : '' ?>>
                                <option value="">Personel Seçiniz</option>
                                <?php if (!empty($personnel)): ?>
                                    <?php foreach ($personnel as $person): ?>
                                    <option value="<?= e($person['id']) ?>" <?= ($person['id'] == $service['personnel_id']) ? 'selected' : '' ?>>
                                        <?= e($person['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <small class="form-text text-muted">Şube seçimi yapıldıktan sonra o şubeye ait personeller listelenir</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="operation_status" class="form-label">İşlem Durumu <span class="text-danger">*</span></label>
                            <select class="form-select" id="operation_status" name="operation_status" required>
                                <option value="">Durum Seçiniz</option>
                                <?php foreach ($operations as $operation): ?>
                                <option value="<?= e($operation['name']) ?>" <?= $operation['name'] == $service['operation_status'] ? 'selected' : '' ?>>
                                    <?= e($operation['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">İşlem durumu seçimi gereklidir.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Açıklama</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?= e($service['description']) ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="price" class="form-label">Fiyat (₺)</label>
                            <input type="number" class="form-control" id="price" name="price" 
                                   value="<?= e($service['price']) ?>" step="0.01" min="0">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="d-flex gap-2 justify-content-end">
                        <a href="services.php" class="btn btn-light">
                            <i class="fas fa-times me-1"></i>
                            İptal
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>
                            Güncelle
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Hidden inputs for technicians to preserve readonly values -->
<?php if ($currentUser['role'] == 'technician'): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add hidden inputs for disabled fields
    const form = document.querySelector('form');
    const disabledSelects = form.querySelectorAll('select[disabled]');
    
    disabledSelects.forEach(select => {
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = select.name;
        hiddenInput.value = select.value;
        form.appendChild(hiddenInput);
    });
});
</script>
<?php endif; ?>

<script>
// Delete service function for the dropdown
function deleteService(serviceId) {
    if (confirm('Bu servisi silmek istediğinizden emin misiniz?')) {
        window.location.href = 'service_delete.php?id=' + serviceId;
    }
}

// Form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();

// Check phone number (same as Flask system)
function checkPhone() {
    const phoneInput = document.getElementById('phone');
    const phoneMessage = document.getElementById('phoneMessage');
    const customerNameInput = document.getElementById('customer_name');
    const cityInput = document.getElementById('city');
    const districtInput = document.getElementById('district');
    const addressInput = document.getElementById('address');
    
    // Skip check if field is readonly (technician)
    if (phoneInput.hasAttribute('readonly')) {
        return;
    }
    
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
                    // Customer exists, show info
                    phoneMessage.textContent = data.message;
                    phoneMessage.className = 'form-text text-success';
                } else if (data.valid) {
                    // Valid new phone
                    phoneMessage.textContent = data.message;
                    phoneMessage.className = 'form-text text-success';
                } else {
                    // Invalid phone format
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

// Update models when brand changes
function updateModelsByBrand() {
    const brandSelect = document.getElementById('brand');
    const modelSelect = document.getElementById('model');
    
    const brandId = brandSelect.value;
    
    if (!brandId) {
        modelSelect.innerHTML = '<option value="">Önce marka seçiniz</option>';
        return;
    }
    
    // Load models for selected brand
    fetch(`ajax/get_models_by_brand.php?brand_id=${brandId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const currentModel = modelSelect.value; // Preserve current selection
                modelSelect.innerHTML = '<option value="">Model Seçiniz</option>';
                
                data.models.forEach(model => {
                    const option = new Option(model.name, model.name);
                    if (model.name === currentModel || model.name === '<?= addslashes($service['model']) ?>') {
                        option.selected = true;
                    }
                    modelSelect.add(option);
                });
                
                // Add "Bilinmiyor" option if not exists
                if (!data.models.find(m => m.name === 'Bilinmiyor')) {
                    const unknownOption = new Option('Bilinmiyor', 'Bilinmiyor');
                    if ('Bilinmiyor' === currentModel) {
                        unknownOption.selected = true;
                    }
                    modelSelect.add(unknownOption);
                }
            } else {
                modelSelect.innerHTML = '<option value="">Model yüklenemedi</option>';
            }
        })
        .catch(error => {
            console.error('Error loading models:', error);
            modelSelect.innerHTML = '<option value="">Model yüklenemedi</option>';
        });
}

// Add new item functionality
function addNewItem(type) {
    // For model, check if brand is selected
    if (type === 'model') {
        const brandSelect = document.getElementById('brand');
        if (!brandSelect.value) {
            showAlert('Model eklemek için önce marka seçiniz!', 'warning');
            return;
        }
    }
    
    const modal = new bootstrap.Modal(document.getElementById('addItemModal'));
    const typeText = {
        'device': 'Cihaz',
        'brand': 'Marka', 
        'model': 'Model',
        'complaint': 'Arıza'
    };
    
    document.getElementById('itemTypeText').textContent = typeText[type];
    document.getElementById('itemTypeLabel').textContent = typeText[type];
    document.getElementById('itemType').value = type;
    document.getElementById('newItemName').value = '';
    
    // Set brand ID for models
    if (type === 'model') {
        const brandSelect = document.getElementById('brand');
        document.getElementById('selectedBrandId').value = brandSelect.value;
    }
    
    modal.show();
}

// Handle add item form submission  
document.addEventListener('DOMContentLoaded', function() {
    const addItemForm = document.getElementById('addItemForm');
    if (addItemForm) {
        addItemForm.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const type = formData.get('type');
    const name = formData.get('name');
    
    // Send AJAX request to add new item  
    fetch('ajax/add_definition.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Add new option to the select
            const select = document.getElementById(type);
            const option = new Option(name, name, true, true);
            select.add(option);
            
            // Close modal
            bootstrap.Modal.getInstance(document.getElementById('addItemModal')).hide();
            
            // Show success message
            showAlert('Başarıyla eklendi!', 'success');
        } else {
            showAlert(data.message || 'Ekleme işlemi başarısız!', 'danger');
        }
    })
    .catch(error => {
        showAlert('Bir hata oluştu!', 'danger');
        console.error('Error:', error);
    });
        });
    }
});

// Update personnel when branch changes
function updatePersonnelByBranch() {
    const branchSelect = document.getElementById('branch_id');
    const personnelSelect = document.getElementById('personnel_id');
    
    if (!branchSelect.value) {
        personnelSelect.innerHTML = '<option value="">Personel Seçiniz</option>';
        return;
    }
    
    const branchId = branchSelect.value;
    personnelSelect.innerHTML = '<option value="">Yükleniyor...</option>';
    
    fetch(`ajax/get_personnel_by_branch.php?branch_id=${branchId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                personnelSelect.innerHTML = '<option value="">Personel Seçiniz</option>';
                data.personnel.forEach(person => {
                    const option = document.createElement('option');
                    option.value = person.id;
                    option.textContent = person.name;
                    personnelSelect.appendChild(option);
                });
            } else {
                personnelSelect.innerHTML = '<option value="">Personel bulunamadı</option>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            personnelSelect.innerHTML = '<option value="">Personel yüklenemedi</option>';
        });
}

// Show alert function
function showAlert(message, type) {
    // Create alert container if not exists
    let alertContainer = document.getElementById('alert-container');
    if (!alertContainer) {
        alertContainer = document.createElement('div');
        alertContainer.id = 'alert-container';
        alertContainer.style.position = 'fixed';
        alertContainer.style.top = '20px';
        alertContainer.style.right = '20px';
        alertContainer.style.zIndex = '9999';
        document.body.appendChild(alertContainer);
    }
    
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show`;
    alert.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    alertContainer.appendChild(alert);
    
    setTimeout(() => {
        alert.remove();
    }, 5000);
}

// Customer update warning system
function toggleCustomerUpdateWarning() {
    const warning = document.getElementById('customerUpdateWarning');
    const checkbox = document.getElementById('updateCustomerInfo');
    
    if (warning && checkbox) {
        warning.classList.remove('d-none');
        // Auto-check the checkbox when customer info is modified
        checkbox.checked = true;
    }
}
</script>

<!-- Add New Item Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1" aria-labelledby="addItemModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addItemModalLabel">Yeni <span id="itemTypeText"></span> Ekle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addItemForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="newItemName" class="form-label"><span id="itemTypeLabel"></span> Adı</label>
                        <input type="text" class="form-control" id="newItemName" name="name" required>
                        <input type="hidden" id="itemType" name="type">
                        <input type="hidden" id="selectedBrandId" name="brand_id">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Ekle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="assets/js/cities_complete.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize city/district dropdowns with current service data
    setupCityDistrict('city', 'district', '<?= addslashes($service['customer_city']) ?>', '<?= addslashes($service['customer_district']) ?>');
    
    // Initialize brand and model dropdowns
    <?php if ($currentBrandId): ?>
    // Set the brand dropdown to current brand and load models
    const brandSelect = document.getElementById('brand');
    if (brandSelect) {
        brandSelect.value = '<?= $currentBrandId ?>';
        updateModelsByBrand(); // This will load models for the current brand
    }
    <?php endif; ?>
});
</script>

<?php require_once 'includes/footer.php'; ?>
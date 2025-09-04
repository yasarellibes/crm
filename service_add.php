<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files first
require_once 'config/auth.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

// Require login
requireLogin();

// Check if user has permission to add services (not for technicians in some cases)
// All users can add services, but technicians might have limitations

// Get form data for dropdowns
$companyId = $_SESSION['company_id'];
$branchId = $_SESSION['branch_id'];

// Get dropdown data with error handling
try {
    $devices = fetchAll("SELECT id, name FROM devices WHERE company_id = ? ORDER BY name", [$companyId]);
    $brands = fetchAll("SELECT id, name FROM brands WHERE company_id = ? ORDER BY name", [$companyId]);
    $models = []; // Models will be loaded dynamically based on brand selection
    $complaints = fetchAll("SELECT id, name FROM complaints WHERE company_id = ? ORDER BY name", [$companyId]);
    $operations = fetchAll("SELECT id, name FROM operations WHERE company_id = ? ORDER BY name", [$companyId]);
} catch (Exception $e) {
    // Fallback data if tables don't exist
    $devices = [['id' => 1, 'name' => 'Kombi'], ['id' => 2, 'name' => 'Klima'], ['id' => 3, 'name' => 'Çamaşır Makinesi']];
    $brands = [['id' => 1, 'name' => 'Arçelik'], ['id' => 2, 'name' => 'Bosch'], ['id' => 3, 'name' => 'Siemens'], ['id' => 4, 'name' => 'Bilinmiyor']];
    $models = [['id' => 1, 'name' => 'Bilinmiyor']];
    $complaints = [['id' => 1, 'name' => 'Çalışmıyor'], ['id' => 2, 'name' => 'Ses yapıyor'], ['id' => 3, 'name' => 'Su akıtıyor']];
    $operations = [['id' => 1, 'name' => 'İşlem Bekliyor'], ['id' => 2, 'name' => 'Devam Ediyor'], ['id' => 3, 'name' => 'Tamamlandı']];
}

// Get branches for current company - branch managers see only their own branch
if ($_SESSION['role'] === 'branch_manager') {
    $branches = fetchAll("SELECT id, name FROM branches WHERE company_id = ? AND id = ? ORDER BY name", [$companyId, $branchId]);
} else {
    $branches = fetchAll("SELECT id, name FROM branches WHERE company_id = ? ORDER BY name", [$companyId]);
}

// Get personnel (role-based filtering) - will be updated via AJAX based on branch selection
if ($_SESSION['role'] === 'branch_manager') {
    // Branch managers see only their branch's personnel
    $personnel = fetchAll("SELECT id, name FROM personnel WHERE company_id = ? AND branch_id = ? ORDER BY name", [$companyId, $branchId]);
} else {
    $personnel = [];
}

// Get branch default city
$defaultCity = '';
$defaultDistrict = '';
if ($branchId) {
    $branchInfo = fetchOne("SELECT city, district FROM branches WHERE id = ?", [$branchId]);
    if ($branchInfo) {
        $defaultCity = $branchInfo['city'];
        $defaultDistrict = $branchInfo['district'];
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    // Get form data
    $customerName = trim($_POST['customer_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $device = trim($_POST['device'] ?? '');
    $brand = trim($_POST['brand'] ?? '');
    $model = trim($_POST['model'] ?? '');
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
    if (empty($brand)) $errors[] = 'Marka seçimi gereklidir.';
    if (empty($model)) $errors[] = 'Model seçimi gereklidir.';
    if (empty($complaint)) $errors[] = 'Arıza seçimi gereklidir.';
    if (empty($serviceDate)) $errors[] = 'Servis tarihi gereklidir.';
    if (empty($operationStatus)) $errors[] = 'İşlem durumu seçimi gereklidir.';
    
    // Phone validation (same as Flask system)
    if ($phone && !preg_match('/^0[5-9]\d{9}$/', $phone)) {
        $errors[] = 'Geçerli bir Türkiye telefon numarası giriniz.';
    }
    
    // Price validation
    if ($price && !is_numeric($price)) {
        $errors[] = 'Fiyat sayısal bir değer olmalıdır.';
    }
    
    if (empty($errors)) {
        try {
            $pdo = getPDO();
            $pdo->beginTransaction();
            
            // Check if customer already exists by phone (same logic as Flask system)
            $existingCustomer = fetchOne("SELECT id FROM customers WHERE phone = ? AND company_id = ?", [$phone, $companyId]);
            
            if ($existingCustomer) {
                // Update existing customer info (same as Flask)
                $customerId = $existingCustomer['id'];
                $updateCustomerQuery = "
                    UPDATE customers 
                    SET name = ?, city = ?, district = ?, address = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE id = ? AND company_id = ?
                ";
                $stmt = $pdo->prepare($updateCustomerQuery);
                $stmt->execute([$customerName, $city, $district, $address, $customerId, $companyId]);
            } else {
                // Insert new customer
                $insertCustomerQuery = "
                    INSERT INTO customers (name, phone, city, district, address, company_id, branch_id, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                ";
                $stmt = $pdo->prepare($insertCustomerQuery);
                $stmt->execute([$customerName, $phone, $city, $district, $address, $companyId, $branchId]);
                $customerId = $pdo->lastInsertId();
            }
            
            // Insert service
            $insertServiceQuery = "
                INSERT INTO services (customer_id, device, brand, model, complaint, 
                                    service_date, description, price, operation_status, 
                                    personnel_id, company_id, branch_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";
            $stmt = $pdo->prepare($insertServiceQuery);
            // Use selected branch if provided, otherwise use current user's branch
            $serviceBranchId = $selectedBranchId ?: $branchId;
            $stmt->execute([
                $customerId, $device, $brand, $model, $complaint, 
                $serviceDate, $description, $price ? floatval($price) : null, 
                $operationStatus, $personnelId, $companyId, $serviceBranchId
            ]);
            
            $pdo->commit();
            
            header('Location: services.php?success=service_added');
            exit;
            
        } catch (Exception $e) {
            if (isset($pdo)) $pdo->rollBack();
            $errors[] = 'Servis eklenirken bir hata oluştu: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Servis Ekle - Serviso';

// Include header after all processing is done
require_once 'includes/header.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="page-header">
                    <h2 class="mb-0">Yeni Servis Ekle</h2>
                    <p class="page-subtitle">Müşteri ve servis bilgilerini girin</p>
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
        
        <!-- Add Form -->
        <form method="POST" class="needs-validation" novalidate>
            <div class="row">
                <!-- Customer Information -->
                <div class="col-lg-6">
                    <div class="form-section">
                        <h5 class="section-title">
                            <i class="fas fa-user me-2"></i>
                            Müşteri Bilgileri
                        </h5>
                        
                        <div class="mb-3">
                            <label for="customer_name" class="form-label">Müşteri Adı <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="customer_name" name="customer_name" 
                                   value="<?= e($_POST['customer_name'] ?? '') ?>" required>
                            <div class="invalid-feedback">Müşteri adı gereklidir.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="phone" class="form-label">Telefon <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?= e($_POST['phone'] ?? '') ?>" 
                                   placeholder="05320528000" required onblur="checkPhone()" oninput="handlePhoneInput()"
                                   maxlength="11" pattern="[0-9]{10,11}" title="Sadece rakam giriniz (10-11 hane)">
                            <div class="invalid-feedback" id="phoneError">Geçerli bir Türkiye telefon numarası giriniz (05XXXXXXXXX).</div>
                            <div class="form-text" id="phoneMessage">11 haneli Türkiye telefon numarası formatında giriniz.</div>
                            
                            <!-- Customer Found Message -->
                            <div id="customerFound" class="mt-2" style="display: none;">
                                <div class="alert alert-info py-2 px-3 mb-0">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div>
                                            <i class="fas fa-user me-2"></i>
                                            <strong>Kayıtlı Müşteri:</strong> 
                                            <span id="customerName" class="text-primary ms-1"></span>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-primary" id="fillCustomerInfo">
                                            <i class="fas fa-magic me-1"></i>Bilgileri Doldur
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Customer Info Display -->
                            <div id="customerInfo" class="mt-2" style="display: none;">
                                <div class="alert alert-info p-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span>Kayıtlı Müşteri: <strong id="customerName" class="text-primary" style="cursor: pointer;" onclick="fillCustomerData()"></strong></span>
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="fillCustomerData()">
                                            <i class="fas fa-fill me-1"></i>Bilgileri Doldur
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="city" class="form-label">İl <span class="text-danger">*</span></label>
                                <select class="form-select" id="city" name="city" required>
                                    <option value="">İl Seçin</option>
                                </select>
                                <div class="invalid-feedback">İl seçimi gereklidir.</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="district" class="form-label">İlçe <span class="text-danger">*</span></label>
                                <select class="form-select" id="district" name="district" required disabled>
                                    <option value="">İlçe Seçin</option>
                                </select>
                                <div class="invalid-feedback">İlçe seçimi gereklidir.</div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">Adres <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="address" name="address" rows="3" required><?= e($_POST['address'] ?? '') ?></textarea>
                            <div class="invalid-feedback">Adres gereklidir.</div>
                        </div>
                    </div>
                </div>
                
                <!-- Service Information -->
                <div class="col-lg-6">
                    <div class="form-section">
                        <h5 class="section-title">
                            <i class="fas fa-wrench me-2"></i>
                            Servis Bilgileri
                        </h5>
                        
                        <!-- Device with Add Button -->
                        <div class="mb-3">
                            <label for="device" class="form-label">Cihaz <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <select class="form-select" id="device" name="device" required>
                                    <option value="">Cihaz Seçiniz</option>
                                    <?php if (!empty($devices)): ?>
                                        <?php foreach ($devices as $device): ?>
                                        <option value="<?= e($device['name']) ?>" <?= ($device['name'] == ($_POST['device'] ?? '')) ? 'selected' : '' ?>>
                                            <?= e($device['name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="Kombi">Kombi</option>
                                        <option value="Klima">Klima</option>
                                        <option value="Çamaşır Makinesi">Çamaşır Makinesi</option>
                                    <?php endif; ?>
                                </select>
                                <button type="button" class="btn btn-outline-primary" onclick="addNewItem('device')">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback">Cihaz seçimi gereklidir.</div>
                        </div>
                        
                        <!-- Brand with Add Button -->
                        <div class="mb-3">
                            <label for="brand" class="form-label">Marka <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <select class="form-select" id="brand" name="brand" required onchange="updateModelsByBrand()">
                                    <option value="">Marka Seçiniz</option>
                                    <?php if (!empty($brands)): ?>
                                        <?php foreach ($brands as $brand): ?>
                                        <option value="<?= e($brand['id']) ?>" data-name="<?= e($brand['name']) ?>" <?= ($brand['name'] == ($_POST['brand'] ?? '')) ? 'selected' : '' ?>>
                                            <?= e($brand['name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="1">Arçelik</option>
                                        <option value="2">Bosch</option>
                                        <option value="3">Siemens</option>
                                    <?php endif; ?>
                                </select>
                                <button type="button" class="btn btn-outline-primary" onclick="addNewItem('brand')">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback">Marka seçimi gereklidir.</div>
                        </div>
                        
                        <!-- Model with Add Button -->
                        <div class="mb-3">
                            <label for="model" class="form-label">Model <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <select class="form-select" id="model" name="model" required disabled>
                                    <option value="">Önce marka seçiniz</option>
                                </select>
                                <button type="button" class="btn btn-outline-primary" onclick="addNewItem('model')" id="addModelBtn" disabled>
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback">Model seçimi gereklidir.</div>
                        </div>
                        
                        <!-- Complaint with Add Button -->
                        <div class="mb-3">
                            <label for="complaint" class="form-label">Arıza <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <select class="form-select" id="complaint" name="complaint" required>
                                    <option value="">Arıza Seçiniz</option>
                                    <?php foreach ($complaints as $complaint): ?>
                                    <option value="<?= e($complaint['name']) ?>" <?= ($complaint['name'] == ($_POST['complaint'] ?? '')) ? 'selected' : '' ?>>
                                        <?= e($complaint['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn btn-outline-primary" onclick="addNewItem('complaint')">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback">Arıza seçimi gereklidir.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="service_date" class="form-label">Servis Tarihi <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="service_date" name="service_date" 
                                   value="<?= e($_POST['service_date'] ?? date('Y-m-d')) ?>" required>
                            <div class="invalid-feedback">Servis tarihi gereklidir.</div>
                        </div>
                        
                        <!-- Branch Selection -->
                        <div class="mb-3">
                            <label for="branch_id" class="form-label">Şube</label>
                            <select class="form-select" id="branch_id" name="branch_id" onchange="updatePersonnelByBranch()">
                                <option value="">Şube Seçiniz</option>
                                <?php if (!empty($branches)): ?>
                                    <?php foreach ($branches as $branch): ?>
                                    <option value="<?= e($branch['id']) ?>" <?= ($branch['id'] == ($_POST['branch_id'] ?? $branchId)) ? 'selected' : '' ?>>
                                        <?= e($branch['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <small class="form-text text-muted">Boş bırakılırsa varsayılan şube kullanılır</small>
                        </div>
                        
                        <!-- Personnel Selection -->
                        <div class="mb-3">
                            <label for="personnel_id" class="form-label">Personel</label>
                            <select class="form-select" id="personnel_id" name="personnel_id">
                                <option value="">Personel Seçiniz</option>
                                <?php if (!empty($personnel)): ?>
                                    <?php foreach ($personnel as $person): ?>
                                    <option value="<?= e($person['id']) ?>" <?= ($person['id'] == ($_POST['personnel_id'] ?? '')) ? 'selected' : '' ?>>
                                        <?= e($person['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <small class="form-text text-muted">Şube seçimi yapıldıktan sonra o şubeye ait personeller listelenir</small>
                        </div>
                        
                        <!-- Operation Status with Add Button -->
                        <div class="mb-3">
                            <label for="operation_status" class="form-label">İşlem Durumu <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <select class="form-select" id="operation_status" name="operation_status" required>
                                    <option value="">Durum Seçiniz</option>
                                    <?php foreach ($operations as $operation): ?>
                                    <option value="<?= e($operation['name']) ?>" <?= ($operation['name'] == ($_POST['operation_status'] ?? '')) ? 'selected' : '' ?>>
                                        <?= e($operation['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn btn-outline-primary" onclick="addNewItem('operation')">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback">İşlem durumu seçimi gereklidir.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Açıklama</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?= e($_POST['description'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="price" class="form-label">Fiyat (₺)</label>
                            <input type="number" class="form-control" id="price" name="price" 
                                   value="<?= e($_POST['price'] ?? '') ?>" step="0.01" min="0">
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
                            Kaydet
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

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

<script>
// Check phone number (same as Flask system)
function checkPhone() {
    const phoneInput = document.getElementById('phone');
    const phoneMessage = document.getElementById('phoneMessage');
    const customerNameInput = document.getElementById('customer_name');
    const cityInput = document.getElementById('city');
    const districtInput = document.getElementById('district');
    const addressInput = document.getElementById('address');
    
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
            console.log('Phone check response:', data);
            const customerInfo = document.getElementById('customerInfo');
            const customerName = document.getElementById('customerName');
            
            if (data.success) {
                if (data.exists) {
                    // Customer exists - show customer info (don't hide phoneMessage)
                    phoneMessage.textContent = 'Kayıtlı müşteri bulundu';
                    phoneMessage.className = 'form-text text-success';
                    phoneMessage.style.display = 'block';
                    
                    const customerFound = document.getElementById('customerFound');
                    const customerNameSpan = document.getElementById('customerName');
                    
                    if (data.customer && data.customer.name) {
                        customerNameSpan.textContent = data.customer.name;
                        customerFound.style.display = 'block';
                        
                        // Store customer data for filling
                        window.currentCustomerData = data.customer;
                        console.log('Customer data stored:', data.customer);
                    }
                    
                } else if (data.valid) {
                    // Valid phone but not registered - hide customer found alert
                    document.getElementById('customerFound').style.display = 'none';
                    phoneMessage.textContent = 'Telefon numarası kayıtlı değil - Yeni müşteri';
                    phoneMessage.className = 'form-text text-warning';
                    phoneMessage.style.display = 'block';
                    window.currentCustomerData = null;
                } else {
                    // Invalid phone format
                    document.getElementById('customerFound').style.display = 'none';
                    phoneMessage.textContent = data.message;
                    phoneMessage.className = 'form-text text-danger';
                    phoneMessage.style.display = 'block';
                    window.currentCustomerData = null;
                }
            } else {
                document.getElementById('customerFound').style.display = 'none';
                phoneMessage.textContent = data.message || 'Telefon kontrol edilemedi.';
                phoneMessage.className = 'form-text text-danger';
                phoneMessage.style.display = 'block';
                window.currentCustomerData = null;
            }
        })
        .catch(error => {
            console.error('Phone check error:', error);
            document.getElementById('customerFound').style.display = 'none';
            phoneMessage.textContent = 'Telefon kontrol edilemedi.';
            phoneMessage.className = 'form-text text-warning';
            phoneMessage.style.display = 'block';
        });
}

// Handle phone input changes
function handlePhoneInput() {
    const phoneInput = document.getElementById('phone');
    const phoneMessage = document.getElementById('phoneMessage');
    const customerFound = document.getElementById('customerFound');
    
    // Clear previous customer data when phone changes
    window.currentCustomerData = null;
    
    // Hide customer found alert
    if (customerFound) {
        customerFound.style.display = 'none';
    }
    
    // Reset phone message
    phoneMessage.textContent = '11 haneli Türkiye telefon numarası formatında giriniz.';
    phoneMessage.className = 'form-text';
    phoneMessage.style.display = 'block';
    
    // If phone is empty or too short, don't check
    const phone = phoneInput.value.trim();
    if (phone.length < 11) {
        return;
    }
    
    // Auto-check phone when 11 digits are entered
    if (phone.length === 11 && /^0[5-9]\d{9}$/.test(phone)) {
        setTimeout(() => checkPhone(), 300); // Small delay for better UX
    }
}

// Fill customer info when button is clicked
document.addEventListener('DOMContentLoaded', function() {
    const fillCustomerBtn = document.getElementById('fillCustomerInfo');
    if (fillCustomerBtn) {
        fillCustomerBtn.addEventListener('click', function() {
            if (window.currentCustomerData) {
                const customer = window.currentCustomerData;
                
                // Fill customer name
                if (customer.name) {
                    document.getElementById('customer_name').value = customer.name;
                    document.getElementById('customer_name').classList.add('is-valid');
                }
                
                // Fill city
                if (customer.city) {
                    const citySelect = document.getElementById('city');
                    citySelect.value = customer.city;
                    
                    // Trigger change event to load districts using the setupCityDistrict system
                    const changeEvent = new Event('change', { bubbles: true });
                    citySelect.dispatchEvent(changeEvent);
                    
                    // Fill district after districts are loaded
                    if (customer.district) {
                        setTimeout(() => {
                            const districtSelect = document.getElementById('district');
                            if (districtSelect && !districtSelect.disabled) {
                                districtSelect.value = customer.district;
                                console.log('District filled:', customer.district);
                            } else {
                                console.log('District select not ready, retrying...');
                                // Retry after longer delay
                                setTimeout(() => {
                                    if (districtSelect && !districtSelect.disabled) {
                                        districtSelect.value = customer.district;
                                        console.log('District filled on retry:', customer.district);
                                    }
                                }, 300);
                            }
                        }, 200);
                    }
                }
                
                // Fill address
                if (customer.address) {
                    document.getElementById('address').value = customer.address;
                }
                
                // Show success message
                const alert = document.querySelector('#customerFound .alert');
                alert.classList.remove('alert-info');
                alert.classList.add('alert-success');
                alert.innerHTML = `
                    <div class="d-flex align-items-center">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Müşteri Bilgileri Dolduruldu:</strong> 
                        <span class="text-success ms-1">${customer.name}</span>
                    </div>
                `;
                
                // Hide the alert after 3 seconds
                setTimeout(() => {
                    document.getElementById('customerFound').style.display = 'none';
                }, 3000);
            }
        });
    }
});

// Cities system handled by cities_complete.js

// Update models when brand changes
function updateModelsByBrand() {
    const brandSelect = document.getElementById('brand');
    const modelSelect = document.getElementById('model');
    const addModelBtn = document.getElementById('addModelBtn');
    
    const brandId = brandSelect.value;
    
    if (!brandId) {
        modelSelect.innerHTML = '<option value="">Önce marka seçiniz</option>';
        modelSelect.disabled = true;
        addModelBtn.disabled = true;
        return;
    }
    
    // Enable model select and add button
    modelSelect.disabled = false;
    addModelBtn.disabled = false;
    
    // Load models for selected brand
    fetch(`ajax/get_models_by_brand.php?brand_id=${brandId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                modelSelect.innerHTML = '<option value="">Model Seçiniz</option>';
                
                data.models.forEach(model => {
                    const option = new Option(model.name, model.name);
                    modelSelect.add(option);
                });
                
                // Don't add "Bilinmiyor" automatically - user can add it manually if needed
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
    
    // Close any existing modal first
    const existingModal = bootstrap.Modal.getInstance(document.getElementById('addItemModal'));
    if (existingModal) {
        existingModal.hide();
    }
    
    // Wait a bit then open new modal to avoid conflicts
    setTimeout(() => {
        const modal = new bootstrap.Modal(document.getElementById('addItemModal'));
        const typeText = {
            'device': 'Cihaz',
            'brand': 'Marka', 
            'model': 'Model',
            'complaint': 'Arıza',
            'operation': 'İşlem Durumu'
        };
        
        document.getElementById('itemTypeText').textContent = typeText[type];
        document.getElementById('itemTypeLabel').textContent = typeText[type];
        document.getElementById('itemType').value = type;
        document.getElementById('newItemName').value = '';
        
        // Set brand ID for models - get fresh value
        if (type === 'model') {
            const brandSelect = document.getElementById('brand');
            const selectedBrandId = brandSelect.value;
            const selectedBrandName = brandSelect.options[brandSelect.selectedIndex].text;
            document.getElementById('selectedBrandId').value = selectedBrandId;
            console.log('Setting brand ID for model:', selectedBrandId, 'Brand name:', selectedBrandName);
        }
        
        modal.show();
    }, 100);
}

// Handle add item form submission
document.getElementById('addItemForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    
    // Show loading state
    submitBtn.disabled = true;
    submitBtn.textContent = 'İşleniyor...';
    
    const formData = new FormData(this);
    const type = formData.get('type');
    const name = formData.get('name');
    
    // Add brand_id for models
    if (type === 'model') {
        const brandId = document.getElementById('selectedBrandId').value;
        formData.append('brand_id', brandId);
        console.log('Adding brand_id to FormData:', brandId);
    }
    
    // Send AJAX request to add new item
    fetch('ajax/add_definition.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        // Check if response is 401 (session expired)
        if (response.status === 401) {
            throw new Error('Session expired');
        }
        // Check if response is HTML (redirect to login)
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('text/html')) {
            throw new Error('Session expired');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // For models, update the model select for current brand
            if (type === 'model') {
                updateModelsByBrand();
                // Select the newly added model
                setTimeout(() => {
                    const modelSelect = document.getElementById('model');
                    modelSelect.value = name;
                }, 100);
            } else if (type === 'brand') {
                // Add new brand option and update models
                const brandSelect = document.getElementById('brand');
                const option = new Option(name, data.id, true, true);
                brandSelect.add(option);
                
                // Enable model controls since brand is now selected
                const modelSelect = document.getElementById('model');
                const addModelBtn = document.getElementById('addModelBtn');
                modelSelect.disabled = false;
                addModelBtn.disabled = false;
                
                // Update models for the newly selected brand
                updateModelsByBrand();
            } else {
                // Add new option to the select
                const select = document.getElementById(type);
                const option = new Option(name, name, true, true);
                select.add(option);
            }
            
            // Reset button
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
            
            // Close modal properly
            const modalInstance = bootstrap.Modal.getInstance(document.getElementById('addItemModal'));
            if (modalInstance) {
                modalInstance.hide();
            }
            
            // Show success message
            showAlert('Başarıyla eklendi!', 'success');
        } else {
            // Reset button on error
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
            showAlert(data.message || 'Ekleme işlemi başarısız!', 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        // Reset button on error
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
        
        if (error.message === 'Session expired') {
            showAlert('Oturum süresi doldu. Lütfen tekrar giriş yapın.', 'warning');
            setTimeout(() => {
                window.location.href = 'login.php';
            }, 2000);
        } else {
            showAlert('Bir hata oluştu!', 'danger');
        }
    });
});

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
    const alertContainer = document.getElementById('alert-container');
    if (alertContainer) {
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
}
</script>

<script src="assets/js/cities_complete.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php $companyDefaults = getCompanyDefaults(); ?>
    setupCityDistrict('city', 'district', '<?= addslashes($companyDefaults['city']) ?>', '<?= addslashes($companyDefaults['district']) ?>');
});
</script>

<?php require_once 'includes/footer.php'; ?>
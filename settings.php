<?php
// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files but not header yet
require_once 'config/auth.php';
require_once 'includes/functions.php';

requireLogin();

// Only super admin can access system settings
if ($_SESSION['role'] !== 'super_admin') {
    header('Location: dashboard.php?error=no_permission');
    exit;
}

$currentUser = $_SESSION;
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// Handle form submission BEFORE any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_system') {
        $errors = [];
        
        // Get form data
        $systemName = trim($_POST['system_name'] ?? '');
        $systemLogo = trim($_POST['system_logo'] ?? '');
        $companyName = trim($_POST['company_name'] ?? '');
        $companyAddress = trim($_POST['company_address'] ?? '');
        $companyPhone = trim($_POST['company_phone'] ?? '');
        $companyEmail = trim($_POST['company_email'] ?? '');
        
        // Validation
        if (!$systemName) $errors[] = 'Sistem adı gereklidir.';
        if (!$companyName) $errors[] = 'Şirket adı gereklidir.';
        
        if (empty($errors)) {
            try {
                require_once 'config/database.php';
                $pdo = getPDO();
                
                // Update or insert system settings
                $settings = [
                    'system_name' => $systemName,
                    'system_logo' => $systemLogo,
                    'company_name' => $companyName,
                    'company_address' => $companyAddress,
                    'company_phone' => $companyPhone,
                    'company_email' => $companyEmail
                ];
                
                foreach ($settings as $key => $value) {
                    $stmt = $pdo->prepare("
                        INSERT INTO system_settings (setting_key, setting_value, updated_by, updated_at)
                        VALUES (?, ?, ?, CURRENT_TIMESTAMP)
                        ON CONFLICT (setting_key) 
                        DO UPDATE SET 
                            setting_value = EXCLUDED.setting_value,
                            updated_by = EXCLUDED.updated_by,
                            updated_at = CURRENT_TIMESTAMP
                    ");
                    $stmt->execute([$key, $value, $currentUser['user_id'] ?? null]);
                }
                
                header('Location: settings.php?success=system_updated');
                exit;
                
            } catch (Exception $e) {
                $errors[] = 'Ayarlar güncellenirken hata oluştu: ' . $e->getMessage();
            }
        }
    }
}

// Get current system settings
$systemSettings = [];
try {
    $settings = fetchAll("SELECT setting_key, setting_value FROM system_settings", []);
    foreach ($settings as $setting) {
        $systemSettings[$setting['setting_key']] = $setting['setting_value'];
    }
} catch (Exception $e) {
    // Settings table might not exist yet
}

// Default values
$defaults = [
    'system_name' => 'Serviso HVAC Yönetim Sistemi',
    'system_logo' => '',
    'company_name' => 'Demo Şirketi',
    'company_address' => '',
    'company_phone' => '',
    'company_email' => ''
];

foreach ($defaults as $key => $value) {
    if (!isset($systemSettings[$key])) {
        $systemSettings[$key] = $value;
    }
}

// Now include header after form processing is complete
require_once 'includes/header.php';

$pageTitle = 'Sistem Ayarları - Serviso';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="page-header">
                    <h2 class="mb-0">Sistem Ayarları</h2>
                    <p class="page-subtitle">Sistem genelindeki ayarları yönetin</p>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($success === 'system_updated'): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i>
            Sistem ayarları başarıyla güncellendi.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

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

        <div class="row">
            <!-- System Settings -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-cog me-2"></i>
                            Genel Sistem Ayarları
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_system">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="system_name" class="form-label">Sistem Adı <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="system_name" name="system_name" 
                                           value="<?= e($systemSettings['system_name']) ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="system_logo" class="form-label">Logo URL</label>
                                    <input type="url" class="form-control" id="system_logo" name="system_logo" 
                                           value="<?= e($systemSettings['system_logo']) ?>"
                                           placeholder="https://example.com/logo.png">
                                </div>
                            </div>
                            
                            <hr class="my-4">
                            
                            <h6 class="fw-bold mb-3">
                                <i class="fas fa-building me-2"></i>Şirket Bilgileri
                            </h6>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="company_name" class="form-label">Şirket Adı <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="company_name" name="company_name" 
                                           value="<?= e($systemSettings['company_name']) ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="company_phone" class="form-label">Telefon</label>
                                    <input type="tel" class="form-control" id="company_phone" name="company_phone" 
                                           value="<?= e($systemSettings['company_phone']) ?>" maxlength="11" placeholder="05320528000" pattern="[0-9]{10,11}" title="Sadece rakam giriniz (10-11 hane)">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="company_email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="company_email" name="company_email" 
                                       value="<?= e($systemSettings['company_email']) ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="company_address" class="form-label">Adres</label>
                                <textarea class="form-control" id="company_address" name="company_address" rows="3"><?= e($systemSettings['company_address']) ?></textarea>
                            </div>
                            
                            <div class="d-flex justify-content-end gap-2">
                                <button type="reset" class="btn btn-outline-secondary">
                                    <i class="fas fa-undo me-1"></i>
                                    Sıfırla
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>
                                    Kaydet
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Settings Info -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            Ayar Bilgileri
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <strong>Bilgi:</strong> Bu ayarlar tüm sistem genelinde geçerlidir. Değişikliklerin etkili olması için sayfayı yenilemeniz gerekebilir.
                        </div>
                        
                        <h6 class="fw-bold mb-3">Erişim Seviyeleri:</h6>
                        <ul class="list-unstyled">
                            <li class="mb-2">
                                <i class="fas fa-crown text-danger me-2"></i>
                                <strong>Süper Admin:</strong> Tüm ayarlara erişim
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-user-tie text-warning me-2"></i>
                                <strong>Şirket Yöneticisi:</strong> Şirket ayarlarına erişim
                            </li>
                        </ul>
                    </div>
                </div>
                
                <!-- System Status -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-server me-2"></i>
                            Sistem Durumu
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6 border-end">
                                <h5 class="text-success mb-0">
                                    <i class="fas fa-check-circle"></i>
                                </h5>
                                <small class="text-muted">Veritabanı</small>
                            </div>
                            <div class="col-6">
                                <h5 class="text-success mb-0">
                                    <i class="fas fa-check-circle"></i>
                                </h5>
                                <small class="text-muted">Uygulama</small>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="text-center">
                            <small class="text-muted">
                                Versiyon: 1.0.0<br>
                                PHP: <?= PHP_VERSION ?><br>
                                Güncel
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
<?php
/**
 * Simple Password Change Page
 */

session_start();
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'includes/functions.php';

requireLogin();

// Get current user data based on user type
if (!isset($_SESSION['email'])) {
    header('Location: login.php');
    exit;
}

$currentUser = null;

if ($_SESSION['user_type'] === 'company') {
    $currentUser = fetchOne("SELECT *, 'company_admin' as role FROM companies WHERE email = ?", [$_SESSION['email']]);
} elseif ($_SESSION['user_type'] === 'branch') {
    $currentUser = fetchOne("SELECT *, 'branch_manager' as role FROM branches WHERE email = ?", [$_SESSION['email']]);
} elseif ($_SESSION['user_type'] === 'user') {
    $currentUser = fetchOne("SELECT * FROM personnel WHERE email = ?", [$_SESSION['email']]);
    if (!$currentUser) {
        $currentUser = fetchOne("SELECT * FROM users WHERE email = ?", [$_SESSION['email']]);
    }
}

if (!$currentUser) {
    error_log("Profile error: User not found for email: " . $_SESSION['email']);
    header('Location: logout.php');
    exit;
}

$errors = [];
$success = '';

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Password change validation
    if (empty($currentPassword)) {
        $errors[] = 'Mevcut şifrenizi giriniz';
    }
    
    if (empty($newPassword)) {
        $errors[] = 'Yeni şifre gereklidir';
    }
    
    if (strlen($newPassword) < 6) {
        $errors[] = 'Yeni şifre en az 6 karakter olmalıdır';
    }
    
    if ($newPassword !== $confirmPassword) {
        $errors[] = 'Şifre onayı eşleşmiyor';
    }
    
    // Verify current password based on user type
    if (empty($errors)) {
        $passwordField = 'password';
        $tableName = '';
        
        if ($_SESSION['user_type'] === 'company') {
            $tableName = 'companies';
        } elseif ($_SESSION['user_type'] === 'branch') {
            $tableName = 'branches';
        } else {
            $tableName = 'personnel';
        }
        
        $user = fetchOne("SELECT {$passwordField} FROM {$tableName} WHERE id = ?", [$currentUser['id']]);
        if (!password_verify($currentPassword, $user[$passwordField])) {
            $errors[] = 'Mevcut şifre yanlış';
        }
    }
    
    // If no errors, update password
    if (empty($errors)) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $result = executeQuery(
            "UPDATE {$tableName} SET password = ? WHERE id = ?",
            [$hashedPassword, $currentUser['id']]
        );
        
        if ($result) {
            $success = 'Şifre başarıyla değiştirildi';
        } else {
            $errors[] = 'Şifre değiştirilirken bir hata oluştu';
        }
    }
}

$pageTitle = 'Şifre Değiştir';
require_once 'includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="page-header">
            <h2 class="mb-0">Şifre Değiştir</h2>
            <p class="page-subtitle">Güvenliğiniz için şifrenizi düzenli olarak değiştirin</p>
        </div>
    </div>
</div>

<!-- Display success message -->
<?php if ($success): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="alert alert-success">
            <i class="fas fa-check-circle me-2"></i>
            <?= e($success) ?>
        </div>
    </div>
</div>
<?php endif; ?>

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

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="clean-table-container">
            <div class="card-header">
                <h5><i class="fas fa-key me-2"></i>Şifre Değiştir</h5>
            </div>
            <div class="p-4">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="current_password" class="form-label">
                            <i class="fas fa-lock me-1"></i>
                            Mevcut Şifre *
                        </label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                        <small class="form-text text-muted">Güvenlik için mevcut şifrenizi giriniz</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">
                            <i class="fas fa-key me-1"></i>
                            Yeni Şifre *
                        </label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                        <small class="form-text text-muted">En az 6 karakter olmalıdır</small>
                    </div>
                    
                    <div class="mb-4">
                        <label for="confirm_password" class="form-label">
                            <i class="fas fa-key me-1"></i>
                            Şifre Onay *
                        </label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        <small class="form-text text-muted">Yeni şifreyi tekrar giriniz</small>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>
                            Şifreyi Değiştir
                        </button>
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>
                            İptal
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
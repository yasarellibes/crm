<?php
/**
 * Login Page - Serviso HVAC System
 */

session_start();
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'includes/functions.php';

// Get system settings for dynamic content
$systemSettings = getSystemSettings();

$error = '';
$success = '';

// Check for registration success message
if (isset($_SESSION['registration_success'])) {
    $success = $_SESSION['registration_success'];
    unset($_SESSION['registration_success']);
}

// Check for logout message
if (isset($_GET['message']) && $_GET['message'] === 'logout') {
    $success = 'Başarıyla çıkış yaptınız.';
}

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']) ? true : false;
    
    if (empty($email) || empty($password)) {
        $error = 'E-posta ve şifre gereklidir.';
    } else {
        if (loginUser($email, $password, $remember)) {
            header('Location: dashboard.php');
            exit;
        } else {
            // Check if it's an expired service issue
            $company = fetchOne("SELECT service_end_date FROM companies WHERE email = ?", [$email]);
            $branch = fetchOne("SELECT company_id FROM branches WHERE email = ?", [$email]);
            $user = fetchOne("SELECT company_id FROM personnel WHERE email = ?", [$email]);
            
            $isExpired = false;
            if ($company && $company['service_end_date'] && $company['service_end_date'] < date('Y-m-d')) {
                $isExpired = true;
            } elseif ($branch && $branch['company_id']) {
                $parentCompany = fetchOne("SELECT service_end_date FROM companies WHERE id = ?", [$branch['company_id']]);
                if ($parentCompany && $parentCompany['service_end_date'] && $parentCompany['service_end_date'] < date('Y-m-d')) {
                    $isExpired = true;
                }
            } elseif ($user && $user['company_id']) {
                $parentCompany = fetchOne("SELECT service_end_date FROM companies WHERE id = ?", [$user['company_id']]);
                if ($parentCompany && $parentCompany['service_end_date'] && $parentCompany['service_end_date'] < date('Y-m-d')) {
                    $isExpired = true;
                }
            }
            
            if ($isExpired) {
                $error = 'service_expired'; // Special flag for expired service
                // Get system settings for contact info
                $systemSettings = getSystemSettings();
            } else {
                $error = 'Geçersiz e-posta veya şifre.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş - <?= e($systemSettings['system_name'] ?? 'Serviso') ?></title>
    
    <!-- Inter Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!-- Modern Login Styles -->
    <style>
        :root {
            --primary-color: #4f46e5;
            --primary-hover: #4338ca;
            --primary-light: rgba(79, 70, 229, 0.1);
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-light: #e2e8f0;
            --radius-lg: 12px;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        
        .login-container {
            background: var(--bg-primary);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            width: 100%;
            max-width: 400px;
            padding: 2rem;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-logo {
            width: 48px;
            height: 48px;
            background: var(--primary-color);
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: white;
            font-size: 1.5rem;
        }
        
        .login-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        
        .login-subtitle {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-light);
            border-radius: var(--radius-lg);
            font-size: 0.875rem;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: all 0.2s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-light);
        }
        
        .remember-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            gap: 1rem;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 1rem;
            height: 1rem;
            accent-color: var(--primary-color);
        }
        
        .checkbox-group label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            cursor: pointer;
        }
        
        .forgot-link {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .forgot-link:hover {
            color: var(--primary-hover);
        }
        
        .btn-primary {
            width: 100%;
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.75rem;
            border-radius: var(--radius-lg);
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-bottom: 1rem;
        }
        
        .btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }
        
        .register-link {
            text-align: center;
            padding-top: 1rem;
            border-top: 1px solid var(--border-light);
        }
        
        .register-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        
        .register-link a:hover {
            color: var(--primary-hover);
        }
        
        .alert {
            padding: 0.75rem 1rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }
        
        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        
        .alert-error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        
        .alert-warning {
            background: #fefce8;
            color: #ca8a04;
            border: 1px solid #fef3c7;
        }
        
        .contact-buttons {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .contact-btn {
            flex: 1;
            padding: 0.5rem;
            border-radius: var(--radius-lg);
            text-decoration: none;
            text-align: center;
            font-size: 0.75rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .contact-btn.whatsapp {
            background: #25d366;
            color: white;
        }
        
        .contact-btn.email {
            background: #64748b;
            color: white;
        }
        
        .contact-btn:hover {
            transform: translateY(-1px);
        }
        
        @media (max-width: 480px) {
            .login-container {
                max-width: 100%;
                padding: 1.5rem;
            }
            
            .remember-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }
        }
    </style>
</head>
<body>
    
    <!-- Modern Login Container -->
    <div class="login-container">
        <!-- Login Header -->
        <div class="login-header">
            <div class="login-logo">
                <?php if (!empty($systemSettings['company_logo'])): ?>
                    <img src="<?= e($systemSettings['company_logo']) ?>" alt="Logo" style="width: 24px; height: 24px; object-fit: contain;">
                <?php else: ?>
                    <i class="fas fa-tools"></i>
                <?php endif; ?>
            </div>
            <h1 class="login-title"><?= e($systemSettings['system_name'] ?? 'Serviso') ?></h1>
            <p class="login-subtitle">Hesabınıza giriş yapın</p>
        </div>
        
        <!-- Success Messages -->
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle" style="margin-right: 0.5rem;"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        
        <!-- Error Messages -->
        <?php if ($error === 'service_expired'): 
            $phone = preg_replace('/[^0-9]/', '', $systemSettings['company_phone']);
            $phoneFormatted = formatPhone($systemSettings['company_phone']);
            $whatsappUrl = "https://wa.me/9{$phone}?text=" . urlencode("Merhaba, hizmet süremi yenilemek istiyorum.");
            $emailUrl = "mailto:{$systemSettings['company_email']}?subject=" . urlencode("Hizmet Süresi Yenileme") . "&body=" . urlencode("Merhaba, hizmet süremi yenilemek istiyorum.");
        ?>
            <div class="alert alert-warning">
                <div style="margin-bottom: 1rem;">
                    <i class="fas fa-exclamation-triangle" style="margin-right: 0.5rem;"></i>
                    <strong>Hizmet süreniz sona ermiş!</strong>
                </div>
                <p style="margin-bottom: 1rem; font-size: 0.8rem;">Sisteme erişebilmek için hizmet sürenizi yenilemeniz gerekmektedir. Yenileme için bizimle iletişime geçin:</p>
                <div class="contact-buttons">
                    <a href="<?= $whatsappUrl ?>" target="_blank" class="contact-btn whatsapp">
                        <i class="fab fa-whatsapp" style="margin-right: 0.25rem;"></i>
                        WhatsApp
                    </a>
                    <a href="<?= $emailUrl ?>" class="contact-btn email">
                        <i class="fas fa-envelope" style="margin-right: 0.25rem;"></i>
                        E-posta
                    </a>
                </div>
            </div>
        <?php elseif ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle" style="margin-right: 0.5rem;"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <!-- Login Form -->
        <form method="POST" action="">
            <div class="form-group">
                <label for="email" class="form-label">E-posta</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="ornek@email.com" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : (isset($_COOKIE['remember_email']) ? htmlspecialchars($_COOKIE['remember_email']) : '') ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password" class="form-label">Şifre</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="Şifrenizi girin" required>
            </div>
            
            <div class="remember-row">
                <div class="checkbox-group">
                    <input type="checkbox" id="remember" name="remember" value="1" <?= isset($_COOKIE['remember_email']) ? 'checked' : '' ?>>
                    <label for="remember">Beni hatırla</label>
                </div>
                <a href="#" class="forgot-link">Şifremi unuttum</a>
            </div>
            
            <button type="submit" class="btn-primary">
                <i class="fas fa-sign-in-alt" style="margin-right: 0.5rem;"></i>
                Giriş Yap
            </button>
        </form>
        
        <div class="register-link">
            <p style="color: var(--text-secondary); font-size: 0.875rem; margin-bottom: 0.5rem;">Hesabınız yok mu?</p>
            <a href="register.php">Şirket Kaydı Oluşturun</a>
        </div>
    </div>

</body>
</html>
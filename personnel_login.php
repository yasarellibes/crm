<?php
/**
 * Personnel Login Page - Separate login for personnel/technicians
 */

session_start();

// Database connection
try {
    $host = $_ENV['PGHOST'] ?? 'localhost';
    $port = $_ENV['PGPORT'] ?? '5432';
    $dbname = $_ENV['PGDATABASE'] ?? 'main';
    $username = $_ENV['PGUSER'] ?? 'replit';
    $password = $_ENV['PGPASSWORD'] ?? '';

    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed");
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($email && $password) {
        try {
            // Check personnel table for login
            $stmt = $pdo->prepare("
                SELECT p.*, b.name as branch_name, c.name as company_name 
                FROM personnel p
                LEFT JOIN branches b ON p.branch_id = b.id
                LEFT JOIN companies c ON p.company_id = c.id
                WHERE p.email = ? AND p.password IS NOT NULL
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['role'] = $user['role'] ?? 'technician';
                $_SESSION['company_id'] = $user['company_id'];
                $_SESSION['branch_id'] = $user['branch_id'];
                $_SESSION['branch_name'] = $user['branch_name'];
                $_SESSION['company_name'] = $user['company_name'];
                $_SESSION['user_type'] = 'personnel';
                $_SESSION['last_login'] = date('Y-m-d H:i:s');
                
                // Update last login
                $updateStmt = $pdo->prepare("UPDATE personnel SET last_login_at = NOW() WHERE id = ?");
                $updateStmt->execute([$user['id']]);
                
                // Redirect to appropriate dashboard
                if ($user['role'] === 'branch_manager') {
                    header('Location: dashboard.php');
                } else {
                    header('Location: technician_dashboard.php');
                }
                exit;
            } else {
                $error = 'Geçersiz e-posta veya şifre';
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $error = 'Giriş işlemi sırasında hata oluştu';
        }
    } else {
        $error = 'E-posta ve şifre alanları zorunludur';
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personel Girişi - Serviso</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            max-width: 400px;
            width: 100%;
        }
        .login-header {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .login-body {
            padding: 2rem;
        }
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 20px;
            transition: all 0.3s;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-login {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            color: white;
            transition: all 0.3s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
            color: white;
        }
        .input-group-text {
            background: transparent;
            border-right: none;
            border-radius: 10px 0 0 10px;
            border: 2px solid #e9ecef;
            border-right: none;
        }
        .input-group .form-control {
            border-left: none;
            border-radius: 0 10px 10px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="login-card">
                    <div class="login-header">
                        <i class="fas fa-user-cog fa-3x mb-3"></i>
                        <h3 class="mb-0">Personel Girişi</h3>
                        <p class="mb-0 opacity-75">Serviso HVAC Yönetim Sistemi</p>
                    </div>
                    
                    <div class="login-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?= htmlspecialchars($error) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="email" class="form-label">E-posta</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-envelope text-muted"></i>
                                    </span>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                                           placeholder="ornek@email.com" required>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" class="form-label">Şifre</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-lock text-muted"></i>
                                    </span>
                                    <input type="password" class="form-control" id="password" name="password" 
                                           placeholder="Şifrenizi girin" required>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-login w-100">
                                <i class="fas fa-sign-in-alt me-2"></i>
                                Giriş Yap
                            </button>
                        </form>
                        
                        <div class="text-center mt-3">
                            <small class="text-muted">
                                Yönetici girişi için 
                                <a href="login.php" class="text-decoration-none">buraya tıklayın</a>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
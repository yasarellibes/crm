<?php
/**
 * Service Expired Page - Shows when company service period has ended
 */
session_start();
require_once 'config/database.php';

// Get company info if user is logged in
$companyInfo = null;
if (isset($_SESSION['company_id'])) {
    $companyInfo = fetchOne("SELECT name, service_end_date FROM companies WHERE id = ?", [$_SESSION['company_id']]);
}

$pageTitle = 'Hizmet Süresi Dolmuş - Serviso';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .expired-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            padding: 3rem;
            text-align: center;
            max-width: 600px;
            width: 90%;
        }
        .expired-icon {
            color: #dc3545;
            font-size: 4rem;
            margin-bottom: 1.5rem;
        }
        .expired-title {
            color: #dc3545;
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 1rem;
        }
        .company-name {
            color: #6c757d;
            font-size: 1.2rem;
            margin-bottom: 2rem;
        }
        .contact-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 2rem;
            margin: 2rem 0;
            border-left: 5px solid #007bff;
        }
        .contact-title {
            color: #007bff;
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 1rem;
        }
        .contact-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.8rem;
            font-size: 1.1rem;
        }
        .contact-item i {
            color: #007bff;
            width: 30px;
            margin-right: 15px;
        }
        .logout-btn {
            background: linear-gradient(45deg, #dc3545, #c82333);
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
            color: white;
            font-weight: bold;
            transition: all 0.3s;
        }
        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(220, 53, 69, 0.3);
        }
        .logo {
            font-size: 2rem;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <div class="expired-container">
        <div class="logo">
            <i class="fas fa-tools me-2"></i>
            Serviso
        </div>
        
        <div class="expired-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        
        <h1 class="expired-title">Hizmet Süresi Dolmuş</h1>
        
        <?php if ($companyInfo): ?>
        <div class="company-name">
            <strong><?= htmlspecialchars($companyInfo['name']) ?></strong>
            <?php if ($companyInfo['service_end_date']): ?>
            <br><small class="text-muted">Bitiş Tarihi: <?= date('d.m.Y', strtotime($companyInfo['service_end_date'])) ?></small>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <div class="alert alert-danger">
            <i class="fas fa-ban me-2"></i>
            <strong>Sistem kullanımınız durdurulmuştur.</strong><br>
            Hizmet sürenizin yenilenmesi için lütfen aşağıdaki iletişim bilgilerini kullanın.
        </div>
        
        <div class="contact-card">
            <div class="contact-title">
                <i class="fas fa-headset me-2"></i>
                Hizmet Yenileme İçin İletişim
            </div>
            
            <div class="contact-item">
                <i class="fas fa-phone"></i>
                <a href="tel:+905491234567" class="text-decoration-none">0549 123 45 67</a>
            </div>
            
            <div class="contact-item">
                <i class="fas fa-envelope"></i>
                <a href="mailto:info@serviso.com.tr" class="text-decoration-none">info@serviso.com.tr</a>
            </div>
            
            <div class="contact-item">
                <i class="fas fa-globe"></i>
                <a href="https://www.serviso.com.tr" target="_blank" class="text-decoration-none">www.serviso.com.tr</a>
            </div>
            
            <div class="contact-item">
                <i class="fas fa-clock"></i>
                <span>Pazartesi - Cuma: 09:00 - 18:00</span>
            </div>
        </div>
        
        <div class="mt-4">
            <p class="text-muted mb-3">
                Hizmet yenileme işleminiz tamamlandıktan sonra sistemi tekrar kullanabilirsiniz.
            </p>
            
            <a href="logout.php" class="btn logout-btn">
                <i class="fas fa-sign-out-alt me-2"></i>
                Güvenli Çıkış
            </a>
        </div>
        
        <div class="mt-4 pt-4 border-top">
            <small class="text-muted">
                © <?= date('Y') ?> Serviso - HVAC Servis Yönetim Sistemi<br>
                Tüm hakları saklıdır.
            </small>
        </div>
    </div>
</body>
</html>
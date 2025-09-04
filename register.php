<?php
/**
 * Company Registration Page
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'cities.php';

// Get system settings for dynamic content
$systemSettings = getSystemSettings();

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['company_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $agreement = isset($_POST['agreement']) ? true : false;
    
    $errors = [];
    
    // Validation
    if (empty($name)) $errors[] = 'Şirket adı gereklidir';
    if (empty($email)) $errors[] = 'E-posta gereklidir';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Geçersiz e-posta formatı';
    if (empty($password)) $errors[] = 'Şifre gereklidir';
    if (strlen($password) < 6) $errors[] = 'Şifre en az 6 karakter olmalıdır';
    if ($password !== $confirmPassword) $errors[] = 'Şifreler eşleşmiyor';
    if (!$agreement) $errors[] = 'Kullanıcı sözleşmesini kabul etmelisiniz';
    
    // Check if email already exists
    if (empty($errors)) {
        $existingCompany = fetchOne("SELECT id FROM companies WHERE email = ?", [$email]);
        if ($existingCompany) {
            $errors[] = 'Bu e-posta adresi zaten kayıtlı';
        }
    }
    
    if (empty($errors)) {
        try {
            // Calculate trial end date (14 days from now)
            $trialEndDate = date('Y-m-d', strtotime('+14 days'));
            
            // Get database connection
            $pdo = getDB();
            
            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert new company
            $sql = "INSERT INTO companies (name, email, phone, city, district, address, password, service_start_date, service_end_date, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_DATE, ?, CURRENT_TIMESTAMP)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $email, $phone, $city, $district, $address, $hashedPassword, $trialEndDate]);
            
            $newCompanyId = $pdo->lastInsertId();
            
            // Create default data for the new company
            createDefaultCompanyData($newCompanyId, $name, $city, $district);
            
            // Set success message and redirect
            $_SESSION['registration_success'] = 'Kayıt başarılı! 14 günlük ücretsiz deneme süreniz başladı.';
            header('Location: login.php');
            exit;
            
        } catch (Exception $e) {
            $errors[] = 'Kayıt sırasında bir hata oluştu: ' . $e->getMessage();
        }
    }
}

// Function to create default data for new company (from companies.php)
function createDefaultCompanyData($companyId, $companyName, $city = '', $district = '') {
    $pdo = getDB();
    try {
        // Create specified devices
        $devices = ['Kombi', 'Klima'];
        foreach ($devices as $device) {
            $stmt = $pdo->prepare("INSERT INTO devices (name, company_id, created_at) VALUES (?, ?, CURRENT_TIMESTAMP)");
            $stmt->execute([$device, $companyId]);
        }
        
        // Create specified brands
        $brands = ['Arçelik', 'Baymak', 'Vestel', 'Bilinmiyor'];
        foreach ($brands as $brand) {
            $stmt = $pdo->prepare("INSERT INTO brands (name, company_id, created_at) VALUES (?, ?, CURRENT_TIMESTAMP)");
            $stmt->execute([$brand, $companyId]);
        }
        
        // Get brand IDs for models
        $brandResults = fetchAll("SELECT id, name FROM brands WHERE company_id = ?", [$companyId]);
        
        // Create "Bilinmiyor" model for each brand
        foreach ($brandResults as $brand) {
            $stmt = $pdo->prepare("INSERT INTO models (name, brand_id, company_id, created_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)");
            $stmt->execute(['Bilinmiyor', $brand['id'], $companyId]);
        }
        
        // Create specified complaints
        $complaints = ['Çalışmıyor'];
        foreach ($complaints as $complaint) {
            $stmt = $pdo->prepare("INSERT INTO complaints (name, company_id, created_at) VALUES (?, ?, CURRENT_TIMESTAMP)");
            $stmt->execute([$complaint, $companyId]);
        }
        
        // Create default operations
        $operations = ['İşlem bekliyor', 'Tamamlandı', 'Parça Bekliyor'];
        foreach ($operations as $operation) {
            $stmt = $pdo->prepare("INSERT INTO operations (name, company_id, created_at) VALUES (?, ?, CURRENT_TIMESTAMP)");
            $stmt->execute([$operation, $companyId]);
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error creating default company data: " . $e->getMessage());
        return false;
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Şirket Kaydı - <?= e($systemSettings['system_name'] ?? 'Serviso') ?></title>
    
    <!-- Inter Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!-- Modern Register Styles -->
    <style>
        :root {
            --primary-color: #4f46e5;
            --primary-hover: #4338ca;
            --primary-light: rgba(79, 70, 229, 0.1);
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --text-muted: #94a3b8;
            --border-light: #e2e8f0;
            --radius-lg: 12px;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --success-color: #10b981;
            --error-color: #ef4444;
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
        
        .register-container {
            background: var(--bg-primary);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            width: 100%;
            max-width: 500px;
            padding: 2rem;
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .register-logo {
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
        
        .register-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        
        .register-subtitle {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        
        .trial-badge {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
            border-radius: var(--radius-lg);
            padding: 0.75rem 1rem;
            margin-bottom: 1.5rem;
            text-align: center;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
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
        
        .form-control.is-invalid {
            border-color: var(--error-color);
        }
        
        .checkbox-group {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 1rem;
            height: 1rem;
            accent-color: var(--primary-color);
            margin-top: 0.125rem;
        }
        
        .checkbox-group label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            cursor: pointer;
            line-height: 1.4;
        }
        
        .checkbox-group a {
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .checkbox-group a:hover {
            color: var(--primary-hover);
            text-decoration: underline;
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
        
        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .login-link {
            text-align: center;
            padding-top: 1rem;
            border-top: 1px solid var(--border-light);
        }
        
        .login-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        
        .login-link a:hover {
            color: var(--primary-hover);
        }
        
        .alert {
            padding: 0.75rem 1rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }
        
        .alert-danger {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        
        .alert ul {
            margin: 0;
            padding-left: 1.25rem;
        }
        
        @media (max-width: 640px) {
            .register-container {
                max-width: 100%;
                padding: 1.5rem;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
            
            .checkbox-group {
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    
    <!-- Modern Register Container -->
    <div class="register-container">
        <!-- Register Header -->
        <div class="register-header">
            <div class="register-logo">
                <?php if (!empty($systemSettings['company_logo'])): ?>
                    <img src="<?= e($systemSettings['company_logo']) ?>" alt="Logo" style="width: 24px; height: 24px; object-fit: contain;">
                <?php else: ?>
                    <i class="fas fa-tools"></i>
                <?php endif; ?>
            </div>
            <h1 class="register-title"><?= e($systemSettings['system_name'] ?? 'Serviso') ?></h1>
            <p class="register-subtitle">Şirket kaydı oluşturun</p>
        </div>
        
        <!-- Trial Badge -->
        <div class="trial-badge">
            <i class="fas fa-gift" style="margin-right: 0.5rem;"></i>
            14 gün ücretsiz deneme
        </div>
        
        <!-- Error Messages -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle" style="margin-right: 0.5rem;"></i>
                <ul style="margin: 0; padding-left: 1.25rem;">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <!-- Register Form -->
        <form method="POST" action="">
            <!-- Şirket Adı -->
            <div class="form-group">
                <label for="company_name" class="form-label">Şirket Adı</label>
                <input type="text" id="company_name" name="company_name" class="form-control" 
                       placeholder="Şirket adınızı girin" 
                       value="<?= isset($_POST['company_name']) ? htmlspecialchars($_POST['company_name']) : '' ?>" required>
            </div>
            
            <!-- Adres -->
            <div class="form-group">
                <label for="address" class="form-label">Adres</label>
                <input type="text" id="address" name="address" class="form-control" 
                       placeholder="Şirket adresinizi girin" 
                       value="<?= isset($_POST['address']) ? htmlspecialchars($_POST['address']) : '' ?>">
            </div>
            
            <!-- İl İlçe -->
            <div class="form-row">
                <div class="form-group">
                    <label for="city" class="form-label">İl</label>
                    <select id="city" name="city" class="form-control" onchange="loadDistricts()" required>
                        <option value="">İl seçin</option>
                        <?php
                        $cities = ['Adana', 'Adıyaman', 'Afyonkarahisar', 'Ağrı', 'Amasya', 'Ankara', 'Antalya', 'Artvin', 'Aydın', 'Balıkesir', 'Bilecik', 'Bingöl', 'Bitlis', 'Bolu', 'Burdur', 'Bursa', 'Çanakkale', 'Çankırı', 'Çorum', 'Denizli', 'Diyarbakır', 'Edirne', 'Elazığ', 'Erzincan', 'Erzurum', 'Eskişehir', 'Gaziantep', 'Giresun', 'Gümüşhane', 'Hakkari', 'Hatay', 'Isparta', 'Mersin', 'İstanbul', 'İzmir', 'Kars', 'Kastamonu', 'Kayseri', 'Kırklareli', 'Kırşehir', 'Kocaeli', 'Konya', 'Kütahya', 'Malatya', 'Manisa', 'Kahramanmaraş', 'Mardin', 'Muğla', 'Muş', 'Nevşehir', 'Niğde', 'Ordu', 'Rize', 'Sakarya', 'Samsun', 'Siirt', 'Sinop', 'Sivas', 'Tekirdağ', 'Tokat', 'Trabzon', 'Tunceli', 'Şanlıurfa', 'Uşak', 'Van', 'Yozgat', 'Zonguldak', 'Aksaray', 'Bayburt', 'Karaman', 'Kırıkkale', 'Batman', 'Şırnak', 'Bartın', 'Ardahan', 'Iğdır', 'Yalova', 'Karabük', 'Kilis', 'Osmaniye', 'Düzce'];
                        foreach ($cities as $city) {
                            $selected = (isset($_POST['city']) && $_POST['city'] === $city) ? 'selected' : '';
                            echo "<option value=\"" . htmlspecialchars($city) . "\" {$selected}>" . htmlspecialchars($city) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="district" class="form-label">İlçe</label>
                    <select id="district" name="district" class="form-control">
                        <option value="">İlçe seçin</option>
                    </select>
                </div>
            </div>
            
            <!-- Telefon Email -->
            <div class="form-row">
                <div class="form-group">
                    <label for="phone" class="form-label">Telefon</label>
                    <input type="tel" id="phone" name="phone" class="form-control phone-input" 
                           placeholder="05312345678" maxlength="11"
                           value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>">
                </div>
                <div class="form-group">
                    <label for="email" class="form-label">E-posta</label>
                    <input type="email" id="email" name="email" class="form-control" 
                           placeholder="ornek@sirket.com" 
                           value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" required>
                </div>
            </div>
            
            <!-- Şifre Şifre Tekrar -->
            <div class="form-row">
                <div class="form-group">
                    <label for="password" class="form-label">Şifre</label>
                    <input type="password" id="password" name="password" class="form-control" 
                           placeholder="Şifrenizi girin" required>
                    <small class="text-muted">En az 6 karakter</small>
                </div>
                <div class="form-group">
                    <label for="confirm_password" class="form-label">Şifre Tekrar</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                           placeholder="Şifrenizi tekrarlayın" required>
                    <div id="password-match-message" style="font-size: 0.75rem; margin-top: 0.25rem; display: none;"></div>
                </div>
            </div>
            
            <!-- Kullanıcı Sözleşmesi -->
            <div class="checkbox-group">
                <input type="checkbox" id="agreement" name="agreement" value="1" required>
                <label for="agreement">
                    <a href="#" onclick="showAgreement()">Kullanıcı sözleşmesini</a> okudum ve kabul ediyorum
                </label>
            </div>
            
            <!-- Kayıt Ol Butonu -->
            <button type="submit" class="btn-primary">
                <i class="fas fa-user-plus" style="margin-right: 0.5rem;"></i>
                Kayıt Ol
            </button>
        </form>
        
        <!-- Giriş Linki -->
        <div class="login-link">
            <p style="color: var(--text-secondary); font-size: 0.875rem; margin-bottom: 0.5rem;">Zaten hesabınız var mı?</p>
            <a href="login.php">Giriş yapın</a>
        </div>
    </div>

    <!-- City-District Data Script -->
    <script>
        // Türkiye İl-İlçe Verileri
        const TURKEY_CITIES = {
            "Adana": ["Aladağ", "Ceyhan", "Çukurova", "Feke", "İmamoğlu", "Karaisalı", "Karataş", "Kozan", "Pozantı", "Saimbeyli", "Sarıçam", "Seyhan", "Tufanbeyli", "Yumurtalık", "Yüreğir"],
            "Adıyaman": ["Besni", "Çelikhan", "Gerger", "Gölbaşı", "Kahta", "Merkez", "Samsat", "Sincik", "Tut"],
            "Afyonkarahisar": ["Başmakçı", "Bayat", "Bolvadin", "Çay", "Çobanlar", "Dazkırı", "Dinar", "Emirdağ", "Evciler", "Hocalar", "İhsaniye", "İscehisar", "Kızılören", "Merkez", "Sandıklı", "Sinanpaşa", "Sultandağı", "Şuhut"],
            "Ağrı": ["Diyadin", "Doğubayazıt", "Eleşkirt", "Hamur", "Merkez", "Patnos", "Taşlıçay", "Tutak"],
            "Amasya": ["Göynücek", "Gümüşhacıköy", "Hamamözü", "Merkez", "Merzifon", "Suluova", "Taşova"],
            "Ankara": ["Akyurt", "Altındağ", "Ayaş", "Bala", "Beypazarı", "Çamlıdere", "Çankaya", "Çubuk", "Elmadağ", "Etimesgut", "Evren", "Gölbaşı", "Güdül", "Haymana", "Kalecik", "Kazan", "Keçiören", "Kızılcahamam", "Mamak", "Nallıhan", "Polatlı", "Pursaklar", "Sincan", "Şereflikoçhisar", "Yenimahalle"],
            "Antalya": ["Akseki", "Aksu", "Alanya", "Demre", "Döşemealtı", "Elmalı", "Finike", "Gazipaşa", "Gündoğmuş", "İbradı", "Kaş", "Kemer", "Kepez", "Konyaaltı", "Korkuteli", "Kumluca", "Manavgat", "Muratpaşa", "Serik"],
            "Artvin": ["Ardanuç", "Arhavi", "Borçka", "Hopa", "Merkez", "Murgul", "Şavşat", "Yusufeli"],
            "Aydın": ["Bozdoğan", "Buharkent", "Çine", "Didim", "Efeler", "Germencik", "İncirliova", "Karacasu", "Karpuzlu", "Koçarlı", "Köşk", "Kuşadası", "Kuyucak", "Nazilli", "Söke", "Sultanhisar", "Yenipazar"],
            "Balıkesir": ["Altıeylül", "Ayvalık", "Balya", "Bandırma", "Bigadiç", "Burhaniye", "Dursunbey", "Edremit", "Erdek", "Gömeç", "Gönen", "Havran", "İvrindi", "Karesi", "Kepsut", "Manyas", "Marmara", "Savaştepe", "Sındırgı", "Susurluk"],
            "Bilecik": ["Bozüyük", "Gölpazarı", "İnhisar", "Merkez", "Osmaneli", "Pazaryeri", "Söğüt", "Yenipazar"],
            "Bingöl": ["Adaklı", "Genç", "Karlıova", "Kiğı", "Merkez", "Solhan", "Yayladere", "Yedisu"],
            "Bitlis": ["Adilcevaz", "Ahlat", "Güroymak", "Hizan", "Merkez", "Mutki", "Tatvan"],
            "Bolu": ["Dörtdivan", "Gerede", "Göynük", "Kıbrıscık", "Mengen", "Merkez", "Mudurnu", "Seben", "Yeniçağa"],
            "Burdur": ["Ağlasun", "Altınyayla", "Bucak", "Çavdır", "Çeltikçi", "Gölhisar", "Karamanlı", "Kemer", "Merkez", "Tefenni", "Yeşilova"],
            "Bursa": ["Büyükorhan", "Gemlik", "Gürsu", "Harmancık", "İnegöl", "İznik", "Karacabey", "Keles", "Kestel", "Mudanya", "Mustafakemalpaşa", "Nilüfer", "Orhaneli", "Orhangazi", "Osmangazi", "Yenişehir", "Yıldırım"],
            "Çanakkale": ["Ayvacık", "Bayramiç", "Biga", "Bozcaada", "Çan", "Eceabat", "Ezine", "Gelibolu", "Gökçeada", "Lapseki", "Merkez", "Yenice"],
            "Çankırı": ["Atkaracalar", "Bayramören", "Çerkeş", "Eldivan", "Ilgaz", "Kızılırmak", "Korgun", "Kurşunlu", "Merkez", "Orta", "Şabanözü", "Yapraklı"],
            "Çorum": ["Alaca", "Bayat", "Boğazkale", "Dodurga", "İskilip", "Kargı", "Laçin", "Mecitözü", "Merkez", "Oğuzlar", "Ortaköy", "Osmancık", "Sungurlu", "Uğurludağ"],
            "Denizli": ["Acıpayam", "Babadağ", "Baklan", "Bekilli", "Beyağaç", "Bozkurt", "Buldan", "Çal", "Çameli", "Çardak", "Çivril", "Güney", "Honaz", "Kale", "Merkezefendi", "Pamukkale", "Sarayköy", "Serinhisar", "Tavas"],
            "Diyarbakır": ["Bağlar", "Bismil", "Çermik", "Çınar", "Çüngüş", "Dicle", "Eğil", "Ergani", "Hani", "Hazro", "Kayapınar", "Kocaköy", "Kulp", "Lice", "Silvan", "Sur", "Yenişehir"],
            "Edirne": ["Enez", "Havsa", "İpsala", "Keşan", "Lalapaşa", "Meriç", "Merkez", "Süloğlu", "Uzunköprü"],
            "Elazığ": ["Ağın", "Alacakaya", "Arıcak", "Baskil", "Karakoçan", "Keban", "Kovancılar", "Maden", "Merkez", "Palu", "Sivrice"],
            "Erzincan": ["Çayırlı", "İliç", "Kemah", "Kemaliye", "Merkez", "Otlukbeli", "Refahiye", "Tercan", "Üzümlü"],
            "Erzurum": ["Aşkale", "Aziziye", "Çat", "Hınıs", "Horasan", "İspir", "Karaçoban", "Karayazı", "Köprüköy", "Narman", "Oltu", "Olur", "Palandöken", "Pasinler", "Pazaryolu", "Şenkaya", "Tekman", "Tortum", "Uzundere", "Yakutiye"],
            "Eskişehir": ["Alpu", "Beylikova", "Çifteler", "Günyüzü", "Han", "İnönü", "Mahmudiye", "Mihalgazi", "Mihalıççık", "Odunpazarı", "Sarıcakaya", "Seyitgazi", "Sivrihisar", "Tepebaşı"],
            "Gaziantep": ["Araban", "İslahiye", "Karkamış", "Nizip", "Nurdağı", "Oğuzeli", "Şahinbey", "Şehitkamil", "Yavuzeli"],
            "Giresun": ["Alucra", "Bulancak", "Çamoluk", "Çanakçı", "Dereli", "Doğankent", "Espiye", "Eynesil", "Görele", "Güce", "Keşap", "Merkez", "Piraziz", "Şebinkarahisar", "Tirebolu", "Yağlıdere"],
            "Gümüşhane": ["Kelkit", "Köse", "Kürtün", "Merkez", "Şiran", "Torul"],
            "Hakkari": ["Çukurca", "Derecik", "Merkez", "Şemdinli", "Yüksekova"],
            "Hatay": ["Altınözü", "Antakya", "Arsuz", "Belen", "Defne", "Dörtyol", "Erzin", "Hassa", "İskenderun", "Kırıkhan", "Kumlu", "Payas", "Reyhanlı", "Samandağ", "Yayladağı"],
            "Isparta": ["Aksu", "Atabey", "Eğirdir", "Gelendost", "Gönen", "Keçiborlu", "Merkez", "Senirkent", "Sütçüler", "Şarkikaraağaç", "Uluborlu", "Yalvaç", "Yenişarbademli"],
            "Mersin": ["Akdeniz", "Anamur", "Aydıncık", "Bozyazı", "Çamlıyayla", "Erdemli", "Gülnar", "Mezitli", "Mut", "Silifke", "Tarsus", "Toroslar", "Yenişehir"],
            "İstanbul": ["Adalar", "Arnavutköy", "Ataşehir", "Avcılar", "Bağcılar", "Bahçelievler", "Bakırköy", "Başakşehir", "Bayrampaşa", "Beşiktaş", "Beykoz", "Beylikdüzü", "Beyoğlu", "Büyükçekmece", "Çatalca", "Çekmeköy", "Esenler", "Esenyurt", "Eyüpsultan", "Fatih", "Gaziosmanpaşa", "Güngören", "Kadıköy", "Kağıthane", "Kartal", "Küçükçekmece", "Maltepe", "Pendik", "Sancaktepe", "Sarıyer", "Silivri", "Sultanbeyli", "Sultangazi", "Şile", "Şişli", "Tuzla", "Ümraniye", "Üsküdar", "Zeytinburnu"],
            "İzmir": ["Aliağa", "Balçova", "Bayındır", "Bayraklı", "Bergama", "Beydağ", "Bornova", "Buca", "Çeşme", "Çiğli", "Dikili", "Foça", "Gaziemir", "Güzelbahçe", "Karabağlar", "Karaburun", "Karşıyaka", "Kemalpaşa", "Kınık", "Kiraz", "Konak", "Menderes", "Menemen", "Narlıdere", "Ödemiş", "Seferihisar", "Selçuk", "Tire", "Torbalı", "Urla"],
            "Kars": ["Akyaka", "Arpaçay", "Digor", "Kağızman", "Merkez", "Sarıkamış", "Selim", "Susuz"],
            "Kastamonu": ["Abana", "Ağlı", "Araç", "Azdavay", "Bozkurt", "Cide", "Çatalzeytin", "Daday", "Devrekani", "Doğanyurt", "Hanönü", "İhsangazi", "İnebolu", "Küre", "Merkez", "Pınarbaşı", "Seydiler", "Şenpazar", "Taşköprü", "Tosya"],
            "Kayseri": ["Akkışla", "Bünyan", "Develi", "Felahiye", "Hacılar", "İncesu", "Kocasinan", "Melikgazi", "Özvatan", "Pınarbaşı", "Sarıoğlan", "Sarız", "Talas", "Tomarza", "Yahyalı", "Yeşilhisar"],
            "Kırklareli": ["Babaeski", "Demirköy", "Kofçaz", "Lüleburgaz", "Merkez", "Pehlivanköy", "Pınarhisar", "Vize"],
            "Kırşehir": ["Akçakent", "Akpınar", "Boztepe", "Çiçekdağı", "Kaman", "Merkez", "Mucur"],
            "Kocaeli": ["Başiskele", "Çayırova", "Darıca", "Derince", "Dilovası", "Gebze", "Gölcük", "İzmit", "Kandıra", "Karamürsel", "Kartepe", "Körfez"],
            "Konya": ["Ahırlı", "Akören", "Akşehir", "Altınekin", "Beyşehir", "Bozkır", "Cihanbeyli", "Çeltik", "Çumra", "Derbent", "Derebucak", "Doğanhisar", "Emirgazi", "Ereğli", "Güneysınır", "Hadim", "Halkapınar", "Hüyük", "Ilgın", "Kadınhanı", "Karapınar", "Karatay", "Kulu", "Meram", "Sarayönü", "Selçuklu", "Seydişehir", "Taşkent", "Tuzlukçu", "Yalıhüyük", "Yunak"],
            "Kütahya": ["Altıntaş", "Aslanapa", "Çavdarhisar", "Domaniç", "Dumlupınar", "Emet", "Gediz", "Hisarcık", "Merkez", "Pazarlar", "Simav", "Şaphane", "Tavşanlı"],
            "Malatya": ["Akçadağ", "Arapgir", "Arguvan", "Battalgazi", "Darende", "Doğanşehir", "Doğanyol", "Hekimhan", "Kale", "Kuluncak", "Pütürge", "Yazıhan", "Yeşilyurt"],
            "Manisa": ["Ahmetli", "Akhisar", "Alaşehir", "Demirci", "Gölmarmara", "Gördes", "Kırkağaç", "Köprübaşı", "Kula", "Merkez", "Salihli", "Sarıgöl", "Saruhanlı", "Selendi", "Soma", "Şehzadeler", "Turgutlu", "Yunusemre"],
            "Kahramanmaraş": ["Afşin", "Andırın", "Çağlayancerit", "Dulkadiroğlu", "Ekinözü", "Elbistan", "Göksun", "Nurhak", "Onikişubat", "Pazarcık", "Türkoğlu"],
            "Mardin": ["Artuklu", "Dargeçit", "Derik", "Kızıltepe", "Mazıdağı", "Midyat", "Nusaybin", "Ömerli", "Savur", "Yeşilli"],
            "Muğla": ["Bodrum", "Dalaman", "Datça", "Fethiye", "Kavaklıdere", "Köyceğiz", "Marmaris", "Menteşe", "Milas", "Ortaca", "Seydikemer", "Ula", "Yatağan"],
            "Muş": ["Bulanık", "Hasköy", "Korkut", "Malazgirt", "Merkez", "Varto"],
            "Nevşehir": ["Acıgöl", "Avanos", "Derinkuyu", "Gülşehir", "Hacıbektaş", "Kozaklı", "Merkez", "Ürgüp"],
            "Niğde": ["Altunhisar", "Bor", "Çamardı", "Çiftlik", "Merkez", "Ulukışla"],
            "Ordu": ["Akkuş", "Altınordu", "Aybastı", "Çamaş", "Çatalpınar", "Çaybaşı", "Fatsa", "Gölköy", "Gülyalı", "Gürgentepe", "İkizce", "Kabadüz", "Kabataş", "Korgan", "Kumru", "Mesudiye", "Perşembe", "Piraziz", "Ulubey", "Ünye"],
            "Rize": ["Ardeşen", "Çamlıhemşin", "Çayeli", "Derepazarı", "Fındıklı", "Güneysu", "Hemşin", "İkizdere", "İyidere", "Kalkandere", "Merkez", "Pazar"],
            "Sakarya": ["Adapazarı", "Akyazı", "Arifiye", "Erenler", "Ferizli", "Geyve", "Hendek", "Karapürçek", "Karasu", "Kaynarca", "Kocaali", "Pamukova", "Sapanca", "Serdivan", "Söğütlü", "Taraklı"],
            "Samsun": ["19 Mayıs", "Alaçam", "Asarcık", "Atakum", "Ayvacık", "Bafra", "Canik", "Çarşamba", "Havza", "İlkadım", "Kavak", "Ladik", "Ondokuzmayıs", "Salıpazarı", "Tekkeköy", "Terme", "Vezirköprü", "Yakakent"],
            "Siirt": ["Baykan", "Eruh", "Kurtalan", "Merkez", "Pervari", "Şirvan", "Tillo"],
            "Sinop": ["Ayancık", "Boyabat", "Dikmen", "Durağan", "Erfelek", "Gerze", "Merkez", "Saraydüzü", "Türkeli"],
            "Sivas": ["Akıncılar", "Altınyayla", "Divriği", "Doğanşar", "Gemerek", "Gölova", "Hafik", "İmranlı", "Kangal", "Koyulhisar", "Merkez", "Suşehri", "Şarkışla", "Ulaş", "Yıldızeli", "Zara"],
            "Tekirdağ": ["Çerkezköy", "Çorlu", "Ergene", "Hayrabolu", "Kapaklı", "Malkara", "Marmaraereğlisi", "Muratlı", "Saray", "Süleymanpaşa", "Şarköy"],
            "Tokat": ["Almus", "Artova", "Başçiftlik", "Erbaa", "Merkez", "Niksar", "Pazar", "Reşadiye", "Sulusaray", "Turhal", "Yeşilyurt", "Zile"],
            "Trabzon": ["Akçaabat", "Araklı", "Arsin", "Beşikdüzü", "Çaykara", "Çarşıbaşı", "Dernekpazarı", "Düzköy", "Hayrat", "Köprübaşı", "Maçka", "Of", "Ortahisar", "Sürmene", "Şalpazarı", "Tonya", "Vakfıkebir", "Yomra"],
            "Tunceli": ["Çemişgezek", "Hozat", "Mazgirt", "Merkez", "Nazımiye", "Ovacık", "Pertek", "Pülümür"],
            "Şanlıurfa": ["Akçakale", "Birecik", "Bozova", "Ceylanpınar", "Eyyübiye", "Halfeti", "Haliliye", "Harran", "Hilvan", "Karaköprü", "Siverek", "Suruç", "Viranşehir"],
            "Uşak": ["Banaz", "Eşme", "Karahallı", "Merkez", "Sivaslı", "Ulubey"],
            "Van": ["Bahçesaray", "Başkale", "Çaldıran", "Çatak", "Edremit", "Erciş", "Gevaş", "Gürpınar", "İpekyolu", "Muradiye", "Özalp", "Saray", "Tuşba"],
            "Yozgat": ["Akdağmadeni", "Aydıncık", "Boğazlıyan", "Çandır", "Çayıralan", "Çekerek", "Kadışehri", "Merkez", "Saraykent", "Sarıkaya", "Sorgun", "Şefaatli", "Yenifakılı", "Yerköy"],
            "Zonguldak": ["Alaplı", "Çaycuma", "Devrek", "Ereğli", "Gökçebey", "Kilimli", "Kozlu", "Merkez"],
            "Aksaray": ["Ağaçören", "Eskil", "Gülağaç", "Güzelyurt", "Merkez", "Ortaköy", "Sarıyahşi"],
            "Bayburt": ["Aydıntepe", "Demirözü", "Merkez"],
            "Karaman": ["Ayrancı", "Başyayla", "Ermenek", "Kazımkarabekir", "Merkez", "Sarıveliler"],
            "Kırıkkale": ["Bahşılı", "Balışeyh", "Çelebi", "Delice", "Karakeçili", "Keskin", "Merkez", "Sulakyurt", "Yahşihan"],
            "Batman": ["Beşiri", "Gercüş", "Hasankeyf", "Kozluk", "Merkez", "Sason"],
            "Şırnak": ["Beytüşşebap", "Cizre", "Güçlükonak", "İdil", "Merkez", "Silopi", "Uludere"],
            "Bartın": ["Amasra", "Kurucaşile", "Merkez", "Ulus"],
            "Ardahan": ["Çıldır", "Damal", "Göle", "Hanak", "Merkez", "Posof"],
            "Iğdır": ["Aralık", "Karakoyunlu", "Merkez", "Tuzluca"],
            "Yalova": ["Altınova", "Armutlu", "Çınarcık", "Çiftlikköy", "Merkez", "Termal"],
            "Karabük": ["Eflani", "Eskipazar", "Merkez", "Ovacık", "Safranbolu", "Yenice"],
            "Kilis": ["Elbeyli", "Merkez", "Musabeyli", "Polateli"],
            "Osmaniye": ["Bahçe", "Düziçi", "Hasanbeyli", "Kadirli", "Merkez", "Sumbas", "Toprakkale"],
            "Düzce": ["Akçakoca", "Cumayeri", "Çilimli", "Gölyaka", "Gümüşova", "Kaynaşlı", "Merkez", "Yığılca"]
        };

        // İlçe yükleme fonksiyonu
        function loadDistricts() {
            const citySelect = document.getElementById('city');
            const districtSelect = document.getElementById('district');
            const selectedCity = citySelect.value;
            
            // İlçe seçimini temizle
            districtSelect.innerHTML = '<option value="">İlçe seçin</option>';
            
            if (selectedCity && TURKEY_CITIES[selectedCity]) {
                const districts = TURKEY_CITIES[selectedCity];
                districts.forEach(function(district) {
                    const option = document.createElement('option');
                    option.value = district;
                    option.textContent = district;
                    districtSelect.appendChild(option);
                });
            }
        }
        
        // Telefon formatı fonksiyonu
        function formatPhone(input) {
            let value = input.value.replace(/\D/g, ''); // Sadece rakamları al
            
            if (value.length > 11) {
                value = value.substr(0, 11);
            }
            
            // Format: 05312345678 (boşluksuz)
            input.value = value;
        }
        
        // Şifre eşleşme kontrolü
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const messageDiv = document.getElementById('password-match-message');
            const confirmInput = document.getElementById('confirm_password');
            
            if (confirmPassword === '') {
                messageDiv.style.display = 'none';
                confirmInput.style.borderColor = '';
                return;
            }
            
            if (password === confirmPassword) {
                messageDiv.style.display = 'block';
                messageDiv.style.color = '#10b981';
                messageDiv.textContent = '✓ Şifreler eşleşiyor';
                confirmInput.style.borderColor = '#10b981';
            } else {
                messageDiv.style.display = 'block';
                messageDiv.style.color = '#ef4444';
                messageDiv.textContent = '✗ Şifreler eşleşmiyor';
                confirmInput.style.borderColor = '#ef4444';
            }
        }
        
        // Kullanıcı sözleşmesi modal
        function showAgreement() {
            alert(`KULLANICI SÖZLEŞMESİ

Bu sözleşme, Serviso HVAC Yönetim Sistemi'ni kullanmak için şirketiniz ile aramızda yapılan anlaşmayı düzenler.

MADDE 1 - TARAFLAR
Bu sözleşme, Serviso HVAC sistemi sağlayıcısı ile kullanıcı şirket arasında yapılmıştır.

MADDE 2 - HİZMET KAPSAMI
- HVAC servis yönetimi
- Müşteri takibi
- Teknisyen koordinasyonu
- Raporlama ve analiz

MADDE 3 - KULLANIM KOŞULLARI
- Sistem sadece yetkili personel tarafından kullanılacaktır
- Kullanıcı bilgileri gizli tutulacaktır
- Sistem kötüye kullanılmayacaktır

MADDE 4 - DENEME SÜRESİ
- 14 gün ücretsiz deneme süresi tanınır
- Deneme sonunda ücretli plana geçiş yapılmalıdır

MADDE 5 - VERİ GÜVENLİĞİ
- Tüm veriler güvenli sunucularda saklanır
- Düzenli yedekleme yapılır
- Veri gizliliği korunur

Bu sözleşmeyi kabul ederek yukarıdaki maddeleri okuduğunuzu ve kabul ettiğinizi beyan edersiniz.`);
        }
        
        // Sayfa yüklendiğinde seçili il varsa ilçeleri yükle
        document.addEventListener('DOMContentLoaded', function() {
            // İl-İlçe sistemi
            const citySelect = document.getElementById('city');
            if (citySelect.value) {
                loadDistricts();
                // POST verisi varsa seçili ilçeyi ayarla
                const selectedDistrict = '<?= isset($_POST['district']) ? htmlspecialchars($_POST['district']) : '' ?>';
                if (selectedDistrict) {
                    setTimeout(function() {
                        const districtSelect = document.getElementById('district');
                        districtSelect.value = selectedDistrict;
                    }, 100);
                }
            }
            
            // Telefon formatı
            const phoneInput = document.getElementById('phone');
            if (phoneInput) {
                phoneInput.addEventListener('input', function() {
                    formatPhone(this);
                });
                
                phoneInput.addEventListener('keypress', function(e) {
                    // Sadece rakam girişine izin ver (backspace, delete, tab, escape, enter hariç)
                    if ([8, 9, 27, 13, 46].indexOf(e.keyCode) !== -1 ||
                        // Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X işlemlerine izin ver
                        (e.keyCode === 65 && e.ctrlKey === true) ||
                        (e.keyCode === 67 && e.ctrlKey === true) ||
                        (e.keyCode === 86 && e.ctrlKey === true) ||
                        (e.keyCode === 88 && e.ctrlKey === true)) {
                        return;
                    }
                    // Rakam değilse engelle
                    if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
                        e.preventDefault();
                    }
                });
            }
            
            // Şifre eşleşme kontrolü
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            
            if (passwordInput && confirmPasswordInput) {
                passwordInput.addEventListener('input', checkPasswordMatch);
                confirmPasswordInput.addEventListener('input', checkPasswordMatch);
            }
        });
    </script>

</body>
</html>
    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    

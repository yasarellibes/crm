<?php
require_once 'includes/header.php';
require_once 'includes/functions.php';

requireLogin();

// Only company admins can access this page
if ($_SESSION['role'] !== 'company_admin') {
    header('Location: dashboard.php');
    exit;
}

$currentUser = $_SESSION;

$message = '';
$messageType = '';

// Get current company data
function getCurrentCompany($companyId) {
    require_once 'config/database.php';
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT * FROM companies WHERE id = ?");
    $stmt->execute([$companyId]);
    return $stmt->fetch();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $district = trim($_POST['district'] ?? '');
    
    if (empty($name)) {
        $message = 'Şirket adı zorunludur';
        $messageType = 'error';
    } else {
        try {
            require_once 'config/database.php';
            $pdo = getPDO();
            
            // Update password only if provided
            if (!empty($_POST['password'])) {
                $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $sql = "UPDATE companies SET name = ?, phone = ?, email = ?, address = ?, city = ?, district = ?, password = ? WHERE id = ?";
                $params = [$name, $phone, $email, $address, $city, $district, $hashedPassword, $currentUser['company_id']];
            } else {
                $sql = "UPDATE companies SET name = ?, phone = ?, email = ?, address = ?, city = ?, district = ? WHERE id = ?";
                $params = [$name, $phone, $email, $address, $city, $district, $currentUser['company_id']];
            }
            
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute($params)) {
                $message = 'Şirket bilgileri başarıyla güncellendi';
                $messageType = 'success';
            } else {
                $message = 'Güncelleme sırasında hata oluştu';
                $messageType = 'error';
            }
        } catch (Exception $e) {
            $message = 'Veritabanı hatası: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Get current company data
$company = getCurrentCompany($currentUser['company_id']);
?>



<div class="main-content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="page-header">
                    <h1><i class="fas fa-building-user me-2"></i>Şirket Ayarları</h1>
                    <p class="text-muted">Şirket bilgilerinizi buradan düzenleyebilirsiniz</p>
                </div>

                <?php if ($message): ?>
                <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-edit me-2"></i>Şirket Bilgileri
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <!-- Şirket Adı -->
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Şirket Adı *</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?= e($company['name'] ?? '') ?>" required>
                                </div>
                                
                                <!-- Telefon -->
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Telefon</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?= e($company['phone'] ?? '') ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <!-- Email -->
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">E-posta</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?= e($company['email'] ?? '') ?>">
                                </div>
                                
                                <!-- Şifre -->
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">Şifre</label>
                                    <input type="password" class="form-control" id="password" name="password" minlength="6">
                                    <div class="form-text">Boş bırakılırsa mevcut şifre korunur</div>
                                </div>
                            </div>
                            
                            <!-- İl - İlçe -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="city" class="form-label">İl</label>
                                    <select class="form-select" id="city" name="city">
                                        <option value="">İl seçin...</option>
                                        <?php
                                        $cities = ['Adana', 'Adıyaman', 'Afyonkarahisar', 'Ağrı', 'Amasya', 'Ankara', 'Antalya', 'Artvin', 'Aydın', 'Balıkesir', 'Bilecik', 'Bingöl', 'Bitlis', 'Bolu', 'Burdur', 'Bursa', 'Çanakkale', 'Çankırı', 'Çorum', 'Denizli', 'Diyarbakır', 'Edirne', 'Elazığ', 'Erzincan', 'Erzurum', 'Eskişehir', 'Gaziantep', 'Giresun', 'Gümüşhane', 'Hakkari', 'Hatay', 'Isparta', 'Mersin', 'İstanbul', 'İzmir', 'Kars', 'Kastamonu', 'Kayseri', 'Kırklareli', 'Kırşehir', 'Kocaeli', 'Konya', 'Kütahya', 'Malatya', 'Manisa', 'Kahramanmaraş', 'Mardin', 'Muğla', 'Muş', 'Nevşehir', 'Niğde', 'Ordu', 'Rize', 'Sakarya', 'Samsun', 'Siirt', 'Sinop', 'Sivas', 'Tekirdağ', 'Tokat', 'Trabzon', 'Tunceli', 'Şanlıurfa', 'Uşak', 'Van', 'Yozgat', 'Zonguldak', 'Aksaray', 'Bayburt', 'Karaman', 'Kırıkkale', 'Batman', 'Şırnak', 'Bartın', 'Ardahan', 'Iğdır', 'Yalova', 'Karabük', 'Kilis', 'Osmaniye', 'Düzce'];
                                        sort($cities);
                                        foreach ($cities as $cityName) {
                                            $selected = ($cityName === ($company['city'] ?? '')) ? 'selected' : '';
                                            echo "<option value=\"$cityName\" $selected>$cityName</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="district" class="form-label">İlçe</label>
                                    <select class="form-select" id="district" name="district">
                                        <option value="">İlçe seçin...</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Adres -->
                            <div class="mb-3">
                                <label for="address" class="form-label">Adres</label>
                                <textarea class="form-control" id="address" name="address" rows="3"><?= e($company['address'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Kaydet
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
// City-District functionality
document.addEventListener('DOMContentLoaded', function() {
    const citySelect = document.getElementById('city');
    const districtSelect = document.getElementById('district');
    
    // Districts data
    const districts = {
        'Adana': ['Aladağ', 'Ceyhan', 'Çukurova', 'Feke', 'İmamoğlu', 'Karaisalı', 'Karataş', 'Kozan', 'Pozantı', 'Saimbeyli', 'Sarıçam', 'Seyhan', 'Tufanbeyli', 'Yumurtalık', 'Yüreğir'],
        'Ankara': ['Akyurt', 'Altındağ', 'Ayaş', 'Bala', 'Beypazarı', 'Çamlıdere', 'Çankaya', 'Çubuk', 'Elmadağ', 'Etimesgut', 'Evren', 'Gölbaşı', 'Güdül', 'Haymana', 'Kahramankazan', 'Kalecik', 'Keçiören', 'Kızılcahamam', 'Mamak', 'Nallıhan', 'Polatlı', 'Pursaklar', 'Sincan', 'Şereflikoçhisar', 'Yenimahalle'],
        'İstanbul': ['Adalar', 'Arnavutköy', 'Ataşehir', 'Avcılar', 'Bağcılar', 'Bahçelievler', 'Bakırköy', 'Başakşehir', 'Bayrampaşa', 'Beşiktaş', 'Beykoz', 'Beylikdüzü', 'Beyoğlu', 'Büyükçekmece', 'Çatalca', 'Çekmeköy', 'Esenler', 'Esenyurt', 'Eyüpsultan', 'Fatih', 'Gaziosmanpaşa', 'Güngören', 'Kadıköy', 'Kağıthane', 'Kartal', 'Küçükçekmece', 'Maltepe', 'Pendik', 'Sancaktepe', 'Sarıyer', 'Silivri', 'Sultangazi', 'Sultanbeyli', 'Şile', 'Şişli', 'Tuzla', 'Ümraniye', 'Üsküdar', 'Zeytinburnu'],
        'İzmir': ['Aliağa', 'Balçova', 'Bayındır', 'Bayraklı', 'Bergama', 'Beydağ', 'Bornova', 'Buca', 'Çeşme', 'Çiğli', 'Dikili', 'Foça', 'Gaziemir', 'Güzelbahçe', 'Karabağlar', 'Karaburun', 'Karşıyaka', 'Kemalpaşa', 'Kınık', 'Kiraz', 'Konak', 'Menderes', 'Menemen', 'Narlıdere', 'Ödemiş', 'Seferihisar', 'Selçuk', 'Tire', 'Torbalı', 'Urla'],
        'Samsun': ['Alaçam', 'Asarcık', 'Atakum', 'Ayvacık', 'Bafra', 'Canik', 'Çarşamba', 'Havza', 'İlkadım', 'Kavak', 'Ladik', 'Ondokuzmayıs', 'Salıpazarı', 'Tekkeköy', 'Terme', 'Vezirköprü', 'Yakakent']
    };
    
    // Load districts function
    function loadDistricts(city, selectedDistrict = '') {
        districtSelect.innerHTML = '<option value="">İlçe seçin...</option>';
        
        if (districts[city]) {
            districts[city].sort().forEach(district => {
                const option = document.createElement('option');
                option.value = district;
                option.textContent = district;
                if (district === selectedDistrict) {
                    option.selected = true;
                }
                districtSelect.appendChild(option);
            });
        }
    }
    
    // City change event
    citySelect.addEventListener('change', function() {
        loadDistricts(this.value);
    });
    
    // Load districts for current city on page load
    const currentCity = citySelect.value;
    const currentDistrict = '<?= e($company['district'] ?? '') ?>';
    if (currentCity) {
        loadDistricts(currentCity, currentDistrict);
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
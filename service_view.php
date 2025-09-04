<?php
require_once 'includes/header.php';
require_once 'includes/functions.php';

requireLogin();

$serviceId = $_GET['id'] ?? null;
if (!$serviceId) {
    header('Location: services.php?error=service_required');
    exit;
}

// Get service details with role-based access
$service = fetchOne("
    SELECT s.*, c.name as customer_name, c.phone as customer_phone, 
           c.address as customer_address, c.city as customer_city, c.district as customer_district,
           p.name as personnel_name, p.email as personnel_email, p.phone as personnel_phone,
           co.name as company_name, b.name as branch_name, 
           COALESCE(br.name, s.brand) as brand_name,
           COALESCE(md.name, s.model) as model_name
    FROM services s
    LEFT JOIN customers c ON s.customer_id = c.id
    LEFT JOIN personnel p ON s.personnel_id = p.id
    LEFT JOIN companies co ON s.company_id = co.id
    LEFT JOIN branches b ON s.branch_id = b.id
    LEFT JOIN brands br ON (CASE WHEN s.brand ~ '^[0-9]+$' THEN CAST(s.brand AS INTEGER) ELSE NULL END) = br.id AND s.company_id = br.company_id
    LEFT JOIN models md ON (CASE WHEN s.model ~ '^[0-9]+$' THEN CAST(s.model AS INTEGER) ELSE NULL END) = md.id AND s.company_id = md.company_id
    WHERE s.id = ?
", [$serviceId]);

if (!$service) {
    header('Location: services.php?error=service_not_found');
    exit;
}

// Apply role-based access control
$currentUser = $_SESSION;
$hasAccess = false;

if ($currentUser['role'] === 'super_admin') {
    $hasAccess = true;
} elseif ($currentUser['role'] === 'company_admin') {
    $hasAccess = ($service['company_id'] == $currentUser['company_id']);
} elseif ($currentUser['role'] === 'branch_manager') {
    $hasAccess = ($service['company_id'] == $currentUser['company_id'] && 
                  $service['branch_id'] == $currentUser['branch_id']);
} elseif ($currentUser['role'] === 'technician') {
    // Technician can only see their assigned services
    $techCheck = fetchOne("
        SELECT COUNT(*) as count FROM personnel 
        WHERE email = ? AND id = ?
    ", [$currentUser['email'], $service['personnel_id']]);
    $hasAccess = ($techCheck['count'] > 0);
}

if (!$hasAccess) {
    header('Location: services.php?error=no_permission');
    exit;
}

$pageTitle = 'Servis Detayları - #' . $serviceId . ' - Serviso';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="page-header">
                    <h2 class="mb-0">
                        <i class="fas fa-wrench me-2"></i>Servis Detayları - #<?= $serviceId ?>
                    </h2>
                    <p class="page-subtitle">
                        <?= e($service['customer_name']) ?> • <?= formatDate($service['service_date']) ?>
                    </p>
                </div>
            </div>
            <div class="col-lg-4 text-lg-end">
                <a href="services.php" class="btn btn-outline-secondary me-2">
                    <i class="fas fa-arrow-left me-1"></i>Servislere Dön
                </a>
                <?php if ($currentUser['role'] !== 'technician'): ?>
                <a href="service_edit.php?id=<?= $serviceId ?>" class="btn btn-primary">
                    <i class="fas fa-edit me-1"></i>Düzenle
                </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="row">
            <!-- Service Details -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-info-circle me-2"></i>Servis Bilgileri
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-6 mb-3">
                                <label class="fw-bold">Cihaz:</label>
                                <div><?= e($service['device']) ?></div>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <label class="fw-bold">Marka:</label>
                                <div><?= e($service['brand_name'] ?? $service['brand'] ?? 'Belirtilmemiş') ?></div>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <label class="fw-bold">Model:</label>
                                <div><?= e($service['model_name'] ?? $service['model'] ?? 'Belirtilmemiş') ?></div>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <label class="fw-bold">Şikayet:</label>
                                <div><?= e($service['complaint']) ?></div>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="fw-bold">Açıklama:</label>
                                <div class="border rounded p-3 bg-light">
                                    <?= $service['description'] ? nl2br(e($service['description'])) : '<span class="text-muted">Açıklama girilmemiş</span>' ?>
                                </div>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <label class="fw-bold">Servis Tarihi:</label>
                                <div><?= date('d.m.Y', strtotime($service['service_date'])) ?></div>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <label class="fw-bold">Durum:</label>
                                <div>
                                    <?php 
                                    $statusClass = [
                                        'Beklemede' => 'warning',
                                        'Devam Ediyor' => 'info', 
                                        'Tamamlandı' => 'success',
                                        'İptal Edildi' => 'danger'
                                    ][$service['operation_status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $statusClass ?> fs-6"><?= e($service['operation_status']) ?></span>
                                </div>
                            </div>
                            <?php if ($service['price']): ?>
                            <div class="col-sm-6 mb-3">
                                <label class="fw-bold">Fiyat:</label>
                                <div class="text-success fs-5"><?= $service['price'] ? number_format($service['price'], 2) . ' ₺' : '-' ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Customer Information -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-user me-2"></i>Müşteri Bilgileri
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-6 mb-3">
                                <label class="fw-bold">Müşteri Adı:</label>
                                <div>
                                    <a href="customer_services.php?customer_id=<?= $service['customer_id'] ?>" class="text-decoration-none">
                                        <?= e($service['customer_name']) ?>
                                    </a>
                                </div>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <label class="fw-bold">Telefon:</label>
                                <div>
                                    <a href="tel:<?= e($service['customer_phone']) ?>" class="phone-link">
                                        <?= $service['customer_phone'] ?>
                                    </a>
                                </div>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="fw-bold">Adres:</label>
                                <div class="d-flex align-items-center">
                                    <div class="flex-fill">
                                        <?= e($service['customer_address']) ?><br>
                                        <small class="text-muted"><?= e($service['customer_city']) ?>, <?= e($service['customer_district']) ?></small>
                                    </div>
                                    <a href="https://maps.google.com/maps?q=<?= urlencode($service['customer_address'] . ' ' . $service['customer_district'] . ' ' . $service['customer_city']) ?>" 
                                       target="_blank" class="btn btn-outline-primary btn-sm ms-2">
                                        <i class="fas fa-map-marker-alt me-1"></i>Harita
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-md-4">
                <!-- Personnel Information -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-user-tie me-2"></i>Atanan Teknisyen
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($service['personnel_name']): ?>
                        <div class="mb-2">
                            <strong><?= e($service['personnel_name']) ?></strong>
                        </div>
                        <?php if ($service['personnel_email']): ?>
                        <div class="mb-2">
                            <a href="mailto:<?= e($service['personnel_email']) ?>"><?= e($service['personnel_email']) ?></a>
                        </div>
                        <?php endif; ?>
                        <?php if ($service['personnel_phone']): ?>
                        <div>
                            <a href="tel:<?= e($service['personnel_phone']) ?>" class="phone-link">
                                <?= formatPhone($service['personnel_phone']) ?>
                            </a>
                        </div>
                        <?php endif; ?>
                        <?php else: ?>
                        <div class="text-muted">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Henüz teknisyen atanmamış
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Company/Branch Information -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-building me-2"></i>Şirket/Şube
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <strong>Şirket:</strong><br>
                            <?= e($service['company_name'] ?? 'Belirtilmemiş') ?>
                        </div>
                        <?php if ($service['branch_name']): ?>
                        <div>
                            <strong>Şube:</strong><br>
                            <?= e($service['branch_name']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Actions -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-cogs me-2"></i>İşlemler
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="service_print.php?id=<?= $serviceId ?>" target="_blank" class="btn btn-success">
                                <i class="fas fa-print me-2"></i>Yazdır
                            </a>
                            <?php if ($currentUser['role'] !== 'technician'): ?>
                            <a href="service_edit.php?id=<?= $serviceId ?>" class="btn btn-primary">
                                <i class="fas fa-edit me-2"></i>Düzenle
                            </a>
                            <button class="btn btn-outline-danger" onclick="confirmDelete(<?= $serviceId ?>)">
                                <i class="fas fa-trash me-2"></i>Sil
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Service History -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-clock me-2"></i>Kayıt Bilgileri
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <strong>Oluşturulma:</strong><br>
                            <small class="text-muted"><?= formatDate($service['created_at']) ?></small>
                        </div>
                        <?php if ($service['updated_at'] && $service['updated_at'] !== $service['created_at']): ?>
                        <div>
                            <strong>Son Güncelleme:</strong><br>
                            <small class="text-muted"><?= formatDate($service['updated_at']) ?></small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(serviceId) {
    if (confirm('Bu servisi silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.')) {
        fetch('service_delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'id=' + serviceId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('Servis başarıyla silindi', 'success');
                setTimeout(() => {
                    window.location.href = 'services.php';
                }, 1500);
            } else {
                showAlert(data.message || 'Silme işlemi başarısız', 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('İşlem başarısız', 'danger');
        });
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
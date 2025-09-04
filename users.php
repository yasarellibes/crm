<?php
require_once 'includes/header.php';
require_once 'includes/functions.php';

requireLogin();

// Check permissions
$currentUser = $_SESSION;
if ($currentUser['role'] == 'technician') {
    header('Location: dashboard.php?error=no_permission');
    exit;
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$companyFilter = $_GET['company_id'] ?? '';
$roleFilter = $_GET['role'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Build query with role-based filtering
$whereConditions = ['1=1'];
$queryParams = [];

// Apply data filters based on user role
if ($currentUser['role'] == 'company_admin') {
    $whereConditions[] = 'u.company_id = ?';
    $queryParams[] = $currentUser['company_id'];
} elseif ($currentUser['role'] == 'branch_manager') {
    $whereConditions[] = 'u.company_id = ?';
    $whereConditions[] = 'u.branch_id = ?';
    $queryParams[] = $currentUser['company_id'];
    $queryParams[] = $currentUser['branch_id'];
}

// Apply search filter
if ($search) {
    $whereConditions[] = '(CONCAT(u.first_name, \' \', u.last_name) ILIKE ? OR u.email ILIKE ? OR c.name ILIKE ?)';
    $searchTerm = "%{$search}%";
    $queryParams[] = $searchTerm;
    $queryParams[] = $searchTerm;
    $queryParams[] = $searchTerm;
}

// Apply company filter (super admin only)
if ($companyFilter && $currentUser['role'] == 'super_admin') {
    $whereConditions[] = 'u.company_id = ?';
    $queryParams[] = $companyFilter;
}

// Apply role filter
if ($roleFilter) {
    $whereConditions[] = 'u.role = ?';
    $queryParams[] = $roleFilter;
}

$whereClause = implode(' AND ', $whereConditions);

// Get total count
$countQuery = "
    SELECT COUNT(*) as total 
    FROM users u
    LEFT JOIN companies c ON u.company_id = c.id
    WHERE $whereClause
";
$totalResult = fetchOne($countQuery, $queryParams);
$totalCount = $totalResult['total'] ?? 0;
$totalPages = ceil($totalCount / $perPage);

// Get users with company and branch info
$query = "
    SELECT u.*, 
           c.name as company_name, 
           b.name as branch_name,
           CONCAT(u.first_name, ' ', u.last_name) as name,
           u.last_login as last_login_at,
           CASE 
               WHEN u.status = 'active' THEN true 
               ELSE false 
           END as is_active
    FROM users u
    LEFT JOIN companies c ON u.company_id = c.id
    LEFT JOIN branches b ON u.branch_id = b.id
    WHERE $whereClause
    ORDER BY u.first_name ASC
    LIMIT $perPage OFFSET $offset
";

$users = fetchAll($query, $queryParams);

// Get companies for filter (super admin only)
$companies = [];
if ($currentUser['role'] == 'super_admin') {
    $companies = fetchAll("SELECT id, name FROM companies ORDER BY name ASC", []);
}

$pageTitle = 'Kullanıcı Yönetimi - Serviso';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="page-header">
                    <h2 class="mb-0">Kullanıcı Yönetimi</h2>
                    <p class="page-subtitle">Sistem kullanıcılarını yönetin</p>
                </div>
            </div>
            <div class="col-lg-4 text-lg-end">
                <button class="btn btn-primary" onclick="showAddUserModal()">
                    <i class="fas fa-user-plus me-1"></i>
                    Yeni Kullanıcı
                </button>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Ad, email, şirket..." value="<?= e($search) ?>">
                            </div>
                            <?php if ($currentUser['role'] == 'super_admin'): ?>
                            <div class="col-md-3">
                                <select class="form-select" name="company_id">
                                    <option value="">Tüm Şirketler</option>
                                    <?php foreach ($companies as $company): ?>
                                    <option value="<?= $company['id'] ?>" <?= $company['id'] == $companyFilter ? 'selected' : '' ?>>
                                        <?= e($company['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                            <div class="col-md-3">
                                <select class="form-select" name="role">
                                    <option value="">Tüm Roller</option>
                                    <option value="super_admin" <?= $roleFilter == 'super_admin' ? 'selected' : '' ?>>Süper Admin</option>
                                    <option value="company_admin" <?= $roleFilter == 'company_admin' ? 'selected' : '' ?>>Şirket Yöneticisi</option>
                                    <option value="branch_manager" <?= $roleFilter == 'branch_manager' ? 'selected' : '' ?>>Şube Müdürü</option>
                                    <option value="technician" <?= $roleFilter == 'technician' ? 'selected' : '' ?>>Teknisyen</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-search me-1"></i>Ara
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Users Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-users me-2"></i>
                    Kullanıcılar (<?= number_format($totalCount) ?>)
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($users)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <h5>Kullanıcı bulunamadı</h5>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Kullanıcı</th>
                                <th>İletişim</th>
                                <th>Rol</th>
                                <th>Şirket/Şube</th>
                                <th>Son Giriş</th>
                                <th>Durum</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <div class="fw-bold"><?= e($user['name']) ?></div>
                                        <small class="text-muted">ID: #<?= $user['id'] ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <a href="mailto:<?= e($user['email']) ?>"><?= e($user['email']) ?></a>
                                        <?php if ($user['phone']): ?>
                                        <br><a href="tel:<?= e($user['phone']) ?>" class="phone-link"><?= formatPhone($user['phone']) ?></a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $roleLabels = [
                                        'super_admin' => ['Süper Admin', 'danger'],
                                        'company_admin' => ['Şirket Yön.', 'warning'],
                                        'branch_manager' => ['Şube Müd.', 'info'],
                                        'technician' => ['Teknisyen', 'success']
                                    ];
                                    $roleInfo = $roleLabels[$user['role']] ?? [$user['role'], 'secondary'];
                                    ?>
                                    <span class="badge bg-<?= $roleInfo[1] ?>"><?= $roleInfo[0] ?></span>
                                </td>
                                <td>
                                    <div>
                                        <div><?= e($user['company_name'] ?? 'Şirket yok') ?></div>
                                        <?php if ($user['branch_name']): ?>
                                        <small class="text-muted"><?= e($user['branch_name']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?= $user['last_login_at'] ? '<span class="text-muted small">' . formatDate($user['last_login_at']) . '</span>' : '<span class="text-muted">Hiç giriş yok</span>' ?>
                                </td>
                                <td>
                                    <?php if ($user['is_active']): ?>
                                    <span class="badge bg-success">Aktif</span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">Pasif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group-horizontal" role="group">
                                        <button class="btn btn-outline-primary btn-action-wide" 
                                                onclick="editUser(<?= $user['id'] ?>)" title="Düzenle">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($user['is_active']): ?>
                                        <button class="btn btn-outline-warning btn-action-wide" 
                                                onclick="toggleUserStatus(<?= $user['id'] ?>, false)" title="Pasif Yap">
                                            <i class="fas fa-user-slash"></i>
                                        </button>
                                        <?php else: ?>
                                        <button class="btn btn-outline-success btn-action-wide" 
                                                onclick="toggleUserStatus(<?= $user['id'] ?>, true)" title="Aktif Yap">
                                            <i class="fas fa-user-check"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="card-footer">
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted">
                        Toplam <?= number_format($totalCount) ?> kayıt, Sayfa <?= $page ?>/<?= $totalPages ?>
                    </small>
                    <?= generatePagination($page, $totalPages, 'users.php?' . http_build_query(['search' => $search, 'company_id' => $companyFilter, 'role' => $roleFilter, 'page' => ''])) ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function showAddUserModal() {
    // Implement add user modal
    showAlert('Kullanıcı ekleme özelliği yakında eklenecek', 'info');
}

function editUser(userId) {
    // Implement edit user modal
    showAlert('Kullanıcı düzenleme özelliği yakında eklenecek', 'info');
}

function toggleUserStatus(userId, activate) {
    const action = activate ? 'aktif' : 'pasif';
    if (confirm(`Bu kullanıcıyı ${action} yapmak istediğinizden emin misiniz?`)) {
        // Implement status toggle
        showAlert('Durum değiştirme özelliği yakında eklenecek', 'info');
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
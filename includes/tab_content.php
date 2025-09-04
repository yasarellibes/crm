<?php
/**
 * Tab Content Generator for AJAX Refresh
 */

$type = $_GET['type'] ?? '';
$currentUser = $_SESSION;

// Get data based on type
switch ($type) {
    case 'branches':
        $data = getBranchesDefinitions();
        break;
    case 'personnel':
        $data = getPersonnelDefinitions();
        break;
    case 'complaints':
        $data = getDefinitions('complaints');
        break;
    case 'devices':
        $data = getDefinitions('devices');
        break;
    case 'brands':
        $data = getDefinitions('brands');
        break;
    case 'models':
        $data = getDefinitionsWithBrand('models');
        break;
    case 'operations':
        $data = getDefinitions('operations');
        break;
    default:
        echo 'Invalid type';
        exit;
}

// Generate tab content based on type
if ($type === 'branches'): ?>
    <div class="definition-section">
        <div class="section-header">
            <h4><i class="fas fa-building me-2"></i>Şube Yönetimi</h4>
            <?php if ($currentUser && in_array($currentUser['role'], ['super_admin', 'company_admin'])): ?>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#branchModal">
                <i class="fas fa-plus me-1"></i>Yeni Şube
            </button>
            <?php endif; ?>
        </div>
        
        <div class="search-bar mb-3">
            <input type="text" class="form-control form-control-sm" placeholder="Şube ara..." onkeyup="filterTable('branches-table', this.value)">
        </div>
        
        <div class="items-list">
            <table class="items-table table-sm" id="branches-table">
                <thead>
                    <tr>
                        <th>Şube Bilgileri</th>
                        <th>İletişim</th>
                        <th width="100">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($data)): ?>
                    <?php foreach ($data as $branch): ?>
                    <tr>
                        <td>
                            <div class="row-content">
                                <div class="row-icon">
                                    <i class="fas fa-building text-primary"></i>
                                </div>
                                <div class="row-details">
                                    <h6 class="mb-1"><?= e($branch['name']) ?></h6>
                                    <small class="text-muted"><?= e($branch['city'] ?? '') ?> <?= e($branch['district'] ?? '') ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <small>
                                <?php if ($branch['phone']): ?>
                                <a href="tel:<?= e($branch['phone']) ?>" class="text-decoration-none">
                                    <i class="fas fa-phone text-success me-1"></i><?= e($branch['phone']) ?>
                                </a><br>
                                <?php endif; ?>
                                <?php if ($branch['email']): ?>
                                <a href="mailto:<?= e($branch['email']) ?>" class="text-decoration-none">
                                    <i class="fas fa-envelope text-info me-1"></i><?= e($branch['email']) ?>
                                </a>
                                <?php endif; ?>
                            </small>
                        </td>
                        <td>
                            <?php if ($currentUser && in_array($currentUser['role'], ['super_admin', 'company_admin'])): ?>
                            <div class="btn-group" role="group">
                                <button class="btn btn-outline-primary btn-sm" onclick="editBranch(<?= $branch['id'] ?>, '<?= e($branch['name']) ?>', '<?= e($branch['phone'] ?? '') ?>', '<?= e($branch['email'] ?? '') ?>', '<?= e($branch['city'] ?? '') ?>', '<?= e($branch['district'] ?? '') ?>', '<?= e($branch['address'] ?? '') ?>')" title="Düzenle">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-outline-danger btn-sm" onclick="deleteItem('branches', <?= $branch['id'] ?>, '<?= e($branch['name']) ?>')" title="Sil">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                            <?php else: ?>
                            <span class="text-muted small">Sadece Okuma</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="3" class="text-center py-4">
                            <div class="empty-state">
                                <i class="fas fa-building fa-2x text-muted mb-2"></i>
                                <p class="text-muted">Henüz şube kaydı yok</p>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($type === 'personnel'): ?>
    <div class="definition-section">
        <div class="section-header">
            <h4><i class="fas fa-users me-2"></i>Personel Yönetimi</h4>
            <?php if ($currentUser && in_array($currentUser['role'], ['super_admin', 'company_admin', 'branch_manager'])): ?>
            <button class="btn btn-primary btn-sm" onclick="openAddModal('personnel')">
                <i class="fas fa-plus me-1"></i>Yeni Personel
            </button>
            <?php endif; ?>
        </div>
        
        <div class="search-bar mb-3">
            <input type="text" class="form-control form-control-sm" placeholder="Personel ara..." onkeyup="filterTable('personnel-table', this.value)">
        </div>
        
        <div class="items-list">
            <table class="items-table table-sm" id="personnel-table">
                <thead>
                    <tr>
                        <th>Personel Bilgileri</th>
                        <th>İletişim</th>
                        <th>Şube</th>
                        <th width="100">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($data)): ?>
                    <?php foreach ($data as $person): ?>
                    <tr>
                        <td>
                            <div class="row-content">
                                <div class="row-icon">
                                    <i class="fas fa-user text-info"></i>
                                </div>
                                <div class="row-details">
                                    <h6 class="mb-1"><?= e($person['name']) ?></h6>
                                    <small class="text-muted"><?= e($person['city'] ?? '') ?> <?= e($person['district'] ?? '') ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <small>
                                <?php if ($person['phone']): ?>
                                <a href="tel:<?= e($person['phone']) ?>" class="text-decoration-none">
                                    <i class="fas fa-phone text-success me-1"></i><?= e($person['phone']) ?>
                                </a><br>
                                <?php endif; ?>
                                <?php if ($person['email']): ?>
                                <a href="mailto:<?= e($person['email']) ?>" class="text-decoration-none">
                                    <i class="fas fa-envelope text-info me-1"></i><?= e($person['email']) ?>
                                </a>
                                <?php endif; ?>
                            </small>
                        </td>
                        <td>
                            <small class="text-muted"><?= e($person['branch_name'] ?? 'Atanmamış') ?></small>
                        </td>
                        <td>
                            <?php if ($currentUser && in_array($currentUser['role'], ['super_admin', 'company_admin', 'branch_manager'])): ?>
                            <div class="btn-group" role="group">
                                <button class="btn btn-outline-primary btn-sm" onclick="editPersonnel(<?= $person['id'] ?>, '<?= e($person['name']) ?>', '<?= e($person['phone'] ?? '') ?>', '<?= e($person['email'] ?? '') ?>', '<?= e($person['city'] ?? '') ?>', '<?= e($person['district'] ?? '') ?>', '<?= e($person['branch_name'] ?? '') ?>', <?= $person['branch_id'] ?? 'null' ?>)" title="Düzenle">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-outline-danger btn-sm" onclick="deleteItem('personnel', <?= $person['id'] ?>, '<?= e($person['name']) ?>')" title="Sil">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                            <?php else: ?>
                            <span class="text-muted small">Sadece Okuma</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="4" class="text-center py-4">
                            <div class="empty-state">
                                <i class="fas fa-users fa-2x text-muted mb-2"></i>
                                <p class="text-muted">Henüz personel kaydı yok</p>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php else: // Simple definition types (complaints, devices, brands, operations, models) ?>
    <div class="definition-section">
        <div class="section-header">
            <h4>
                <i class="fas fa-<?= $type === 'complaints' ? 'exclamation-triangle' : ($type === 'devices' ? 'microchip' : ($type === 'brands' ? 'tag' : ($type === 'models' ? 'cube' : 'cog'))) ?> me-2"></i>
                <?= $type === 'complaints' ? 'Şikayet Türleri' : ($type === 'devices' ? 'Cihaz Türleri' : ($type === 'brands' ? 'Marka Yönetimi' : ($type === 'models' ? 'Model Yönetimi' : 'Operasyon Türleri'))) ?>
            </h4>
            <?php if ($currentUser && in_array($currentUser['role'], ['super_admin', 'company_admin', 'branch_manager'])): ?>
            <button class="btn btn-primary btn-sm" onclick="openAddModal('<?= $type ?>')">
                <i class="fas fa-plus me-1"></i>Yeni <?= $type === 'complaints' ? 'Şikayet' : ($type === 'devices' ? 'Cihaz' : ($type === 'brands' ? 'Marka' : ($type === 'models' ? 'Model' : 'Operasyon'))) ?>
            </button>
            <?php endif; ?>
        </div>
        
        <div class="search-bar mb-3">
            <input type="text" class="form-control form-control-sm" placeholder="Ara..." onkeyup="filterTable('<?= $type ?>-table', this.value)">
        </div>
        
        <div class="items-list">
            <table class="items-table table-sm" id="<?= $type ?>-table">
                <thead>
                    <tr>
                        <th><?= $type === 'models' ? 'Model / Marka' : 'Ad' ?></th>
                        <?php if ($type === 'operations'): ?>
                        <th width="80">Renk</th>
                        <?php endif; ?>
                        <th width="80">Kaynak</th>
                        <th width="100">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($data)): ?>
                    <?php foreach ($data as $item): ?>
                    <tr>
                        <td>
                            <div class="row-content">
                                <div class="row-icon">
                                    <?php if ($type === 'operations' && isset($item['color'])): ?>
                                    <div class="operation-color-indicator" style="background-color: <?= e($item['color']) ?>; width: 20px; height: 20px; border-radius: 50%; display: inline-block;"></div>
                                    <?php else: ?>
                                    <i class="fas fa-<?= $type === 'complaints' ? 'exclamation-triangle text-warning' : ($type === 'devices' ? 'microchip text-primary' : ($type === 'brands' ? 'tag text-success' : ($type === 'models' ? 'cube text-info' : 'cog text-secondary'))) ?>"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="row-details">
                                    <h6 class="mb-0"><?= e($item['name']) ?></h6>
                                    <?php if ($type === 'models' && isset($item['brand_name'])): ?>
                                    <small class="text-muted"><?= e($item['brand_name']) ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <?php if ($type === 'operations'): ?>
                        <td>
                            <input type="color" value="<?= e($item['color'] ?? '#6c757d') ?>" onchange="updateOperationColor(<?= $item['id'] ?>, this.value)" class="form-control form-control-color" style="width: 50px; height: 30px; padding: 1px;">
                        </td>
                        <?php endif; ?>
                        <td>
                            <?php if (!empty($item['branch_id'])): ?>
                                <span class="badge bg-info text-dark">Şube</span>
                            <?php else: ?>
                                <span class="badge bg-primary">Şirket</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group" role="group">
                                <?php if ($type === 'models'): ?>
                                <button class="btn btn-outline-primary btn-sm" onclick="editItem('models', <?= $item['id'] ?>, '<?= e($item['name']) ?>', {brand_id: <?= $item['brand_id'] ?? 0 ?>})" title="Düzenle">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php else: ?>
                                <button class="btn btn-outline-primary btn-sm" onclick="editItem('<?= $type ?>', <?= $item['id'] ?>, '<?= e($item['name']) ?>')" title="Düzenle">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php endif; ?>
                                <button class="btn btn-outline-danger btn-sm" onclick="deleteItem('<?= $type ?>', <?= $item['id'] ?>, '<?= e($item['name']) ?>')" title="Sil">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="3" class="text-center py-4">
                            <div class="empty-state">
                                <i class="fas fa-<?= $type === 'complaints' ? 'exclamation-triangle' : ($type === 'devices' ? 'microchip' : ($type === 'brands' ? 'tag' : ($type === 'models' ? 'cube' : 'cog'))) ?> fa-2x text-muted mb-2"></i>
                                <p class="text-muted">Henüz kayıt yok</p>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
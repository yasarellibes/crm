<!-- Definition Modals for CRUD Operations -->

<!-- Generic Add/Edit Modal -->
<div class="modal fade" id="definitionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="definitionModalTitle">Ekle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="definitionForm">
                <div class="modal-body">
                    <input type="hidden" id="definition_id" name="id">
                    <input type="hidden" id="definition_type" name="type">
                    <input type="hidden" id="definition_action" name="action">
                    
                    <div class="mb-3">
                        <label for="definition_name" class="form-label">Ad/Açıklama *</label>
                        <input type="text" class="form-control" id="definition_name" name="name" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Model Add/Edit Modal -->
<div class="modal fade" id="modelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modelModalTitle">Model Ekle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="modelForm">
                <div class="modal-body">
                    <input type="hidden" id="model_id" name="id">
                    <input type="hidden" name="type" value="models">
                    <input type="hidden" id="model_action" name="action">
                    
                    <div class="mb-3">
                        <label for="model_brand" class="form-label">Marka *</label>
                        <select class="form-select" id="model_brand" name="brand_id" required>
                            <option value="">Marka Seçiniz</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="model_name" class="form-label">Model Adı *</label>
                        <input type="text" class="form-control" id="model_name" name="name" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Personnel Add Modal -->
<div class="modal fade" id="personnelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Personel Ekle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="personnelForm">
                <div class="modal-body">
                    <input type="hidden" name="type" value="personnel">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="personnel_name" class="form-label">Ad Soyad *</label>
                            <input type="text" class="form-control" id="personnel_name" name="name" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="personnel_email" class="form-label">E-posta *</label>
                            <input type="email" class="form-control" id="personnel_email" name="email" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="personnel_password" class="form-label">Şifre *</label>
                            <input type="password" class="form-control" id="personnel_password" name="password" required minlength="6">
                            <div class="form-text">En az 6 karakter</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="personnel_phone" class="form-label">Telefon</label>
                            <input type="tel" class="form-control" id="personnel_phone" name="phone" maxlength="11" placeholder="05320528000" pattern="[0-9]{10,11}" title="Sadece rakam giriniz (10-11 hane)">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="personnel_role" class="form-label">Rol *</label>
                            <select class="form-select" id="personnel_role" name="role" required>
                                <option value="technician">Teknisyen</option>
                                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin'): ?>
                                <option value="branch_manager">Şube Müdürü</option>
                                <option value="company_admin">Şirket Admin</option>
                                <option value="super_admin">Süper Admin</option>
                                <?php elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'company_admin'): ?>
                                <option value="branch_manager">Şube Müdürü</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="personnel_branch" class="form-label">Şube *</label>
                            <select class="form-select" id="personnel_branch" name="branch_id" required>
                                <option value="">Şube Seçin</option>
                            </select>
                        </div>
                    </div>
                    
                    <?php
                    // Get company info for current session
                    $companyInfo = null;
                    if (isset($_SESSION['company_id'])) {
                        $companyInfo = fetchOne("SELECT city, district FROM companies WHERE id = ?", [$_SESSION['company_id']]);
                    }
                    ?>
                    <input type="hidden" name="city" value="<?= e($companyInfo['city'] ?? '') ?>">
                    <input type="hidden" name="district" value="<?= e($companyInfo['district'] ?? '') ?>">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Personel konumu: <strong><?= e($companyInfo['city'] ?? 'Belirtilmemiş') ?> / <?= e($companyInfo['district'] ?? 'Belirtilmemiş') ?></strong>
                        <br><small>Personel otomatik olarak şirket konumuna atanır</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>
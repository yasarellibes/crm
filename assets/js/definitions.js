/**
 * Definitions Page JavaScript Functions
 */

// Update operation color
function updateOperationColor(operationId, color) {
    fetch('definitions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=update_operation_color&operation_id=${operationId}&color=${encodeURIComponent(color)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update the color indicator
            const indicator = document.querySelector(`input[onchange*="${operationId}"]`).closest('tr').querySelector('.operation-color-indicator');
            if (indicator) {
                indicator.style.backgroundColor = color;
            }
            showAlert('Renk güncellendi!', 'success');
        } else {
            showAlert(data.message || 'Renk güncellenirken hata oluştu!', 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Bir hata oluştu!', 'danger');
    });
}

// Tab switching with AJAX refresh
function switchTab(tabType) {
    // Update active tab
    document.querySelectorAll('.definition-tabs-compact .nav-link').forEach(link => {
        link.classList.remove('active');
    });
    document.querySelector(`#${tabType}-tab`).classList.add('active');
    
    // Load tab content via AJAX
    fetch(`definitions.php?ajax=refresh&type=${tabType}`)
        .then(response => response.text())
        .then(html => {
            document.querySelector(`#${tabType}`).innerHTML = html;
            document.querySelectorAll('.tab-pane').forEach(pane => {
                pane.classList.remove('show', 'active');
            });
            document.querySelector(`#${tabType}`).classList.add('show', 'active');
        })
        .catch(error => {
            console.error('Error loading tab:', error);
            showAlert('Sekmeler yüklenirken hata oluştu', 'error');
        });
}

// Add new item modal
function openAddModal(type) {
    const modalTitle = {
        'complaints': 'Yeni Şikayet Türü',
        'devices': 'Yeni Cihaz Türü',
        'brands': 'Yeni Marka',
        'operations': 'Yeni Operasyon Türü'
    };
    
    const modal = document.getElementById('addItemModal');
    if (!modal) {
        createAddModal();
    }
    
    document.getElementById('addItemModalLabel').textContent = modalTitle[type] || 'Yeni Öğe';
    document.getElementById('itemType').value = type;
    document.getElementById('itemName').value = '';
    document.getElementById('itemBrandId').style.display = type === 'models' ? 'block' : 'none';
    
    if (type === 'models') {
        loadBrands();
    }
    
    new bootstrap.Modal(modal).show();
}

// Create add modal if it doesn't exist
function createAddModal() {
    const modalHTML = `
    <div class="modal fade" id="addItemModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addItemModalLabel">Yeni Öğe</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form onsubmit="submitAddForm(event)">
                    <div class="modal-body">
                        <input type="hidden" id="itemType" name="type">
                        <input type="hidden" id="itemId" name="id">
                        
                        <div class="mb-3">
                            <label for="itemName" class="form-label">Ad</label>
                            <input type="text" class="form-control" id="itemName" name="name" required>
                        </div>
                        
                        <div class="mb-3" id="itemBrandId" style="display: none;">
                            <label for="brandSelect" class="form-label">Marka</label>
                            <select class="form-select" id="brandSelect" name="brand_id">
                                <option value="">Marka seçin...</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>`;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

// Submit add/edit form
function submitAddForm(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const type = formData.get('type');
    
    fetch('definitions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('addItemModal')).hide();
            showAlert(data.message || 'İşlem başarılı', 'success');
            switchTab(type); // Refresh the current tab
        } else {
            showAlert(data.message || 'İşlem başarısız', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('İşlem sırasında hata oluştu', 'error');
    });
}

// Edit item
function editItem(type, id, name) {
    openAddModal(type);
    document.getElementById('addItemModalLabel').textContent = 'Düzenle';
    document.getElementById('itemId').value = id;
    document.getElementById('itemName').value = name;
}

// Edit model with brand
function editModel(id, name, brandId) {
    openAddModal('models');
    document.getElementById('addItemModalLabel').textContent = 'Model Düzenle';
    document.getElementById('itemId').value = id;
    document.getElementById('itemName').value = name;
    
    loadBrands().then(() => {
        document.getElementById('brandSelect').value = brandId;
    });
}

// Delete item
function deleteItem(type, id, name) {
    if (!confirm(`"${name}" öğesini silmek istediğinizden emin misiniz?`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('type', type);
    formData.append('id', id);
    
    fetch('definitions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message || 'Silme işlemi başarılı', 'success');
            switchTab(type); // Refresh the current tab
        } else {
            showAlert(data.message || 'Silme işlemi başarısız', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Silme işlemi sırasında hata oluştu', 'error');
    });
}

// Load brands for model selection
function loadBrands() {
    return fetch('definitions.php?action=get_brands')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('brandSelect');
                select.innerHTML = '<option value="">Marka seçin...</option>';
                data.brands.forEach(brand => {
                    select.innerHTML += `<option value="${brand.id}">${brand.name}</option>`;
                });
            }
        });
}

// Branch management functions
function showAddBranchModal() {
    const modal = document.getElementById('addBranchModal');
    if (!modal) {
        createBranchModal();
    }
    
    // Wait for modal to be created, then setup form
    setTimeout(() => {
        // Reset form
        document.getElementById('branchForm').reset();
        document.getElementById('branchId').value = '';
        document.getElementById('addBranchModalLabel').textContent = 'Yeni Şube Ekle';
        
        // Set password as required for new branch
        const passwordField = document.getElementById('branchPassword');
        console.log('Password field found:', passwordField);
        if (passwordField) {
            const passwordText = passwordField.nextElementSibling;
            passwordField.setAttribute('required', 'required');
            if (passwordText) passwordText.textContent = 'En az 6 karakter gerekli';
            console.log('Password field configured as required');
        } else {
            console.error('Password field NOT found!');
        }
        
        // Load company city and setup dropdowns
        loadCompanyCityForBranch();
        
        new bootstrap.Modal(modal).show();
    }, 100);
}

function createBranchModal() {
    console.log('Creating branch modal with password field...');
    const modalHTML = `
    <div class="modal fade" id="addBranchModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addBranchModalLabel">Yeni Şube</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="branchForm" onsubmit="submitBranchForm(event)">
                    <div class="modal-body">
                        <input type="hidden" id="branchId" name="id">
                        <input type="hidden" name="type" value="branches">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="branchName" class="form-label">Şube Adı</label>
                                <input type="text" class="form-control" id="branchName" name="name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="branchPhone" class="form-label">Telefon</label>
                                <input type="tel" class="form-control" id="branchPhone" name="phone">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="branchEmail" class="form-label">E-posta</label>
                                <input type="email" class="form-control" id="branchEmail" name="email">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="branchPassword" class="form-label">Şifre</label>
                                <input type="password" class="form-control" id="branchPassword" name="password" minlength="6">
                                <div class="form-text">Boş bırakılırsa mevcut şifre korunur</div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="branchCity" class="form-label">Şehir</label>
                                <select class="form-select" id="branchCity" name="city">
                                    <option value="">Şehir seçin</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="branchDistrict" class="form-label">İlçe</label>
                                <select class="form-select" id="branchDistrict" name="district">
                                    <option value="">İlçe seçin</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="branchAddress" class="form-label">Adres</label>
                            <textarea class="form-control" id="branchAddress" name="address" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>`;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

function editBranch(id, name, phone, email, city, district, address) {
    showAddBranchModal();
    document.getElementById('addBranchModalLabel').textContent = 'Şube Düzenle';
    document.getElementById('branchId').value = id;
    document.getElementById('branchName').value = name;
    document.getElementById('branchPhone').value = phone;
    document.getElementById('branchEmail').value = email;
    document.getElementById('branchAddress').value = address;
    
    // For edit mode, password is optional
    const passwordField = document.getElementById('branchPassword');
    if (passwordField) {
        const passwordText = passwordField.nextElementSibling;
        passwordField.removeAttribute('required');
        passwordField.value = ''; // Clear password field for security
        if (passwordText) passwordText.textContent = 'Boş bırakılırsa mevcut şifre korunur';
    }
    
    // Load company city first, then set specific values
    loadCompanyCityForBranch().then(() => {
        // Set specific values if provided
        if (city) {
            document.getElementById('branchCity').value = city;
            loadDistricts(city);
            
            setTimeout(() => {
                if (district) {
                    document.getElementById('branchDistrict').value = district;
                }
            }, 100);
        }
    });
}

function submitBranchForm(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    
    fetch('definitions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('addBranchModal')).hide();
            showAlert(data.message || 'Şube işlemi başarılı', 'success');
            switchTab('branches');
        } else {
            showAlert(data.message || 'Şube işlemi başarısız', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('İşlem sırasında hata oluştu', 'error');
    });
}

// Personnel management functions
function showAddPersonnelModal() {
    const modal = document.getElementById('addPersonnelModal');
    if (!modal) {
        createPersonnelModal();
    }
    
    // Reset form
    document.getElementById('personnelForm').reset();
    document.getElementById('personnelId').value = '';
    document.getElementById('addPersonnelModalLabel').textContent = 'Yeni Personel Ekle';
    
    // Set password as required for new personnel
    const passwordField = document.getElementById('personnelPassword');
    const passwordText = passwordField.nextElementSibling;
    passwordField.setAttribute('required', 'required');
    passwordText.textContent = 'En az 6 karakter gerekli';
    
    loadBranchesForPersonnel();
    loadCompanyCityForPersonnel();
    new bootstrap.Modal(modal).show();
}

function createPersonnelModal() {
    const modalHTML = `
    <div class="modal fade" id="addPersonnelModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addPersonnelModalLabel">Yeni Personel</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="personnelForm" onsubmit="submitPersonnelForm(event)">
                    <div class="modal-body">
                        <input type="hidden" id="personnelId" name="id">
                        <input type="hidden" name="type" value="personnel">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="personnelName" class="form-label">Ad Soyad</label>
                                <input type="text" class="form-control" id="personnelName" name="name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="personnelPhone" class="form-label">Telefon</label>
                                <input type="tel" class="form-control" id="personnelPhone" name="phone">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="personnelEmail" class="form-label">E-posta</label>
                                <input type="email" class="form-control" id="personnelEmail" name="email">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="personnelPassword" class="form-label">Şifre</label>
                                <input type="password" class="form-control" id="personnelPassword" name="password" minlength="6">
                                <div class="form-text">Boş bırakılırsa mevcut şifre korunur</div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="personnelBranch" class="form-label">Şube</label>
                                <select class="form-select" id="personnelBranch" name="branch_id">
                                    <option value="">Şube seçin</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <!-- Spacer for alignment -->
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="personnelCity" class="form-label">Şehir</label>
                                <select class="form-select" id="personnelCity" name="city">
                                    <option value="">Şehir seçin</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="personnelDistrict" class="form-label">İlçe</label>
                                <select class="form-select" id="personnelDistrict" name="district">
                                    <option value="">İlçe seçin</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>`;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

function editPersonnel(id, name, phone, email, city, district, branchName, branchId) {
    showAddPersonnelModal();
    document.getElementById('addPersonnelModalLabel').textContent = 'Personel Düzenle';
    document.getElementById('personnelId').value = id;
    document.getElementById('personnelName').value = name || '';
    document.getElementById('personnelPhone').value = phone || '';
    document.getElementById('personnelEmail').value = email || '';
    
    // For edit mode, password is optional
    const passwordField = document.getElementById('personnelPassword');
    const passwordText = passwordField.nextElementSibling;
    passwordField.removeAttribute('required');
    passwordField.value = ''; // Clear password field for security
    passwordText.textContent = 'Boş bırakılırsa mevcut şifre korunur';
    
    // Load branches and company city, then set values
    Promise.all([
        loadBranchesForPersonnel(),
        loadCompanyCityForPersonnel()
    ]).then(() => {
        // Set branch selection if branchId is available
        if (branchId) {
            const branchSelect = document.getElementById('personnelBranch');
            branchSelect.value = branchId;
        }
        
        // Set city and district values
        const citySelect = document.getElementById('personnelCity');
        const districtSelect = document.getElementById('personnelDistrict');
        
        if (city && citySelect) {
            citySelect.value = city;
            // Load districts for this city and set district
            loadDistrictsPersonnel(city);
            setTimeout(() => {
                if (district && districtSelect) {
                    districtSelect.value = district;
                }
            }, 200);
        }
    }).catch(error => {
        console.error('Error loading personnel edit data:', error);
    });
}

function submitPersonnelForm(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    
    fetch('definitions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('addPersonnelModal')).hide();
            showAlert(data.message || 'Personel işlemi başarılı', 'success');
            switchTab('personnel');
        } else {
            showAlert(data.message || 'Personel işlemi başarısız', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('İşlem sırasında hata oluştu', 'error');
    });
}

function loadBranchesForPersonnel() {
    return fetch('definitions.php?action=get_branches')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('personnelBranch');
                if (select) {
                    select.innerHTML = '<option value="">Şube seçin</option>';
                    data.branches.forEach(branch => {
                        select.innerHTML += `<option value="${branch.id}">${branch.name}</option>`;
                    });
                }
            }
            return data;
        });
}

function loadCompanyCityForPersonnel() {
    return fetch('definitions.php?action=get_company_info')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.company) {
                const citySelect = document.getElementById('personnelCity');
                const districtSelect = document.getElementById('personnelDistrict');
                
                if (citySelect && districtSelect) {
                    const companyCity = data.company.city || '';
                    
                    // Load Turkish cities
                    const cities = [
                        'Adana', 'Adıyaman', 'Afyonkarahisar', 'Ağrı', 'Amasya', 'Ankara', 'Antalya', 'Artvin',
                        'Aydın', 'Balıkesir', 'Bilecik', 'Bingöl', 'Bitlis', 'Bolu', 'Burdur', 'Bursa',
                        'Çanakkale', 'Çankırı', 'Çorum', 'Denizli', 'Diyarbakır', 'Edirne', 'Elazığ', 'Erzincan',
                        'Erzurum', 'Eskişehir', 'Gaziantep', 'Giresun', 'Gümüşhane', 'Hakkari', 'Hatay', 'Isparta',
                        'Mersin', 'İstanbul', 'İzmir', 'Kars', 'Kastamonu', 'Kayseri', 'Kırklareli', 'Kırşehir',
                        'Kocaeli', 'Konya', 'Kütahya', 'Malatya', 'Manisa', 'Kahramanmaraş', 'Mardin', 'Muğla',
                        'Muş', 'Nevşehir', 'Niğde', 'Ordu', 'Rize', 'Sakarya', 'Samsun', 'Siirt', 'Sinop',
                        'Sivas', 'Tekirdağ', 'Tokat', 'Trabzon', 'Tunceli', 'Şanlıurfa', 'Uşak', 'Van',
                        'Yozgat', 'Zonguldak', 'Aksaray', 'Bayburt', 'Karaman', 'Kırıkkale', 'Batman', 'Şırnak',
                        'Bartın', 'Ardahan', 'Iğdır', 'Yalova', 'Karabük', 'Kilis', 'Osmaniye', 'Düzce'
                    ].sort();
                    
                    citySelect.innerHTML = '<option value="">Şehir seçin</option>';
                    cities.forEach(city => {
                        const selected = city === companyCity ? 'selected' : '';
                        citySelect.innerHTML += `<option value="${city}" ${selected}>${city}</option>`;
                    });
                    
                    // Load districts for company city
                    if (companyCity) {
                        loadDistrictsPersonnel(companyCity);
                    }
                    
                    // Add event listener for city change - use specific handler for personnel
                    citySelect.removeEventListener('change', handlePersonnelCityChangeNew);
                    citySelect.addEventListener('change', handlePersonnelCityChangeNew);
                }
            }
            return data;
        });
}

// Dedicated handler for personnel city change
function handlePersonnelCityChangeNew(event) {
    const selectedCity = event.target.value;
    console.log('PERSONNEL CITY CHANGED TO:', selectedCity);
    alert('Personnel city changed: ' + selectedCity); // Debug alert
    loadDistrictsPersonnel(selectedCity);
}

// Personnel-specific district loading function
function loadDistrictsPersonnel(city) {
    const districts = {
        'İstanbul': ['Adalar', 'Arnavutköy', 'Ataşehir', 'Avcılar', 'Bağcılar', 'Bahçelievler', 'Bakırköy', 'Başakşehir', 'Bayrampaşa', 'Beşiktaş', 'Beykoz', 'Beylikdüzü', 'Beyoğlu', 'Büyükçekmece', 'Çatalca', 'Çekmeköy', 'Esenler', 'Esenyurt', 'Eyüpsultan', 'Fatih', 'Gaziosmanpaşa', 'Güngören', 'Kadıköy', 'Kağıthane', 'Kartal', 'Küçükçekmece', 'Maltepe', 'Pendik', 'Sancaktepe', 'Sarıyer', 'Silivri', 'Sultangazi', 'Sultanbeyli', 'Şile', 'Şişli', 'Tuzla', 'Ümraniye', 'Üsküdar', 'Zeytinburnu'],
        'Ankara': ['Akyurt', 'Altındağ', 'Ayaş', 'Bala', 'Beypazarı', 'Çamlıdere', 'Çankaya', 'Çubuk', 'Elmadağ', 'Etimesgut', 'Evren', 'Gölbaşı', 'Güdül', 'Haymana', 'Kahramankazan', 'Kalecik', 'Keçiören', 'Kızılcahamam', 'Mamak', 'Nallıhan', 'Polatlı', 'Pursaklar', 'Sincan', 'Şereflikoçhisar', 'Yenimahalle'],
        'İzmir': ['Aliağa', 'Balçova', 'Bayındır', 'Bayraklı', 'Bergama', 'Beydağ', 'Bornova', 'Buca', 'Çeşme', 'Çiğli', 'Dikili', 'Foça', 'Gaziemir', 'Güzelbahçe', 'Karabağlar', 'Karaburun', 'Karşıyaka', 'Kemalpaşa', 'Kınık', 'Kiraz', 'Konak', 'Menderes', 'Menemen', 'Narlıdere', 'Ödemiş', 'Seferihisar', 'Selçuk', 'Tire', 'Torbalı', 'Urla'],
        'Bursa': ['Büyükorhan', 'Gemlik', 'Gürsu', 'Harmancık', 'İnegöl', 'İznik', 'Karacabey', 'Keles', 'Kestel', 'Mudanya', 'Mustafakemalpaşa', 'Nilüfer', 'Orhaneli', 'Orhangazi', 'Osmangazi', 'Yenişehir', 'Yıldırım'],
        'Antalya': ['Akseki', 'Aksu', 'Alanya', 'Demre', 'Döşemealtı', 'Elmalı', 'Finike', 'Gazipaşa', 'Gündoğmuş', 'İbradı', 'Kas', 'Kemer', 'Kepez', 'Konyaaltı', 'Korkuteli', 'Kumluca', 'Manavgat', 'Muratpaşa', 'Serik'],
        'Adana': ['Aladağ', 'Ceyhan', 'Çukurova', 'Feke', 'İmamoğlu', 'Karaisalı', 'Karataş', 'Kozan', 'Pozantı', 'Saimbeyli', 'Sarıçam', 'Seyhan', 'Tufanbeyli', 'Yumurtalık', 'Yüreğir'],
        'Konya': ['Ahırlı', 'Akören', 'Akşehir', 'Altınekin', 'Beyşehir', 'Bozkır', 'Cihanbeyli', 'Çeltik', 'Çumra', 'Derbent', 'Derebucak', 'Doğanhisar', 'Emirgazi', 'Ereğli', 'Güneysinir', 'Hadim', 'Halkapınar', 'Hüyük', 'Ilgın', 'Kadınhanı', 'Karapınar', 'Karatay', 'Kulu', 'Meram', 'Sarayönü', 'Selçuklu', 'Seydişehir', 'Taşkent', 'Tuzlukçu', 'Yalıhüyük', 'Yunak'],
        'Gaziantep': ['Araban', 'İslahiye', 'Karkamış', 'Nizip', 'Nurdağı', 'Oğuzeli', 'Sahinbey', 'Şaruhanbeyli', 'Yavuzeli'],
        'Mersin': ['Akdeniz', 'Anamur', 'Aydıncık', 'Bozyazı', 'Çamlıyayla', 'Erdemli', 'Gülnar', 'Mezitli', 'Mut', 'Silifke', 'Tarsus', 'Toroslar', 'Yenişehir'],
        'Kayseri': ['Akkışla', 'Bünyan', 'Develi', 'Felahiye', 'Hacılar', 'İncesu', 'Kocasinan', 'Melikgazi', 'Özvatan', 'Pınarbaşı', 'Sarıoğlan', 'Sarız', 'Talas', 'Tomarza', 'Yahyalı', 'Yeşilhisar'],
        'Samsun': ['Alaçam', 'Asarcık', 'Atakum', 'Ayvacık', 'Bafra', 'Canik', 'Çarşamba', 'Havza', 'İlkadım', 'Kavak', 'Ladik', 'Ondokuzmayıs', 'Salıpazarı', 'Tekkeköy', 'Terme', 'Vezirköprü', 'Yakakent']
    };
    
    const districtSelect = document.getElementById('personnelDistrict');
    console.log('PERSONNEL DISTRICT SELECT ELEMENT:', districtSelect);
    if (districtSelect) {
        districtSelect.innerHTML = '<option value="">İlçe seçin</option>';
        
        if (districts[city]) {
            console.log('LOADING', districts[city].length, 'DISTRICTS FOR PERSONNEL IN', city);
            alert('Loading ' + districts[city].length + ' districts for ' + city); // Debug alert
            districts[city].sort().forEach(district => {
                districtSelect.innerHTML += `<option value="${district}">${district}</option>`;
            });
        } else {
            console.log('NO DISTRICTS FOUND FOR PERSONNEL CITY:', city);
            alert('No districts found for: ' + city); // Debug alert
        }
    } else {
        console.error('PERSONNEL DISTRICT SELECT NOT FOUND!');
        alert('Personnel district select element not found!'); // Debug alert
    }
}

function handleCityChange(event) {
    const selectedCity = event.target.value;
    console.log('City changed to:', selectedCity);
    loadDistricts(selectedCity);
}





// Load districts based on city
function loadDistricts(city) {
    const districts = {
        'İstanbul': ['Adalar', 'Arnavutköy', 'Ataşehir', 'Avcılar', 'Bağcılar', 'Bahçelievler', 'Bakırköy', 'Başakşehir', 'Bayrampaşa', 'Beşiktaş', 'Beykoz', 'Beylikdüzü', 'Beyoğlu', 'Büyükçekmece', 'Çatalca', 'Çekmeköy', 'Esenler', 'Esenyurt', 'Eyüpsultan', 'Fatih', 'Gaziosmanpaşa', 'Güngören', 'Kadıköy', 'Kağıthane', 'Kartal', 'Küçükçekmece', 'Maltepe', 'Pendik', 'Sancaktepe', 'Sarıyer', 'Silivri', 'Sultangazi', 'Sultanbeyli', 'Şile', 'Şişli', 'Tuzla', 'Ümraniye', 'Üsküdar', 'Zeytinburnu'],
        'Ankara': ['Akyurt', 'Altındağ', 'Ayaş', 'Bala', 'Beypazarı', 'Çamlıdere', 'Çankaya', 'Çubuk', 'Elmadağ', 'Etimesgut', 'Evren', 'Gölbaşı', 'Güdül', 'Haymana', 'Kahramankazan', 'Kalecik', 'Keçiören', 'Kızılcahamam', 'Mamak', 'Nallıhan', 'Polatlı', 'Pursaklar', 'Sincan', 'Şereflikoçhisar', 'Yenimahalle'],
        'İzmir': ['Aliağa', 'Balçova', 'Bayındır', 'Bayraklı', 'Bergama', 'Beydağ', 'Bornova', 'Buca', 'Çeşme', 'Çiğli', 'Dikili', 'Foça', 'Gaziemir', 'Güzelbahçe', 'Karabağlar', 'Karaburun', 'Karşıyaka', 'Kemalpaşa', 'Kınık', 'Kiraz', 'Konak', 'Menderes', 'Menemen', 'Narlıdere', 'Ödemiş', 'Seferihisar', 'Selçuk', 'Tire', 'Torbalı', 'Urla'],
        'Bursa': ['Büyükorhan', 'Gemlik', 'Gürsu', 'Harmancık', 'İnegöl', 'İznik', 'Karacabey', 'Keles', 'Kestel', 'Mudanya', 'Mustafakemalpaşa', 'Nilüfer', 'Orhaneli', 'Orhangazi', 'Osmangazi', 'Yenişehir', 'Yıldırım'],
        'Antalya': ['Akseki', 'Aksu', 'Alanya', 'Demre', 'Döşemealtı', 'Elmalı', 'Finike', 'Gazipaşa', 'Gündoğmuş', 'İbradı', 'Kas', 'Kemer', 'Kepez', 'Konyaaltı', 'Korkuteli', 'Kumluca', 'Manavgat', 'Muratpaşa', 'Serik'],
        'Adana': ['Aladağ', 'Ceyhan', 'Çukurova', 'Feke', 'İmamoğlu', 'Karaisalı', 'Karataş', 'Kozan', 'Pozantı', 'Saimbeyli', 'Sarıçam', 'Seyhan', 'Tufanbeyli', 'Yumurtalık', 'Yüreğir'],
        'Konya': ['Ahırlı', 'Akören', 'Akşehir', 'Altınekin', 'Beyşehir', 'Bozkır', 'Cihanbeyli', 'Çeltik', 'Çumra', 'Derbent', 'Derebucak', 'Doğanhisar', 'Emirgazi', 'Ereğli', 'Güneysinir', 'Hadim', 'Halkapınar', 'Hüyük', 'Ilgın', 'Kadınhanı', 'Karapınar', 'Karatay', 'Kulu', 'Meram', 'Sarayönü', 'Selçuklu', 'Seydişehir', 'Taşkent', 'Tuzlukçu', 'Yalıhüyük', 'Yunak'],
        'Gaziantep': ['Araban', 'İslahiye', 'Karkamış', 'Nizip', 'Nurdağı', 'Oğuzeli', 'Sahinbey', 'Şaruhanbeyli', 'Yavuzeli'],
        'Mersin': ['Akdeniz', 'Anamur', 'Aydıncık', 'Bozyazı', 'Çamlıyayla', 'Erdemli', 'Gülnar', 'Mezitli', 'Mut', 'Silifke', 'Tarsus', 'Toroslar', 'Yenişehir'],
        'Kayseri': ['Akkışla', 'Bünyan', 'Develi', 'Felahiye', 'Hacılar', 'İncesu', 'Kocasinan', 'Melikgazi', 'Özvatan', 'Pınarbaşı', 'Sarıoğlan', 'Sarız', 'Talas', 'Tomarza', 'Yahyalı', 'Yeşilhisar'],
        'Samsun': ['Alaçam', 'Asarcık', 'Atakum', 'Ayvacık', 'Bafra', 'Canik', 'Çarşamba', 'Havza', 'İlkadım', 'Kavak', 'Ladik', 'Ondokuzmayıs', 'Salıpazarı', 'Tekkeköy', 'Terme', 'Vezirköprü', 'Yakakent']
    };
    
    // Update both personnel and branch district selects if they exist
    const districtSelects = [
        document.getElementById('personnelDistrict'),
        document.getElementById('branchDistrict')
    ].filter(select => select !== null);
    
    console.log('Found district selects:', districtSelects.length, 'for city:', city);
    
    districtSelects.forEach(districtSelect => {
        districtSelect.innerHTML = '<option value="">İlçe seçin</option>';
        
        if (districts[city]) {
            console.log('Loading', districts[city].length, 'districts for', city);
            districts[city].sort().forEach(district => {
                districtSelect.innerHTML += `<option value="${district}">${district}</option>`;
            });
        } else {
            console.log('No districts found for city:', city);
        }
    });
}

// Load company city for branch modal
function loadCompanyCityForBranch() {
    return fetch('definitions.php?action=get_company_info')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.company) {
                const citySelect = document.getElementById('branchCity');
                const districtSelect = document.getElementById('branchDistrict');
                
                if (citySelect && districtSelect) {
                    const companyCity = data.company.city || '';
                    
                    // Load Turkish cities
                    const cities = [
                        'Adana', 'Adıyaman', 'Afyonkarahisar', 'Ağrı', 'Amasya', 'Ankara', 'Antalya', 'Artvin',
                        'Aydın', 'Balıkesir', 'Bilecik', 'Bingöl', 'Bitlis', 'Bolu', 'Burdur', 'Bursa',
                        'Çanakkale', 'Çankırı', 'Çorum', 'Denizli', 'Diyarbakır', 'Edirne', 'Elazığ', 'Erzincan',
                        'Erzurum', 'Eskişehir', 'Gaziantep', 'Giresun', 'Gümüşhane', 'Hakkari', 'Hatay', 'Isparta',
                        'Mersin', 'İstanbul', 'İzmir', 'Kars', 'Kastamonu', 'Kayseri', 'Kırklareli', 'Kırşehir',
                        'Kocaeli', 'Konya', 'Kütahya', 'Malatya', 'Manisa', 'Kahramanmaraş', 'Mardin', 'Muğla',
                        'Muş', 'Nevşehir', 'Niğde', 'Ordu', 'Rize', 'Sakarya', 'Samsun', 'Siirt', 'Sinop',
                        'Sivas', 'Tekirdağ', 'Tokat', 'Trabzon', 'Tunceli', 'Şanlıurfa', 'Uşak', 'Van',
                        'Yozgat', 'Zonguldak', 'Aksaray', 'Bayburt', 'Karaman', 'Kırıkkale', 'Batman', 'Şırnak',
                        'Bartın', 'Ardahan', 'Iğdır', 'Yalova', 'Karabük', 'Kilis', 'Osmaniye', 'Düzce'
                    ].sort();
                    
                    citySelect.innerHTML = '<option value="">Şehir seçin</option>';
                    cities.forEach(city => {
                        const selected = city === companyCity ? 'selected' : '';
                        citySelect.innerHTML += `<option value="${city}" ${selected}>${city}</option>`;
                    });
                    
                    // Load districts for company city
                    if (companyCity) {
                        loadDistricts(companyCity);
                    }
                    
                    // Add event listener for city change
                    citySelect.removeEventListener('change', handleCityChange);
                    citySelect.addEventListener('change', handleCityChange);
                }
            }
            return data;
        });
}

// Table filtering
function filterTable(tableId, searchTerm) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const rows = table.querySelectorAll('tbody tr');
    const term = searchTerm.toLowerCase();
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(term) ? '' : 'none';
    });
}

// Alert function
function showAlert(message, type = 'info') {
    const alertClass = type === 'error' ? 'alert-danger' : (type === 'success' ? 'alert-success' : 'alert-info');
    const alertHTML = `
        <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>`;
    
    // Find or create alert container
    let container = document.querySelector('.alert-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'alert-container position-fixed top-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }
    
    container.insertAdjacentHTML('afterbegin', alertHTML);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        const alert = container.querySelector('.alert');
        if (alert) {
            alert.remove();
        }
    }, 5000);
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Setup tab click handlers
    document.querySelectorAll('.definition-tabs-compact .nav-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const tabType = this.id.replace('-tab', '');
            switchTab(tabType);
        });
    });
});

// Use the HTML modal system instead of creating dynamic modals
function showAddItem(type) {
    const modal = document.getElementById('addItemModal');
    const form = document.getElementById('itemForm');
    
    // Reset form
    form.reset();
    
    // Set type
    document.getElementById('itemType').value = type;
    document.getElementById('itemId').value = '';
    
    // Hide all specific fields first
    document.querySelectorAll('#branchFields, #personnelFields, #modelFields').forEach(el => {
        el.style.display = 'none';
    });
    
    // Configure modal based on type
    if (type === 'branches') {
        document.getElementById('addItemModalLabel').textContent = 'Yeni Şube Ekle';
        document.getElementById('branchFields').style.display = 'block';
        
        // Set password as required for new branch
        const passwordField = document.getElementById('itemPassword');
        if (passwordField) {
            passwordField.setAttribute('required', 'required');
            const helpText = passwordField.nextElementSibling;
            if (helpText) helpText.textContent = 'En az 6 karakter gerekli';
        }
        
        // Load cities and set company default
        loadCompanyCityForBranches();
    } else if (type === 'personnel') {
        document.getElementById('addItemModalLabel').textContent = 'Yeni Personel Ekle';
        document.getElementById('personnelFields').style.display = 'block';
        
        // Set password as required for new personnel
        const passwordField = document.getElementById('personnelPassword');
        if (passwordField) {
            passwordField.setAttribute('required', 'required');
            const helpText = document.getElementById('passwordHelp');
            if (helpText) helpText.textContent = 'En az 6 karakter gerekli';
        }
        
        loadBranchesForPersonnel();
        loadCompanyCityForPersonnel();
    } else if (type === 'models') {
        document.getElementById('addItemModalLabel').textContent = 'Yeni Model Ekle';
        document.getElementById('modelFields').style.display = 'block';
        loadBrands();
    } else {
        document.getElementById('addItemModalLabel').textContent = 'Yeni ' + getTypeDisplayName(type) + ' Ekle';
    }
    
    // Show modal
    new bootstrap.Modal(modal).show();
}

function getTypeDisplayName(type) {
    const names = {
        'complaints': 'Şikayet',
        'devices': 'Cihaz',
        'branches': 'Şube',
        'personnel': 'Personel',
        'models': 'Model',
        'operations': 'Operasyon'
    };
    return names[type] || type;
}

// Alias for backward compatibility
function openAddModal(type) {
    showAddItem(type);
}

// Edit item function for universal modal
function editItem(type, id, name, data = {}) {
    const modal = document.getElementById('addItemModal');
    const form = document.getElementById('itemForm');
    
    // Reset form
    form.reset();
    
    // Set type and ID
    document.getElementById('itemType').value = type;
    document.getElementById('itemId').value = id;
    document.getElementById('itemName').value = name;
    
    // Hide all specific fields first
    document.querySelectorAll('#branchFields, #personnelFields, #modelFields').forEach(el => {
        el.style.display = 'none';
    });
    
    // Configure modal based on type
    if (type === 'branches') {
        document.getElementById('addItemModalLabel').textContent = 'Şube Düzenle';
        document.getElementById('branchFields').style.display = 'block';
        
        // Fill branch data
        if (data.phone) document.getElementById('itemPhone').value = data.phone;
        if (data.email) document.getElementById('itemEmail').value = data.email;
        if (data.address) document.getElementById('itemAddress').value = data.address;
        
        // Password is optional for edit
        const passwordField = document.getElementById('itemPassword');
        if (passwordField) {
            passwordField.removeAttribute('required');
            passwordField.value = '';
            const helpText = passwordField.nextElementSibling;
            if (helpText) helpText.textContent = 'Boş bırakılırsa mevcut şifre korunur';
        }
        
        // Load cities and set values
        loadCompanyCityForBranches().then(() => {
            if (data.city) {
                document.getElementById('itemCity').value = data.city;
                loadDistrictsUniversal(data.city);
                setTimeout(() => {
                    if (data.district) {
                        document.getElementById('itemDistrict').value = data.district;
                    }
                }, 100);
            }
        });
    }
    
    // Show modal
    new bootstrap.Modal(modal).show();
}

// Load company city for branches (universal modal)
function loadCompanyCityForBranches() {
    return fetch('definitions.php?action=get_company_info')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.company) {
                const citySelect = document.getElementById('itemCity');
                const districtSelect = document.getElementById('itemDistrict');
                
                if (citySelect && districtSelect) {
                    const companyCity = data.company.city || '';
                    
                    // Load Turkish cities
                    const cities = [
                        'Adana', 'Adıyaman', 'Afyonkarahisar', 'Ağrı', 'Amasya', 'Ankara', 'Antalya', 'Artvin',
                        'Aydın', 'Balıkesir', 'Bilecik', 'Bingöl', 'Bitlis', 'Bolu', 'Burdur', 'Bursa',
                        'Çanakkale', 'Çankırı', 'Çorum', 'Denizli', 'Diyarbakır', 'Edirne', 'Elazığ', 'Erzincan',
                        'Erzurum', 'Eskişehir', 'Gaziantep', 'Giresun', 'Gümüşhane', 'Hakkari', 'Hatay', 'Isparta',
                        'Mersin', 'İstanbul', 'İzmir', 'Kars', 'Kastamonu', 'Kayseri', 'Kırklareli', 'Kırşehir',
                        'Kocaeli', 'Konya', 'Kütahya', 'Malatya', 'Manisa', 'Kahramanmaraş', 'Mardin', 'Muğla',
                        'Muş', 'Nevşehir', 'Niğde', 'Ordu', 'Rize', 'Sakarya', 'Samsun', 'Siirt', 'Sinop',
                        'Sivas', 'Tekirdağ', 'Tokat', 'Trabzon', 'Tunceli', 'Şanlıurfa', 'Uşak', 'Van',
                        'Yozgat', 'Zonguldak', 'Aksaray', 'Bayburt', 'Karaman', 'Kırıkkale', 'Batman', 'Şırnak',
                        'Bartın', 'Ardahan', 'Iğdır', 'Yalova', 'Karabük', 'Kilis', 'Osmaniye', 'Düzce'
                    ].sort();
                    
                    citySelect.innerHTML = '<option value="">Şehir seçin</option>';
                    cities.forEach(city => {
                        const selected = city === companyCity ? 'selected' : '';
                        citySelect.innerHTML += `<option value="${city}" ${selected}>${city}</option>`;
                    });
                    
                    // Load districts for company city
                    if (companyCity) {
                        loadDistrictsUniversal(companyCity);
                    }
                    
                    // Add event listener for city change
                    citySelect.removeEventListener('change', handleUniversalCityChange);
                    citySelect.addEventListener('change', handleUniversalCityChange);
                }
            }
            return data;
        });
}

// Handle city change for universal modal
function handleUniversalCityChange(event) {
    const selectedCity = event.target.value;
    loadDistrictsUniversal(selectedCity);
}

// Load districts for universal modal
function loadDistrictsUniversal(city) {
    const districts = {
        'İstanbul': ['Adalar', 'Arnavutköy', 'Ataşehir', 'Avcılar', 'Bağcılar', 'Bahçelievler', 'Bakırköy', 'Başakşehir', 'Bayrampaşa', 'Beşiktaş', 'Beykoz', 'Beylikdüzü', 'Beyoğlu', 'Büyükçekmece', 'Çatalca', 'Çekmeköy', 'Esenler', 'Esenyurt', 'Eyüpsultan', 'Fatih', 'Gaziosmanpaşa', 'Güngören', 'Kadıköy', 'Kağıthane', 'Kartal', 'Küçükçekmece', 'Maltepe', 'Pendik', 'Sancaktepe', 'Sarıyer', 'Silivri', 'Sultangazi', 'Sultanbeyli', 'Şile', 'Şişli', 'Tuzla', 'Ümraniye', 'Üsküdar', 'Zeytinburnu'],
        'Ankara': ['Akyurt', 'Altındağ', 'Ayaş', 'Bala', 'Beypazarı', 'Çamlıdere', 'Çankaya', 'Çubuk', 'Elmadağ', 'Etimesgut', 'Evren', 'Gölbaşı', 'Güdül', 'Haymana', 'Kahramankazan', 'Kalecik', 'Keçiören', 'Kızılcahamam', 'Mamak', 'Nallıhan', 'Polatlı', 'Pursaklar', 'Sincan', 'Şereflikoçhisar', 'Yenimahalle'],
        'İzmir': ['Aliağa', 'Balçova', 'Bayındır', 'Bayraklı', 'Bergama', 'Beydağ', 'Bornova', 'Buca', 'Çeşme', 'Çiğli', 'Dikili', 'Foça', 'Gaziemir', 'Güzelbahçe', 'Karabağlar', 'Karaburun', 'Karşıyaka', 'Kemalpaşa', 'Kınık', 'Kiraz', 'Konak', 'Menderes', 'Menemen', 'Narlıdere', 'Ödemiş', 'Seferihisar', 'Selçuk', 'Tire', 'Torbalı', 'Urla'],
        'Samsun': ['Alaçam', 'Asarcık', 'Atakum', 'Ayvacık', 'Bafra', 'Canik', 'Çarşamba', 'Havza', 'İlkadım', 'Kavak', 'Ladik', 'Ondokuzmayıs', 'Salıpazarı', 'Tekkeköy', 'Terme', 'Vezirköprü', 'Yakakent'],
        'Ağrı': ['Diyadin', 'Doğubayazıt', 'Eleşkirt', 'Hamur', 'Merkez', 'Patnos', 'Taşlıçay', 'Tutak']
    };
    
    const districtSelect = document.getElementById('itemDistrict');
    if (districtSelect) {
        districtSelect.innerHTML = '<option value="">İlçe seçin</option>';
        
        if (districts[city]) {
            districts[city].sort().forEach(district => {
                districtSelect.innerHTML += `<option value="${district}">${district}</option>`;
            });
        }
    }
    
    // Also update personnel districts if visible
    const personnelDistrictSelect = document.getElementById('personnelDistrict');
    if (personnelDistrictSelect) {
        personnelDistrictSelect.innerHTML = '<option value="">İlçe seçin</option>';
        
        if (districts[city]) {
            districts[city].sort().forEach(district => {
                personnelDistrictSelect.innerHTML += `<option value="${district}">${district}</option>`;
            });
        }
    }
}
/* Cache buster: Mon Sep  1 04:15:57 PM UTC 2025 */

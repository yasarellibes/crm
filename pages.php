<?php
/**
 * Pages Management System - Super Admin Only
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'includes/functions.php';

// Check if user is logged in and is super admin
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get current user and check role
$currentUser = getCurrentUser();
if (!$currentUser || $currentUser['role'] !== 'super_admin') {
    header('Location: login.php');
    exit;
}



$systemSettings = getSystemSettings();

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_page') {
        $title = trim($_POST['title'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $content = $_POST['content'] ?? '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $errors = [];
        
        if (empty($title)) $errors[] = 'Sayfa başlığı gereklidir';
        if (empty($slug)) $errors[] = 'Sayfa URL\'i gereklidir';
        if (empty($content)) $errors[] = 'Sayfa içeriği gereklidir';
        
        // Check if slug already exists
        if (empty($errors)) {
            $existing = fetchOne("SELECT id FROM pages WHERE slug = ?", [$slug]);
            if ($existing) {
                $errors[] = 'Bu URL zaten kullanılıyor';
            }
        }
        
        if (empty($errors)) {
            try {
                $sql = "INSERT INTO pages (title, slug, content, is_active, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
                executeQuery($sql, [$title, $slug, $content, $is_active, $_SESSION['user_id']]);
                
                $message = 'Sayfa başarıyla oluşturuldu';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Sayfa oluşturulurken hata oluştu: ' . $e->getMessage();
                $messageType = 'danger';
            }
        } else {
            $message = implode('<br>', $errors);
            $messageType = 'danger';
        }
    }
    
    elseif ($action === 'update_page') {
        $id = intval($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $content = $_POST['content'] ?? '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $errors = [];
        
        if (empty($title)) $errors[] = 'Sayfa başlığı gereklidir';
        if (empty($slug)) $errors[] = 'Sayfa URL\'i gereklidir';
        if (empty($content)) $errors[] = 'Sayfa içeriği gereklidir';
        
        // Check if slug already exists (excluding current page)
        if (empty($errors)) {
            $existing = fetchOne("SELECT id FROM pages WHERE slug = ? AND id != ?", [$slug, $id]);
            if ($existing) {
                $errors[] = 'Bu URL zaten kullanılıyor';
            }
        }
        
        if (empty($errors)) {
            try {
                $sql = "UPDATE pages SET title = ?, slug = ?, content = ?, is_active = ?, updated_by = ?, updated_at = NOW() WHERE id = ?";
                executeQuery($sql, [$title, $slug, $content, $is_active, $_SESSION['user_id'], $id]);
                
                $message = 'Sayfa başarıyla güncellendi';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Sayfa güncellenirken hata oluştu: ' . $e->getMessage();
                $messageType = 'danger';
            }
        } else {
            $message = implode('<br>', $errors);
            $messageType = 'danger';
        }
    }
    
    elseif ($action === 'delete_page') {
        $id = intval($_POST['id'] ?? 0);
        
        try {
            executeQuery("DELETE FROM pages WHERE id = ?", [$id]);
            $message = 'Sayfa başarıyla silindi';
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Sayfa silinirken hata oluştu: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Get all pages
$pages = fetchAll("SELECT p.*, 'Admin' as creator_name FROM pages p ORDER BY p.created_at DESC");

// Get page for editing if requested
$editPage = null;
if (isset($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $editPage = fetchOne("SELECT * FROM pages WHERE id = ?", [$editId]);
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sayfa Yönetimi - <?= e($systemSettings['system_name'] ?? 'Serviso') ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Quill.js Editor -->
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content">
        <div class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-header">
                            <h1><i class="fas fa-file-alt"></i> Sayfa Yönetimi</h1>
                            <p class="text-muted">Web sitesi sayfalarını yönetin (Kullanıcı Sözleşmesi, Gizlilik Politikası vb.)</p>
                        </div>
                        
                        <!-- Success/Error Messages -->
                        <?php if ($message): ?>
                            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                                <?= $message ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Add New Page Button -->
                        <?php if (!$editPage): ?>
                        <div class="mb-4">
                            <button type="button" class="btn btn-primary" onclick="showAddForm()">
                                <i class="fas fa-plus"></i> Yeni Sayfa Ekle
                            </button>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Add/Edit Page Form -->
                        <div class="card mb-4" id="pageForm" style="<?= $editPage ? 'display: block;' : 'display: none;' ?>">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-<?= $editPage ? 'edit' : 'plus' ?>"></i>
                                    <?= $editPage ? 'Sayfa Düzenle' : 'Yeni Sayfa Ekle' ?>
                                </h5>
                                <?php if (!$editPage): ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="hideAddForm()">
                                    <i class="fas fa-times"></i> İptal
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="<?= $editPage ? 'update_page' : 'create_page' ?>">
                                    <?php if ($editPage): ?>
                                        <input type="hidden" name="id" value="<?= $editPage['id'] ?>">
                                    <?php endif; ?>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="title" class="form-label">Sayfa Başlığı *</label>
                                                <input type="text" class="form-control" id="title" name="title" 
                                                       value="<?= $editPage ? e($editPage['title']) : '' ?>" 
                                                       placeholder="Kullanıcı Sözleşmesi" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="slug" class="form-label">Sayfa URL *</label>
                                                <input type="text" class="form-control" id="slug" name="slug" 
                                                       value="<?= $editPage ? e($editPage['slug']) : '' ?>" 
                                                       placeholder="kullanici-sozlesmesi" required>
                                                <div class="form-text">Sadece harf, rakam ve tire (-) kullanın</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="content" class="form-label">Sayfa İçeriği *</label>
                                        <div id="editor" style="height: 300px; border: 1px solid #ddd; border-radius: 8px;"><?= $editPage ? $editPage['content'] : '' ?></div>
                                        <textarea id="content" name="content" style="display: none;"><?= $editPage ? e($editPage['content']) : '' ?></textarea>
                                        <div id="content-error" style="color: #dc3545; font-size: 0.875rem; margin-top: 0.25rem; display: none;">
                                            Sayfa içeriği gereklidir
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                                   <?= ($editPage && $editPage['is_active']) || !$editPage ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="is_active">
                                                Aktif (Sayfa yayında olsun)
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> 
                                            <?= $editPage ? 'Güncelle' : 'Oluştur' ?>
                                        </button>
                                        <?php if ($editPage): ?>
                                            <a href="pages.php" class="btn btn-secondary">
                                                <i class="fas fa-times"></i> İptal
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Pages List -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-list"></i> Mevcut Sayfalar</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($pages)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">Henüz sayfa eklenmemiş</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Başlık</th>
                                                    <th>URL</th>
                                                    <th>Durum</th>
                                                    <th>Oluşturan</th>
                                                    <th>Tarih</th>
                                                    <th>İşlemler</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($pages as $page): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?= e($page['title']) ?></strong>
                                                        </td>
                                                        <td>
                                                            <code><?= e($page['slug']) ?></code>
                                                        </td>
                                                        <td>
                                                            <?php if ($page['is_active']): ?>
                                                                <span class="badge bg-success">Aktif</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary">Pasif</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?= e($page['creator_name'] ?? 'Bilinmiyor') ?></td>
                                                        <td><?= date('d.m.Y H:i', strtotime($page['created_at'])) ?></td>
                                                        <td>
                                                            <div class="btn-group btn-group-sm">
                                                                <a href="pages.php?edit=<?= $page['id'] ?>" class="btn btn-outline-primary">
                                                                    <i class="fas fa-edit"></i>
                                                                </a>
                                                                <button type="button" class="btn btn-outline-danger" 
                                                                        onclick="deletePage(<?= $page['id'] ?>, '<?= e($page['title']) ?>')">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Delete Form (Hidden) -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete_page">
        <input type="hidden" name="id" id="deleteId">
    </form>
    
    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script src="assets/js/main.js"></script>
    
    <script>
        // Initialize Quill Editor
        var quill = new Quill('#editor', {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'color': [] }, { 'background': [] }],
                    [{ 'align': [] }],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    [{ 'indent': '-1'}, { 'indent': '+1' }],
                    ['link', 'blockquote', 'code-block'],
                    ['clean']
                ]
            },
            placeholder: 'Sayfa içeriğinizi buraya yazın...'
        });
        
        // Form submit edilirken Quill içeriğini textarea'ya kopyala ve validasyon
        document.querySelector('form').addEventListener('submit', function(e) {
            const content = quill.root.innerHTML;
            const textContent = quill.getText().trim();
            
            // İçerik kontrolü
            if (textContent.length === 0) {
                e.preventDefault();
                document.getElementById('content-error').style.display = 'block';
                document.getElementById('editor').style.borderColor = '#dc3545';
                document.getElementById('editor').scrollIntoView({ behavior: 'smooth' });
                return false;
            }
            
            // Hata mesajını gizle
            document.getElementById('content-error').style.display = 'none';
            document.getElementById('editor').style.borderColor = '#ddd';
            
            // İçeriği textarea'ya kopyala
            document.querySelector('#content').value = content;
        });
        
        // Quill'e yazı yazıldığında hata mesajını gizle
        quill.on('text-change', function() {
            const textContent = quill.getText().trim();
            if (textContent.length > 0) {
                document.getElementById('content-error').style.display = 'none';
                document.getElementById('editor').style.borderColor = '#ddd';
            }
        });
        
        // Auto-generate slug from title
        document.getElementById('title').addEventListener('input', function() {
            const title = this.value;
            const slug = title
                .toLowerCase()
                .replace(/ğ/g, 'g')
                .replace(/ü/g, 'u')
                .replace(/ş/g, 's')
                .replace(/ı/g, 'i')
                .replace(/ö/g, 'o')
                .replace(/ç/g, 'c')
                .replace(/[^a-z0-9\s-]/g, '')
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-')
                .replace(/^-|-$/g, '');
            
            document.getElementById('slug').value = slug;
        });
        
        // Show/Hide Add Form
        function showAddForm() {
            document.getElementById('pageForm').style.display = 'block';
            document.getElementById('title').focus();
        }
        
        function hideAddForm() {
            document.getElementById('pageForm').style.display = 'none';
            // Clear form
            document.getElementById('title').value = '';
            document.getElementById('slug').value = '';
            quill.setContents([]);
            document.getElementById('content-error').style.display = 'none';
            document.getElementById('editor').style.borderColor = '#ddd';
        }
        
        // Delete page function
        function deletePage(id, title) {
            if (confirm('"{}" sayfasını silmek istediğinizden emin misiniz?\n\nBu işlem geri alınamaz.'.replace('{}', title))) {
                document.getElementById('deleteId').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
    </script>
</body>
</html>
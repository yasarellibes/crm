<?php
/**
 * Forgot Password Handler
 */

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'E-posta adresi gereklidir']);
        exit;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Geçersiz e-posta formatı']);
        exit;
    }
    
    try {
        // Check if email exists in companies, users, branches, or personnel tables
        $company = fetchOne("SELECT id, name FROM companies WHERE email = ?", [$email]);
        $user = fetchOne("SELECT id, name FROM users WHERE email = ?", [$email]);
        $branch = fetchOne("SELECT id, name FROM branches WHERE email = ?", [$email]);
        $personnel = fetchOne("SELECT id, name FROM personnel WHERE email = ?", [$email]);
        
        if (!$company && !$user && !$branch && !$personnel) {
            // Don't reveal that email doesn't exist for security
            echo json_encode(['success' => true, 'message' => 'Eğer bu e-posta adresi sistemimizde kayıtlı ise, şifre sıfırlama talimatları gönderilmiştir.']);
            exit;
        }
        
        // Generate reset token (simple implementation for demo)
        $resetToken = bin2hex(random_bytes(32));
        $resetExpiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Store reset token (in a real system, you'd store this in a password_resets table)
        // For demo purposes, we'll just return success
        
        // In a real implementation, you would:
        // 1. Store the reset token in database with expiry
        // 2. Send email with reset link
        // 3. Create a password reset form that validates the token
        
        echo json_encode([
            'success' => true, 
            'message' => 'Şifre sıfırlama talimatları e-posta adresinize gönderilmiştir. (Demo: Lütfen sistem yöneticisine başvurun)'
        ]);
        
    } catch (Exception $e) {
        error_log("Forgot password error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Bir hata oluştu. Lütfen tekrar deneyin.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek']);
}
?>
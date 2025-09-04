<?php
session_start();
require_once '../config/auth.php';
require_once '../includes/functions.php';

// Check if user is logged in
requireLogin();

header('Content-Type: application/json');

$brandId = $_GET['brand_id'] ?? '';

if (empty($brandId)) {
    echo json_encode(['success' => false, 'message' => 'Marka ID gereklidir']);
    exit;
}

try {
    $companyId = $_SESSION['company_id'];
    
    // Get models for the selected brand
    $models = fetchAll("
        SELECT id, name 
        FROM models 
        WHERE brand_id = ? AND company_id = ?
        ORDER BY name
    ", [$brandId, $companyId]);
    
    echo json_encode([
        'success' => true,
        'models' => $models
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Modeller yüklenirken hata oluştu: ' . $e->getMessage()
    ]);
}
?>
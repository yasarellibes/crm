<?php
session_start();
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'includes/functions.php';

// Require login
requireLogin();

$serviceId = $_GET['id'] ?? 0;

if (!$serviceId) {
    header('Location: services.php');
    exit;
}

// Get service details with company, branch and personnel information
$serviceQuery = "
    SELECT s.*, 
           c.name as customer_name, c.phone as customer_phone, c.email as customer_email,
           c.address as customer_address, c.city as customer_city, c.district as customer_district,
           p.name as personnel_name,
           comp.name as company_name, comp.phone as company_phone, comp.email as company_email,
           comp.address as company_address, comp.city as company_city, comp.district as company_district,
           b.name as branch_name, b.phone as branch_phone, b.address as branch_address,
           b.city as branch_city, b.district as branch_district,
           COALESCE(brand_table.name, s.brand) as brand_name,
           COALESCE(model_table.name, s.model) as model_name
    FROM services s
    LEFT JOIN customers c ON s.customer_id = c.id
    LEFT JOIN personnel p ON s.personnel_id = p.id
    LEFT JOIN companies comp ON s.company_id = comp.id
    LEFT JOIN branches b ON s.branch_id = b.id
    LEFT JOIN brands brand_table ON (CASE WHEN s.brand ~ '^[0-9]+$' THEN CAST(s.brand AS INTEGER) ELSE NULL END) = brand_table.id AND s.company_id = brand_table.company_id
    LEFT JOIN models model_table ON (CASE WHEN s.model ~ '^[0-9]+$' THEN CAST(s.model AS INTEGER) ELSE NULL END) = model_table.id AND s.company_id = model_table.company_id
    WHERE s.id = ?
";

list($serviceQuery, $serviceParams) = applyDataFilter($serviceQuery, [$serviceId], 's');
$service = fetchOne($serviceQuery, $serviceParams);

if (!$service) {
    header('Location: services.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Servis Fişi - <?= e($service['customer_name']) ?></title>
    
    <style>
        /* 8cm Thermal Printer Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        @page {
            size: 80mm auto;
            margin: 0;
        }
        
        body {
            font-family: 'Courier New', monospace;
            font-size: 11px;
            line-height: 1.2;
            color: #000;
            background: white;
            width: 80mm;
            margin: 0;
            padding: 2mm;
        }
        
        .receipt {
            width: 100%;
        }
        
        .center {
            text-align: center;
        }
        
        .left {
            text-align: left;
        }
        
        .right {
            text-align: right;
        }
        
        .bold {
            font-weight: bold;
        }
        
        .separator {
            border-bottom: 1px dashed #000;
            margin: 3mm 0;
            height: 1px;
        }
        
        .company-info {
            text-align: center;
            margin-bottom: 3mm;
        }
        
        .branch-info {
            text-align: center;
            margin-bottom: 3mm;
        }
        
        .section {
            margin-bottom: 3mm;
        }
        
        .row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1mm;
        }
        
        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 8mm;
            padding-top: 3mm;
        }
        
        .signature-area {
            text-align: center;
            width: 45%;
        }
        
        .signature-line {
            border-bottom: 1px solid #000;
            height: 8mm;
            margin-bottom: 2mm;
        }
        
        .price-section {
            text-align: center;
            font-size: 12px;
            font-weight: bold;
            margin: 3mm 0;
        }
        
        @media print {
            body {
                -webkit-print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <div class="receipt">
        <!-- Form Title -->
        <div class="center" style="font-size: 14px; font-weight: bold; margin-bottom: 5mm;">
            SERVİS FORMU
        </div>
        
        <!-- Company Information -->
        <div class="company-info">
            <div class="bold"><?= e($service['company_name']) ?></div>
            <?php if ($service['company_address']): ?>
            <div><?= e($service['company_address']) ?></div>
            <?php endif; ?>
            <div><?= e($service['company_city']) ?><?= $service['company_district'] ? ', ' . e($service['company_district']) : '' ?></div>
            <?php if ($service['company_phone']): ?>
            <div><?= e($service['company_phone']) ?></div>
            <?php endif; ?>
        </div>
        
        <div class="separator"></div>
        
        <!-- Branch Information -->
        <?php if ($service['branch_name']): ?>
        <div class="branch-info">
            <div class="bold"><?= e($service['branch_name']) ?></div>
            <?php if ($service['branch_address']): ?>
            <div><?= e($service['branch_address']) ?></div>
            <?php endif; ?>
            <div><?= e($service['branch_city'] ?? $service['company_city']) ?><?= ($service['branch_district'] ?? $service['company_district']) ? ', ' . e($service['branch_district'] ?? $service['company_district']) : '' ?></div>
            <?php if ($service['branch_phone']): ?>
            <div><?= e($service['branch_phone']) ?></div>
            <?php endif; ?>
        </div>
        
        <div class="separator"></div>
        <?php endif; ?>
        
        <!-- Customer Information -->
        <div class="section">
            <div class="bold">MÜŞTERI BİLGİLERİ</div>
            <div><?= e($service['customer_name']) ?></div>
            <div><?= e($service['customer_phone']) ?></div>
            <div><?= e($service['customer_address']) ?></div>
            <div><?= e($service['customer_city']) ?>, <?= e($service['customer_district']) ?></div>
        </div>
        
        <div class="separator"></div>
        
        <!-- Service Information (Device Details) -->
        <div class="section">
            <div class="bold">CİHAZ BİLGİSİ</div>
            <div class="row">
                <span>Cihaz:</span>
                <span><?= e($service['device']) ?></span>
            </div>
            <div class="row">
                <span>Marka:</span>
                <span><?= e($service['brand_name'] ?? $service['brand'] ?? 'Belirtilmemiş') ?></span>
            </div>
            <div class="row">
                <span>Model:</span>
                <span><?= e($service['model_name'] ?? $service['model'] ?? 'Belirtilmemiş') ?></span>
            </div>
            <div class="row">
                <span>Şikayet:</span>
                <span><?= e($service['complaint']) ?></span>
            </div>
            <?php if ($service['description']): ?>
            <div style="margin-top: 2mm;">
                <div class="bold">Açıklama:</div>
                <div><?= nl2br(e($service['description'])) ?></div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="separator"></div>
        
        <!-- Service Details -->
        <div class="section">
            <div class="bold">SERVİS DETAY</div>
            <div class="row">
                <span>Tarih:</span>
                <span><?= date('d.m.Y', strtotime($service['service_date'])) ?></span>
            </div>
            <div class="row">
                <span>Servis No:</span>
                <span>#<?= $service['id'] ?></span>
            </div>
            <div class="row">
                <span>Durum:</span>
                <span><?= e($service['operation_status']) ?></span>
            </div>
        </div>
        
        <div class="separator"></div>
        
        <!-- Price -->
        <?php if ($service['price']): ?>
        <div class="price-section">
            <div class="bold">FİYAT: <?= number_format($service['price'], 2) ?> ₺</div>
        </div>
        
        <div class="separator"></div>
        <?php endif; ?>
        
        <!-- Signatures -->
        <div class="signatures">
            <div class="signature-area">
                <div><?= e($service['customer_name']) ?></div>
                <div class="signature-line"></div>
                <div>Müşteri İmzası</div>
            </div>
            
            <div class="signature-area">
                <div><?= e($service['personnel_name'] ?? 'Teknisyen') ?></div>
                <div class="signature-line"></div>
                <div>Teknisyen İmzası</div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="center" style="margin-top: 5mm; font-size: 10px;">
            <div><?= date('d.m.Y H:i') ?></div>
            <div>Teşekkür ederiz</div>
        </div>
    </div>

    <script>
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>
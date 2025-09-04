<?php
/**
 * Dashboard Page - Main Overview
 * Equivalent to Flask dashboard functionality
 */

// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle AJAX requests for calendar
if (isset($_GET['ajax']) && $_GET['ajax'] === 'calendar') {
    require_once 'config/database.php';
    require_once 'includes/functions.php';
    header('Content-Type: application/json');
    
    try {
        $month = intval($_POST['month'] ?? date('n'));
        $year = intval($_POST['year'] ?? date('Y'));
        
        // Get services count by date for the specified month (PostgreSQL compatible)
        $query = "
            SELECT DATE(service_date) as date, COUNT(*) as count
            FROM services s
            WHERE EXTRACT(YEAR FROM service_date) = ? AND EXTRACT(MONTH FROM service_date) = ?
        ";
        $params = [$year, $month];
        
        // Apply role-based filtering
        if ($_SESSION['role'] === 'company_admin' && $_SESSION['user_type'] === 'company') {
            $query .= " AND s.company_id = ?";
            $params[] = $_SESSION['company_id'];
        } elseif ($_SESSION['role'] === 'branch_manager' && isset($_SESSION['branch_id'])) {
            $query .= " AND s.branch_id = ?";
            $params[] = $_SESSION['branch_id'];
        } elseif ($_SESSION['role'] === 'technician' && isset($_SESSION['user_id'])) {
            $query .= " AND s.personnel_id = ?";
            $params[] = $_SESSION['user_id'];
        }
        $query .= " GROUP BY DATE(service_date)";
        
        $servicesByDate = fetchAll($query, $params);
        
        // Format data for JavaScript
        $calendarData = [];
        foreach ($servicesByDate as $row) {
            $calendarData[$row['date']] = intval($row['count']);
        }
        
        echo json_encode([
            'success' => true,
            'calendar_data' => $calendarData
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'calendar_data' => []
        ]);
    }
    exit;
}

// Technicians cannot access dashboard - redirect to services
if (isset($_SESSION['role']) && $_SESSION['role'] === 'technician') {
    header('Location: services.php?message=technician_redirect');
    exit;
}

$pageTitle = 'Dashboard - Serviso';
require_once 'includes/header.php';

// Get dashboard statistics
$stats = getDashboardStats();

// Get current month year for JavaScript
$currentMonth = date('n');
$currentYear = date('Y');

// Get recent services with full customer data
$recentServicesQuery = "
    SELECT s.id, s.service_date, s.operation_status, s.device, s.brand, s.price,
           s.customer_id, 
           c.name as customer_name, c.phone as customer_phone,
           c.address as customer_address, c.district as customer_district, c.city as customer_city,
           p.name as personnel_name
    FROM services s
    LEFT JOIN customers c ON s.customer_id = c.id
    LEFT JOIN personnel p ON s.personnel_id = p.id
    WHERE 1=1
";

list($recentServicesQuery, $recentServicesParams) = applyDataFilter($recentServicesQuery, [], 's');
$recentServicesQuery .= " ORDER BY s.created_at DESC LIMIT 5";

$recentServices = fetchAll($recentServicesQuery, $recentServicesParams);

// Calculate revenue based on role hierarchy
$revenueQuery = "SELECT COALESCE(SUM(price), 0) as total_revenue FROM services s WHERE 1=1";
$revenueParams = [];
$revenueTitle = 'Toplam Ciro';

if (isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'super_admin':
            // Super admin sees all revenue
            $revenueTitle = 'Toplam Ciro (Tüm Şirketler)';
            break;
        case 'company_admin':
            // Company admin sees only their company's revenue
            $revenueQuery .= " AND s.company_id = ?";
            $revenueParams[] = $_SESSION['company_id'];
            $revenueTitle = 'Toplam Ciro (Şirket)';
            break;
        case 'branch_manager':
            // Branch manager sees only their branch's revenue
            $revenueQuery .= " AND s.company_id = ? AND s.branch_id = ?";
            $revenueParams[] = $_SESSION['company_id'];
            $revenueParams[] = $_SESSION['branch_id'];
            $revenueTitle = 'Toplam Ciro (Şube)';
            break;
        case 'technician':
            // Technician sees only assigned services revenue
            $revenueQuery .= " AND s.personnel_id = (SELECT id FROM personnel WHERE email = ? LIMIT 1)";
            $revenueParams[] = $_SESSION['email'];
            $revenueTitle = 'Toplam Ciro (Atanan Servisler)';
            break;
    }
}

$revenueResult = fetchOne($revenueQuery, $revenueParams);
$totalRevenue = $revenueResult['total_revenue'] ?? 0;
?>

<!-- Quick Actions Section -->
<?php if (isset($_SESSION['role']) && $_SESSION['role'] != 'technician'): ?>
<div class="action-grid">
    <a href="service_add.php" class="action-card">
        <div class="action-icon bg-primary">
            <i class="fas fa-plus"></i>
        </div>
        <div class="action-content">
            <h5>Yeni Servis</h5>
            <p>Hızlı servis kaydı oluştur</p>
        </div>
    </a>
    
    <a href="customer_add.php" class="action-card">
        <div class="action-icon bg-success">
            <i class="fas fa-user-plus"></i>
        </div>
        <div class="action-content">
            <h5>Yeni Müşteri</h5>
            <p>Müşteri bilgisi ekle</p>
        </div>
    </a>
    
    <a href="services.php" class="action-card">
        <div class="action-icon bg-warning">
            <i class="fas fa-list"></i>
        </div>
        <div class="action-content">
            <h5>Servis Listesi</h5>
            <p>Tüm servisleri görüntüle</p>
        </div>
    </a>
</div>
<?php endif; ?>

<!-- Statistics Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon bg-primary">
                <i class="fas fa-users"></i>
            </div>
        </div>
        <div class="stat-value"><?= number_format($stats['customers']) ?></div>
        <div class="stat-label">Toplam Müşteri</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon bg-success">
                <i class="fas fa-wrench"></i>
            </div>
        </div>
        <div class="stat-value"><?= number_format($stats['services']) ?></div>
        <div class="stat-label">Toplam Servis</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon bg-warning">
                <i class="fas fa-money-bill-wave"></i>
            </div>
        </div>
        <div class="stat-value"><?= number_format($totalRevenue, 2) ?> ₺</div>
        <div class="stat-label"><?= e($revenueTitle) ?></div>
    </div>
</div>
        </div>
    </div>
</div>

<!-- Service Calendar -->
<div class="dashboard-section calendar-section">
    <div class="section-header">
        <h4 class="section-title">
            <i class="fas fa-calendar-alt me-2"></i>
            Servis Takvimi
        </h4>
        <div class="calendar-navigation">
            <button class="btn btn-sm btn-outline-primary" onclick="previousMonth()">
                <i class="fas fa-chevron-left"></i>
            </button>
            <span id="currentMonth" class="current-month-display"></span>
            <button class="btn btn-sm btn-outline-primary" onclick="nextMonth()">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
    </div>
    <div class="calendar-container">
        <div id="serviceCalendar" class="monthly-calendar">
            <!-- Calendar will be generated by JavaScript -->
        </div>
    </div>
</div>
</div>





<!-- Dashboard Calendar JavaScript -->
<script>
// Calendar variables
let currentMonth = <?= $currentMonth ?>;
let currentYear = <?= $currentYear ?>;
const monthNames = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran',
    'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];

// Initialize calendar when page loads
document.addEventListener('DOMContentLoaded', function() {
    updateCalendar();
});

// Calendar navigation functions
function previousMonth() {
    currentMonth--;
    if (currentMonth < 1) {
        currentMonth = 12;
        currentYear--;
    }
    updateCalendar();
}

function nextMonth() {
    currentMonth++;
    if (currentMonth > 12) {
        currentMonth = 1;
        currentYear++;
    }
    updateCalendar();
}

// Update calendar display
function updateCalendar() {
    // Update month display
    document.getElementById('currentMonth').textContent = 
        monthNames[currentMonth - 1] + ' ' + currentYear;
    
    // Load calendar data
    loadCalendarData();
}

// Load calendar data from server
function loadCalendarData() {
    const formData = new FormData();
    formData.append('month', currentMonth);
    formData.append('year', currentYear);
    
    fetch('dashboard.php?ajax=calendar', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            renderCalendar(data.calendar_data);
        } else {
            console.error('Calendar data error:', data.message);
        }
    })
    .catch(error => {
        console.error('Calendar fetch error:', error);
    });
}

// Render monthly calendar with 30-day view
function renderCalendar(calendarData) {
    const firstDay = new Date(currentYear, currentMonth - 1, 1).getDay();
    const daysInMonth = new Date(currentYear, currentMonth, 0).getDate();
    
    // Adjust for Monday start (Turkish standard)
    const adjustedFirstDay = (firstDay === 0) ? 6 : firstDay - 1;
    
    let html = `
        <div class="monthly-calendar-grid">
            <div class="calendar-header">
                <div class="calendar-day-name">Pzt</div>
                <div class="calendar-day-name">Sal</div>
                <div class="calendar-day-name">Çar</div>
                <div class="calendar-day-name">Per</div>
                <div class="calendar-day-name">Cum</div>
                <div class="calendar-day-name">Cmt</div>
                <div class="calendar-day-name">Paz</div>
            </div>
            <div class="calendar-days">
    `;
    
    // Empty cells for days before month starts
    for (let i = 0; i < adjustedFirstDay; i++) {
        html += '<div class="calendar-day empty"></div>';
    }
    
    // Days of the month
    for (let day = 1; day <= daysInMonth; day++) {
        const dateStr = `${currentYear}-${currentMonth.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
        const serviceCount = calendarData[dateStr] || 0;
        const isToday = isDateToday(currentYear, currentMonth, day);
        
        html += `
            <div class="calendar-day ${isToday ? 'today' : ''} ${serviceCount > 0 ? 'has-services' : ''}" 
                 onclick="showDayServices('${dateStr}')" data-date="${dateStr}">
                <div class="day-number">${day}</div>
                ${serviceCount > 0 ? `<div class="service-indicator">${serviceCount}</div>` : ''}
            </div>
        `;
    }
    
    html += `
            </div>
        </div>
    `;
    
    document.getElementById('serviceCalendar').innerHTML = html;
}

// Check if date is today
function isDateToday(year, month, day) {
    const today = new Date();
    return year === today.getFullYear() && 
           month === (today.getMonth() + 1) && 
           day === today.getDate();
}

// Show services for a specific day
function showDayServices(date) {
    // You can implement a modal or redirect to services page with date filter
    window.location.href = `services.php?date=${date}`;
}
</script>

<!-- Modern Dashboard CSS -->
<style>
/* Dashboard Container */
.dashboard-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
    width: 100%;
}

/* Main content wrapper for better centering */
.main-content {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
    width: 100%;
}



.dashboard-section {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    overflow: hidden;
    margin-bottom: 30px;
}

.section-header {
    background: #f8f9fa;
    color: #495057;
    padding: 20px 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #dee2e6;
}

.section-title {
    margin: 0;
    font-weight: 600;
    font-size: 18px;
}

/* Action Buttons */
.action-buttons-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    padding: 25px;
    max-width: 800px;
    margin: 0 auto;
}

.action-button {
    display: flex;
    align-items: center;
    padding: 20px;
    border-radius: 10px;
    text-decoration: none;
    color: white;
    transition: all 0.3s ease;
    border: none;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.action-button:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    color: white;
    text-decoration: none;
}

.action-button.primary { background: #6c757d; }
.action-button.success { background: #495057; }
.action-button.info { background: #868e96; }
.action-button.warning { background: #adb5bd; }

.action-icon {
    font-size: 24px;
    margin-right: 15px;
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255,255,255,0.2);
    border-radius: 10px;
}

.action-title {
    font-weight: 600;
    font-size: 16px;
    margin-bottom: 4px;
}

.action-subtitle {
    font-size: 13px;
    opacity: 0.9;
}

/* Statistics Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    padding: 25px;
    max-width: 700px;
    margin: 0 auto;
}

.stat-item {
    display: flex;
    align-items: center;
    padding: 20px;
    border-radius: 10px;
    color: white;
    transition: transform 0.3s ease;
}

.stat-item:hover {
    transform: translateY(-2px);
}

.stat-item.primary { background: #6c757d; }
.stat-item.success { background: #495057; }
.stat-item.warning { background: #868e96; }
.stat-item.danger { background: #adb5bd; }
.stat-item.info { background: #343a40; }

.stat-item.full-width {
    grid-column: 1 / -1;
}

.stat-icon {
    font-size: 28px;
    margin-right: 20px;
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255,255,255,0.2);
    border-radius: 12px;
}

.stat-number {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 4px;
}

.stat-label {
    font-size: 14px;
    opacity: 0.9;
}

/* Calendar Section */
.calendar-section {
    min-height: 500px;
    max-width: 800px;
    margin: 20px auto;
}

.calendar-navigation {
    display: flex;
    align-items: center;
    gap: 15px;
}

.current-month-display {
    font-weight: 600;
    color: #495057;
    font-size: 16px;
    min-width: 150px;
    text-align: center;
}

.calendar-container {
    padding: 25px;
    background: #f8fafc;
}

/* Monthly Calendar Grid */
.monthly-calendar-grid {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.calendar-header {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    background: #f1f5f9;
}

.calendar-day-name {
    padding: 15px 8px;
    text-align: center;
    font-weight: 600;
    color: #475569;
    font-size: 14px;
    border-right: 1px solid #e2e8f0;
}

.calendar-day-name:last-child {
    border-right: none;
}

.calendar-days {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
}

.calendar-day {
    aspect-ratio: 1;
    border: 1px solid #e2e8f0;
    background: white;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
    min-height: 80px;
}

.calendar-day:hover {
    background: #f1f5f9;
    transform: scale(1.02);
}

.calendar-day.empty {
    background: #f8fafc;
    cursor: default;
}

.calendar-day.empty:hover {
    transform: none;
    background: #f8fafc;
}

.calendar-day.today {
    background: #495057;
    color: white;
    font-weight: 700;
}

.calendar-day.today:hover {
    background: #343a40;
}

.calendar-day.has-services {
    background: #e9ecef;
    color: #495057;
}

.calendar-day.has-services:hover {
    background: #dee2e6;
}

.day-number {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 4px;
}

.service-indicator {
    background: #6c757d;
    color: white;
    font-size: 10px;
    font-weight: 600;
    padding: 2px 6px;
    border-radius: 10px;
    min-width: 20px;
    text-align: center;
}

.calendar-day.today .service-indicator {
    background: rgba(255,255,255,0.3);
    color: white;
}



/* Responsive Design */
@media (max-width: 1024px) {
    .dashboard-container, .main-content {
        max-width: 100%;
        padding: 0 15px;
    }
    
    .action-buttons-grid {
        grid-template-columns: 1fr 1fr;
        max-width: none;
    }
    
    .stats-grid {
        grid-template-columns: repeat(3, 1fr);
        max-width: none;
    }
    
    .calendar-section {
        max-width: none;
        margin: 20px 0;
    }
}

@media (max-width: 768px) {
    .dashboard-container, .main-content {
        padding: 0 10px;
    }
    
    .action-buttons-grid {
        grid-template-columns: 1fr;
        gap: 15px;
        padding: 20px;
    }
    
    .action-button {
        padding: 20px 15px;
        text-align: center;
    }
    
    .action-icon {
        margin-right: 12px;
        width: 45px;
        height: 45px;
        font-size: 20px;
    }
    
    .action-title {
        font-size: 15px;
    }
    
    .action-subtitle {
        font-size: 12px;
        margin-top: 2px;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
        gap: 15px;
        padding: 20px;
    }
    
    .stat-item {
        padding: 18px;
        flex-direction: row;
        align-items: center;
    }
    
    .stat-icon {
        width: 50px;
        height: 50px;
        font-size: 24px;
        margin-right: 15px;
        margin-bottom: 0;
    }
    
    .stat-number {
        font-size: 24px;
    }
    
    .stat-label {
        font-size: 13px;
    }
    
    .section-header {
        padding: 15px 20px;
        flex-direction: column;
        gap: 10px;
        text-align: center;
    }
    
    .section-title {
        font-size: 16px;
    }
    
    .calendar-container {
        padding: 15px;
    }
    
    .calendar-navigation {
        flex-direction: column;
        gap: 10px;
    }
    
    .current-month-display {
        min-width: auto;
        margin: 0 10px;
    }
    
    .calendar-day {
        min-height: 50px;
        padding: 5px;
    }
    
    .day-number {
        font-size: 13px;
        margin-bottom: 2px;
    }
    
    .service-indicator {
        font-size: 9px;
        padding: 1px 4px;
        min-width: 16px;
    }
    
    .calendar-day-name {
        padding: 10px 4px;
        font-size: 12px;
    }
}

@media (max-width: 480px) {
    .dashboard-container, .main-content {
        padding: 0 5px;
    }
    
    .action-button {
        padding: 15px 10px;
    }
    
    .action-icon {
        width: 40px;
        height: 40px;
        font-size: 18px;
        margin-right: 10px;
    }
    
    .action-title {
        font-size: 14px;
    }
    
    .stat-item {
        padding: 15px;
    }
    
    .stat-icon {
        width: 45px;
        height: 45px;
        font-size: 20px;
        margin-right: 12px;
    }
    
    .stat-number {
        font-size: 20px;
    }
    
    .calendar-day {
        min-height: 45px;
    }
    
    .day-number {
        font-size: 12px;
    }
    
    .service-indicator {
        font-size: 8px;
        padding: 1px 3px;
        min-width: 14px;
    }
}

.quick-action-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    color: white;
    border-radius: 12px;
    padding: 20px;
    height: 100px;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.quick-action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    color: white;
    text-decoration: none;
}

.quick-action-btn i {
    font-size: 24px;
    margin-bottom: 8px;
}

.quick-action-btn span {
    font-weight: 600;
    font-size: 14px;
}
</style>

<?php require_once 'includes/footer.php'; ?>
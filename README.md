# Serviso - HVAC Service Management System

**Official Website:** [serviso.com.tr](https://serviso.com.tr)

Serviso is a comprehensive HVAC (Heating, Ventilation, and Air Conditioning) service management system specifically designed for cPanel shared hosting environments. Built with native PHP and MySQL, it provides a complete solution for managing service requests, customers, technicians, and equipment across multiple companies and branches.

## Features

### Core Functionality
- **Service Management**: Complete service request tracking from creation to completion
- **Customer Management**: Centralized customer database with contact information  
- **Equipment Tracking**: Device, brand, and model management with hierarchical relationships
- **Personnel Management**: Role-based user system with authentication
- **Company & Branch Management**: Multi-tenant architecture supporting multiple organizations

### Interactive Features
- **Clickable Phone Numbers**: Direct calling functionality with `tel:` links
- **Google Maps Integration**: Click addresses to open in Google Maps
- **Customer Detail Links**: Navigate directly to customer profiles
- **Excel Export**: CSV export with UTF-8 BOM for proper Turkish character support
- **Advanced Search & Filtering**: Real-time search and date-based filtering
- **Print System**: Professional service receipts with company branding

### Role-Based Access Control
- **Super Admin**: Full system access across all companies
- **Company Admin**: Company-wide access and management
- **Branch Manager**: Branch-specific access and oversight
- **Technician**: Field-focused mobile interface

### Technology Stack
- **Backend**: Native PHP 8.0+ (no frameworks for maximum compatibility)
- **Database**: MySQL/MariaDB with prepared statements
- **Frontend**: Bootstrap 5, Font Awesome 6, Vanilla JavaScript
- **Security**: Password hashing, SQL injection protection, session management
- **Hosting**: Optimized for cPanel shared hosting environments

## Installation

### Requirements
- PHP 8.0 or higher
- MySQL 5.7 or MariaDB 10.3+
- Web server with mod_rewrite support
- cPanel hosting environment

### Quick Installation
1. Extract `hvac-php-cpanel.tar.gz` to your domain's public_html folder
2. Create a MySQL database in cPanel
3. Update database credentials in `config/database.php`
4. Import the database schema from `database/schema.sql`
5. Access your domain to start using the system

### Demo Accounts
- **Super Admin**: `super_admin@example.com` / `admin123`
- **Company Admin**: `company_admin@example.com` / `admin123`
- **Branch Manager**: `branch_manager@example.com` / `admin123`
- **Technician**: `technician@example.com` / `admin123`

## File Structure

```
cpanel_hvac_system/
├── config/
│   ├── database.php          # Database configuration
│   └── auth.php              # Authentication system
├── includes/
│   ├── header.php            # Common header template
│   ├── footer.php            # Common footer template
│   └── functions.php         # Utility functions
├── assets/
│   ├── css/style.css         # Custom CSS styling
│   └── js/main.js            # JavaScript functionality
├── ajax/
│   ├── export_services.php   # Excel export handler
│   ├── delete_service.php    # Service deletion
│   └── delete_customer.php   # Customer deletion
├── database/
│   └── schema.sql            # MySQL database schema
├── modals/
│   └── definition_modals.php # Modal components
├── login.php                 # Login page
├── dashboard.php             # Main dashboard
├── services.php              # Service management
├── customers.php             # Customer management
├── definitions.php           # System definitions
└── profile.php               # User profile
```

## Key Features Detail

### Interactive Tables
- Telephone numbers are clickable (`tel:` protocol) for direct dialing
- Addresses link to Google Maps for navigation
- Customer names link to detailed customer profiles
- Hover effects and responsive design

### Data Export
- Excel-compatible CSV export with UTF-8 BOM
- Respects current filters and search criteria
- Turkish character support maintained

### Security Features
- Prepared statements prevent SQL injection
- Password hashing with PHP's `password_hash()`
- Session-based authentication
- Role-based data filtering

### Mobile Optimization
- Responsive Bootstrap 5 design
- Touch-friendly interface elements
- Mobile-specific CSS optimizations

## Configuration

### Database Setup
Update `config/database.php` with your cPanel MySQL details:

```php
private $host = 'localhost';
private $username = 'your_cpanel_username_dbname';
private $password = 'your_database_password';
private $database = 'your_cpanel_username_dbname';
```

### URL Rewriting
The system uses `.htaccess` for clean URLs. Ensure mod_rewrite is enabled on your hosting.

## Support

For technical support and customization requests, contact us through [serviso.com.tr](https://serviso.com.tr).

## License

This system is proprietary software. All rights reserved to Serviso.

---

**Serviso HVAC Service Management System**  
Built for professional HVAC service companies  
[serviso.com.tr](https://serviso.com.tr)
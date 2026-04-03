# CECSMS 🖥️📦
### Computer Equipment & Consumables Stock Management System  

CECSMS is a web-based inventory and asset management system built for institutions. It helps manage the full lifecycle of assets—from procurement and allocation to maintenance and disposal—while keeping everything organized and easy to track.

---

## 🚀 Key Features  

- 📦 **Asset & Stock Management**  
  Track IT hardware and consumables (toners, peripherals, etc.) with real-time visibility.  

- 🪑 **Furniture Inventory**  
  Manage non-IT assets like office furniture with tagging support.  

- 🤝 **Vendor Management**  
  Maintain a centralized list of suppliers and service providers with service history.  

- 🚚 **Dispatch Tracking**  
  Monitor movement of items from central stock to divisions or departments.  

- 📍 **Unit Asset Assignment**  
  Assign assets to labs, offices, or units with clear ownership tracking.  

- 🛠️ **Service Management**  
  Log repairs, maintenance history, and servicing schedules.  

- 🎨 **Modern UI**  
  Clean, responsive interface inspired by modern SaaS dashboards.  

- 🔐 **Role-Based Access Control (RBAC)**  
  - **SuperAdmin:** Full system access across institutions  
  - **Admin:** Manage institution-level operations  
  - **Staff:** View assigned assets and raise service requests  

---

## 🛠️ Tech Stack  

| Layer     | Technology |
|----------|-----------|
| Backend  | PHP 8.x |
| Database | MySQL / MariaDB |
| Frontend | Bootstrap 5, jQuery, SweetAlert2 |
| Security | CSRF Protection, Password Hashing (bcrypt) |
| Icons    | Bootstrap Icons |

---

## 📂 Project Structure

```text
├── admin/            # Auth, Dashboard, and Global Layouts
├── config/           # Database Connection & Environment settings
├── divisions/        # Division-level asset assignment & notifications
├── furniture_stock/  # Management of non-IT institutional assets
├── includes/         # Core functions, Security headers, & Session logic
├── master/           # Master Data (Institutions, Units, Items Master)
├── services/         # Maintenance logs & Service tracking
├── stock/            # Central store inventory & Dispatch logic
├── users/            # RBAC User Management
└── vendors/          # Supplier & Service Provider directory

---

## ⚙️ Installation & Setup

### Prerequisites
* **Web Server:** Apache or Nginx
* **PHP:** Version 7.4 or higher
* **Database:** MySQL Server 5.7+ or MariaDB

### Steps
1. **Clone the Repository**
   ```bash
   git clone [https://github.com/dinesh-suvarna/cecsms.git](https://github.com/dinesh-suvarna/cecsms.git)

2.  **Database Configuration**
    * Create a database named `cecsms_db` using phpMyAdmin or MySQL CLI.
    * Import the provided SQL schema located in `database/schema.sql`.
    * Update `config/db.php` with your local credentials:
    ```php
    <?php
    // config/db.php
    $host = "localhost";
    $user = "root";
    $pass = "your_password";
    $dbname = "cecsms_db";

    $conn = mysqli_connect($host, $user, $pass, $dbname);
    ?>
    ```

3.  **Environment Check**
    * Ensure the `includes/session.php` is properly configured for your specific domain or local path.
    * Verify that `mod_rewrite` is enabled if you plan to use custom routing.

4.  **Access the System**
    * Open your browser and navigate to: `http://localhost/cecsms/admin/login.php`
    * Use your assigned SuperAdmin or Admin credentials to log in.

---

## 📊 Roadmap
- [x] **Modern Emerald SaaS UI:** Refactored entire interface for better UX.
- [x] **Vendor Management:** Dedicated module for third-party service providers.
- [ ] **Asset Tagging:** QR Code/Barcode generation for physical hardware.
- [ ] **Stock Alerts:** Automated low-stock email notifications for consumables.
- [ ] **Reporting:** Advanced PDF/Excel export functionality for audits.

---

## 🛡️ License
Distributed under the MIT License. See `LICENSE` for more information.

---
**Developed for institutional asset tracking and operational efficiency.**

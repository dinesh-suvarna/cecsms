# CECSMS 🖥️📦
### Computer Equipment & Consumables Stock Management System

**CECSMS** is a professional, web-based inventory and asset management solution designed for large-scale institutions. It streamlines the tracking of hardware lifecycles—from procurement and departmental assignment to maintenance and final e-waste disposal.

---

## 🚀 Key Features

* **📦 Asset & Stock Management:** Real-time tracking of hardware inventory and consumable levels (toners, peripherals, etc.).
* **🚚 Dispatch Tracking:** Monitor the movement of equipment from central stores to specific institutional divisions.
* **📍 Unit Asset Assignment:** Granular tracking of assets assigned to specific Labs, Offices, or Research Units.
* **🛠️ Service Management:** Log maintenance history, repairs, and scheduled servicing for high-value hardware.
* **♻️ E-Waste Management:** Dedicated workflow for decommissioning obsolete equipment in compliance with disposal policies.
* **🔐 Role-Based Access Control (RBAC):**
    * **SuperAdmin:** Manage multiple institutions, system configurations, and user auditing.
    * **Admin:** Manage inventory, track dispatches, and generate departmental reports.

---

## 🛠️ Tech Stack

| Layer          | Technology                                   |
| :------------- | :------------------------------------------- |
| **Backend** | PHP 8.x (Procedural/OOP Hybrid)              |
| **Database** | MySQL / MariaDB                              |
| **Frontend** | Bootstrap 5, jQuery, SweetAlert2             |
| **Analytics** | Chart.js (Inventory Visualizations)          |
| **Icons** | Bootstrap Icons                              |

---

## 🏗️ System Architecture

The system uses a hierarchical data model to reflect institutional structures:
`Institution` ➡️ `Division` ➡️ `Unit (Lab/Office/Store)`




---

## ⚙️ Installation & Setup

### Prerequisites
* Web Server (Apache/Nginx)
* PHP 7.4 or higher
* MySQL Server

### Steps
1.  **Clone the Repository**
    ```bash
    git clone https://github.com/dinesh-suvarna/cecsms.git
    ```
2.  **Database Configuration**
    * Create a database named `cecsms_db`.
    * Import the provided SQL schema (located in `/database/schema.sql`).
    * Update `config/db.php` with your database credentials:
    ```php
    $host = "localhost";
    $user = "root";
    $pass = "your_password";
    $dbname = "cecsms_db";
    ```
3.  **Directory Permissions**
    * Ensure the `uploads/` directory (if applicable) is writable by the server.
4.  **Access the System**
    * Navigate to `http://localhost/cecsms` in your browser.

---

## 📊 Roadmap
- [ ] QR Code/Barcode generation for physical asset tagging.
- [ ] Automated low-stock email notifications for consumables.
- [ ] PDF Export functionality for audit reports.
- [ ] Mobile-responsive scanning interface.

---

## 🛡️ License
Distributed under the MIT License. See `LICENSE` for more information.

---
**Developed for institutional asset tracking and efficiency.**

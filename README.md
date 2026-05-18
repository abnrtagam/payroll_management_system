# Payroll Management System

A production-minded Payroll Management System built for the subject **IT221 - Information Management**. This project demonstrates key database concepts including transaction management, concurrency control, and data warehousing.

## 🚀 Features

- **Dashboard**: Real-time summary statistics using advanced SQL queries.
- **Employee Management**: Add, edit, and delete employee records with input validation.
- **Payroll Processing**: Batch process payroll for active employees with concurrency checks.
- **Payroll History**: View past payroll runs with department and year filters.
- **Data Warehouse & ETL**: On-demand ETL process to load data into a star schema for analysis.

## 🛠️ Technology Stack

- **Backend**: PHP 8.2 (using PDO with prepared statements)
- **Database**: MySQL (via XAMPP)
- **Frontend**: HTML5, Vanilla CSS (Custom Theme), Bootstrap 5, Font Awesome
- **Fonts**: IBM Plex Sans (Body), IBM Plex Mono (Numbers/Currency)

## 📊 Database Topics Demonstrated

This system explicitly demonstrates the following six database topics:

1. **Advanced SQL**: Utilization of window functions (`RANK()`, `SUM() OVER`) and correlated subqueries in views.
2. **Transaction Management**: All-or-nothing payroll processing wrapped in `BEGIN`, `COMMIT`, and `ROLLBACK`.
3. **Concurrency Control**: Optimistic locking using a `version` column on the `employees` table.
4. **Data Warehousing**: A separate star schema consisting of 1 fact table and 4 dimension tables.
5. **Data Integration (ETL)**: A stored procedure (`sp_run_etl`) that extracts, transforms, and loads data into the warehouse.
6. **Referential Integrity**: Strict foreign key constraints using `ON DELETE RESTRICT`.

## ⚙️ Setup Instructions

### Prerequisites
- XAMPP installed (Apache and MySQL).

### Installation
1. Move the `payroll_management_IM` folder into your XAMPP `htdocs` directory:
   ```text
   C:\xampp\htdocs\payroll_management_IM
   ```
2. Open the XAMPP Control Panel and start **Apache** and **MySQL**.
3. Open your browser and navigate to `http://localhost/phpmyadmin`.
4. Go to the **SQL** tab and paste the contents of `sql/payroll_system.sql` to create the database and seed data.
5. Access the application at: `http://localhost/payroll_management_IM`

## 📁 File Structure

```text
payroll_management_IM/
├── assets/
│   ├── css/
│   │   └── theme.css
│   └── js/
│       └── app.js
├── config/
│   └── db.php
├── includes/
│   ├── header.php
│   ├── sidebar.php
│   └── footer.php
├── pages/
│   ├── dashboard.php
│   ├── employees/
│   │   ├── index.php
│   │   ├── add.php
│   │   ├── edit.php
│   │   └── delete.php
│   ├── payroll/
│   │   ├── process.php
│   │   └── history.php
│   └── warehouse/
│       └── index.php
├── sql/
│   └── payroll_system.sql
└── index.php
```

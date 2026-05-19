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

## 📂 Folder & File Breakdown

To understand how this system operates, here is an explanation of every folder and file in the project, written in plain terms.

### `config/`
This folder handles database connectivity settings.
* **`db.php`**: Responsible for connecting our PHP application to the MySQL database. It uses PHP Data Objects (PDO) with custom settings to enforce strict security (real prepared statements to prevent SQL injections) and readable debugging output (throwing exceptions when queries fail).

### `includes/`
This folder stores reusable frontend templates to prevent writing the same code multiple times.
* **`header.php`**: Contains the head section of the HTML document, including links to the external Bootstrap 5 CSS library and icons.
* **`sidebar.php`**: Holds the main navigation sidebar. It determines which page is currently open and highlights the corresponding link in gold/amber.
* **`footer.php`**: Closes the page layouts and loads the Bootstrap Javascript libraries needed for interactions.

### `assets/`
This folder stores static styling and client-side behavior assets.
* **`css/theme.css`**: The design stylesheet. It defines the application's look and feel, such as the dark charcoal sidebar, off-white panels, gold color codes, borderless clean tables, and customized fonts (IBM Plex Sans for general text and IBM Plex Mono for numeric amounts).
* **`js/app.js`**: Contains simple client-side Javascript. It configures error/success alerts to automatically disappear after five seconds and prompts users with a confirmation message before starting the ETL process.

### `pages/`
The core directory containing all the user-facing application screens, grouped logically into subfolders.
* **`dashboard.php`**: The home page of the system. It displays aggregated metrics (Active Employees, total payrolls processed, total wages paid, and last payroll run date) using advanced SQL calculations, and renders a department summary list using a pre-configured database view.
* **`employees/`**: Manages employee-related actions.
  * **`index.php`**: Pulls employee profiles from the unified employee database view and lists them in a clean table.
  * **`add.php`**: Displays a form to create a new employee. Uses parameterized queries (prepared statements) to validate input and save new records securely.
  * **`edit.php`**: Allows updating employee records. Implements concurrency control by checking the record's version before saving changes.
  * **`delete.php`**: Handles deleting employees. It tests database constraints by blocking a deletion if an employee already has payroll records attached to them.
* **`payroll/`**: Responsible for calculations and pay history.
  * **`process.php`**: Computes wages and deductions for a selected month/year. It manages transaction safety by executing all inserts and updates in a single block. If anything fails (such as a concurrent database update conflict), it cancels all changes to protect the data.
  * **`history.php`**: Displays a historical log of all processed payrolls, allowing filters by department and year.
* **`warehouse/`**: Houses analytical features.
  * **`index.php`**: Displays reporting data stored inside the Analytical Fact table and triggers the ETL process with a button.

### `sql/`
* **`payroll_system.sql`**: The primary SQL database blueprint. It creates the database, configures the tables, sets up referential integrity constraints, builds views, defines the ETL procedure, and populates the database with initial sample data.

### `index.php` (Root)
* The entry point of the app. It automatically redirects users to the Dashboard page.

## 🗄️ Database Schema

This section documents the MySQL database structure, detailing the role, key columns, and connections for each table, view, and procedure.

### Transactional Tables (OLTP)
These tables handle day-to-day operations and store active system records.

#### `departments`
* **Purpose**: Stores the departments in the organization.
* **Key Columns**: `dept_id` (Primary key, auto-incrementing number), `dept_name` (Unique department title).
* **Relationships**: Links to the `positions` table. You cannot delete a department if positions are linked to it.

#### `positions`
* **Purpose**: Stores job titles and their default starting salaries.
* **Key Columns**: `pos_id` (Primary key), `dept_id` (Foreign key pointing to `departments`), `pos_title` (Title of job), `base_salary` (Standard pay).
* **Relationships**: Connects to `departments` via `dept_id` and to `employees` via `pos_id`.

#### `employees`
* **Purpose**: Stores personnel records for all active and inactive staff members.
* **Key Columns**: `emp_id` (Primary key), `pos_id` (Foreign key pointing to `positions`), `email` (Unique login email), `status` (Active, Inactive, Terminated status indicator), `version` (Version number used for checking concurrency conflicts).
* **Relationships**: Linked to `positions` via `pos_id`. Connects to `payroll_records`.

#### `payroll_records`
* **Purpose**: Stores the monthly summary of an employee's earnings, deductions, and net take-home pay.
* **Key Columns**: `record_id` (Primary key), `emp_id` (Foreign key pointing to `employees`), `period_month`/`period_year` (Pay period details), `basic_pay`, `total_deductions`, `net_pay` (Calculated values).
* **Relationships**: Connected to `employees` via `emp_id` and has multiple linked records in `payroll_items`. Each employee can only have one payroll record per specific month and year.

#### `payroll_items`
* **Purpose**: Stores individual line-item details (earnings or deductions) for a specific payroll record.
* **Key Columns**: `item_id` (Primary key), `record_id` (Foreign key pointing to `payroll_records`), `item_type` (Earning or Deduction label), `description` (e.g., "Tax", "SSS"), `amount` (Monetary value).
* **Relationships**: Connected to `payroll_records` via `record_id` with `ON DELETE CASCADE` (deleting a payroll summary deletes all its line items).

### Star Schema Tables (OLAP)
These tables are optimized for analytics, summarizing data into dimension and fact tables for fast reporting.

#### `dim_employee`
* **Purpose**: A dimension table storing flat employee information for analytics.
* **Key Columns**: `dim_emp_id` (Primary key), `source_emp_id` (Matches `emp_id` from the operational table), `full_name` (Combined first and last names), `status`.
* **Relationships**: Links to the central `fact_payroll` table.

#### `dim_department`
* **Purpose**: A dimension table storing department information for analytics.
* **Key Columns**: `dim_dept_id` (Primary key), `source_dept_id` (Matches `dept_id` from the operational table), `dept_name`.
* **Relationships**: Links to the central `fact_payroll` table.

#### `dim_position`
* **Purpose**: A dimension table storing job position information for analytics.
* **Key Columns**: `dim_pos_id` (Primary key), `source_pos_id` (Matches `pos_id` from the operational table), `pos_title`, `base_salary`.
* **Relationships**: Links to the central `fact_payroll` table.

#### `dim_time`
* **Purpose**: A dimension table defining time periods for analytics.
* **Key Columns**: `dim_time_id` (Primary key), `period_month`, `period_year`, `quarter` (Derived 1-4 calendar quarter), `period_label` (Readable format, e.g. "Jan 2026").
* **Relationships**: Links to the central `fact_payroll` table.

#### `fact_payroll`
* **Purpose**: The central table containing numerical measurements (metrics) of payroll transactions, linked to all dimensions.
* **Key Columns**: `fact_id` (Primary key), `dim_emp_id`, `dim_dept_id`, `dim_pos_id`, `dim_time_id` (Foreign keys pointing to their dimension tables), `basic_pay`, `total_deductions`, `net_pay`.
* **Relationships**: Links all dimension tables (`dim_employee`, `dim_department`, `dim_position`, `dim_time`) together to enable fast analysis.

### Advanced Database Objects
These views and procedures implement complex logic directly inside the MySQL database server.

#### `v_employee_full` (View)
* **Purpose**: Combines employee information with their active positions and department names into a single virtual table.
* **Key Columns**: `emp_id`, `full_name` (Combined last and first name), `email`, `pos_title`, `dept_name`, `base_salary`.
* **Relationships**: Runs an `INNER JOIN` query linking `employees`, `positions`, and `departments`.

#### `v_department_payroll_summary` (View)
* **Purpose**: Calculates total net pay and payroll records generated for each department.
* **Key Columns**: `dept_id`, `dept_name`, `employees_paid`, `total_records`, `total_net_pay`, `last_payroll_date`.
* **Relationships**: Links departments and payroll logs, utilizing a correlated subquery to dynamically find the latest date.

#### `v_payroll_with_rank` (View)
* **Purpose**: Ranks employees by net salary within their department and tracks cumulative take-home pay.
* **Key Columns**: `record_id`, `full_name`, `dept_name`, `net_pay`, `pay_rank_in_dept`, `running_total`.
* **Relationships**: Utilizes SQL window functions: `RANK() OVER (PARTITION BY ... ORDER BY net_pay DESC)` to rank employees and `SUM() OVER (PARTITION BY ...)` to track running totals over time.

#### `sp_run_etl()` (Stored Procedure)
* **Purpose**: Extracts transactional data, transforms it, and loads it into the star schema tables.
* **Key Steps**: Opens a transaction, temporarily disables foreign keys to clear (`TRUNCATE`) old data warehouse tables, pulls records from operational tables while transforming them (formatting labels, calculating quarters), inserts the new records into dimensions and facts, and commits the transaction.

## 🔄 System Logical Flow

This section describes the flow of data and operations within the system, detailing which files handle specific functions.

### 1. Initialization & Navigation
- **Entry Point**: `index.php` (Root) redirects users to the Dashboard.
- **Database Connection**: `config/db.php` is required by all functional pages to establish a secure PDO connection.
- **Layout & Navigation**: `includes/header.php`, `includes/sidebar.php`, and `includes/footer.php` provide a consistent UI and navigation links.

### 2. Employee Management Flow
- **Listing**: `pages/employees/index.php` fetches data from the `v_employee_full` view to display all employees.
- **Creation**: `pages/employees/add.php` collects employee details and inserts them using a prepared statement after validating the email is unique.
- **Modification**: `pages/employees/edit.php` handles updates. It reads the current `version` of the employee record. Upon saving, it checks if the version still matches to prevent overwriting concurrent changes.
- **Removal**: `pages/employees/delete.php` attempts to delete an employee. It catches foreign key constraint errors if the employee has existing payroll records.

### 3. Payroll Processing Flow
- **Input**: `pages/payroll/process.php` allows selection of the month and year.
- **Execution**:
  1. Starts a database transaction (`BEGIN`).
  2. Reads active employees.
  3. Checks for existing records to prevent duplicates.
  4. Increments the employee `version` column. If the update fails (0 rows affected), it rolls back the transaction (Concurrency Control).
  5. Inserts records into `payroll_records` and `payroll_items`.
  6. Commits the transaction (`COMMIT`).

### 4. Data Warehouse & ETL Flow
- **Display**: `pages/warehouse/index.php` queries the `fact_payroll` table joined with dimension tables to show analytical data.
- **ETL Trigger**: Clicking the "Run ETL Process" button on the same page calls the `sp_run_etl()` stored procedure.
- **ETL Execution** (Inside DB):
  1. Truncates star schema tables.
  2. Extracts data from transactional tables.
  3. Transforms data (e.g., deriving quarter, formatting names).
  4. Loads data into dimension and fact tables.


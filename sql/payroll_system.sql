-- ============================================================
-- Payroll Management System — Full Database Schema
-- IT221 Information Management
-- ============================================================
-- This script creates:
--   1. OLTP tables with full referential integrity
--   2. Star-schema (OLAP) tables for data warehousing
--   3. Three database views (advanced SQL, window functions)
--   4. One stored procedure for ETL (sp_run_etl)
--   5. Seed data for testing
-- ============================================================

DROP DATABASE IF EXISTS payroll_system;
CREATE DATABASE payroll_system CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE payroll_system;

-- ============================================================
-- 1. OLTP TABLES (Transactional)
-- ============================================================
-- Relationship chain:
--   departments → positions → employees → payroll_records → payroll_items
-- All foreign keys use ON DELETE RESTRICT to enforce referential integrity:
--   you cannot delete a parent row while child rows reference it.
-- ============================================================

-- ------------------------------------------------------------
-- 1a. departments
-- ------------------------------------------------------------
CREATE TABLE departments (
    dept_id     INT AUTO_INCREMENT PRIMARY KEY,
    dept_name   VARCHAR(100) NOT NULL UNIQUE,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 1b. positions
-- Each position belongs to exactly one department.
-- ------------------------------------------------------------
CREATE TABLE positions (
    pos_id      INT AUTO_INCREMENT PRIMARY KEY,
    dept_id     INT          NOT NULL,
    pos_title   VARCHAR(100) NOT NULL,
    base_salary DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_positions_dept
        FOREIGN KEY (dept_id) REFERENCES departments(dept_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 1c. employees
-- Has a `version` column for optimistic concurrency control.
-- When processing payroll, the system reads the current version,
-- then updates only if the version has not changed. If another
-- process modified the row, the version will differ and the
-- UPDATE will affect 0 rows → ROLLBACK is triggered.
-- ------------------------------------------------------------
CREATE TABLE employees (
    emp_id      INT AUTO_INCREMENT PRIMARY KEY,
    pos_id      INT          NOT NULL,
    first_name  VARCHAR(60)  NOT NULL,
    last_name   VARCHAR(60)  NOT NULL,
    email       VARCHAR(150) NOT NULL UNIQUE,
    hire_date   DATE         NOT NULL,
    status      ENUM('active','inactive','terminated') NOT NULL DEFAULT 'active',
    version     INT          NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_employees_pos
        FOREIGN KEY (pos_id) REFERENCES positions(pos_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 1d. payroll_records
-- One record per employee per pay period (month/year).
-- The UNIQUE constraint prevents duplicate payroll for the
-- same employee in the same period.
-- ------------------------------------------------------------
CREATE TABLE payroll_records (
    record_id    INT AUTO_INCREMENT PRIMARY KEY,
    emp_id       INT            NOT NULL,
    period_month TINYINT        NOT NULL CHECK (period_month BETWEEN 1 AND 12),
    period_year  SMALLINT       NOT NULL CHECK (period_year BETWEEN 2000 AND 2099),
    basic_pay    DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    total_deductions DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    net_pay      DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    processed_at DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    notes        TEXT           NULL,

    CONSTRAINT uq_payroll_period
        UNIQUE (emp_id, period_month, period_year),

    CONSTRAINT fk_payroll_emp
        FOREIGN KEY (emp_id) REFERENCES employees(emp_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 1e. payroll_items
-- Line-item breakdown for each payroll record.
-- item_type = 'earning' or 'deduction'
-- ------------------------------------------------------------
CREATE TABLE payroll_items (
    item_id     INT AUTO_INCREMENT PRIMARY KEY,
    record_id   INT           NOT NULL,
    item_type   ENUM('earning','deduction') NOT NULL,
    description VARCHAR(100)  NOT NULL,
    amount      DECIMAL(12,2) NOT NULL DEFAULT 0.00,

    CONSTRAINT fk_items_record
        FOREIGN KEY (record_id) REFERENCES payroll_records(record_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB;


-- ============================================================
-- 2. STAR-SCHEMA TABLES (OLAP / Data Warehouse)
-- ============================================================
-- Star schema: fact_payroll at center, surrounded by four
-- dimension tables. Used for analytical queries and reporting.
-- ============================================================

-- ------------------------------------------------------------
-- 2a. dim_employee
-- ------------------------------------------------------------
CREATE TABLE dim_employee (
    dim_emp_id   INT AUTO_INCREMENT PRIMARY KEY,
    source_emp_id INT         NOT NULL,
    full_name    VARCHAR(130) NOT NULL,
    email        VARCHAR(150) NOT NULL,
    hire_date    DATE         NOT NULL,
    status       VARCHAR(20)  NOT NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 2b. dim_department
-- ------------------------------------------------------------
CREATE TABLE dim_department (
    dim_dept_id   INT AUTO_INCREMENT PRIMARY KEY,
    source_dept_id INT         NOT NULL,
    dept_name     VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 2c. dim_position
-- ------------------------------------------------------------
CREATE TABLE dim_position (
    dim_pos_id    INT AUTO_INCREMENT PRIMARY KEY,
    source_pos_id INT          NOT NULL,
    pos_title     VARCHAR(100) NOT NULL,
    base_salary   DECIMAL(12,2) NOT NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 2d. dim_time
-- ------------------------------------------------------------
CREATE TABLE dim_time (
    dim_time_id  INT AUTO_INCREMENT PRIMARY KEY,
    period_month TINYINT      NOT NULL,
    period_year  SMALLINT     NOT NULL,
    quarter      TINYINT      NOT NULL,
    period_label VARCHAR(20)  NOT NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 2e. fact_payroll
-- Foreign keys reference all four dimension tables.
-- ------------------------------------------------------------
CREATE TABLE fact_payroll (
    fact_id      INT AUTO_INCREMENT PRIMARY KEY,
    dim_emp_id   INT            NOT NULL,
    dim_dept_id  INT            NOT NULL,
    dim_pos_id   INT            NOT NULL,
    dim_time_id  INT            NOT NULL,
    basic_pay    DECIMAL(12,2)  NOT NULL,
    total_deductions DECIMAL(12,2) NOT NULL,
    net_pay      DECIMAL(12,2)  NOT NULL,

    CONSTRAINT fk_fact_emp
        FOREIGN KEY (dim_emp_id)  REFERENCES dim_employee(dim_emp_id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_fact_dept
        FOREIGN KEY (dim_dept_id) REFERENCES dim_department(dim_dept_id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_fact_pos
        FOREIGN KEY (dim_pos_id)  REFERENCES dim_position(dim_pos_id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_fact_time
        FOREIGN KEY (dim_time_id) REFERENCES dim_time(dim_time_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB;


-- ============================================================
-- 3. DATABASE VIEWS (Advanced SQL)
-- ============================================================

-- ------------------------------------------------------------
-- 3a. v_employee_full (View)
-- Purpose: Combines employee profiles with their active positions 
--          and department details into a single flat view.
-- Key Features: Performs a 3-way INNER JOIN linking employees, 
--               positions, and departments. Useful for simplifying 
--               frequent lookups in PHP pages.
-- ------------------------------------------------------------
CREATE OR REPLACE VIEW v_employee_full AS
SELECT
    e.emp_id,
    e.first_name,
    e.last_name,
    CONCAT(e.last_name, ', ', e.first_name) AS full_name,
    e.email,
    e.hire_date,
    e.status,
    e.version,
    p.pos_id,
    p.pos_title,
    p.base_salary,
    d.dept_id,
    d.dept_name
FROM employees e
INNER JOIN positions p ON e.pos_id = p.pos_id
INNER JOIN departments d ON p.dept_id = d.dept_id;

-- ------------------------------------------------------------
-- 3b. v_department_payroll_summary (View)
-- Purpose: Generates high-level aggregated payroll statistics 
--          grouped by department.
-- Key Features:
--   1. GROUP BY & Aggregate Functions: Counts paid employees (COUNT DISTINCT), 
--      total records (COUNT), and sums take-home wages (SUM with COALESCE).
--   2. Correlated Subquery: Finds the most recent processed date for each 
--      department by querying payroll_records matching the current row's dept_id.
-- ------------------------------------------------------------
CREATE OR REPLACE VIEW v_department_payroll_summary AS
SELECT
    d.dept_id,
    d.dept_name,
    COUNT(DISTINCT pr.emp_id) AS employees_paid,
    COUNT(pr.record_id)       AS total_records,
    COALESCE(SUM(pr.net_pay), 0) AS total_net_pay,
    (
        SELECT MAX(pr2.processed_at)
        FROM payroll_records pr2
        INNER JOIN employees e2 ON pr2.emp_id = e2.emp_id
        INNER JOIN positions p2 ON e2.pos_id = p2.pos_id
        WHERE p2.dept_id = d.dept_id
    ) AS last_payroll_date
FROM departments d
LEFT JOIN positions p   ON d.dept_id = p.dept_id
LEFT JOIN employees e   ON p.pos_id  = e.pos_id
LEFT JOIN payroll_records pr ON e.emp_id = pr.emp_id
GROUP BY d.dept_id, d.dept_name;

-- ------------------------------------------------------------
-- 3c. v_payroll_with_rank (View)
-- Purpose: Performs advanced payroll analytics and year-to-date tracking.
-- Key Features (SQL Window Functions):
--   1. RANK() OVER: Ranks employees by their net pay within their specific 
--      department and pay period, with the highest earner receiving rank 1.
--   2. SUM() OVER:
--      - `dept_period_total`: Calculates the total net payroll spent by a 
--        particular department in a specific month and year.
--      - `running_total`: Computes a cumulative running total of all net pay 
--        received by each employee chronologically over time.
-- ------------------------------------------------------------
CREATE OR REPLACE VIEW v_payroll_with_rank AS
SELECT
    pr.record_id,
    e.emp_id,
    CONCAT(e.last_name, ', ', e.first_name) AS full_name,
    d.dept_name,
    pr.period_month,
    pr.period_year,
    pr.basic_pay,
    pr.total_deductions,
    pr.net_pay,
    pr.processed_at,
    RANK() OVER (
        PARTITION BY d.dept_id, pr.period_month, pr.period_year
        ORDER BY pr.net_pay DESC
    ) AS pay_rank_in_dept,
    SUM(pr.net_pay) OVER (
        PARTITION BY d.dept_id, pr.period_month, pr.period_year
    ) AS dept_period_total,
    SUM(pr.net_pay) OVER (
        PARTITION BY e.emp_id
        ORDER BY pr.period_year, pr.period_month
        ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
    ) AS running_total
FROM payroll_records pr
INNER JOIN employees e  ON pr.emp_id = e.emp_id
INNER JOIN positions p  ON e.pos_id  = p.pos_id
INNER JOIN departments d ON p.dept_id = d.dept_id;


-- ============================================================
-- 4. STORED PROCEDURE — sp_run_etl
-- Purpose: Runs the Extract, Transform, and Load (ETL) process 
--          to refresh the OLAP Data Warehouse tables.
-- Key Features:
--   1. Transaction Isolation: Wraps operations in START TRANSACTION and COMMIT. 
--      If an error occurs, it rolls back (ROLLBACK) to prevent partial data states.
--   2. Disabling Constraints: Temporarily sets FOREIGN_KEY_CHECKS = 0 to truncate 
--      old dimensional data, then resets it to 1 to preserve integrity.
--   3. Data Transformation: Concatenates names, derives quarters using CEIL(), 
--      and maps numerical months to abbreviated text labels using ELT().
-- ============================================================

DELIMITER $$

CREATE PROCEDURE sp_run_etl()
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    -- ----- TRUNCATE (disable FK checks temporarily) -----
    SET FOREIGN_KEY_CHECKS = 0;
    TRUNCATE TABLE fact_payroll;
    TRUNCATE TABLE dim_employee;
    TRUNCATE TABLE dim_department;
    TRUNCATE TABLE dim_position;
    TRUNCATE TABLE dim_time;
    SET FOREIGN_KEY_CHECKS = 1;

    -- ----- LOAD dim_employee -----
    INSERT INTO dim_employee (source_emp_id, full_name, email, hire_date, status)
    SELECT
        e.emp_id,
        CONCAT(e.first_name, ' ', e.last_name),
        e.email,
        e.hire_date,
        e.status
    FROM employees e;

    -- ----- LOAD dim_department -----
    INSERT INTO dim_department (source_dept_id, dept_name)
    SELECT dept_id, dept_name
    FROM departments;

    -- ----- LOAD dim_position -----
    INSERT INTO dim_position (source_pos_id, pos_title, base_salary)
    SELECT pos_id, pos_title, base_salary
    FROM positions;

    -- ----- LOAD dim_time -----
    -- Extract unique (month, year) combinations from payroll_records,
    -- derive quarter and a human-readable period_label.
    INSERT INTO dim_time (period_month, period_year, quarter, period_label)
    SELECT DISTINCT
        pr.period_month,
        pr.period_year,
        CEIL(pr.period_month / 3),
        CONCAT(
            ELT(pr.period_month,
                'Jan','Feb','Mar','Apr','May','Jun',
                'Jul','Aug','Sep','Oct','Nov','Dec'),
            ' ',
            pr.period_year
        )
    FROM payroll_records pr;

    -- ----- LOAD fact_payroll -----
    -- Joins OLTP payroll_records back to dimension tables
    -- using the source IDs for lookup.
    INSERT INTO fact_payroll (dim_emp_id, dim_dept_id, dim_pos_id, dim_time_id,
                              basic_pay, total_deductions, net_pay)
    SELECT
        de.dim_emp_id,
        dd.dim_dept_id,
        dp.dim_pos_id,
        dt.dim_time_id,
        pr.basic_pay,
        pr.total_deductions,
        pr.net_pay
    FROM payroll_records pr
    INNER JOIN employees e    ON pr.emp_id     = e.emp_id
    INNER JOIN positions p    ON e.pos_id      = p.pos_id
    INNER JOIN dim_employee de   ON de.source_emp_id  = e.emp_id
    INNER JOIN dim_department dd ON dd.source_dept_id  = p.dept_id
    INNER JOIN dim_position dp   ON dp.source_pos_id  = p.pos_id
    INNER JOIN dim_time dt       ON dt.period_month    = pr.period_month
                                AND dt.period_year     = pr.period_year;

    COMMIT;
END$$

DELIMITER ;


-- ============================================================
-- 5. SEED DATA
-- ============================================================
-- Realistic sample data so the system has something to display
-- immediately after import.
-- ============================================================

-- Departments
INSERT INTO departments (dept_name) VALUES
('Finance'),
('Human Resources'),
('Engineering'),
('Marketing'),
('Operations');

-- Positions (linked to departments)
INSERT INTO positions (dept_id, pos_title, base_salary) VALUES
-- Finance (dept_id = 1)
(1, 'Finance Manager',   55000.00),
(1, 'Accountant',        38000.00),
(1, 'Financial Analyst',  42000.00),
-- Human Resources (dept_id = 2)
(2, 'HR Manager',        50000.00),
(2, 'HR Specialist',     35000.00),
-- Engineering (dept_id = 3)
(3, 'Engineering Lead',  65000.00),
(3, 'Software Developer', 48000.00),
(3, 'QA Engineer',       40000.00),
-- Marketing (dept_id = 4)
(4, 'Marketing Manager', 52000.00),
(4, 'Content Strategist', 36000.00),
-- Operations (dept_id = 5)
(5, 'Operations Manager', 54000.00),
(5, 'Logistics Coordinator', 32000.00);

-- Employees
INSERT INTO employees (pos_id, first_name, last_name, email, hire_date, status) VALUES
(1,  'Abner',   'Tagam',     'abner.tagam@company.com',     '2022-03-15', 'active'),
(2,  'Russell', 'Cutanda',   'russell.cutanda@company.com', '2021-06-01', 'active'),
(3,  'Aron',    'Tumampos',  'aron.tumampos@company.com',  '2023-01-10', 'active'),
(4,  'Marvel',  'Lumbab',    'marvel.lumbab@company.com',   '2020-11-20', 'active'),
(5,  'Paul',    'Villareal', 'paul.villareal@company.com',  '2022-08-05', 'active'),
(6,  'Carlos',  'Mendoza',   'carlos.mendoza@company.com',   '2019-04-12', 'active'),
(7,  'Maria',   'Garcia',    'maria.garcia@company.com',     '2021-09-18', 'active'),
(8,  'Jose',    'Rivera',    'jose.rivera@company.com',      '2023-05-22', 'active'),
(9,  'Isabella','Torres',    'isabella.torres@company.com',  '2020-07-30', 'active'),
(10, 'Rafael',  'Aquino',    'rafael.aquino@company.com',    '2022-02-14', 'active'),
(11, 'Patricia','Bautista',  'patricia.bautista@company.com','2021-12-01', 'active'),
(12, 'Miguel',  'Castillo',  'miguel.castillo@company.com',  '2023-03-08', 'active');

-- Payroll Records — January 2026
INSERT INTO payroll_records (emp_id, period_month, period_year, basic_pay, total_deductions, net_pay, processed_at) VALUES
(1,  1, 2026, 55000.00, 8250.00, 46750.00, '2026-01-31 17:00:00'),
(2,  1, 2026, 38000.00, 5700.00, 32300.00, '2026-01-31 17:00:00'),
(3,  1, 2026, 42000.00, 6300.00, 35700.00, '2026-01-31 17:00:00'),
(4,  1, 2026, 50000.00, 7500.00, 42500.00, '2026-01-31 17:00:00'),
(5,  1, 2026, 35000.00, 5250.00, 29750.00, '2026-01-31 17:00:00'),
(6,  1, 2026, 65000.00, 9750.00, 55250.00, '2026-01-31 17:00:00'),
(7,  1, 2026, 48000.00, 7200.00, 40800.00, '2026-01-31 17:00:00'),
(8,  1, 2026, 40000.00, 6000.00, 34000.00, '2026-01-31 17:00:00'),
(9,  1, 2026, 52000.00, 7800.00, 44200.00, '2026-01-31 17:00:00'),
(10, 1, 2026, 36000.00, 5400.00, 30600.00, '2026-01-31 17:00:00'),
(11, 1, 2026, 54000.00, 8100.00, 45900.00, '2026-01-31 17:00:00'),
(12, 1, 2026, 32000.00, 4800.00, 27200.00, '2026-01-31 17:00:00');

-- Payroll Records — February 2026
INSERT INTO payroll_records (emp_id, period_month, period_year, basic_pay, total_deductions, net_pay, processed_at) VALUES
(1,  2, 2026, 55000.00, 8250.00, 46750.00, '2026-02-28 17:00:00'),
(2,  2, 2026, 38000.00, 5700.00, 32300.00, '2026-02-28 17:00:00'),
(3,  2, 2026, 42000.00, 6300.00, 35700.00, '2026-02-28 17:00:00'),
(4,  2, 2026, 50000.00, 7500.00, 42500.00, '2026-02-28 17:00:00'),
(5,  2, 2026, 35000.00, 5250.00, 29750.00, '2026-02-28 17:00:00'),
(6,  2, 2026, 65000.00, 9750.00, 55250.00, '2026-02-28 17:00:00'),
(7,  2, 2026, 48000.00, 7200.00, 40800.00, '2026-02-28 17:00:00'),
(8,  2, 2026, 40000.00, 6000.00, 34000.00, '2026-02-28 17:00:00'),
(9,  2, 2026, 52000.00, 7800.00, 44200.00, '2026-02-28 17:00:00'),
(10, 2, 2026, 36000.00, 5400.00, 30600.00, '2026-02-28 17:00:00'),
(11, 2, 2026, 54000.00, 8100.00, 45900.00, '2026-02-28 17:00:00'),
(12, 2, 2026, 32000.00, 4800.00, 27200.00, '2026-02-28 17:00:00');

-- Payroll Records — March 2026
INSERT INTO payroll_records (emp_id, period_month, period_year, basic_pay, total_deductions, net_pay, processed_at) VALUES
(1,  3, 2026, 55000.00, 8250.00, 46750.00, '2026-03-31 17:00:00'),
(2,  3, 2026, 38000.00, 5700.00, 32300.00, '2026-03-31 17:00:00'),
(3,  3, 2026, 42000.00, 6300.00, 35700.00, '2026-03-31 17:00:00'),
(4,  3, 2026, 50000.00, 7500.00, 42500.00, '2026-03-31 17:00:00'),
(5,  3, 2026, 35000.00, 5250.00, 29750.00, '2026-03-31 17:00:00'),
(6,  3, 2026, 65000.00, 9750.00, 55250.00, '2026-03-31 17:00:00'),
(7,  3, 2026, 48000.00, 7200.00, 40800.00, '2026-03-31 17:00:00'),
(8,  3, 2026, 40000.00, 6000.00, 34000.00, '2026-03-31 17:00:00'),
(9,  3, 2026, 52000.00, 7800.00, 44200.00, '2026-03-31 17:00:00'),
(10, 3, 2026, 36000.00, 5400.00, 30600.00, '2026-03-31 17:00:00'),
(11, 3, 2026, 54000.00, 8100.00, 45900.00, '2026-03-31 17:00:00'),
(12, 3, 2026, 32000.00, 4800.00, 27200.00, '2026-03-31 17:00:00');

-- Payroll Items — sample line items for January 2026 (records 1–12)
-- Each record gets a basic salary earning + standard deductions
INSERT INTO payroll_items (record_id, item_type, description, amount) VALUES
-- Employee 1 (record_id = 1)
(1, 'earning',    'Basic Salary',   55000.00),
(1, 'deduction',  'Tax',            5500.00),
(1, 'deduction',  'SSS',            1375.00),
(1, 'deduction',  'PhilHealth',     1375.00),
-- Employee 2 (record_id = 2)
(2, 'earning',    'Basic Salary',   38000.00),
(2, 'deduction',  'Tax',            3800.00),
(2, 'deduction',  'SSS',            950.00),
(2, 'deduction',  'PhilHealth',     950.00),
-- Employee 3 (record_id = 3)
(3, 'earning',    'Basic Salary',   42000.00),
(3, 'deduction',  'Tax',            4200.00),
(3, 'deduction',  'SSS',            1050.00),
(3, 'deduction',  'PhilHealth',     1050.00),
-- Employee 4 (record_id = 4)
(4, 'earning',    'Basic Salary',   50000.00),
(4, 'deduction',  'Tax',            5000.00),
(4, 'deduction',  'SSS',            1250.00),
(4, 'deduction',  'PhilHealth',     1250.00),
-- Employee 5 (record_id = 5)
(5, 'earning',    'Basic Salary',   35000.00),
(5, 'deduction',  'Tax',            3500.00),
(5, 'deduction',  'SSS',            875.00),
(5, 'deduction',  'PhilHealth',     875.00),
-- Employee 6 (record_id = 6)
(6, 'earning',    'Basic Salary',   65000.00),
(6, 'deduction',  'Tax',            6500.00),
(6, 'deduction',  'SSS',            1625.00),
(6, 'deduction',  'PhilHealth',     1625.00),
-- Employee 7 (record_id = 7)
(7, 'earning',    'Basic Salary',   48000.00),
(7, 'deduction',  'Tax',            4800.00),
(7, 'deduction',  'SSS',            1200.00),
(7, 'deduction',  'PhilHealth',     1200.00),
-- Employee 8 (record_id = 8)
(8, 'earning',    'Basic Salary',   40000.00),
(8, 'deduction',  'Tax',            4000.00),
(8, 'deduction',  'SSS',            1000.00),
(8, 'deduction',  'PhilHealth',     1000.00),
-- Employee 9 (record_id = 9)
(9, 'earning',    'Basic Salary',   52000.00),
(9, 'deduction',  'Tax',            5200.00),
(9, 'deduction',  'SSS',            1300.00),
(9, 'deduction',  'PhilHealth',     1300.00),
-- Employee 10 (record_id = 10)
(10, 'earning',   'Basic Salary',   36000.00),
(10, 'deduction', 'Tax',            3600.00),
(10, 'deduction', 'SSS',            900.00),
(10, 'deduction', 'PhilHealth',     900.00),
-- Employee 11 (record_id = 11)
(11, 'earning',   'Basic Salary',   54000.00),
(11, 'deduction', 'Tax',            5400.00),
(11, 'deduction', 'SSS',            1350.00),
(11, 'deduction', 'PhilHealth',     1350.00),
-- Employee 12 (record_id = 12)
(12, 'earning',   'Basic Salary',   32000.00),
(12, 'deduction', 'Tax',            3200.00),
(12, 'deduction', 'SSS',            800.00),
(12, 'deduction', 'PhilHealth',     800.00);


-- ============================================================
-- 6. VERIFICATION QUERIES (optional — run to confirm setup)
-- ============================================================
-- SELECT * FROM v_employee_full;
-- SELECT * FROM v_department_payroll_summary;
-- SELECT * FROM v_payroll_with_rank;
-- CALL sp_run_etl();
-- SELECT * FROM fact_payroll;
-- SELECT * FROM dim_time;

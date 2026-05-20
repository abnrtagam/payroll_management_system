# IT221 – Payroll Management System
## Defense Q&A Preparation

---

## 1. DATABASE DESIGN & REFERENTIAL INTEGRITY

**Q: Walk me through the tables in your database. What are they and how are they related?**

A: The system has two layers of tables. The transactional layer has five tables: `departments`, `positions`, `employees`, `payroll_records`, and `payroll_items`. They form a chain — departments hold positions, positions belong to employees, and employees have payroll records. Each payroll record can have multiple line items in `payroll_items`. The warehouse layer has five more tables: four dimension tables (`dim_employee`, `dim_department`, `dim_position`, `dim_time`) and one fact table (`fact_payroll`) that references all four dimensions using foreign keys.

---

**Q: What is referential integrity and where did you apply it?**

A: Referential integrity means the database enforces that relationships between tables are always valid — you cannot have a child record pointing to a parent that doesn't exist. I applied it using `FOREIGN KEY` constraints with `ON DELETE RESTRICT` throughout the schema. For example, you cannot delete an employee who still has payroll records, and you cannot delete a department that still has positions or employees assigned to it. The database itself blocks those operations and my PHP catches the error and shows a proper message to the user.

---

**Q: Why did you use `ON DELETE RESTRICT` instead of `ON DELETE CASCADE`?**

A: In a payroll system, deleting records by accident would be dangerous. If I used CASCADE, deleting one department could silently wipe out all its employees and all their payroll history. RESTRICT forces the user to deal with those dependencies manually first, which is the correct behavior for financial data where audit trails matter.

---

**Q: What is a UNIQUE constraint and where did you use it?**

A: A UNIQUE constraint prevents duplicate values in a column or combination of columns. I used it in three places: `emp_code` must be unique per employee, `email` must be unique per employee, and the combination of `(emp_id, period_month, period_year)` in `payroll_records` must be unique. That last one prevents the system from accidentally processing payroll twice for the same employee in the same month.

---

## 2. ADVANCED SQL

**Q: What is a view and why did you create one instead of writing the query directly in PHP?**

A: A view is a saved SQL query stored in the database that behaves like a virtual table. I use views for two reasons: first, it keeps the PHP code clean — instead of writing a long multi-table JOIN every time, I just query the view. Second, the view is defined once and reused across multiple pages, so if the logic changes I only update it in one place. For example, `v_employee_full` joins employees, departments, and positions, and I use it on the employee list page without rewriting the JOIN each time.

---

**Q: What is a window function? How is it different from GROUP BY?**

A: A window function performs a calculation across a set of related rows without collapsing them into one row the way GROUP BY does. For example, `GROUP BY` can give me the total payroll per department, but I lose the individual employee rows. With a window function like `SUM(net_pay) OVER (PARTITION BY period_year, period_month)`, I can show each employee's row AND the total for their payroll period on the same row simultaneously. I also used `RANK() OVER (PARTITION BY period_year, period_month ORDER BY net_pay DESC)` to rank employees by their pay within each period — something GROUP BY simply cannot do.

---

**Q: What are prepared statements and why are they important?**

A: A prepared statement separates the SQL structure from the data values. Instead of putting user input directly into a query string, you write the query with `?` placeholders, then bind the values separately. This completely prevents SQL injection attacks because the database treats the bound values as data, never as executable SQL. Every single database query in this system uses PDO with prepared statements — there are no raw string-concatenated queries.

---

**Q: What is a subquery? Give an example from your system.**

A: A subquery is a SELECT statement nested inside another query. In the `v_department_payroll_summary` view, I use a subquery to calculate total payout per department while aggregating employee counts, allowing the outer query to reference that derived total without needing a separate query from PHP.

---

## 3. TRANSACTION MANAGEMENT

**Q: What is a database transaction?**

A: A transaction is a group of SQL operations that are treated as a single unit of work. Either all of them succeed together, or none of them take effect. This is described by the ACID properties — specifically Atomicity, which guarantees the all-or-nothing behavior. In my system, processing payroll for all active employees is wrapped in a single transaction so that if anything fails midway — a duplicate record, a concurrency conflict, or a database error — the entire operation is rolled back and no partial data is saved.

---

**Q: Walk me through exactly what happens in your payroll transaction — step by step.**

A: When the user clicks Process Payroll:
1. PHP calls `$pdo->beginTransaction()` to start the transaction.
2. It queries all active employees.
3. For each employee, it checks if a payroll record already exists for that month and year. If one exists, it skips that employee.
4. It then attempts to increment the employee's version column using `UPDATE employees SET version = version + 1 WHERE emp_id = ? AND version = ?`. This is the concurrency check.
5. If zero rows were affected, it means the version changed — another process modified the record. The system calls `ROLLBACK` and throws an error.
6. If the version check passes, it inserts the payroll record into `payroll_records` and the line items into `payroll_items`.
7. After all employees are processed successfully, it calls `COMMIT` to save everything permanently.
8. If any exception is thrown at any point, the catch block calls `ROLLBACK` to undo everything.

---

**Q: What is ROLLBACK and when does it trigger in your system?**

A: ROLLBACK undoes all SQL operations that happened since the last `BEGIN`. In my system it triggers in two situations: first, if the concurrency check fails — meaning the employee record was modified by another process between when we read it and when we tried to update it. Second, if any unexpected PHP exception or database error occurs during the payroll loop. The catch block always calls `ROLLBACK` to guarantee no partial data is ever committed.

---

**Q: What happens if the server crashes in the middle of a transaction?**

A: MySQL automatically rolls back any uncommitted transaction when a connection drops or the server crashes. Because we haven't called COMMIT yet, none of the intermediate inserts are permanently saved. The database returns to its state before the transaction started. This is another benefit of using transactions — the system is crash-safe.

---

## 4. CONCURRENCY CONTROL

**Q: What is concurrency control and why does a payroll system need it?**

A: Concurrency control prevents data corruption when multiple users or processes access and modify the same record at the same time. In a payroll system, imagine two administrators processing payroll simultaneously. Without concurrency control, they could both read the same employee data, both calculate payroll based on the same figures, and both insert records — resulting in duplicate payroll or incorrect totals. Concurrency control ensures only one process can complete a modification at a time.

---

**Q: Explain your version column. How does it work?**

A: Every employee record has a `version` column that starts at 0. When a process wants to modify an employee's data, it first reads the current version. When it executes the update, it includes a condition: `WHERE emp_id = ? AND version = ?` and increments version by 1 in the SET clause. If another process already modified the same record and incremented the version, the WHERE condition no longer matches and MySQL returns 0 rows affected. My code checks `rowCount()` — if it's 0, the version changed, meaning a conflict occurred, and I trigger ROLLBACK. If it's 1, the update succeeded and I proceed. This technique is called optimistic locking.

---

**Q: What is the difference between optimistic and pessimistic locking?**

A: Pessimistic locking assumes conflicts will happen and locks the row immediately when you start reading it — no one else can touch it until you're done. This uses `SELECT ... FOR UPDATE`. Optimistic locking assumes conflicts are rare. It doesn't lock anything upfront. Instead, it detects a conflict only at the moment of writing by checking if the data changed since it was read, using a version column or timestamp. I chose optimistic locking because in a school-scale system conflicts are rare, and pessimistic locking can cause performance issues if a transaction takes too long and holds the lock.

---

**Q: What would happen if you didn't have concurrency control in your payroll processing?**

A: Without it, two administrators running payroll at the same time could both pass the duplicate-check (because neither has committed yet), both calculate payroll for the same employees, and both successfully insert records — resulting in employees being paid twice. The version column catches this scenario because only the first process to commit can successfully increment the version. The second one's update returns 0 rows and triggers a rollback.

---

**Q: Give a specific scenario of how the version column prevents errors.**

A: Imagine HR Staff A clicks "Run Payroll" and the system reads Employee Abner's salary as ₱38,000 (Version 0). At that exact second, HR Staff B edits Abner's profile and promotes him, changing his salary to ₱55,000 (Version becomes 1). When Staff A's payroll process tries to save the paycheck, it checks if the version is still 0. Since it's now 1, the system aborts the payroll run. Without this, Abner would have been paid the old ₱38,000 salary!

---

**Q: Why does the version column only increase when running payroll, but not when running the ETL process?**

A: The version column is a lock to protect data *modifications*. Running Payroll actually creates new financial records based on the employee data, so it requires strict protection. The ETL process, on the other hand, only *reads* the employee data to copy it into the warehouse. Because ETL doesn't update the operational employee records, the version number stays the same.

---

## 5. DATA WAREHOUSING & STAR SCHEMA

**Q: What is a data warehouse and how is it different from your regular database tables?**

A: The regular transactional tables (OLTP) are optimized for fast inserts, updates, and deletes — the day-to-day operations. A data warehouse (OLAP) is optimized for analysis and reporting across large sets of historical data. They have different structures and different purposes. My transactional tables enforce strict referential integrity and normalization. The warehouse tables denormalize the data into a star schema to make analytical queries faster and simpler to write — fewer joins, better for aggregations.

---

**Q: What is a star schema? Name the tables in yours.**

A: A star schema has one central fact table surrounded by dimension tables. The fact table stores measurable events — in my case, individual payroll transactions with amounts. The dimension tables store the context — who, where, when, and what. My star schema has: one fact table (`fact_payroll`) with the financial figures, and four dimension tables: `dim_employee` (who was paid), `dim_department` (which department), `dim_position` (what position), and `dim_time` (when — month, year, quarter). The fact table links to all four dimensions using foreign keys.

---

**Q: Why does your dim_time table have a `quarter` column if quarter can be derived from month?**

A: In data warehousing, pre-computing derived values is intentional. The purpose of the warehouse is fast analytical queries. If I need to group payroll by quarter, I don't want every query to recalculate `CEIL(month/3)` every time it runs. By storing it in `dim_time` during the ETL load, the analytical query just reads the value directly. This is the "Transform" part of ETL — we do the computation once at load time, not at query time.

---

**Q: What is the exact difference between the Payroll History page and the Data Warehouse page?**

A: Payroll History acts like a filing cabinet of individual pay slips — it shows detailed, per-person records directly from the live operational tables (OLTP), and it updates instantly. The Data Warehouse acts like a manager's summary report — it shows aggregated, transformed data (like "Jan 2026") from the OLAP tables, optimized for big-picture analysis, and it only updates when you manually run the ETL process.

---

**Q: What is the difference between the `payroll_records` table and the `payroll_items` table?**

A: Think of it like a grocery receipt. `payroll_records` is the header of the receipt — it shows the summary totals for one paycheck (e.g., Abner received ₱46,750 net pay in Jan 2026). `payroll_items` are the individual line items on that receipt — it shows exactly where the deductions came from (e.g., ₱5,500 Tax, ₱1,375 SSS, ₱1,375 PhilHealth).

---

## 6. DATA INTEGRATION / ETL

**Q: What does ETL stand for and what does each step mean in your system?**

A: ETL stands for Extract, Transform, Load. Extract means reading data from the source — in my case, the transactional tables like `employees`, `departments`, `positions`, and `payroll_records`. Transform means converting that data into the format needed for the warehouse — for example, concatenating `first_name` and `last_name` into `full_name`, calculating the `quarter` from `period_month`, and building a readable `period_label` like "January 2025". Load means inserting the transformed data into the star schema tables — the dimension tables first, then the fact table.

---

**Q: Why do you truncate the dimension and fact tables at the start of ETL instead of just inserting new records?**

A: This is called a full-refresh ETL strategy. Because my warehouse is small and school-scale, it's simpler and safer to wipe everything and reload from scratch each time. This guarantees the warehouse is always consistent with the current state of the transactional tables — no stale data, no orphaned records, no mismatched keys. For large production systems you would use incremental loading to only process new or changed records, but full-refresh is appropriate here.

---

**Q: Where is the ETL stored in your system and how is it triggered?**

A: The ETL logic lives inside a stored procedure called `sp_run_etl()` in the MySQL database. A stored procedure is a named block of SQL code stored inside the database itself. On the Data Warehouse page, there is a "Run ETL Process" button. Clicking it sends a request to PHP which calls `CALL sp_run_etl()` using PDO. The procedure then executes the full Extract-Transform-Load sequence inside the database without the data needing to travel back and forth between PHP and MySQL.

---

**Q: Why did you put ETL logic in a stored procedure instead of PHP?**

A: Three reasons. First, performance — all the data transformation and loading happens entirely inside the database engine, which is far faster than fetching rows into PHP and reinserting them. Second, separation of concerns — the ETL is a database operation and belongs in the database layer. Third, it's a requirement of the rubric to demonstrate a stored procedure, so it also directly addresses that criterion.

---

**Q: Walk me through the exact step-by-step flow of your `sp_run_etl()` procedure.**

A: First, it declares an EXIT HANDLER to rollback everything if an error occurs. Second, it starts the transaction. Third, it temporarily disables foreign key checks, wipes (TRUNCATES) all 5 warehouse tables, and re-enables the checks. Fourth, it extracts data from the live tables, transforms it (like combining first and last names, or calculating quarters), and loads it into the 4 dimension tables. Fifth, it links the raw payroll records to the new dimension IDs and loads the financial numbers into the `fact_payroll` table. Finally, it commits the transaction.

---

**Q: Why does the ETL process wipe out ALL 5 warehouse tables (including dimensions) instead of just the fact table?**

A: Because `fact_payroll` relies on the exact IDs of the dimension tables. If we only wiped the fact table, but an employee's name changed or a new department was added in the live database, our dimension tables would be outdated. Wiping all 5 ensures the entire warehouse perfectly matches the live database.

---

**Q: What operational steps must a user take in the system before they can run the ETL process and see new data?**

A: The ETL process only extracts existing data. Therefore, the HR admin must first ensure there are active employees in the system, and then they must go to the Process Payroll page and successfully run payroll for a specific month. Once those live operational records (`payroll_records`) exist, the user can run the ETL to sync them into the Data Warehouse.

---

**Q: If the ETL wipes out the old data, why do previous months still show up in the Data Warehouse?**

A: "Wiping out" means it temporarily empties the warehouse tables to prevent duplicates. However, the ETL then goes to the live database and copies *everything* from the beginning of time — including all previous months and the brand new month — and pastes it all back into the warehouse. A data warehouse is designed to hold all historical data forever for yearly reporting.

---

## 7. GENERAL SYSTEM QUESTIONS

**Q: Why did you use PDO instead of mysqli?**

A: PDO (PHP Data Objects) provides a database-agnostic interface — if I needed to switch from MySQL to PostgreSQL, I would only change the connection string. More practically, PDO has a cleaner API for prepared statements with named placeholders, better exception handling with `PDO::ERRMODE_EXCEPTION`, and more consistent behavior across query types. mysqli is MySQL-specific and slightly more verbose for prepared statements.

---

**Q: What happens if a user tries to delete an employee who has payroll records?**

A: The `payroll_records` table has a foreign key to `employees` with `ON DELETE RESTRICT`. When PHP executes the DELETE query, MySQL blocks it and throws an integrity constraint violation error. My `delete.php` wraps the query in a try-catch block, catches the `PDOException`, checks if the error code is `23000` (integrity constraint violation), and shows a friendly error message telling the user that this employee has existing payroll records and cannot be deleted.

---

**Q: What pages does your system have and what does each one do?**

A: Five main pages. The Dashboard shows summary statistics — total employees, total payroll records, total amount paid, and the date of the last payroll run — using aggregate SQL queries. Employee Management has four sub-pages for listing, adding, editing, and deleting employees, all using PDO prepared statements. Process Payroll lets you select a month and year, then runs the full payroll transaction with concurrency control. Payroll History lists all payroll records with JOIN data from the employee and department tables, and can be filtered by department and year. The Data Warehouse page shows analytical data from the star schema tables and has the ETL button.

---

**Q: What would you improve if you had more time?**

A: A few things. First, I would add authentication — currently anyone who knows the URL can access the system. Second, I would add audit logging — a table that records who did what and when, which is important for a financial system. Third, I would improve the ETL to support incremental loads instead of full-refresh, which would scale better with large datasets. Fourth, I would add input validation on the frontend using JavaScript in addition to the server-side validation already present.

---

**Q: Why does phpMyAdmin only show 25 rows in a table, but the web dashboard shows all 60 rows?**

A: This is due to pagination. By default, phpMyAdmin limits the display to 25 rows per page to load quickly in the browser, but you can click "Next" or change the row limit to see the rest. The web dashboard PHP code executes a `SELECT *` without a `LIMIT` clause, so it fetches and displays all 60 rows at once on a single page.

---

*Prepared for IT221 – Information Management Final Term PIT Defense*

<?php
// pages/warehouse/index.php
// ============================================================
// Data Warehouse — Displays data from the star schema.
// Also provides a button to run the ETL stored procedure.
// ============================================================
require_once __DIR__ . '/../../config/db.php';

$page_title = 'Data Warehouse';
$current_page = 'warehouse';
$success = '';
$error = '';

// ── Handle ETL Run ──────────────────────────────────────────
if (isset($_POST['run_etl'])) {
    try {
        // Call the stored procedure
        $pdo->exec("CALL sp_run_etl()");
        $success = 'ETL process completed successfully. Star schema has been reloaded.';
    } catch (PDOException $e) {
        $error = 'ETL Error: ' . $e->getMessage();
    }
}

// Fetch fact table data with joins to dimensions
try {
    $stmt = $pdo->query("
        SELECT 
            f.fact_id,
            e.full_name,
            d.dept_name,
            p.pos_title,
            t.period_label,
            f.net_pay
        FROM fact_payroll f
        INNER JOIN dim_employee de   ON f.dim_emp_id = de.dim_emp_id
        INNER JOIN dim_department dd ON f.dim_dept_id = dd.dim_dept_id
        INNER JOIN dim_position dp   ON f.dim_pos_id = dp.dim_pos_id
        INNER JOIN dim_time dt       ON f.dim_time_id = dt.dim_time_id
        
        -- Join back to source names for display if needed, or use dim names
        -- We'll use the dimension names as they are already populated by ETL
        -- Wait, the dimensions HAVE the names! Let's use them.
        -- dim_employee has full_name
        -- dim_department has dept_name
        -- dim_position has pos_title
        -- dim_time has period_label
    ");
    
    // Let's rewrite the query to use dimension names directly
    $stmt = $pdo->query("
        SELECT 
            f.fact_id,
            de.full_name,
            dd.dept_name,
            dp.pos_title,
            dt.period_label,
            f.net_pay
        FROM fact_payroll f
        INNER JOIN dim_employee de   ON f.dim_emp_id = de.dim_emp_id
        INNER JOIN dim_department dd ON f.dim_dept_id = dd.dim_dept_id
        INNER JOIN dim_position dp   ON f.dim_pos_id = dp.dim_pos_id
        INNER JOIN dim_time dt       ON f.dim_time_id = dt.dim_time_id
        ORDER BY f.fact_id DESC
    ");
    $fact_data = $stmt->fetchAll();
} catch (PDOException $e) {
    // If ETL hasn't run yet, table might be empty or query might fail if tables don't exist
    // But they were created in Step 1.
    $fact_data = [];
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h4">Data Warehouse & ETL</h1>
    
    <!-- ETL Run Button -->
    <form method="POST" action="">
        <button type="submit" name="run_etl" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-bolt me-1"></i>Run ETL Process
        </button>
    </form>
</div>

<!-- Messages -->
<?php if ($success): ?>
    <div class="alert" style="background-color: #E8F8F5; border: 1px solid #A3E4D7; color: #117864; padding: 12px 16px; border-radius: 4px; margin-bottom: 16px;">
        <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert" style="background-color: #FDEDEC; border: 1px solid #E6B0AA; color: var(--color-destructive); padding: 12px 16px; border-radius: 4px; margin-bottom: 16px;">
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div class="alert" style="background-color: #F4F6F7; border: 1px solid #D5DBDB; color: #5D6D7E; padding: 12px 16px; border-radius: 4px; margin-bottom: 20px;">
    <i class="fa-solid fa-circle-info me-2"></i>
    <strong>Note:</strong> This page displays data from the <strong>Star Schema</strong> (OLAP tables). Running the ETL process will truncate the warehouse tables and reload them with transformed data from the transactional tables.
</div>

<!-- Fact Table Display -->
<div class="card">
    <div class="card-header">Fact Payroll Table</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Fact ID</th>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Position</th>
                        <th>Period</th>
                        <th class="text-right">Net Pay</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($fact_data)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">No data in warehouse. Click "Run ETL Process" to load data.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($fact_data as $row): ?>
                        <tr>
                            <td class="numeric"><?php echo $row['fact_id']; ?></td>
                            <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['dept_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['pos_title']); ?></td>
                            <td class="numeric"><?php echo htmlspecialchars($row['period_label']); ?></td>
                            <td class="amount text-right">₱<?php echo number_format($row['net_pay'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

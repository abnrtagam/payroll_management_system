<?php
// pages/dashboard.php
require_once __DIR__ . '/../config/db.php';

$page_title = 'Dashboard';
$current_page = 'dashboard';

// Fetch summary stats using advanced SQL (subquery + aggregates)
try {
    $stmt = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM employees WHERE status = 'active') AS total_employees,
            COUNT(record_id) AS total_payroll_records,
            COALESCE(SUM(net_pay), 0) AS total_amount_paid,
            MAX(processed_at) AS last_payroll_date
        FROM payroll_records
    ");
    $stats = $stmt->fetch();
} catch (PDOException $e) {
    die("Error fetching stats: " . $e->getMessage());
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h4">Dashboard</h1>
</div>

<!-- Stats Cards -->
<div class="row">
    <!-- Total Employees -->
    <div class="col-md-3 mb-4">
        <div class="card">
            <div class="card-body">
                <div class="text-muted small text-uppercase font-weight-bold">Active Employees</div>
                <div class="h3 amount mt-2"><?php echo $stats['total_employees']; ?></div>
            </div>
        </div>
    </div>
    
    <!-- Total Payroll Records -->
    <div class="col-md-3 mb-4">
        <div class="card">
            <div class="card-body">
                <div class="text-muted small text-uppercase font-weight-bold">Payroll Records</div>
                <div class="h3 amount mt-2"><?php echo $stats['total_payroll_records']; ?></div>
            </div>
        </div>
    </div>
    
    <!-- Total Amount Paid -->
    <div class="col-md-3 mb-4">
        <div class="card">
            <div class="card-body">
                <div class="text-muted small text-uppercase font-weight-bold">Total Amount Paid</div>
                <div class="h3 amount mt-2 text-accent" style="color: var(--color-accent);">
                    ₱<?php echo number_format($stats['total_amount_paid'], 2); ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Last Payroll Date -->
    <div class="col-md-3 mb-4">
        <div class="card">
            <div class="card-body">
                <div class="text-muted small text-uppercase font-weight-bold">Last Payroll Date</div>
                <div class="h6 date mt-3">
                    <?php 
                    echo $stats['last_payroll_date'] 
                        ? date('M d, Y', strtotime($stats['last_payroll_date'])) 
                        : 'N/A'; 
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Department Summary Table -->
<div class="row mt-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                Department Payroll Summary
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Department</th>
                                <th>Employees Paid</th>
                                <th>Total Records</th>
                                <th class="text-right">Total Net Pay</th>
                                <th>Last Paid</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Fetch from view v_department_payroll_summary
                            $dept_stmt = $pdo->query("SELECT * FROM v_department_payroll_summary ORDER BY total_net_pay DESC");
                            while ($row = $dept_stmt->fetch()) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($row['dept_name']) . "</td>";
                                echo "<td class='numeric'>" . $row['employees_paid'] . "</td>";
                                echo "<td class='numeric'>" . $row['total_records'] . "</td>";
                                echo "<td class='amount text-right'>₱" . number_format($row['total_net_pay'], 2) . "</td>";
                                echo "<td class='date'>" . ($row['last_payroll_date'] ? date('M d, Y', strtotime($row['last_payroll_date'])) : 'N/A') . "</td>";
                                echo "</tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
// Payroll History — Lists all records with JOIN on employees.
// Filters by department and year.
require_once __DIR__ . '/../../config/db.php';

$page_title = 'Payroll History';
$current_page = 'history';

// Fetch filters from URL
$dept_id = (int)($_GET['dept_id'] ?? 0);
$year    = (int)($_GET['year'] ?? 0);

// Fetch departments for filter dropdown
$dept_stmt = $pdo->query("SELECT * FROM departments ORDER BY dept_name");
$departments = $dept_stmt->fetchAll();

// Build query
$sql = "
    SELECT 
        pr.record_id,
        CONCAT(e.last_name, ', ', e.first_name) AS full_name,
        d.dept_name,
        pr.period_month,
        pr.period_year,
        pr.net_pay,
        pr.processed_at
    FROM payroll_records pr
    INNER JOIN employees e ON pr.emp_id = e.emp_id
    INNER JOIN positions p ON e.pos_id = p.pos_id
    INNER JOIN departments d ON p.dept_id = d.dept_id
    WHERE 1=1
";

$params = [];

if ($dept_id > 0) {
    $sql .= " AND d.dept_id = ?";
    $params[] = $dept_id;
}

if ($year > 0) {
    $sql .= " AND pr.period_year = ?";
    $params[] = $year;
}

$sql .= " ORDER BY pr.processed_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$history = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h4">Payroll History</h1>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3 align-items-center">
            <div class="col-auto">
                <label for="dept_id" class="form-label mb-0">Department</label>
            </div>
            <div class="col-auto">
                <select class="form-control" id="dept_id" name="dept_id">
                    <option value="0">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo $dept['dept_id']; ?>" <?php echo ($dept_id == $dept['dept_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept['dept_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-auto">
                <label for="year" class="form-label mb-0">Year</label>
            </div>
            <div class="col-auto">
                <select class="form-control" id="year" name="year">
                    <option value="0">All Years</option>
                    <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo ($year == $y) ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div class="col-auto">
                <button type="submit" class="btn btn-outline">
                    <i class="fa-solid fa-filter me-1"></i>Filter
                </button>
                <a href="/payroll_management_IM/pages/payroll/history.php" class="btn btn-outline text-muted">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- History Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Period</th>
                        <th>Employee</th>
                        <th>Department</th>
                        <th class="text-right">Net Pay</th>
                        <th>Processed At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">No records found.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($history as $row): ?>
                        <tr>
                            <td class="numeric">
                                <?php echo date('F', mktime(0, 0, 0, $row['period_month'], 1)) . ' ' . $row['period_year']; ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['dept_name']); ?></td>
                            <td class="amount text-right">₱<?php echo number_format($row['net_pay'], 2); ?></td>
                            <td class="date"><?php echo date('M d, Y h:i A', strtotime($row['processed_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

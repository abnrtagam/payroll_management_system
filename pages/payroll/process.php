<?php
// pages/payroll/process.php
// ============================================================
// Process Payroll — Wraps the insertion of payroll records
// and items in a database transaction with concurrency check.
// ============================================================
require_once __DIR__ . '/../../config/db.php';

$page_title = 'Process Payroll';
$current_page = 'process';
$errors = [];
$success = '';

// ── Handle Form Submission ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $month = (int)($_POST['month'] ?? 0);
    $year  = (int)($_POST['year'] ?? 0);

    if ($month < 1 || $month > 12) $errors[] = 'Invalid month.';
    if ($year < 2000 || $year > 2099) $errors[] = 'Invalid year.';

    if (empty($errors)) {
        try {
            // 1. Fetch all active employees
            $stmt = $pdo->query("
                SELECT e.emp_id, e.version, p.base_salary 
                FROM employees e
                INNER JOIN positions p ON e.pos_id = p.pos_id
                WHERE e.status = 'active'
            ");
            $employees = $stmt->fetchAll();

            if (empty($employees)) {
                $errors[] = 'No active employees found to process.';
            } else {
                /*
                   Concurrency Control and Transaction Logic:
                   We use a transaction to ensure that either all payroll records are created
                   or none are. We also check the version of each employee to ensure
                   no one modified the employee record while we are processing.
                */
                $pdo->beginTransaction();
                $processed_count = 0;

                foreach ($employees as $emp) {
                    $emp_id = $emp['emp_id'];
                    $current_version = $emp['version'];
                    $basic_pay = $emp['base_salary'];
                    
                    // Simple calculation for demo: 15% deductions
                    $deductions = $basic_pay * 0.15;
                    $net_pay = $basic_pay - $deductions;

                    // A. Check for existing record for this period (prevent duplicates)
                    $check = $pdo->prepare("
                        SELECT COUNT(*) FROM payroll_records 
                        WHERE emp_id = ? AND period_month = ? AND period_year = ?
                    ");
                    $check->execute([$emp_id, $month, $year]);
                    
                    if ($check->fetchColumn() > 0) {
                        // Skip if already processed for this employee
                        continue;
                    }

                    // B. Update employee version (Concurrency Check)
                    $update_version = $pdo->prepare("
                        UPDATE employees 
                        SET version = version + 1 
                        WHERE emp_id = ? AND version = ?
                    ");
                    $update_version->execute([$emp_id, $current_version]);

                    if ($update_version->rowCount() === 0) {
                        // Concurrency conflict detected! Rollback and abort.
                        $pdo->rollBack();
                        throw new Exception("Concurrency Conflict: Employee ID {$emp_id} was modified by another process. Payroll aborted.");
                    }

                    // C. Insert payroll record
                    $ins_record = $pdo->prepare("
                        INSERT INTO payroll_records (emp_id, period_month, period_year, basic_pay, total_deductions, net_pay)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $ins_record->execute([$emp_id, $month, $year, $basic_pay, $deductions, $net_pay]);
                    $record_id = $pdo->lastInsertId();

                    // D. Insert payroll items (Earning)
                    $ins_item = $pdo->prepare("
                        INSERT INTO payroll_items (record_id, item_type, description, amount)
                        VALUES (?, 'earning', 'Basic Salary', ?)
                    ");
                    $ins_item->execute([$record_id, $basic_pay]);

                    // E. Insert payroll items (Deductions)
                    $ins_item->execute([$record_id, $basic_pay * 0.10]); // Tax
                    $pdo->prepare("UPDATE payroll_items SET item_type = 'deduction', description = 'Tax' WHERE item_id = ?")->execute([$pdo->lastInsertId()]);
                    
                    $processed_count++;
                }

                if ($processed_count > 0) {
                    $pdo->commit();
                    $success = "Successfully processed payroll for {$processed_count} employees.";
                } else {
                    $pdo->rollBack();
                    $errors[] = 'All employees have already been paid for this period.';
                }
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h4">Process Payroll</h1>
</div>

<!-- Error Messages -->
<?php if (!empty($errors)): ?>
    <div class="alert" style="background-color: #FDEDEC; border: 1px solid #E6B0AA; color: var(--color-destructive); padding: 12px 16px; border-radius: 4px; margin-bottom: 16px;">
        <ul style="margin: 0; padding-left: 18px;">
            <?php foreach ($errors as $err): ?>
                <li><?php echo htmlspecialchars($err); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- Success Message -->
<?php if ($success): ?>
    <div class="alert" style="background-color: #E8F8F5; border: 1px solid #A3E4D7; color: #117864; padding: 12px 16px; border-radius: 4px; margin-bottom: 16px;">
        <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

<!-- Process Form -->
<div class="card">
    <div class="card-header">Select Period</div>
    <div class="card-body">
        <form method="POST" action="">
            <div class="row g-3 align-items-center">
                <div class="col-auto">
                    <label for="month" class="form-label mb-0">Month</label>
                </div>
                <div class="col-auto">
                    <select class="form-control" id="month" name="month" required>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo (date('n') == $m) ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="col-auto">
                    <label for="year" class="form-label mb-0">Year</label>
                </div>
                <div class="col-auto">
                    <select class="form-control" id="year" name="year" required>
                        <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                            <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-gears me-1"></i>Run Payroll
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

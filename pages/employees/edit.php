<?php
// pages/employees/edit.php
// ============================================================
// Edit Employee — Uses optimistic concurrency control.
// When updating, we check if the `version` still matches the
// value we read when the form was loaded. If it doesn't, it
// means another process modified the record.
// ============================================================
require_once __DIR__ . '/../../config/db.php';

$page_title = 'Edit Employee';
$current_page = 'employees';
$errors = [];
$success = '';

$emp_id = (int)($_GET['id'] ?? 0);

if ($emp_id <= 0) {
    header('Location: /payroll_management_IM/pages/employees/index.php');
    exit;
}

// Fetch positions for the dropdown
$pos_stmt = $pdo->query("
    SELECT p.pos_id, p.pos_title, p.base_salary, d.dept_name
    FROM positions p
    INNER JOIN departments d ON p.dept_id = d.dept_id
    ORDER BY d.dept_name, p.pos_title
");
$positions = $pos_stmt->fetchAll();

// Fetch current employee data
$stmt = $pdo->prepare("SELECT * FROM employees WHERE emp_id = ?");
$stmt->execute([$emp_id]);
$employee = $stmt->fetch();

if (!$employee) {
    die("Employee not found.");
}

// ── Handle form submission ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $pos_id     = (int)($_POST['pos_id'] ?? 0);
    $hire_date  = trim($_POST['hire_date'] ?? '');
    $status     = trim($_POST['status'] ?? 'active');
    $form_version = (int)($_POST['version'] ?? 0); // Version when form was loaded

    // Validate
    if ($first_name === '') $errors[] = 'First name is required.';
    if ($last_name === '')  $errors[] = 'Last name is required.';
    if ($email === '')      $errors[] = 'Email is required.';
    if ($pos_id <= 0)       $errors[] = 'Please select a position.';
    if ($hire_date === '')  $errors[] = 'Hire date is required.';

    // Check for duplicate email (excluding this employee)
    if (empty($errors)) {
        $check = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE email = ? AND emp_id != ?");
        $check->execute([$email, $emp_id]);
        if ($check->fetchColumn() > 0) {
            $errors[] = 'An employee with this email already exists.';
        }
    }

    // Update if no errors
    if (empty($errors)) {
        try {
            /* 
               Optimistic Concurrency Control Check:
               We only update if the version in the database matches the version
               we loaded into the form. If it matches, we increment the version.
               If it doesn't match (0 rows affected), it means another user
               updated the record in the meantime.
            */
            $update = $pdo->prepare("
                UPDATE employees 
                SET pos_id = ?, first_name = ?, last_name = ?, email = ?, hire_date = ?, status = ?, version = version + 1
                WHERE emp_id = ? AND version = ?
            ");
            
            $update->execute([$pos_id, $first_name, $last_name, $email, $hire_date, $status, $emp_id, $form_version]);

            if ($update->rowCount() === 0) {
                $errors[] = 'Concurrency Conflict: This record has been modified by another process. Please reload the page and try again.';
            } else {
                $success = 'Employee updated successfully.';
                // Refresh employee data
                $stmt->execute([$emp_id]);
                $employee = $stmt->fetch();
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h4">Edit Employee</h1>
    <a href="/payroll_management_IM/pages/employees/index.php" class="btn btn-outline btn-sm">
        <i class="fa-solid fa-arrow-left me-1"></i>Back to List
    </a>
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

<!-- Edit Employee Form -->
<div class="card">
    <div class="card-header">Employee Information</div>
    <div class="card-body">
        <form method="POST" action="">
            <!-- Hidden field for version concurrency check -->
            <input type="hidden" name="version" value="<?php echo $employee['version']; ?>">

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="first_name" class="form-label">First Name</label>
                    <input type="text" class="form-control" id="first_name" name="first_name"
                           value="<?php echo htmlspecialchars($employee['first_name']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="last_name" class="form-label">Last Name</label>
                    <input type="text" class="form-control" id="last_name" name="last_name"
                           value="<?php echo htmlspecialchars($employee['last_name']); ?>" required>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email"
                           value="<?php echo htmlspecialchars($employee['email']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="hire_date" class="form-label">Hire Date</label>
                    <input type="date" class="form-control" id="hire_date" name="hire_date"
                           value="<?php echo htmlspecialchars($employee['hire_date']); ?>" required>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="pos_id" class="form-label">Position</label>
                    <select class="form-control" id="pos_id" name="pos_id" required>
                        <?php foreach ($positions as $pos): ?>
                            <option value="<?php echo $pos['pos_id']; ?>"
                                <?php echo ($employee['pos_id'] == $pos['pos_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($pos['dept_name'] . ' — ' . $pos['pos_title'] . ' (₱' . number_format($pos['base_salary'], 2) . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-control" id="status" name="status">
                        <option value="active" <?php echo ($employee['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo ($employee['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        <option value="terminated" <?php echo ($employee['status'] === 'terminated') ? 'selected' : ''; ?>>Terminated</option>
                    </select>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk me-1"></i>Update Employee
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

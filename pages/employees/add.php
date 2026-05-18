<?php
// pages/employees/add.php
// ============================================================
// Add Employee — Uses PDO prepared statement to insert a new
// employee into the database. Fetches positions (with their
// department) to populate the dropdown.
// ============================================================
require_once __DIR__ . '/../../config/db.php';

$page_title = 'Add Employee';
$current_page = 'employees';
$errors = [];
$success = '';

// Fetch positions with department names for the dropdown
$pos_stmt = $pdo->query("
    SELECT p.pos_id, p.pos_title, p.base_salary, d.dept_name
    FROM positions p
    INNER JOIN departments d ON p.dept_id = d.dept_id
    ORDER BY d.dept_name, p.pos_title
");
$positions = $pos_stmt->fetchAll();

// ── Handle form submission ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $pos_id     = (int)($_POST['pos_id'] ?? 0);
    $hire_date  = trim($_POST['hire_date'] ?? '');
    $status     = trim($_POST['status'] ?? 'active');

    // Validate
    if ($first_name === '') $errors[] = 'First name is required.';
    if ($last_name === '')  $errors[] = 'Last name is required.';
    if ($email === '')      $errors[] = 'Email is required.';
    if ($pos_id <= 0)       $errors[] = 'Please select a position.';
    if ($hire_date === '')  $errors[] = 'Hire date is required.';

    // Check for duplicate email (using prepared statement)
    if (empty($errors)) {
        $check = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetchColumn() > 0) {
            $errors[] = 'An employee with this email already exists.';
        }
    }

    // Insert if no errors (using PDO prepared statement)
    if (empty($errors)) {
        try {
            $insert = $pdo->prepare("
                INSERT INTO employees (pos_id, first_name, last_name, email, hire_date, status)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $insert->execute([$pos_id, $first_name, $last_name, $email, $hire_date, $status]);
            $success = 'Employee added successfully.';

            // Clear form values after success
            $first_name = $last_name = $email = $hire_date = '';
            $pos_id = 0;
            $status = 'active';
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h4">Add Employee</h1>
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

<!-- Add Employee Form -->
<div class="card">
    <div class="card-header">Employee Information</div>
    <div class="card-body">
        <form method="POST" action="">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="first_name" class="form-label">First Name</label>
                    <input type="text" class="form-control" id="first_name" name="first_name"
                           value="<?php echo htmlspecialchars($first_name ?? ''); ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="last_name" class="form-label">Last Name</label>
                    <input type="text" class="form-control" id="last_name" name="last_name"
                           value="<?php echo htmlspecialchars($last_name ?? ''); ?>" required>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email"
                           value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="hire_date" class="form-label">Hire Date</label>
                    <input type="date" class="form-control" id="hire_date" name="hire_date"
                           value="<?php echo htmlspecialchars($hire_date ?? ''); ?>" required>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="pos_id" class="form-label">Position</label>
                    <select class="form-control" id="pos_id" name="pos_id" required>
                        <option value="">— Select Position —</option>
                        <?php foreach ($positions as $pos): ?>
                            <option value="<?php echo $pos['pos_id']; ?>"
                                <?php echo (isset($pos_id) && $pos_id == $pos['pos_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($pos['dept_name'] . ' — ' . $pos['pos_title'] . ' (₱' . number_format($pos['base_salary'], 2) . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-control" id="status" name="status">
                        <option value="active" <?php echo (isset($status) && $status === 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo (isset($status) && $status === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        <option value="terminated" <?php echo (isset($status) && $status === 'terminated') ? 'selected' : ''; ?>>Terminated</option>
                    </select>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk me-1"></i>Save Employee
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

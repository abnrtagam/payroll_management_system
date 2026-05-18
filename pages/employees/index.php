<?php
// pages/employees/index.php
require_once __DIR__ . '/../../config/db.php';

$page_title = 'Employees';
$current_page = 'employees';

// Fetch all employees using the view v_employee_full
try {
    $stmt = $pdo->query("SELECT * FROM v_employee_full ORDER BY emp_id ASC");
    $employees = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error fetching employees: " . $e->getMessage());
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h4">Employee Management</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="/payroll_management_IM/pages/employees/add.php" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-user-plus me-2"></i>Add Employee
        </a>
    </div>
</div>

<!-- Employees Table -->
<div class="card">
    <div class="card-header">
        Active Employees
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Department</th>
                        <th>Position</th>
                        <th class="text-right">Base Salary</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $emp): ?>
                        <tr>
                            <td class="numeric"><?php echo $emp['emp_id']; ?></td>
                            <td><?php echo htmlspecialchars($emp['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($emp['email']); ?></td>
                            <td><?php echo htmlspecialchars($emp['dept_name']); ?></td>
                            <td><?php echo htmlspecialchars($emp['pos_title']); ?></td>
                            <td class="amount text-right">₱<?php echo number_format($emp['base_salary'], 2); ?></td>
                            <td>
                                <?php if ($emp['status'] === 'active'): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php elseif ($emp['status'] === 'inactive'): ?>
                                    <span class="badge badge-warning">Inactive</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Terminated</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="/payroll_management_IM/pages/employees/edit.php?id=<?php echo $emp['emp_id']; ?>" class="btn btn-outline btn-sm me-1">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </a>
                                <a href="/payroll_management_IM/pages/employees/delete.php?id=<?php echo $emp['emp_id']; ?>" class="btn btn-outline btn-sm text-danger" onclick="return confirm('Are you sure you want to delete this employee?');">
                                    <i class="fa-solid fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

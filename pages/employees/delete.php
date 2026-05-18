<?php
// pages/employees/delete.php
// ============================================================
// Delete Employee — Checks for referential integrity.
// If the employee has payroll records, deletion will fail due
// to the foreign key constraint (ON DELETE RESTRICT).
// ============================================================
require_once __DIR__ . '/../../config/db.php';

$emp_id = (int)($_GET['id'] ?? 0);

if ($emp_id <= 0) {
    header('Location: /payroll_management_IM/pages/employees/index.php');
    exit;
}

try {
    // Attempt to delete the employee
    $stmt = $pdo->prepare("DELETE FROM employees WHERE emp_id = ?");
    $stmt->execute([$emp_id]);
    
    // If successful, redirect with success message
    header('Location: /payroll_management_IM/pages/employees/index.php?success=Employee deleted successfully.');
    exit;
} catch (PDOException $e) {
    // Check if error is due to foreign key constraint (SQLSTATE 23000)
    if ($e->getCode() === '23000') {
        $error = 'Cannot delete employee because they have payroll records. Set status to inactive instead.';
    } else {
        $error = 'Database error: ' . $e->getMessage();
    }
    
    // Redirect back with error message
    header('Location: /payroll_management_IM/pages/employees/index.php?error=' . urlencode($error));
    exit;
}

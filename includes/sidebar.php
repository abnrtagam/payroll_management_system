<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-brand">
        <i class="fa-solid fa-scale-balanced me-2"></i>PAYROLL SYS
    </div>
    <div class="sidebar-nav">
        <a href="/payroll_management_IM/pages/dashboard.php" class="nav-link <?php echo ($current_page === 'dashboard') ? 'active' : ''; ?>">
            <i class="fa-solid fa-chart-pie"></i>Dashboard
        </a>
        <a href="/payroll_management_IM/pages/employees/index.php" class="nav-link <?php echo ($current_page === 'employees') ? 'active' : ''; ?>">
            <i class="fa-solid fa-users"></i>Employees
        </a>
        <a href="/payroll_management_IM/pages/payroll/process.php" class="nav-link <?php echo ($current_page === 'process') ? 'active' : ''; ?>">
            <i class="fa-solid fa-calculator"></i>Process Payroll
        </a>
        <a href="/payroll_management_IM/pages/payroll/history.php" class="nav-link <?php echo ($current_page === 'history') ? 'active' : ''; ?>">
            <i class="fa-solid fa-clock-rotate-left"></i>Payroll History
        </a>
        <a href="/payroll_management_IM/pages/warehouse/index.php" class="nav-link <?php echo ($current_page === 'warehouse') ? 'active' : ''; ?>">
            <i class="fa-solid fa-database"></i>Data Warehouse
        </a>
    </div>
    <div class="p-3 text-muted" style="font-size: 0.8rem; border-top: 1px solid rgba(255,255,255,0.05);">
        <small>IT221 — IM Project</small>
    </div>
</div>

<!-- Main Content Wrapper (starts here, ends in footer) -->
<div class="main-content flex-grow-1">

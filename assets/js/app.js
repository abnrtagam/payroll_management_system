// assets/js/app.js
// Custom JavaScript for Payroll Management System

document.addEventListener('DOMContentLoaded', function() {
    console.log('Payroll System UI initialized.');
    
    // Auto-dismiss alerts after 5 seconds if they exist
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
});

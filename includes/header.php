<nav style="background: #2c3e50; padding: 15px; color: white; display: flex; justify-content: space-between; align-items: center;">
    <div class="logo">
        <strong>SPMS</strong> | Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>
    </div>
    
    <div class="nav-links">
        <?php if($_SESSION['role'] == 'student'): ?>
            <a href="../dashboards/student.php" style="color: white; margin-right: 15px; text-decoration: none;">My Dashboard</a>
        
        <?php elseif($_SESSION['role'] == 'coordinator'): ?>
            <a href="../dashboards/coordinator.php" style="color: white; margin-right: 15px; text-decoration: none;">Coordinator Panel</a>
            <a href="../dashboards/manage_allocations.php" style="color: white; margin-right: 15px; text-decoration: none;">Allocations</a>
        
        <?php elseif($_SESSION['role'] == 'supervisor'): ?>
            <a href="../dashboards/supervisor.php" style="color: white; margin-right: 15px; text-decoration: none;">My Students</a>
        <?php endif; ?>

        <a href="../actions/logout.php" style="background: #e74c3c; color: white; padding: 5px 12px; border-radius: 4px; text-decoration: none;">Logout</a>
    </div>
</nav>
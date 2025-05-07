<nav id="sidebar" class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
    <div class="position-sticky pt-3">
        <div class="sidebar-header text-center">
            <img src="images/logo.jpeg" alt="MeaTrack Logo" class="img-fluid mb-3" style="max-width: 150px;">
            <h4 class="text-white">MeTech</h4>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>" href="index.php">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'inventory.php' ? 'active' : '' ?>" href="inventory.php">
                    <i class="fas fa-boxes me-2"></i>Inventory
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'spoilage.php' ? 'active' : '' ?>" href="spoilage.php">
                    <i class="fas fa-cogs me-2"></i>Spoilage Tracking
                </a>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'monitoring.php' ? 'active' : '' ?>" href="monitoring.php">
                    <i class="fas fa-temperature-low me-2"></i>Condition Monitoring
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'loss-analysis.php' ? 'active' : '' ?>" href="loss-analysis.php">
                    <i class="fas fa-chart-pie me-2"></i>Loss Analysis
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'distribution.php' ? 'active' : '' ?>" href="distribution.php">
                    <i class="fas fa-truck me-2"></i>Distribution
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : '' ?>" href="reports.php">
                    <i class="fas fa-file-alt me-2"></i>Reports
                </a>
            </li>
            <?php if (isAuthenticated() && getCurrentUser($pdo)['role'] === 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : '' ?>" href="users.php">
                        <i class="fas fa-users me-2"></i>User Management
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : '' ?>" href="settings.php">
                        <i class="fas fa-cog me-2"></i>Settings
                    </a>
                </li>
                
            <?php endif; ?>
        </ul>
        
        <div class="mt-4 px-3">
            <div class="text-light small">Logged in as: <?= getCurrentUser($pdo)['name'] ?></div>
            <a href="logout.php" class="btn btn-sm btn-outline-light w-100 mt-2">
                <i class="fas fa-sign-out-alt me-1"></i>Logout
            </a>
        </div>
    </div>
</nav>
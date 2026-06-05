<nav class="navbar navbar-expand-lg top-navbar">
    <div class="container-fluid">
        <button class="sidebar-toggle d-lg-none me-2" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <button class="sidebar-toggle d-none d-lg-inline-flex me-2" onclick="toggleSidebarCollapse()">
            <i class="fas fa-bars"></i>
        </button>
        <div class="navbar-search d-none d-md-flex">
            <i class="fas fa-search search-icon"></i>
            <input type="text" id="globalSearch" class="form-control" placeholder="Search patients, users..." autocomplete="off">
            <div id="searchResults" class="search-results d-none"></div>
        </div>
        <div class="navbar-actions ms-auto">
            <button class="action-btn theme-toggle" onclick="toggleTheme()" title="Toggle Theme">
                <i class="fas fa-moon" id="themeIcon"></i>
            </button>
            <div class="action-btn notification-dropdown" id="notificationBtn">
                <i class="fas fa-bell"></i>
                <span class="notification-badge" id="notifCount">0</span>
                <div class="dropdown-menu-notif" id="notifDropdown">
                    <div class="notif-header">
                        <h6>Notifications</h6>
                        <a href="#" onclick="markAllRead()" class="small">Mark all read</a>
                    </div>
                    <div class="notif-body" id="notifBody">
                        <div class="text-center text-muted py-3">No notifications</div>
                    </div>
                    <div class="notif-footer">
                        <a href="<?= APP_URL ?>/modules/notifications/index.php">View All</a>
                    </div>
                </div>
            </div>
            <div class="dropdown">
                <button class="action-btn dropdown-toggle" data-bs-toggle="dropdown">
                    <?php if (Auth::user()["avatar"]): ?>
                        <img src="<?= APP_URL ?>/assets/uploads/<?= Auth::user()["avatar"] ?>" class="nav-avatar" alt="">
                    <?php else: ?>
                        <div class="nav-avatar-placeholder"><?= strtoupper(substr(Auth::user()["first_name"], 0, 1)) ?></div>
                    <?php endif; ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?= APP_URL ?>/modules/auth/profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                    <li><a class="dropdown-item" href="<?= APP_URL ?>/modules/auth/change-password.php"><i class="fas fa-key me-2"></i>Change Password</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="<?= APP_URL ?>/modules/auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>
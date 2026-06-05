<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="<?= APP_URL ?>/index.php" class="sidebar-brand">
            <div class="brand-icon">
                <i class="fas fa-hospital-alt"></i>
            </div>
            <div class="brand-text">
                <span class="brand-name">HMS</span>
                <span class="brand-sub">Hospital System</span>
            </div>
        </a>
        <button class="sidebar-toggle d-lg-none" onclick="toggleSidebar()">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="sidebar-user">
        <div class="user-avatar">
            <?php if (Auth::user()["avatar"]): ?>
                <img src="<?= APP_URL ?>/assets/uploads/<?= Auth::user()["avatar"] ?>" alt="Avatar">
            <?php else: ?>
                <div class="avatar-placeholder"><?= strtoupper(substr(Auth::user()["first_name"], 0, 1) . substr(Auth::user()["last_name"], 0, 1)) ?></div>
            <?php endif; ?>
        </div>
        <div class="user-info">
            <span class="user-name"><?= Auth::user()["first_name"] . " " . Auth::user()["last_name"] ?></span>
            <span class="user-role"><?= Auth::user()["role_display"] ?></span>
        </div>
    </div>
    <nav class="sidebar-nav">
        <ul class="nav-list">
            <li class="nav-item">
                <a href="<?= APP_URL ?>/index.php" class="nav-link <?= basename($_SERVER["SCRIPT_NAME"]) == "index.php" ? "active" : "" ?>">
                    <i class="fas fa-chart-pie"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <?php if (Auth::isSuperAdmin() || Auth::hasPermission("manage_patients") || Auth::hasPermission("view_patients")): ?>
            <li class="nav-section">Patient Management</li>
            <li class="nav-item">
                <a href="<?= APP_URL ?>/modules/patients/register.php" class="nav-link">
                    <i class="fas fa-user-plus"></i><span>Register Patient</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= APP_URL ?>/modules/patients/list.php" class="nav-link">
                    <i class="fas fa-users"></i><span>All Patients</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= APP_URL ?>/modules/patients/search.php" class="nav-link">
                    <i class="fas fa-search"></i><span>Search Patients</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (Auth::userHasRole("receptionist") || Auth::isSuperAdmin() || Auth::hasPermission("manage_appointments")): ?>
            <li class="nav-section">Reception</li>
            <li class="nav-item">
                <a href="<?= APP_URL ?>/modules/reception/appointments.php" class="nav-link">
                    <i class="fas fa-calendar-check"></i><span>Appointments</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= APP_URL ?>/modules/reception/queue.php" class="nav-link">
                    <i class="fas fa-list-ol"></i><span>Queue Management</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= APP_URL ?>/modules/reception/checkin.php" class="nav-link">
                    <i class="fas fa-sign-in-alt"></i><span>Check-In Patient</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (Auth::userHasRole("doctor") || Auth::isSuperAdmin() || Auth::hasPermission("manage_consultations")): ?>
            <li class="nav-section">Doctor</li>
            <li class="nav-item">
                <a href="<?= APP_URL ?>/modules/doctor/patients.php" class="nav-link">
                    <i class="fas fa-stethoscope"></i><span>My Patients</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= APP_URL ?>/modules/doctor/consultation.php" class="nav-link">
                    <i class="fas fa-notes-medical"></i><span>Consultations</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= APP_URL ?>/modules/doctor/prescriptions.php" class="nav-link">
                    <i class="fas fa-prescription"></i><span>Prescriptions</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= APP_URL ?>/modules/doctor/referrals.php" class="nav-link">
                    <i class="fas fa-share-alt"></i><span>Referrals</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (Auth::userHasRole("lab_technician") || Auth::isSuperAdmin() || Auth::hasPermission("manage_lab_tests")): ?>
            <li class="nav-section">Laboratory</li>
            <li class="nav-item">
                <a href="<?= APP_URL ?>/modules/laboratory/tests.php" class="nav-link">
                    <i class="fas fa-flask"></i><span>Test Requests</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= APP_URL ?>/modules/laboratory/results.php" class="nav-link">
                    <i class="fas fa-file-medical-alt"></i><span>Enter Results</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= APP_URL ?>/modules/laboratory/categories.php" class="nav-link">
                    <i class="fas fa-list"></i><span>Test Categories</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (Auth::userHasRole("pharmacist") || Auth::isSuperAdmin() || Auth::hasPermission("manage_medicines")): ?>
            <li class="nav-section">Pharmacy</li>
            <li class="nav-item">
                <a href="<?= APP_URL ?>/modules/pharmacy/inventory.php" class="nav-link">
                    <i class="fas fa-pills"></i><span>Medicine Inventory</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= APP_URL ?>/modules/pharmacy/dispensing.php" class="nav-link">
                    <i class="fas fa-hand-holding-medical"></i><span>Dispensing</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= APP_URL ?>/modules/pharmacy/purchases.php" class="nav-link">
                    <i class="fas fa-truck-loading"></i><span>Purchases</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= APP_URL ?>/modules/pharmacy/alerts.php" class="nav-link">
                    <i class="fas fa-exclamation-triangle"></i><span>Low Stock Alerts</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (Auth::userHasRole("cashier") || Auth::isSuperAdmin() || Auth::hasPermission("manage_invoices")): ?>
            <li class="nav-section">Billing</li>
            <li class="nav-item">
                <a href="<?= APP_URL ?>/modules/billing/invoices.php" class="nav-link">
                    <i class="fas fa-file-invoice"></i><span>Invoices</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= APP_URL ?>/modules/billing/payments.php" class="nav-link">
                    <i class="fas fa-credit-card"></i><span>Payments</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= APP_URL ?>/modules/billing/receipts.php" class="nav-link">
                    <i class="fas fa-receipt"></i><span>Receipts</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (Auth::userHasRole("nurse") || Auth::isSuperAdmin() || Auth::hasPermission("manage_nursing")): ?>
            <li class="nav-section">Nursing</li>
            <li class="nav-item">
                <a href="<?= APP_URL ?>/modules/nursing/tasks.php" class="nav-link">
                    <i class="fas fa-tasks"></i><span>Nursing Tasks</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= APP_URL ?>/modules/nursing/patients.php" class="nav-link">
                    <i class="fas fa-procedures"></i><span>Assigned Patients</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (Auth::userHasRole("cashier") || Auth::isAdmin() || Auth::hasPermission("manage_admissions")): ?>
            <li class="nav-section">Admissions</li>
            <li class="nav-item">
                <a href="<?= APP_URL ?>/modules/admission/admissions.php" class="nav-link">
                    <i class="fas fa-door-open"></i><span>Admissions</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= APP_URL ?>/modules/admission/wards.php" class="nav-link">
                    <i class="fas fa-building"></i><span>Wards</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= APP_URL ?>/modules/admission/beds.php" class="nav-link">
                    <i class="fas fa-bed"></i><span>Beds</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= APP_URL ?>/modules/admission/discharges.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i><span>Discharges</span>
                </a>
            </li>
            <?php endif; ?>

            <li class="nav-section">Records</li>
            <?php if (Auth::userHasRole("records_officer") || Auth::isSuperAdmin() || Auth::hasPermission("manage_medical_records")): ?>
            <li class="nav-item">
                <a href="<?= APP_URL ?>/modules/records/medical-records.php" class="nav-link">
                    <i class="fas fa-folder-open"></i><span>Medical Records</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (Auth::isSuperAdmin() || Auth::hasPermission("view_reports")): ?>
            <li class="nav-section">Reports</li>
            <li class="nav-item">
                <a href="<?= APP_URL ?>/modules/reports/index.php" class="nav-link">
                    <i class="fas fa-chart-bar"></i><span>Reports</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (Auth::isSuperAdmin() || Auth::isAdmin()): ?>
            <li class="nav-section">Administration</li>
            <li class="nav-item">
                <a href="<?= APP_URL ?>/modules/admin/users.php" class="nav-link">
                    <i class="fas fa-user-cog"></i><span>Manage Users</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= APP_URL ?>/modules/admin/roles.php" class="nav-link">
                    <i class="fas fa-user-tag"></i><span>Roles</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= APP_URL ?>/modules/admin/departments.php" class="nav-link">
                    <i class="fas fa-building"></i><span>Departments</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= APP_URL ?>/modules/admin/settings.php" class="nav-link">
                    <i class="fas fa-cog"></i><span>Settings</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= APP_URL ?>/modules/admin/logs.php" class="nav-link">
                    <i class="fas fa-history"></i><span>Activity Logs</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>
    <div class="sidebar-footer">
        <a href="<?= APP_URL ?>/modules/auth/logout.php" class="nav-link">
            <i class="fas fa-sign-out-alt"></i><span>Logout</span>
        </a>
    </div>
</aside>
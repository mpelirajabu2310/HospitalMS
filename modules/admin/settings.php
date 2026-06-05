<?php
define('PAGE_TITLE', 'Hospital Settings');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
Auth::requireAuth();
if (!Auth::isSuperAdmin()) { redirect('/index.php'); }

$userId = Auth::id();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $settings = [
        'hospital_name' => sanitize($_POST['hospital_name'] ?? ''),
        'hospital_address' => sanitize($_POST['hospital_address'] ?? ''),
        'hospital_phone' => sanitize($_POST['hospital_phone'] ?? ''),
        'hospital_email' => sanitize($_POST['hospital_email'] ?? ''),
        'currency' => sanitize($_POST['currency'] ?? 'TZS'),
        'tax_rate' => floatval($_POST['tax_rate'] ?? 0),
        'default_appointment_duration' => intval($_POST['default_appointment_duration'] ?? 30),
        'timezone' => sanitize($_POST['timezone'] ?? 'Africa/Nairobi'),
        'date_format' => sanitize($_POST['date_format'] ?? 'd M Y'),
    ];

    foreach ($settings as $key => $value) {
        setSetting($key, $value);
    }

    logActivity($userId, 'update_settings', 'admin', 'Updated hospital settings');
    set_flash('success', 'Settings saved successfully.', 'success');
    redirect('/modules/admin/settings.php');
}

include_once __DIR__ . '/../../includes/header.php';
echo display_flash('success');
echo display_flash('error');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-cog me-2 text-primary"></i>Hospital Settings</h4>
</div>

<form method="POST">
    <?= csrf_field() ?>
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="fas fa-hospital me-2 text-primary"></i>Hospital Info</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-medium">Hospital Name</label>
                        <input type="text" name="hospital_name" class="form-control" value="<?= htmlspecialchars(getSetting('hospital_name', '')) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Address</label>
                        <textarea name="hospital_address" class="form-control" rows="2"><?= htmlspecialchars(getSetting('hospital_address', '')) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Phone</label>
                        <input type="text" name="hospital_phone" class="form-control" value="<?= htmlspecialchars(getSetting('hospital_phone', '')) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Email</label>
                        <input type="email" name="hospital_email" class="form-control" value="<?= htmlspecialchars(getSetting('hospital_email', '')) ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="fas fa-dollar-sign me-2 text-primary"></i>Financial</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-medium">Currency</label>
                        <select name="currency" class="form-select">
                            <option value="TZS" <?= getSetting('currency', 'TZS') === 'TZS' ? 'selected' : '' ?>>TZS - Tanzanian Shilling</option>
                            <option value="KES" <?= getSetting('currency', 'TZS') === 'KES' ? 'selected' : '' ?>>KES - Kenyan Shilling</option>
                            <option value="USD" <?= getSetting('currency', 'TZS') === 'USD' ? 'selected' : '' ?>>USD - US Dollar</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Tax Rate (%)</label>
                        <input type="number" name="tax_rate" class="form-control" min="0" max="100" step="0.01" value="<?= htmlspecialchars(getSetting('tax_rate', '0')) ?>">
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="fas fa-calendar-alt me-2 text-primary"></i>Appointments</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-medium">Default Appointment Duration (minutes)</label>
                        <input type="number" name="default_appointment_duration" class="form-control" min="5" max="240" step="5" value="<?= htmlspecialchars(getSetting('default_appointment_duration', '30')) ?>">
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="fas fa-globe me-2 text-primary"></i>Regional</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-medium">Timezone</label>
                        <select name="timezone" class="form-select">
                            <?php $tz = getSetting('timezone', 'Africa/Nairobi'); ?>
                            <optgroup label="Africa">
                                <option value="Africa/Nairobi" <?= $tz === 'Africa/Nairobi' ? 'selected' : '' ?>>Africa/Nairobi (UTC+3)</option>
                                <option value="Africa/Dar_es_Salaam" <?= $tz === 'Africa/Dar_es_Salaam' ? 'selected' : '' ?>>Africa/Dar es Salaam (UTC+3)</option>
                                <option value="Africa/Kampala" <?= $tz === 'Africa/Kampala' ? 'selected' : '' ?>>Africa/Kampala (UTC+3)</option>
                                <option value="Africa/Lagos" <?= $tz === 'Africa/Lagos' ? 'selected' : '' ?>>Africa/Lagos (UTC+1)</option>
                                <option value="Africa/Cairo" <?= $tz === 'Africa/Cairo' ? 'selected' : '' ?>>Africa/Cairo (UTC+2)</option>
                                <option value="Africa/Johannesburg" <?= $tz === 'Africa/Johannesburg' ? 'selected' : '' ?>>Africa/Johannesburg (UTC+2)</option>
                            </optgroup>
                            <optgroup label="Asia">
                                <option value="Asia/Dubai" <?= $tz === 'Asia/Dubai' ? 'selected' : '' ?>>Asia/Dubai (UTC+4)</option>
                                <option value="Asia/Kolkata" <?= $tz === 'Asia/Kolkata' ? 'selected' : '' ?>>Asia/Kolkata (UTC+5:30)</option>
                            </optgroup>
                            <optgroup label="Europe">
                                <option value="Europe/London" <?= $tz === 'Europe/London' ? 'selected' : '' ?>>Europe/London (UTC+0)</option>
                                <option value="Europe/Berlin" <?= $tz === 'Europe/Berlin' ? 'selected' : '' ?>>Europe/Berlin (UTC+1)</option>
                            </optgroup>
                            <optgroup label="Americas">
                                <option value="America/New_York" <?= $tz === 'America/New_York' ? 'selected' : '' ?>>America/New York (UTC-5)</option>
                                <option value="America/Chicago" <?= $tz === 'America/Chicago' ? 'selected' : '' ?>>America/Chicago (UTC-6)</option>
                                <option value="America/Denver" <?= $tz === 'America/Denver' ? 'selected' : '' ?>>America/Denver (UTC-7)</option>
                                <option value="America/Los_Angeles" <?= $tz === 'America/Los_Angeles' ? 'selected' : '' ?>>America/Los Angeles (UTC-8)</option>
                            </optgroup>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Date Format</label>
                        <select name="date_format" class="form-select">
                            <?php $df = getSetting('date_format', 'd M Y'); ?>
                            <option value="d M Y" <?= $df === 'd M Y' ? 'selected' : '' ?>>01 Jan 2024</option>
                            <option value="Y-m-d" <?= $df === 'Y-m-d' ? 'selected' : '' ?>>2024-01-01</option>
                            <option value="m/d/Y" <?= $df === 'm/d/Y' ? 'selected' : '' ?>>01/01/2024</option>
                            <option value="d/m/Y" <?= $df === 'd/m/Y' ? 'selected' : '' ?>>01/01/2024</option>
                            <option value="F j, Y" <?= $df === 'F j, Y' ? 'selected' : '' ?>>January 1, 2024</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4 mb-4">
        <button type="submit" class="btn btn-primary btn-lg">
            <i class="fas fa-save me-1"></i> Save All Settings
        </button>
    </div>
</form>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>

<?php
define('PAGE_TITLE', 'Register Patient');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
Auth::requireAuth();

$userId = Auth::id();
$defaultCountryId = getSetting('default_country_id', '');
$defaultCurrency = getSetting('currency', 'TZS');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $firstName = sanitize($_POST['first_name'] ?? '');
    $lastName = sanitize($_POST['last_name'] ?? '');
    $middleName = sanitize($_POST['middle_name'] ?? '');
    $dob = sanitize($_POST['date_of_birth'] ?? '');
    $gender = sanitize($_POST['gender'] ?? '');
    $bloodGroup = sanitize($_POST['blood_group'] ?? 'unknown');
    $maritalStatus = sanitize($_POST['marital_status'] ?? 'single');
    $phone = sanitize($_POST['phone'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $addressLine1 = sanitize($_POST['address_line1'] ?? '');
    $countryId = intval($_POST['country_id'] ?? 0) ?: null;
    $regionId = intval($_POST['region_id'] ?? 0) ?: null;
    $districtId = intval($_POST['district_id'] ?? 0) ?: null;
    $wardId = intval($_POST['ward_id'] ?? 0) ?: null;
    $villageId = intval($_POST['village_id'] ?? 0) ?: null;
    $idNumber = sanitize($_POST['id_number'] ?? '');
    $idType = sanitize($_POST['id_type'] ?? 'National ID');
    $nationality = sanitize($_POST['nationality'] ?? '');
    $occupation = sanitize($_POST['occupation'] ?? '');
    $emergencyName = sanitize($_POST['emergency_contact_name'] ?? '');
    $emergencyPhone = sanitize($_POST['emergency_contact_phone'] ?? '');
    $emergencyRelation = sanitize($_POST['emergency_contact_relation'] ?? '');

    $countryName = '';
    if ($countryId) {
        $c = Database::fetch("SELECT name FROM countries WHERE id = ?", [$countryId]);
        $countryName = $c ? $c['name'] : '';
    }

    $errors = [];
    if (empty($firstName)) $errors[] = 'First name is required.';
    if (empty($lastName)) $errors[] = 'Last name is required.';
    if (empty($dob)) $errors[] = 'Date of birth is required.';
    if (empty($gender)) $errors[] = 'Gender is required.';
    if (empty($phone)) $errors[] = 'Phone number is required.';
    if (!empty($email) && !validateEmail($email)) $errors[] = 'Invalid email address.';

    // Validate location hierarchy
    if ($regionId && $countryId) {
        $r = Database::fetch("SELECT id FROM regions WHERE id = ? AND country_id = ?", [$regionId, $countryId]);
        if (!$r) $errors[] = 'Selected region does not belong to the selected country.';
    }
    if ($districtId && $regionId) {
        $d = Database::fetch("SELECT id FROM districts WHERE id = ? AND region_id = ?", [$districtId, $regionId]);
        if (!$d) $errors[] = 'Selected district does not belong to the selected region.';
    }
    if ($wardId && $districtId) {
        $w = Database::fetch("SELECT id FROM wards WHERE id = ? AND district_id = ?", [$wardId, $districtId]);
        if (!$w) $errors[] = 'Selected ward does not belong to the selected district.';
    }
    if ($villageId && $wardId) {
        $v = Database::fetch("SELECT id FROM villages WHERE id = ? AND ward_id = ?", [$villageId, $wardId]);
        if (!$v) $errors[] = 'Selected village does not belong to the selected ward.';
    }

    if (empty($errors)) {
        $patientNumber = generatePatientNumber();
        $photoPath = null;
        if (!empty($_FILES['photo']['name'])) {
            $photoPath = uploadFile($_FILES['photo'], 'patients');
        }

        $patientId = Database::insert(
            "INSERT INTO patients (patient_number, first_name, last_name, middle_name, date_of_birth, gender, blood_group, marital_status, phone, email, address_line1, country, city, state, country_id, region_id, district_id, ward_id, village_id, id_number, id_type, nationality, occupation, emergency_contact_name, emergency_contact_phone, emergency_contact_relation, photo, registration_date, registered_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$patientNumber, $firstName, $lastName, $middleName, $dob, $gender, $bloodGroup, $maritalStatus, $phone, $email ?: null, $addressLine1, $countryName, '', '', $countryId, $regionId, $districtId, $wardId, $villageId, $idNumber ?: null, $idType, $nationality ?: null, $occupation ?: null, $emergencyName ?: null, $emergencyPhone ?: null, $emergencyRelation ?: null, $photoPath, date('Y-m-d'), $userId]
        );

        logActivity($userId, 'create_patient', 'patients', "Registered patient: $firstName $lastName ($patientNumber)");
        auditLog($userId, 'create', 'patients', $patientId, null, ['patient_number' => $patientNumber, 'first_name' => $firstName, 'last_name' => $lastName]);
        set_flash('success', 'Patient registered successfully.', 'success');
        redirect('/modules/patients/profile.php?id=' . $patientId);
    } else {
        set_flash('error', implode(' ', $errors), 'error');
    }
}

include_once __DIR__ . '/../../includes/header.php';
echo display_flash('success');
echo display_flash('error');
$countries = Database::fetchAll("SELECT id, name FROM countries WHERE status = 'active' ORDER BY name");
?>
<style>
.select2-container--default .select2-selection--single { height: 38px; border: 2px solid var(--border-color); border-radius: 6px; }
.select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 34px; padding-left: 10px; }
.select2-container--default .select2-selection--single .select2-selection__arrow { height: 34px; }
</style>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-user-plus me-2 text-primary"></i>Register Patient</h4>
    <a href="<?= APP_URL ?>/modules/patients/list.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Back to List</a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="row g-3">
                <h6 class="text-primary border-bottom pb-2"><i class="fas fa-user me-2"></i>Personal Information</h6>
                <div class="col-md-4">
                    <label class="form-label fw-medium">First Name <span class="text-danger">*</span></label>
                    <input type="text" name="first_name" class="form-control" required value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium">Middle Name</label>
                    <input type="text" name="middle_name" class="form-control" value="<?= htmlspecialchars($_POST['middle_name'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium">Last Name <span class="text-danger">*</span></label>
                    <input type="text" name="last_name" class="form-control" required value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-medium">Date of Birth <span class="text-danger">*</span></label>
                    <input type="date" name="date_of_birth" class="form-control" required value="<?= htmlspecialchars($_POST['date_of_birth'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-medium">Gender <span class="text-danger">*</span></label>
                    <select name="gender" class="form-select" required>
                        <option value="">Select</option>
                        <option value="male" <?= ($_POST['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Male</option>
                        <option value="female" <?= ($_POST['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                        <option value="other" <?= ($_POST['gender'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-medium">Blood Group</label>
                    <select name="blood_group" class="form-select">
                        <option value="unknown" <?= ($_POST['blood_group'] ?? 'unknown') === 'unknown' ? 'selected' : '' ?>>Unknown</option>
                        <option value="A+" <?= ($_POST['blood_group'] ?? '') === 'A+' ? 'selected' : '' ?>>A+</option>
                        <option value="A-" <?= ($_POST['blood_group'] ?? '') === 'A-' ? 'selected' : '' ?>>A-</option>
                        <option value="B+" <?= ($_POST['blood_group'] ?? '') === 'B+' ? 'selected' : '' ?>>B+</option>
                        <option value="B-" <?= ($_POST['blood_group'] ?? '') === 'B-' ? 'selected' : '' ?>>B-</option>
                        <option value="AB+" <?= ($_POST['blood_group'] ?? '') === 'AB+' ? 'selected' : '' ?>>AB+</option>
                        <option value="AB-" <?= ($_POST['blood_group'] ?? '') === 'AB-' ? 'selected' : '' ?>>AB-</option>
                        <option value="O+" <?= ($_POST['blood_group'] ?? '') === 'O+' ? 'selected' : '' ?>>O+</option>
                        <option value="O-" <?= ($_POST['blood_group'] ?? '') === 'O-' ? 'selected' : '' ?>>O-</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-medium">Marital Status</label>
                    <select name="marital_status" class="form-select">
                        <option value="single" <?= ($_POST['marital_status'] ?? 'single') === 'single' ? 'selected' : '' ?>>Single</option>
                        <option value="married" <?= ($_POST['marital_status'] ?? '') === 'married' ? 'selected' : '' ?>>Married</option>
                        <option value="divorced" <?= ($_POST['marital_status'] ?? '') === 'divorced' ? 'selected' : '' ?>>Divorced</option>
                        <option value="widowed" <?= ($_POST['marital_status'] ?? '') === 'widowed' ? 'selected' : '' ?>>Widowed</option>
                    </select>
                </div>

                <h6 class="text-primary border-bottom pb-2 mt-2"><i class="fas fa-address-book me-2"></i>Contact Information</h6>
                <div class="col-md-4">
                    <label class="form-label fw-medium">Phone <span class="text-danger">*</span></label>
                    <input type="text" name="phone" class="form-control" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium">Photo</label>
                    <input type="file" name="photo" class="form-control" accept="image/*">
                </div>
                <div class="col-md-12">
                    <label class="form-label fw-medium">Street/Address Line 1</label>
                    <input type="text" name="address_line1" class="form-control" placeholder="e.g. Plot 123, Mtaa wa Kati" value="<?= htmlspecialchars($_POST['address_line1'] ?? '') ?>">
                </div>

                <h6 class="text-primary border-bottom pb-2 mt-2"><i class="fas fa-map-marker-alt me-2"></i>Location <small class="text-muted">(searchable dropdowns)</small></h6>
                <div class="col-md-4">
                    <label class="form-label fw-medium">Country</label>
                    <select name="country_id" id="countrySelect" class="form-select" onchange="loadRegions()">
                        <option value="">Select Country</option>
                        <?php foreach ($countries as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= (intval($_POST['country_id'] ?? $defaultCountryId) === $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium">Region/State</label>
                    <select name="region_id" id="regionSelect" class="form-select" onchange="loadDistricts()" disabled>
                        <option value="">Select Region</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium">District</label>
                    <select name="district_id" id="districtSelect" class="form-select" onchange="loadWards()" disabled>
                        <option value="">Select District</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium">Ward</label>
                    <select name="ward_id" id="wardSelect" class="form-select" onchange="loadVillages()" disabled>
                        <option value="">Select Ward</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium">Village/Street</label>
                    <select name="village_id" id="villageSelect" class="form-select" disabled>
                        <option value="">Select Village</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium">Nationality</label>
                    <input type="text" name="nationality" class="form-control" placeholder="e.g. Tanzanian" value="<?= htmlspecialchars($_POST['nationality'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium">Occupation</label>
                    <input type="text" name="occupation" class="form-control" value="<?= htmlspecialchars($_POST['occupation'] ?? '') ?>">
                </div>

                <h6 class="text-primary border-bottom pb-2 mt-2"><i class="fas fa-id-card me-2"></i>Identification</h6>
                <div class="col-md-6">
                    <label class="form-label fw-medium">ID Number</label>
                    <input type="text" name="id_number" class="form-control" value="<?= htmlspecialchars($_POST['id_number'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-medium">ID Type</label>
                    <select name="id_type" class="form-select">
                        <option value="National ID" <?= ($_POST['id_type'] ?? 'National ID') === 'National ID' ? 'selected' : '' ?>>National ID</option>
                        <option value="Passport" <?= ($_POST['id_type'] ?? '') === 'Passport' ? 'selected' : '' ?>>Passport</option>
                        <option value="Driving License" <?= ($_POST['id_type'] ?? '') === 'Driving License' ? 'selected' : '' ?>>Driving License</option>
                        <option value="Birth Certificate" <?= ($_POST['id_type'] ?? '') === 'Birth Certificate' ? 'selected' : '' ?>>Birth Certificate</option>
                        <option value="Alien Card" <?= ($_POST['id_type'] ?? '') === 'Alien Card' ? 'selected' : '' ?>>Alien Card</option>
                        <option value="Other" <?= ($_POST['id_type'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>

                <h6 class="text-primary border-bottom pb-2 mt-2"><i class="fas fa-phone-alt me-2"></i>Emergency Contact</h6>
                <div class="col-md-4">
                    <label class="form-label fw-medium">Contact Name</label>
                    <input type="text" name="emergency_contact_name" class="form-control" value="<?= htmlspecialchars($_POST['emergency_contact_name'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium">Contact Phone</label>
                    <input type="text" name="emergency_contact_phone" class="form-control" value="<?= htmlspecialchars($_POST['emergency_contact_phone'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium">Relation</label>
                    <input type="text" name="emergency_contact_relation" class="form-control" placeholder="e.g. Spouse, Parent" value="<?= htmlspecialchars($_POST['emergency_contact_relation'] ?? '') ?>">
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Register Patient</button>
                <a href="<?= APP_URL ?>/modules/patients/list.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#countrySelect').select2({ placeholder: 'Search country...', allowClear: true, width: '100%' });
    $('#regionSelect').select2({ placeholder: 'Search region...', allowClear: true, width: '100%' });
    $('#districtSelect').select2({ placeholder: 'Search district...', allowClear: true, width: '100%' });
    $('#wardSelect').select2({ placeholder: 'Search ward...', allowClear: true, width: '100%' });
    $('#villageSelect').select2({ placeholder: 'Search village...', allowClear: true, width: '100%' });

    var countryId = $('#countrySelect').val();
    if (countryId) {
        loadRegions(countryId, function() {
            var regionId = '<?= intval($_POST['region_id'] ?? 0) ?>';
            if (regionId) { $('#regionSelect').val(regionId).trigger('change'); }
        });
    }
});

function loadRegions(countryId, callback) {
    countryId = countryId || $('#countrySelect').val();
    var sel = $('#regionSelect');
    sel.prop('disabled', true).empty().append('<option value="">Loading...</option>');
    $('#districtSelect').empty().append('<option value="">Select District</option>').prop('disabled', true);
    $('#wardSelect').empty().append('<option value="">Select Ward</option>').prop('disabled', true);
    $('#villageSelect').empty().append('<option value="">Select Village</option>').prop('disabled', true);
    if (!countryId) {
        sel.empty().append('<option value="">Select Region</option>').prop('disabled', true);
        if (callback) callback();
        return;
    }
    $.get('<?= APP_URL ?>/api/locations.php?action=regions&country_id=' + countryId, function(data) {
        sel.empty().append('<option value="">Select Region</option>');
        $.each(data, function(i, r) { sel.append('<option value="' + r.id + '">' + r.name + '</option>'); });
        sel.prop('disabled', false);
        if (callback) callback();
    });
}

function loadDistricts(regionId) {
    regionId = regionId || $('#regionSelect').val();
    var sel = $('#districtSelect');
    sel.prop('disabled', true).empty().append('<option value="">Loading...</option>');
    $('#wardSelect').empty().append('<option value="">Select Ward</option>').prop('disabled', true);
    $('#villageSelect').empty().append('<option value="">Select Village</option>').prop('disabled', true);
    if (!regionId) {
        sel.empty().append('<option value="">Select District</option>').prop('disabled', true);
        return;
    }
    $.get('<?= APP_URL ?>/api/locations.php?action=districts&region_id=' + regionId, function(data) {
        sel.empty().append('<option value="">Select District</option>');
        $.each(data, function(i, d) { sel.append('<option value="' + d.id + '">' + d.name + '</option>'); });
        sel.prop('disabled', false);
    });
}

function loadWards(districtId) {
    districtId = districtId || $('#districtSelect').val();
    var sel = $('#wardSelect');
    sel.prop('disabled', true).empty().append('<option value="">Loading...</option>');
    $('#villageSelect').empty().append('<option value="">Select Village</option>').prop('disabled', true);
    if (!districtId) {
        sel.empty().append('<option value="">Select Ward</option>').prop('disabled', true);
        return;
    }
    $.get('<?= APP_URL ?>/api/locations.php?action=wards&district_id=' + districtId, function(data) {
        sel.empty().append('<option value="">Select Ward</option>');
        $.each(data, function(i, w) { sel.append('<option value="' + w.id + '">' + w.name + '</option>'); });
        sel.prop('disabled', false);
    });
}

function loadVillages(wardId) {
    wardId = wardId || $('#wardSelect').val();
    var sel = $('#villageSelect');
    sel.prop('disabled', true).empty().append('<option value="">Loading...</option>');
    if (!wardId) {
        sel.empty().append('<option value="">Select Village</option>').prop('disabled', true);
        return;
    }
    $.get('<?= APP_URL ?>/api/locations.php?action=villages&ward_id=' + wardId, function(data) {
        sel.empty().append('<option value="">Select Village</option>');
        $.each(data, function(i, v) { sel.append('<option value="' + v.id + '">' + v.name + '</option>'); });
        sel.prop('disabled', false);
    });
}
</script>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>

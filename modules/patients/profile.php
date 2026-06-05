<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
Auth::requireAuth();

$patientId = intval($_GET['id'] ?? 0);
$patient = Database::fetch("SELECT * FROM patients WHERE id = ?", [$patientId]);
if (!$patient) {
    set_flash('error', 'Patient not found.', 'error');
    redirect('/modules/patients/list.php');
}

define('PAGE_TITLE', 'Patient Profile - ' . $patient['first_name'] . ' ' . $patient['last_name']);

$userId = Auth::id();
$activeTab = $_GET['tab'] ?? 'info';

$visits = Database::fetchAll("SELECT v.*, u.first_name as doctor_first, u.last_name as doctor_last FROM visits v LEFT JOIN users u ON v.referred_to = u.id WHERE v.patient_id = ? ORDER BY v.created_at DESC LIMIT 20", [$patientId]);
$medicalRecords = Database::fetchAll("SELECT * FROM medical_records WHERE patient_id = ? ORDER BY record_date DESC LIMIT 20", [$patientId]);
$invoices = Database::fetchAll("SELECT * FROM invoices WHERE patient_id = ? ORDER BY created_at DESC LIMIT 20", [$patientId]);
$appointments = Database::fetchAll("SELECT a.*, u.first_name as doctor_first, u.last_name as doctor_last FROM appointments a LEFT JOIN users u ON a.doctor_id = u.id WHERE a.patient_id = ? ORDER BY a.appointment_date DESC LIMIT 20", [$patientId]);

$age = $patient['date_of_birth'] ? date_diff(date_create($patient['date_of_birth']), date_create('today'))->y : 0;

$regionName = $patient['region_id'] ? (Database::fetch("SELECT name FROM regions WHERE id = ?", [$patient['region_id']])['name'] ?? '') : '';
$districtName = $patient['district_id'] ? (Database::fetch("SELECT name FROM districts WHERE id = ?", [$patient['district_id']])['name'] ?? '') : '';
$wardName = $patient['ward_id'] ? (Database::fetch("SELECT name FROM location_wards WHERE id = ?", [$patient['ward_id']])['name'] ?? '') : '';
$villageName = $patient['village_id'] ? (Database::fetch("SELECT name FROM villages WHERE id = ?", [$patient['village_id']])['name'] ?? '') : '';

include_once __DIR__ . '/../../includes/header.php';
echo display_flash('success');
echo display_flash('error');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-0"><i class="fas fa-user me-2 text-primary"></i>Patient Profile</h4>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= APP_URL ?>/modules/reception/checkin.php?patient_id=<?= $patient['id'] ?>" class="btn btn-success btn-sm"><i class="fas fa-plus-circle me-1"></i> New Visit</a>
        <a href="<?= APP_URL ?>/modules/reception/appointments.php?patient_id=<?= $patient['id'] ?>" class="btn btn-info btn-sm text-white"><i class="fas fa-calendar-plus me-1"></i> Book Appointment</a>
        <a href="<?= APP_URL ?>/modules/billing/invoices.php?patient_id=<?= $patient['id'] ?>" class="btn btn-warning btn-sm"><i class="fas fa-file-invoice me-1"></i> Create Invoice</a>
        <a href="<?= APP_URL ?>/modules/admission/admit.php?patient_id=<?= $patient['id'] ?>" class="btn btn-secondary btn-sm"><i class="fas fa-procedures me-1"></i> Admit Patient</a>
        <a href="<?= APP_URL ?>/modules/patients/register.php?id=<?= $patient['id'] ?>" class="btn btn-outline-primary btn-sm"><i class="fas fa-edit me-1"></i> Edit</a>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-auto">
                <?php if ($patient['photo']): ?>
                    <img src="<?= APP_URL ?>/assets/uploads/<?= htmlspecialchars($patient['photo']) ?>" class="rounded-circle" width="80" height="80" style="object-fit:cover">
                <?php else: ?>
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width:80px;height:80px;font-size:32px"><?= strtoupper(substr($patient['first_name'], 0, 1) . substr($patient['last_name'], 0, 1)) ?></div>
                <?php endif; ?>
            </div>
            <div class="col">
                <h5 class="mb-1"><?= htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) ?></h5>
                <div class="d-flex flex-wrap gap-3 text-muted small">
                    <span><i class="fas fa-id-card me-1"></i> <?= htmlspecialchars($patient['patient_number']) ?></span>
                    <span><i class="fas fa-calendar me-1"></i> Age: <?= $age ?> yrs</span>
                    <span><i class="fas fa-venus-mars me-1"></i> <?= ucfirst($patient['gender']) ?></span>
                    <span><?= getStatusBadge($patient['status']) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<ul class="nav nav-tabs mb-4">
    <li class="nav-item"><a class="nav-link <?= $activeTab === 'info' ? 'active' : '' ?>" href="?id=<?= $patient['id'] ?>&tab=info"><i class="fas fa-info-circle me-1"></i>Info</a></li>
    <li class="nav-item"><a class="nav-link <?= $activeTab === 'visits' ? 'active' : '' ?>" href="?id=<?= $patient['id'] ?>&tab=visits"><i class="fas fa-clinic-medical me-1"></i>Visits <span class="badge bg-secondary"><?= count($visits) ?></span></a></li>
    <li class="nav-item"><a class="nav-link <?= $activeTab === 'records' ? 'active' : '' ?>" href="?id=<?= $patient['id'] ?>&tab=records"><i class="fas fa-notes-medical me-1"></i>Medical Records</a></li>
    <li class="nav-item"><a class="nav-link <?= $activeTab === 'billing' ? 'active' : '' ?>" href="?id=<?= $patient['id'] ?>&tab=billing"><i class="fas fa-file-invoice-dollar me-1"></i>Billing</a></li>
    <li class="nav-item"><a class="nav-link <?= $activeTab === 'appointments' ? 'active' : '' ?>" href="?id=<?= $patient['id'] ?>&tab=appointments"><i class="fas fa-calendar-check me-1"></i>Appointments</a></li>
</ul>

<div class="tab-content">
    <?php if ($activeTab === 'info'): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <h6 class="text-primary border-bottom pb-2"><i class="fas fa-user me-2"></i>Personal Information</h6>
                        <table class="table table-sm">
                            <tr><td class="fw-medium text-muted" style="width:160px">Patient Number</td><td><?= htmlspecialchars($patient['patient_number']) ?></td></tr>
                            <tr><td class="fw-medium text-muted">Full Name</td><td><?= htmlspecialchars($patient['first_name'] . ' ' . ($patient['middle_name'] ? $patient['middle_name'] . ' ' : '') . $patient['last_name']) ?></td></tr>
                            <tr><td class="fw-medium text-muted">Date of Birth</td><td><?= formatDate($patient['date_of_birth']) ?> (Age: <?= $age ?>)</td></tr>
                            <tr><td class="fw-medium text-muted">Gender</td><td><?= ucfirst($patient['gender']) ?></td></tr>
                            <tr><td class="fw-medium text-muted">Blood Group</td><td><?= htmlspecialchars($patient['blood_group']) ?></td></tr>
                            <tr><td class="fw-medium text-muted">Marital Status</td><td><?= ucfirst($patient['marital_status']) ?></td></tr>
                            <tr><td class="fw-medium text-muted">Occupation</td><td><?= htmlspecialchars($patient['occupation'] ?? '-') ?></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-primary border-bottom pb-2"><i class="fas fa-address-book me-2"></i>Contact Information</h6>
                        <table class="table table-sm">
                            <tr><td class="fw-medium text-muted" style="width:160px">Phone</td><td><?= htmlspecialchars($patient['phone']) ?></td></tr>
                            <tr><td class="fw-medium text-muted">Email</td><td><?= htmlspecialchars($patient['email'] ?? '-') ?></td></tr>
                            <tr><td class="fw-medium text-muted">Address</td><td><?= htmlspecialchars($patient['address_line1'] ?? '-') ?></td></tr>
                            <tr><td class="fw-medium text-muted">Country</td><td><?= htmlspecialchars($patient['country'] ?? '-') ?></td></tr>
                            <tr><td class="fw-medium text-muted">Region</td><td><?= htmlspecialchars($regionName ?: '-') ?></td></tr>
                            <tr><td class="fw-medium text-muted">District</td><td><?= htmlspecialchars($districtName ?: '-') ?></td></tr>
                            <tr><td class="fw-medium text-muted">Ward</td><td><?= htmlspecialchars($wardName ?: '-') ?></td></tr>
                            <tr><td class="fw-medium text-muted">Village/Street</td><td><?= htmlspecialchars($villageName ?: '-') ?></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-primary border-bottom pb-2"><i class="fas fa-id-card me-2"></i>Identification</h6>
                        <table class="table table-sm">
                            <tr><td class="fw-medium text-muted" style="width:160px">ID Number</td><td><?= htmlspecialchars($patient['id_number'] ?? '-') ?></td></tr>
                            <tr><td class="fw-medium text-muted">ID Type</td><td><?= htmlspecialchars($patient['id_type'] ?? '-') ?></td></tr>
                            <tr><td class="fw-medium text-muted">Nationality</td><td><?= htmlspecialchars($patient['nationality']) ?></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-primary border-bottom pb-2"><i class="fas fa-phone-alt me-2"></i>Emergency Contact</h6>
                        <table class="table table-sm">
                            <tr><td class="fw-medium text-muted" style="width:160px">Name</td><td><?= htmlspecialchars($patient['emergency_contact_name'] ?? '-') ?></td></tr>
                            <tr><td class="fw-medium text-muted">Phone</td><td><?= htmlspecialchars($patient['emergency_contact_phone'] ?? '-') ?></td></tr>
                            <tr><td class="fw-medium text-muted">Relation</td><td><?= htmlspecialchars($patient['emergency_contact_relation'] ?? '-') ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($activeTab === 'visits'): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Visit #</th><th>Date</th><th>Time</th><th>Type</th><th>Status</th><th>Doctor/Dept</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($visits)): ?>
                                <tr><td colspan="6" class="text-center text-muted py-4">No visits found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($visits as $v): ?>
                                    <tr>
                                        <td class="fw-medium"><?= htmlspecialchars($v['visit_number']) ?></td>
                                        <td><?= formatDate($v['visit_date']) ?></td>
                                        <td><?= formatDateTime($v['visit_time'], 'H:i') ?></td>
                                        <td><?= ucfirst($v['type']) ?></td>
                                        <td><?= getStatusBadge($v['status']) ?></td>
                                        <td><?= htmlspecialchars(($v['doctor_first'] ?? '') . ' ' . ($v['doctor_last'] ?? '')) ?: '-' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php elseif ($activeTab === 'records'): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Date</th><th>Type</th><th>Description</th><th>Notes</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($medicalRecords)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-4">No medical records found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($medicalRecords as $mr): ?>
                                    <tr>
                                        <td><?= formatDate($mr['record_date']) ?></td>
                                        <td><?= getStatusBadge($mr['record_type']) ?></td>
                                        <td><?= htmlspecialchars(truncate($mr['description'] ?? '', 60)) ?></td>
                                        <td><?= htmlspecialchars(truncate($mr['notes'] ?? '', 60)) ?: '-' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php elseif ($activeTab === 'billing'): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Invoice #</th><th>Date</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($invoices)): ?>
                                <tr><td colspan="6" class="text-center text-muted py-4">No invoices found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($invoices as $inv): ?>
                                    <tr>
                                        <td class="fw-medium"><?= htmlspecialchars($inv['invoice_number']) ?></td>
                                        <td><?= formatDate($inv['invoice_date']) ?></td>
                                        <td><?= formatCurrency($inv['total']) ?></td>
                                        <td><?= formatCurrency($inv['paid_amount']) ?></td>
                                        <td><?= formatCurrency($inv['total'] - $inv['paid_amount']) ?></td>
                                        <td><?= getStatusBadge($inv['status']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php elseif ($activeTab === 'appointments'): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Date</th><th>Time</th><th>Doctor</th><th>Type</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($appointments)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">No appointments found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($appointments as $a): ?>
                                    <tr>
                                        <td><?= formatDate($a['appointment_date']) ?></td>
                                        <td><?= date('H:i', strtotime($a['appointment_time'])) ?></td>
                                        <td><?= htmlspecialchars(($a['doctor_first'] ?? '') . ' ' . ($a['doctor_last'] ?? '')) ?: '-' ?></td>
                                        <td><?= ucfirst($a['type']) ?></td>
                                        <td><?= getStatusBadge($a['status']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>

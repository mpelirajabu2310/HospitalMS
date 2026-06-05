<?php
define('PAGE_TITLE', 'My Prescriptions');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
Auth::requireAuth();

$userId = Auth::id();
$statusFilter = $_GET['status'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

$where = "p.doctor_id = ?";
$params = [$userId];

if ($statusFilter && $statusFilter !== 'all') {
    $where .= " AND p.status = ?";
    $params[] = $statusFilter;
}
if ($dateFrom) {
    $where .= " AND p.prescription_date >= ?";
    $params[] = $dateFrom;
}
if ($dateTo) {
    $where .= " AND p.prescription_date <= ?";
    $params[] = $dateTo;
}

$prescriptions = Database::fetchAll(
    "SELECT p.*, CONCAT(pt.first_name, ' ', pt.last_name) as patient_name, pt.patient_number,
            (SELECT COUNT(*) FROM prescription_items WHERE prescription_id = p.id) as items_count
     FROM prescriptions p
     JOIN patients pt ON p.patient_id = pt.id
     WHERE $where
     ORDER BY p.created_at DESC",
    $params
);

include_once __DIR__ . '/../../includes/header.php';
echo display_flash('success');
echo display_flash('error');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-prescription me-2 text-primary"></i>My Prescriptions</h4>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-medium small mb-0">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="dispensed" <?= $statusFilter === 'dispensed' ? 'selected' : '' ?>>Dispensed</option>
                    <option value="partially_dispensed" <?= $statusFilter === 'partially_dispensed' ? 'selected' : '' ?>>Partially Dispensed</option>
                    <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    <option value="expired" <?= $statusFilter === 'expired' ? 'selected' : '' ?>>Expired</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-medium small mb-0">From Date</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= $dateFrom ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-medium small mb-0">To Date</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= $dateTo ?>">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter me-1"></i> Filter</button>
                <a href="<?= APP_URL ?>/modules/doctor/prescriptions.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-undo me-1"></i> Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="prescriptionsTable">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>Patient #</th>
                        <th class="text-center">Medicines</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($prescriptions)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No prescriptions found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($prescriptions as $p): ?>
                            <tr>
                                <td><?= formatDate($p['prescription_date']) ?></td>
                                <td class="fw-medium"><?= htmlspecialchars($p['patient_name']) ?></td>
                                <td><?= htmlspecialchars($p['patient_number']) ?></td>
                                <td class="text-center"><span class="badge bg-secondary"><?= $p['items_count'] ?></span></td>
                                <td><?= getStatusBadge($p['status']) ?></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#prescriptionModal" data-prescription-id="<?= $p['id'] ?>">
                                        <i class="fas fa-eye me-1"></i> View Details
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="prescriptionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-prescription me-2 text-primary"></i>Prescription Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="prescriptionModalBody">
                <div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 text-muted">Loading...</p></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#prescriptionsTable').DataTable({
        pageLength: 25,
        order: [[0, 'desc']],
        columnDefs: [{ orderable: false, targets: [5] }]
    });

    $('#prescriptionModal').on('show.bs.modal', function(e) {
        const prescriptionId = e.relatedTarget.dataset.prescriptionId;
        const body = document.getElementById('prescriptionModalBody');
        body.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 text-muted">Loading...</p></div>';
        fetch('<?= APP_URL ?>/api/search.php?action=prescription_details&id=' + prescriptionId)
            .then(r => r.json())
            .then(data => {
                let html = '';
                if (data.error) {
                    html = '<div class="alert alert-danger">' + data.error + '</div>';
                } else {
                    html = '<div class="mb-3"><table class="table table-sm mb-0">';
                    html += '<tr><td class="text-muted" style="width:120px">Patient</td><td><strong>' + data.patient_name + '</strong> (' + data.patient_number + ')</td></tr>';
                    html += '<tr><td class="text-muted">Date</td><td>' + data.prescription_date + '</td></tr>';
                    html += '<tr><td class="text-muted">Status</td><td>' + data.status_badge + '</td></tr>';
                    if (data.notes) html += '<tr><td class="text-muted">Notes</td><td>' + data.notes + '</td></tr>';
                    html += '</table></div>';
                    html += '<h6 class="fw-medium mb-2">Prescribed Items</h6>';
                    html += '<div class="table-responsive"><table class="table table-bordered table-sm mb-0"><thead class="table-light"><tr><th>Medicine</th><th>Dosage</th><th>Frequency</th><th>Duration</th><th>Qty</th><th>Route</th><th>Instructions</th></tr></thead><tbody>';
                    data.items.forEach(function(item) {
                        html += '<tr>';
                        html += '<td>' + item.medicine_name + '</td>';
                        html += '<td>' + item.dosage + '</td>';
                        html += '<td>' + item.frequency + '</td>';
                        html += '<td>' + item.duration + '</td>';
                        html += '<td>' + item.quantity + '</td>';
                        html += '<td>' + item.route + '</td>';
                        html += '<td>' + (item.instructions || '-') + '</td>';
                        html += '</tr>';
                    });
                    html += '</tbody></table></div>';
                }
                body.innerHTML = html;
            })
            .catch(() => {
                body.innerHTML = '<div class="alert alert-danger">Failed to load prescription details.</div>';
            });
    });
});
</script>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>

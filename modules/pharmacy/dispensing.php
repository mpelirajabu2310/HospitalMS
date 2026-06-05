<?php
define('PAGE_TITLE', 'Dispense Medicines');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
Auth::requireAuth();

$userId = Auth::id();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $prescriptionId = intval($_POST['prescription_id'] ?? 0);
    $items = $_POST['items'] ?? [];

    if ($prescriptionId && !empty($items)) {
        $prescription = Database::fetch(
            "SELECT p.*, pt.first_name as p_first_name, pt.last_name as p_last_name, pt.id as patient_id,
                    d.first_name as d_first_name, d.last_name as d_last_name
             FROM prescriptions p
             JOIN patients pt ON p.patient_id = pt.id
             JOIN users d ON p.doctor_id = d.id
             WHERE p.id = ?",
            [$prescriptionId]
        );

        if (!$prescription) {
            set_flash('error', 'Prescription not found.', 'warning');
            redirect('modules/pharmacy/dispensing.php');
        }

        try {
            $db = Database::getInstance()->getConnection();
            $db->beginTransaction();

            $totalDispensed = 0;
            $totalItems = 0;
            $saleTotal = 0;
            $saleItems = [];

            foreach ($items as $itemId => $data) {
                $qtyToDispense = floatval($data['quantity'] ?? 0);
                if ($qtyToDispense <= 0) continue;

                $item = Database::fetch(
                    "SELECT pi.*, m.selling_price, m.current_stock, m.name as medicine_name
                     FROM prescription_items pi
                     JOIN medicines m ON pi.medicine_id = m.id
                     WHERE pi.id = ? AND pi.prescription_id = ?",
                    [$itemId, $prescriptionId]
                );

                if (!$item) continue;
                if ($qtyToDispense > $item['quantity']) {
                    throw new Exception("Cannot dispense more than prescribed quantity for {$item['medicine_name']}");
                }
                if ($qtyToDispense > $item['current_stock']) {
                    throw new Exception("Insufficient stock for {$item['medicine_name']}");
                }

                Database::query(
                    "UPDATE prescription_items SET dispensed_quantity = dispensed_quantity + ?, status = CASE WHEN (dispensed_quantity + ?) >= quantity THEN 'dispensed' ELSE 'partially_dispensed' END WHERE id = ?",
                    [$qtyToDispense, $qtyToDispense, $itemId]
                );

                Database::query(
                    "UPDATE medicines SET current_stock = current_stock - ? WHERE id = ?",
                    [$qtyToDispense, $item['medicine_id']]
                );

                Database::insert(
                    "INSERT INTO stock_movements (medicine_id, quantity, type, reference_type, reference_id, performed_by) VALUES (?, ?, 'sale', 'prescription', ?, ?)",
                    [$item['medicine_id'], -$qtyToDispense, $prescriptionId, $userId]
                );

                $lineTotal = $qtyToDispense * $item['selling_price'];
                $saleTotal += $lineTotal;
                $saleItems[] = [
                    'medicine_id' => $item['medicine_id'],
                    'quantity' => $qtyToDispense,
                    'unit_price' => $item['selling_price'],
                    'total' => $lineTotal,
                ];
                $totalDispensed++;
                $totalItems++;
            }

            if ($totalItems === 0) {
                throw new Exception('No items to dispense.');
            }

            $remainingPending = Database::fetch(
                "SELECT COUNT(*) as c FROM prescription_items WHERE prescription_id = ? AND status = 'pending'",
                [$prescriptionId]
            )['c'];

            if ($remainingPending == 0) {
                $presStatus = 'dispensed';
            } else {
                $presStatus = 'partially_dispensed';
            }

            Database::query(
                "UPDATE prescriptions SET status = ? WHERE id = ?",
                [$presStatus, $prescriptionId]
            );

            $saleId = Database::insert(
                "INSERT INTO pharmacy_sales (prescription_id, patient_id, pharmacist_id, sale_date, subtotal, total, status) VALUES (?, ?, ?, NOW(), ?, ?, 'completed')",
                [$prescriptionId, $prescription['patient_id'], $userId, $saleTotal, $saleTotal]
            );

            foreach ($saleItems as $si) {
                Database::insert(
                    "INSERT INTO pharmacy_sale_items (sale_id, medicine_id, quantity, unit_price, total) VALUES (?, ?, ?, ?, ?)",
                    [$saleId, $si['medicine_id'], $si['quantity'], $si['unit_price'], $si['total']]
                );
            }

            $invoiceNumber = generateInvoiceNumber();
            $invoiceId = Database::insert(
                "INSERT INTO invoices (invoice_number, patient_id, invoice_date, subtotal, total, status, created_by) VALUES (?, ?, CURDATE(), ?, ?, 'pending', ?)",
                [$invoiceNumber, $prescription['patient_id'], $saleTotal, $saleTotal, $userId]
            );

            Database::insert(
                "INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, total, reference_type, reference_id) VALUES (?, 'Pharmacy Dispensing', ?, ?, ?, 'pharmacy_sale', ?)",
                [$invoiceId, $totalItems, $saleTotal / $totalItems, $saleTotal, $saleId]
            );

            $cashiers = Database::fetchAll("SELECT u.id FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name IN ('cashier', 'admin', 'super_admin')");
            foreach ($cashiers as $cashier) {
                createNotification(
                    $cashier['id'],
                    'pharmacy_charge',
                    'New Pharmacy Charges',
                    "Pharmacy charges of " . formatCurrency($saleTotal) . " for {$prescription['p_first_name']} {$prescription['p_last_name']} are pending billing.",
                    APP_URL . '/modules/billing/invoices.php',
                    'prescription',
                    $prescriptionId
                );
            }

            $db->commit();
            logActivity($userId, 'medicines_dispensed', 'pharmacy', "Dispensed prescription #$prescriptionId");
            set_flash('success', 'Medicines dispensed successfully. Invoice created and cashier notified.');
            redirect('modules/pharmacy/dispensing.php');
        } catch (Exception $e) {
            $db->rollBack();
            set_flash('error', $e->getMessage(), 'danger');
            redirect('modules/pharmacy/dispensing.php');
        }
    } else {
        set_flash('error', 'No items selected for dispensing.', 'warning');
        redirect('modules/pharmacy/dispensing.php');
    }
}

$prescriptions = Database::fetchAll(
    "SELECT p.*, pt.first_name as p_first_name, pt.last_name as p_last_name, pt.patient_number,
            u.first_name as d_first_name, u.last_name as d_last_name,
            (SELECT COUNT(*) FROM prescription_items WHERE prescription_id = p.id) as items_count,
            (SELECT COUNT(*) FROM prescription_items WHERE prescription_id = p.id AND status = 'pending') as pending_count
     FROM prescriptions p
     JOIN patients pt ON p.patient_id = pt.id
     JOIN users u ON p.doctor_id = u.id
     WHERE p.status IN ('active', 'partially_dispensed')
     ORDER BY p.created_at DESC
     LIMIT 50"
);

include_once __DIR__ . '/../../includes/header.php';
echo display_flash('success');
echo display_flash('error');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-prescription-bottle me-2 text-primary"></i>Dispense Medicines</h4>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0"><i class="fas fa-list me-2 text-primary"></i>Active Prescriptions</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Items</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($prescriptions)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No active prescriptions found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($prescriptions as $p): ?>
                            <tr>
                                <td class="small"><?= formatDate($p['prescription_date']) ?></td>
                                <td>
                                    <a href="<?= APP_URL ?>/modules/patients/profile.php?id=<?= $p['patient_id'] ?>" class="text-decoration-none fw-medium">
                                        <?= htmlspecialchars($p['p_first_name'] . ' ' . $p['p_last_name']) ?>
                                    </a>
                                    <br><small class="text-muted"><?= htmlspecialchars($p['patient_number']) ?></small>
                                </td>
                                <td class="small"><?= htmlspecialchars($p['d_first_name'] . ' ' . $p['d_last_name']) ?></td>
                                <td>
                                    <span class="badge bg-info"><?= $p['items_count'] ?> items</span>
                                    <?php if ($p['pending_count'] > 0): ?>
                                        <span class="badge bg-warning text-dark"><?= $p['pending_count'] ?> pending</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= getStatusBadge($p['status']) ?></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#dispenseModal"
                                        data-id="<?= $p['id'] ?>"
                                        data-patient="<?= htmlspecialchars($p['p_first_name'] . ' ' . $p['p_last_name']) ?>"
                                        data-patient-number="<?= htmlspecialchars($p['patient_number']) ?>"
                                        data-doctor="<?= htmlspecialchars($p['d_first_name'] . ' ' . $p['d_last_name']) ?>"
                                        data-date="<?= formatDate($p['prescription_date']) ?>">
                                        <i class="fas fa-hand-holding me-1"></i>Dispense
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

<div class="modal fade" id="dispenseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" onsubmit="return confirm('Confirm dispense? Stock will be deducted.')">
                <?= csrf_field() ?>
                <input type="hidden" name="prescription_id" id="dispPrescriptionId" value="0">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-prescription me-2 text-primary"></i>Dispense Prescription</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="dispenseBody">
                    <div class="row mb-3" id="dispenseInfo">
                        <div class="col-md-4"><small class="text-muted">Patient:</small><br><strong id="dispPatient"></strong></div>
                        <div class="col-md-3"><small class="text-muted">#:</small><br><strong id="dispPatientNumber"></strong></div>
                        <div class="col-md-3"><small class="text-muted">Doctor:</small><br><strong id="dispDoctor"></strong></div>
                        <div class="col-md-2"><small class="text-muted">Date:</small><br><strong id="dispDate"></strong></div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Medicine</th>
                                    <th>Prescribed</th>
                                    <th>Dosage</th>
                                    <th>Frequency</th>
                                    <th>Available Stock</th>
                                    <th>To Dispense</th>
                                </tr>
                            </thead>
                            <tbody id="dispItemsContainer">
                                <tr><td colspan="6" class="text-center text-muted py-3">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check me-1"></i>Confirm Dispense</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var dispenseModal = document.getElementById('dispenseModal');
    dispenseModal.addEventListener('show.bs.modal', function(event) {
        var btn = event.relatedTarget;
        var id = btn.getAttribute('data-id');
        document.getElementById('dispPrescriptionId').value = id;
        document.getElementById('dispPatient').textContent = btn.getAttribute('data-patient');
        document.getElementById('dispPatientNumber').textContent = btn.getAttribute('data-patient-number');
        document.getElementById('dispDoctor').textContent = btn.getAttribute('data-doctor');
        document.getElementById('dispDate').textContent = btn.getAttribute('data-date');

        var container = document.getElementById('dispItemsContainer');
        container.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin me-1"></i>Loading items...</td></tr>';

        fetch('<?= APP_URL ?>/api/pharmacy/prescription-items.php?id=' + id)
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.error) {
                    container.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-3">' + data.error + '</td></tr>';
                    return;
                }
                var html = '';
                data.forEach(function(item) {
                    var maxQty = Math.min(parseFloat(item.quantity), parseFloat(item.current_stock));
                    var dispensed = parseFloat(item.dispensed_quantity) || 0;
                    var remaining = parseFloat(item.quantity) - dispensed;
                    var disabled = remaining <= 0 || parseFloat(item.current_stock) <= 0 ? 'disabled' : '';
                    html += '<tr>' +
                        '<td>' + item.medicine_name + '</td>' +
                        '<td>' + item.quantity + '</td>' +
                        '<td>' + item.dosage + '</td>' +
                        '<td>' + item.frequency + '</td>' +
                        '<td class="' + (parseFloat(item.current_stock) <= 0 ? 'text-danger fw-bold' : '') + '">' + parseFloat(item.current_stock) + '</td>' +
                        '<td><input type="number" name="items[' + item.id + '][quantity]" class="form-control form-control-sm" min="0" max="' + Math.min(remaining, item.current_stock) + '" step="0.01" value="' + Math.min(remaining, item.current_stock) + '" ' + disabled + '></td>' +
                        '</tr>';
                });
                container.innerHTML = html || '<tr><td colspan="6" class="text-center text-muted py-3">No items to dispense.</td></tr>';
            })
            .catch(function() {
                container.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-3">Failed to load items.</td></tr>';
            });
    });
});
</script>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>

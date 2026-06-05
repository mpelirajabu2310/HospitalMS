<?php
define('PAGE_TITLE', 'Enter Lab Results');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
Auth::requireAuth();

$userId = Auth::id();
$requestId = intval($_GET['request_id'] ?? 0);

if (!$requestId) {
    set_flash('error', 'No test request specified.', 'warning');
    redirect('modules/laboratory/tests.php');
}

$request = Database::fetch(
    "SELECT lr.*, p.id as patient_id, p.first_name as p_first_name, p.last_name as p_last_name,
            p.patient_number, lt.name as test_name, lt.reference_range, lt.specimen_type,
            u.first_name as d_first_name, u.last_name as d_last_name
     FROM lab_requests lr
     JOIN patients p ON lr.patient_id = p.id
     JOIN lab_tests lt ON lr.lab_test_id = lt.id
     JOIN users u ON lr.doctor_id = u.id
     WHERE lr.id = ?",
    [$requestId]
);

if (!$request) {
    set_flash('error', 'Test request not found.', 'warning');
    redirect('modules/laboratory/tests.php');
}

if ($request['status'] === 'completed') {
    set_flash('error', 'Results already entered for this request.', 'warning');
    redirect('modules/laboratory/tests.php');
}

// Results are entered as free-form parameters per test request

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $parameters = $_POST['parameters'] ?? [];
    $notes = sanitize($_POST['notes'] ?? '');
    $attachment = null;

    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $attachment = uploadFile($_FILES['attachment'], 'lab_results');
    }

    if (empty($parameters)) {
        set_flash('error', 'Please enter at least one result.', 'warning');
        redirect('modules/laboratory/results.php?request_id=' . $requestId);
    }

    try {
        $db = Database::getInstance()->getConnection();
        $db->beginTransaction();

        foreach ($parameters as $param) {
            $paramName = sanitize($param['name'] ?? '');
            $resultValue = sanitize($param['value'] ?? '');
            $refRange = sanitize($param['reference_range'] ?? '');
            $unit = sanitize($param['unit'] ?? '');
            $isAbnormal = isset($param['is_abnormal']) ? 1 : 0;

            Database::insert(
                "INSERT INTO lab_results (lab_request_id, test_parameter, result_value, reference_range, unit, is_abnormal, notes, attachment_path, verified_by, verified_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [$requestId, $paramName, $resultValue, $refRange, $unit, $isAbnormal, $notes, $attachment, $userId]
            );
        }

        Database::query(
            "UPDATE lab_requests SET status = 'completed', completed_by = ?, completed_at = NOW() WHERE id = ?",
            [$userId, $requestId]
        );

        $notifMsg = "Lab results for {$request['test_name']} ({$request['p_first_name']} {$request['p_last_name']}) are ready.";
        createNotification(
            $request['doctor_id'],
            'lab_result',
            'Lab Results Ready',
            $notifMsg,
            APP_URL . '/modules/laboratory/tests.php',
            'lab_request',
            $requestId
        );

        Database::insert(
            "INSERT INTO medical_records (patient_id, record_type, record_date, description, attachment_path, notes, created_by)
             VALUES (?, 'lab_result', CURDATE(), ?, ?, ?, ?)",
            [
                $request['patient_id'],
                "Lab Results: {$request['test_name']}",
                $attachment,
                $notes,
                $userId
            ]
        );

        $db->commit();
        logActivity($userId, 'results_entered', 'laboratory', "Results entered for lab request #$requestId");
        set_flash('success', 'Lab results saved successfully. Notification sent to doctor.');
        redirect('modules/laboratory/tests.php');
    } catch (Exception $e) {
        $db->rollBack();
        set_flash('error', 'Failed to save results: ' . $e->getMessage(), 'danger');
        redirect('modules/laboratory/results.php?request_id=' . $requestId);
    }
}

include_once __DIR__ . '/../../includes/header.php';
echo display_flash('success');
echo display_flash('error');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-flask me-2 text-primary"></i>Enter Lab Results</h4>
    <a href="<?= APP_URL ?>/modules/laboratory/tests.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back to Requests</a>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-3">
                <small class="text-muted d-block">Patient</small>
                <strong><?= htmlspecialchars($request['p_first_name'] . ' ' . $request['p_last_name']) ?></strong>
                <br><small class="text-muted"><?= htmlspecialchars($request['patient_number']) ?></small>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">Test</small>
                <strong><?= htmlspecialchars($request['test_name']) ?></strong>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">Requesting Doctor</small>
                <strong><?= htmlspecialchars($request['d_first_name'] . ' ' . $request['d_last_name']) ?></strong>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">Specimen Type</small>
                <strong><?= htmlspecialchars($request['specimen_type'] ?? 'N/A') ?></strong>
            </div>
        </div>
        <?php if ($request['clinical_notes']): ?>
            <hr>
            <div>
                <small class="text-muted d-block">Clinical Notes</small>
                <p class="mb-0"><?= nl2br(htmlspecialchars($request['clinical_notes'])) ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0"><i class="fas fa-edit me-2 text-primary"></i>Test Results</h5>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <?php for ($i = 0; $i < 5; $i++): ?>
                <div class="row g-2 mb-3 align-items-end border-bottom pb-3">
                    <div class="col-md-3">
                        <label class="form-label fw-medium small">Test Parameter</label>
                        <input type="text" name="parameters[<?= $i ?>][name]" class="form-control" placeholder="e.g. Parameter name">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-medium small">Result</label>
                        <textarea name="parameters[<?= $i ?>][value]" class="form-control" rows="2" <?= $i === 0 ? 'required' : '' ?>></textarea>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-medium small">Reference Range</label>
                        <input type="text" name="parameters[<?= $i ?>][reference_range]" class="form-control" placeholder="e.g. 13.5-17.5">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-medium small">Unit</label>
                        <input type="text" name="parameters[<?= $i ?>][unit]" class="form-control" placeholder="e.g. g/dL">
                    </div>
                    <div class="col-md-2 d-flex align-items-center">
                        <div class="form-check">
                            <input type="checkbox" name="parameters[<?= $i ?>][is_abnormal]" class="form-check-input" id="abnormal_<?= $i ?>" value="1">
                            <label class="form-check-label small" for="abnormal_<?= $i ?>">Abnormal</label>
                        </div>
                    </div>
                </div>
            <?php endfor; ?>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-medium small">Attachment (optional)</label>
                    <input type="file" name="attachment" class="form-control" accept=".jpg,.jpeg,.png,.gif,.pdf">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-medium small">Notes</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Additional notes..."></textarea>
                </div>
            </div>

            <div class="d-flex justify-content-end">
                <a href="<?= APP_URL ?>/modules/laboratory/tests.php" class="btn btn-outline-secondary me-2">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Results</button>
            </div>
        </form>
    </div>
</div>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>

<?php
$title = 'Customer Complaints & Investigation Management';
require __DIR__ . '/../../layouts/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Customer Complaints Management</h1>
            <p class="text-muted mb-0">Product Quality Inquiries, OOS Recalls, and CAPA Escalations</p>
        </div>
        <div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newComplaintModal">
                <i class="bi bi-plus-lg"></i> Log Complaint
            </button>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <span class="fw-bold">Complaints Log</span>
            <span class="badge bg-secondary"><?= count($complaints) ?> records</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Complaint #</th>
                            <th>Customer & Batch</th>
                            <th>Category</th>
                            <th>Risk Level</th>
                            <th>Description</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($complaints)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No customer complaints logged.</td></tr>
                        <?php else: ?>
                            <?php foreach ($complaints as $c): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($c['complaint_number']) ?></code></td>
                                    <td>
                                        <strong><?= htmlspecialchars($c['customer_name'] ?? 'Direct Customer') ?></strong><br>
                                        <small class="text-muted">Batch: <?= htmlspecialchars($c['batch_number'] ?? 'N/A') ?></small>
                                    </td>
                                    <td><span class="badge bg-info text-dark"><?= htmlspecialchars($c['complaint_type']) ?></span></td>
                                    <td>
                                        <?php $rb = match($c['risk_level']) {
                                            'Critical' => 'bg-danger',
                                            'High' => 'bg-warning text-dark',
                                            'Medium' => 'bg-secondary',
                                            default => 'bg-light text-dark border'
                                        }; ?>
                                        <span class="badge <?= $rb ?>"><?= htmlspecialchars($c['risk_level']) ?></span>
                                    </td>
                                    <td style="max-width: 350px;" class="text-truncate"><?= htmlspecialchars($c['description']) ?></td>
                                    <td><span class="badge bg-primary"><?= htmlspecialchars($c['status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Log Complaint -->
<div class="modal fade" id="newComplaintModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="/complaints" method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Log Customer Complaint</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Customer / Client</label>
                        <select name="customer_id" class="form-select">
                            <option value="">-- Direct / Anonymous --</option>
                            <?php foreach ($customers as $cust): ?>
                                <option value="<?= $cust['id'] ?>"><?= htmlspecialchars($cust['customer_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Batch / Lot Number</label>
                        <input type="text" name="batch_number" class="form-control" placeholder="e.g. LOT-2026-091">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Complaint Category</label>
                        <select name="complaint_type" class="form-select">
                            <option value="OOS_Result">OOS Result / Analytical Discrepancy</option>
                            <option value="Packaging">Packaging Defect</option>
                            <option value="Labeling">Labeling / Barcode Error</option>
                            <option value="Contamination">Foreign Matter / Contamination</option>
                            <option value="Delayed_TAT">Turnaround Time Delay</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Risk Severity Level</label>
                        <select name="risk_level" class="form-select">
                            <option value="Low">Low</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="High">High</option>
                            <option value="Critical">Critical (Patient Safety / Recall)</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Complaint Narrative</label>
                    <textarea name="description" class="form-control" rows="4" required placeholder="Detailed description of customer feedback or defect..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Log Complaint</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>

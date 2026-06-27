<?php $title = 'Compliance Dashboard'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-shield-check me-2"></i>Compliance Dashboard</h4>
    <div class="d-flex gap-2">
        <span class="badge bg-success bg-opacity-10 text-success px-3 py-2"><i class="bi bi-check-circle me-1"></i>Compliant</span>
        <span class="badge bg-info bg-opacity-10 text-info px-3 py-2"><i class="bi bi-clock-history me-1"></i>Last Audit: <?= $lastAuditDate ?? 'N/A' ?></span>
    </div>
</div>

<!-- Compliance Status Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card text-center h-100 border-<?= $compliance['gdpr']['status'] === 'Compliant' ? 'success' : 'danger' ?>">
            <div class="card-body">
                <i class="bi bi-shield-lock display-5 text-<?= $compliance['gdpr']['status'] === 'Compliant' ? 'success' : 'danger' ?> mb-2 d-block"></i>
                <h5 class="fw-bold">GDPR</h5>
                <div class="display-6 fw-bold text-<?= $compliance['gdpr']['status'] === 'Compliant' ? 'success' : 'danger' ?>"><?= $compliance['gdpr']['score'] ?? 0 ?>%</div>
                <span class="badge bg-<?= $compliance['gdpr']['status'] === 'Compliant' ? 'success' : 'danger' ?> mt-2"><?= e($compliance['gdpr']['status'] ?? 'Unknown') ?></span>
                <div class="mt-2"><small class="text-muted"><?= $compliance['gdpr']['details'] ?? '' ?></small></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center h-100 border-<?= $compliance['hipaa']['status'] === 'Compliant' ? 'success' : 'danger' ?>">
            <div class="card-body">
                <i class="bi bi-hospital display-5 text-<?= $compliance['hipaa']['status'] === 'Compliant' ? 'success' : 'danger' ?> mb-2 d-block"></i>
                <h5 class="fw-bold">HIPAA</h5>
                <div class="display-6 fw-bold text-<?= $compliance['hipaa']['status'] === 'Compliant' ? 'success' : 'danger' ?>"><?= $compliance['hipaa']['score'] ?? 0 ?>%</div>
                <span class="badge bg-<?= $compliance['hipaa']['status'] === 'Compliant' ? 'success' : 'danger' ?> mt-2"><?= e($compliance['hipaa']['status'] ?? 'Unknown') ?></span>
                <div class="mt-2"><small class="text-muted"><?= $compliance['hipaa']['details'] ?? '' ?></small></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center h-100 border-info">
            <div class="card-body">
                <i class="bi bi-archive display-5 text-info mb-2 d-block"></i>
                <h5 class="fw-bold">Data Retention</h5>
                <div class="display-6 fw-bold"><?= $dataRetentionStats['compliant'] ?? 0 ?>/<?= $dataRetentionStats['total'] ?? 0 ?></div>
                <span class="badge bg-info mt-2">Policies Active</span>
                <div class="mt-2"><small class="text-muted"><?= $dataRetentionStats['expiring_soon'] ?? 0 ?> expiring soon</small></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center h-100 border-warning">
            <div class="card-body">
                <i class="bi bi-journal-check display-5 text-warning mb-2 d-block"></i>
                <h5 class="fw-bold">Consent Logs</h5>
                <div class="display-6 fw-bold"><?= $consentStats['total'] ?? 0 ?></div>
                <span class="badge bg-warning mt-2">Records</span>
                <div class="mt-2"><small class="text-muted"><?= $consentStats['pending'] ?? 0 ?> pending review</small></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Data Retention Policies -->
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-archive me-1"></i>Data Retention Policies</h6>
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#retentionModal"><i class="bi bi-plus"></i> Add Policy</button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Entity Type</th>
                            <th>Retention Period</th>
                            <th>Auto-Purge</th>
                            <th>Records Affected</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($retentionPolicies)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No retention policies defined.</td></tr>
                        <?php else: foreach ($retentionPolicies as $rp): ?>
                        <tr>
                            <td><span class="badge bg-info bg-opacity-10 text-info"><?= e($rp['entity_type']) ?></span></td>
                            <td><?= $rp['retention_days'] ?> days</td>
                            <td><?= $rp['auto_purge'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' ?></td>
                            <td><?= $rp['records_affected'] ?? 0 ?></td>
                            <td>
                                <form method="POST" action="/compliance/retention/<?= $rp['id'] ?>" class="d-inline" onsubmit="return confirm('Delete this policy?')">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Consent Logs -->
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-journal-check me-1"></i>Consent Logs</h6></div>
            <div class="table-responsive" style="max-height:300px;overflow-y:auto;">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>User</th>
                            <th>Action</th>
                            <th>Purpose</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($consentLogs)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-4">No consent records found.</td></tr>
                        <?php else: foreach ($consentLogs as $cl): ?>
                        <tr>
                            <td><?= e($cl['user_name'] ?? '—') ?></td>
                            <td><span class="badge bg-<?= $cl['action'] === 'Granted' ? 'success' : ($cl['action'] === 'Revoked' ? 'danger' : 'secondary') ?>"><?= e($cl['action']) ?></span></td>
                            <td><small><?= e($cl['purpose'] ?? '—') ?></small></td>
                            <td><small class="text-muted"><?= date('d M Y H:i', strtotime($cl['created_at'])) ?></small></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Privacy Logs -->
    <div class="col-md-12 mt-3">
        <div class="card shadow-sm">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-eye-slash me-1"></i>Privacy Access Logs</h6></div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Timestamp</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Data Type</th>
                            <th>Record ID</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($privacyLogs)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No privacy access logs recorded.</td></tr>
                        <?php else: foreach ($privacyLogs as $pl): ?>
                        <tr>
                            <td><small class="text-muted"><?= date('d M Y H:i:s', strtotime($pl['created_at'])) ?></small></td>
                            <td><?= e($pl['user_name'] ?? '—') ?></td>
                            <td><span class="badge bg-<?= match($pl['action']) { 'Access'=>'info', 'Export'=>'warning', 'Delete'=>'danger', 'Rectify'=>'primary', default=>'secondary' } ?>"><?= e($pl['action']) ?></span></td>
                            <td><?= e($pl['data_type'] ?? '—') ?></td>
                            <td><?= e($pl['record_id'] ?? '—') ?></td>
                            <td><small class="text-muted"><?= e($pl['ip_address'] ?? '—') ?></small></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Retention Policy Modal -->
<div class="modal fade" id="retentionModal" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
<form method="POST" action="/compliance/retention">
    <?= csrf_field() ?>
    <div class="modal-header"><h5 class="modal-title"><i class="bi bi-archive me-1"></i>Add Retention Policy</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">Entity Type <span class="text-danger">*</span></label>
            <select name="entity_type" class="form-select" required>
                <option value="">— Select —</option>
                <option value="AuditLog">Audit Logs</option>
                <option value="Sample">Sample Records</option>
                <option value="TestResult">Test Results</option>
                <option value="Batch">Batch Records</option>
                <option value="COA">COA Documents</option>
                <option value="Consent">Consent Records</option>
                <option value="Notification">Notifications</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Retention Period (days) <span class="text-danger">*</span></label>
            <input type="number" name="retention_days" class="form-control" required min="1" value="365">
        </div>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="auto_purge" value="1">
            <label class="form-check-label">Auto-purge after retention period</label>
        </div>
        <div class="alert alert-warning py-2 mb-0">
            <i class="bi bi-exclamation-triangle me-1"></i>Auto-purge permanently deletes records. Ensure compliance with local regulations.
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Add Policy</button>
    </div>
</form>
</div></div></div>

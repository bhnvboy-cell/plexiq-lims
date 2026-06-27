<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="page-title mb-1"><i class="bi bi-award me-2"></i>Supplier Qualifications</h4>
        <span class="text-muted small"><?= count($qualifications) ?> record(s) for <?= e($supplier['supplier_name'] ?? '') ?></span>
    </div>
    <div class="d-flex gap-2">
        <a href="/suppliers/<?= $supplier['id'] ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Supplier</a>
        <?php if (in_array($auth['role'], ['Admin'])): ?>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#qualificationModal"><i class="bi bi-plus-lg me-1"></i>Add Qualification</button>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($qualifications)): ?>
<div class="card">
    <div class="empty-state">
        <i class="bi bi-award"></i>
        <h5>No Qualifications</h5>
        <p class="text-muted">No qualification records for this supplier.</p>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Date</th>
                    <th>Result</th>
                    <th>Certificate</th>
                    <th>Auditor</th>
                    <th>Expiry</th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($qualifications as $q): ?>
                <tr>
                    <td><span class="badge bg-info bg-opacity-10 text-info"><?= e($q['qualification_type']) ?></span></td>
                    <td><?= e($q['qualification_date']) ?></td>
                    <td><?php $rbadge = match ($q['result']) { 'Pass'=>'success', 'Fail'=>'danger', 'Conditional'=>'warning', default=>'secondary' }; ?>
                        <span class="badge bg-<?= $rbadge ?>"><?= e($q['result']) ?></span>
                    </td>
                    <td><?= e($q['certificate_number'] ?? '-') ?></td>
                    <td><?= e($q['auditor'] ?? '-') ?></td>
                    <td><small class="text-muted"><?= e($q['expiry_date'] ?? '-') ?></small></td>
                    <td class="text-end">
                        <?php if ($auth['role'] === 'Admin'): ?>
                        <form method="POST" action="/suppliers/<?= $supplier['id'] ?>/qualifications/<?= $q['id'] ?>" class="d-inline" onsubmit="return confirm('Delete this qualification?')">
                            <input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Add Qualification Modal -->
<div class="modal fade" id="qualificationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="/suppliers/<?= $supplier['id'] ?>/qualifications">
                <input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-lg me-1"></i>Add Qualification</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Qualification Type <span class="text-danger">*</span></label>
                        <select name="qualification_type" class="form-select" required>
                            <option value="">-- Select --</option>
                            <option value="Audit">Audit</option>
                            <option value="Assessment">Assessment</option>
                            <option value="Certification">Certification</option>
                            <option value="Evaluation">Evaluation</option>
                            <option value="Site Visit">Site Visit</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Qualification Date <span class="text-danger">*</span></label>
                        <input name="qualification_date" type="date" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Result <span class="text-danger">*</span></label>
                        <select name="result" class="form-select" required>
                            <option value="Pass">Pass</option>
                            <option value="Fail">Fail</option>
                            <option value="Conditional">Conditional</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Certificate Number</label>
                        <input name="certificate_number" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Auditor</label>
                        <input name="auditor" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Expiry Date</label>
                        <input name="expiry_date" type="date" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

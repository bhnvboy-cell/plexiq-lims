<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="page-title mb-1"><i class="bi bi-journal-text me-2"></i>Calibration Records</h4>
        <span class="text-muted small"><?= count($records) ?> record(s)</span>
    </div>
    <div class="d-flex gap-2">
        <a href="/calibrations" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
        <?php if ($auth['role'] === 'Admin'): ?>
        <a href="/calibrations/records/" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Record</a>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($records)): ?>
<div class="card">
    <div class="empty-state">
        <i class="bi bi-journal-text"></i>
        <h5>No Calibration Records</h5>
        <p class="text-muted">No calibration records have been entered yet.</p>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Instrument</th>
                    <th>Standard</th>
                    <th>Type</th>
                    <th>Result</th>
                    <th>As-Found</th>
                    <th>As-Left</th>
                    <th>Certificate</th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $r): ?>
                <tr>
                    <td><small class="text-muted"><?= e($r['calibration_date']) ?></small></td>
                    <td><strong><?= e($r['instrument_name'] ?? '-') ?></strong></td>
                    <td><?= e($r['standard_name'] ?? '-') ?></td>
                    <td><span class="badge bg-secondary bg-opacity-10 text-secondary"><?= e($r['calibration_type'] ?? '-') ?></span></td>
                    <td><span class="badge bg-<?= $r['result']==='Pass'?'success':($r['result']==='Fail'?'danger':'warning') ?>"><?= e($r['result']) ?></span></td>
                    <td><?= e($r['as_found_value'] ?? '-') ?></td>
                    <td><?= e($r['as_left_value'] ?? '-') ?></td>
                    <td><?= e($r['certificate_number'] ?? '-') ?></td>
                    <td class="text-end">
                        <?php if ($auth['role'] === 'Admin'): ?>
                        <a href="/calibrations/records?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/../partials/pagination.php'; ?>
<?php endif; ?>

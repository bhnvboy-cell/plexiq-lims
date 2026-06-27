<?php $title = 'Instrument Calibrations'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="page-title mb-0"><i class="bi bi-calendar-check me-2"></i>Instrument Calibrations</h4>
    <a href="/master/calibrations/create" class="btn btn-primary btn-sm"><i class="bi bi-plus"></i> New Calibration</a>
</div>
<?php if (!empty($upcoming)): ?>
<div class="alert alert-warning py-2"><i class="bi bi-exclamation-triangle me-1"></i><?= count($upcoming) ?> calibration(s) due within 30 days</div>
<?php endif; ?>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Instrument</th><th>Date</th><th>Calibrated By</th><th>Standard</th><th>Result</th><th>Cert #</th><th>Next Due</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($calibrations as $c): ?>
                <tr>
                    <td><strong><?= e($c['instrument_name'] ?? 'ID: '.$c['instrument_id']) ?></strong></td>
                    <td><?= e($c['calibration_date']) ?></td>
                    <td><?= e($c['calibrated_by'] ?? '-') ?></td>
                    <td><?= e($c['calibration_standard'] ?? '-') ?></td>
                    <td><span class="badge bg-<?= $c['result']==='Pass'?'success':($c['result']==='Fail'?'danger':'warning') ?>"><?= e($c['result']) ?></span></td>
                    <td><?= e($c['certificate_number'] ?? '-') ?></td>
                    <td><?= $c['next_calibration_date'] ? e($c['next_calibration_date']) : '-' ?></td>
                    <td><a href="/master/calibrations/<?= $c['id'] ?>/edit" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($calibrations)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No calibration records found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

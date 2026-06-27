<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="page-title mb-1"><i class="bi bi-calendar-check me-2"></i>Calibration Management</h4>
        <span class="text-muted small">Enhanced calibration tracking</span>
    </div>
    <div class="d-flex gap-2">
        <a href="/calibrations/standards" class="btn btn-outline-primary btn-sm"><i class="bi bi-rulers me-1"></i>Standards</a>
        <a href="/calibrations/schedules" class="btn btn-outline-primary btn-sm"><i class="bi bi-calendar-week me-1"></i>Schedules</a>
        <a href="/calibrations/records/" class="btn btn-outline-primary btn-sm"><i class="bi bi-journal-text me-1"></i>Records</a>
        <?php if ($auth['role'] === 'Admin'): ?>
        <a href="/calibrations/standards" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Standard</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stats-card stats-card-red">
            <i class="bi bi-exclamation-triangle stat-icon"></i>
            <div class="stat-value"><?= $stats['overdue'] ?? 0 ?></div>
            <div class="stat-label">Overdue</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card stats-card-orange">
            <i class="bi bi-clock stat-icon"></i>
            <div class="stat-value"><?= $stats['upcoming'] ?? 0 ?></div>
            <div class="stat-label">Due Within 30 Days</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card stats-card-green">
            <i class="bi bi-check-circle stat-icon"></i>
            <div class="stat-value"><?= $stats['completed_this_month'] ?? 0 ?></div>
            <div class="stat-label">Completed This Month</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card stats-card-blue">
            <i class="bi bi-rulers stat-icon"></i>
            <div class="stat-value"><?= $stats['total_standards'] ?? 0 ?></div>
            <div class="stat-label">Total Standards</div>
        </div>
    </div>
</div>

<?php if ($stats['overdue'] > 0): ?>
<div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle me-1"></i><strong><?= $stats['overdue'] ?></strong> calibration(s) are overdue!</div>
<?php endif; ?>
<?php if ($stats['upcoming'] > 0): ?>
<div class="alert alert-warning py-2"><i class="bi bi-clock me-1"></i><strong><?= $stats['upcoming'] ?></strong> calibration(s) due within 30 days.</div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-rulers me-1"></i>Calibration Standards</span>
                <a href="/calibrations/standards" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Code</th><th>Name</th><th>Type</th><th>Serial #</th><th>Interval (days)</th><th>Last Calibration</th><th>Next Due</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($standards as $s): ?>
                        <tr>
                            <td><span class="fw-medium"><?= e($s['standard_code']) ?></span></td>
                            <td><strong><?= e($s['standard_name']) ?></strong></td>
                            <td><span class="badge bg-info bg-opacity-10 text-info"><?= e($s['standard_type'] ?? '-') ?></span></td>
                            <td><?= e($s['serial_number'] ?? '-') ?></td>
                            <td><?= e($s['calibration_interval_days'] ?? '-') ?></td>
                            <td><small class="text-muted"><?= e($s['last_calibration_date'] ?? '-') ?></small></td>
                            <td><?php
                                $next = $s['next_calibration_date'] ?? null;
                                if ($next):
                                    $days = (strtotime($next) - time()) / 86400;
                                    $badge = $days < 0 ? 'danger' : ($days < 30 ? 'warning' : 'success');
                                ?>
                                <span class="badge bg-<?= $badge ?>"><?= e($next) ?></span>
                                <?php else: ?><span class="text-muted">-</span><?php endif; ?>
                            </td>
                            <td><?= ($s['is_active'] ?? true) ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>' ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($standards)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No calibration standards defined.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-calendar-week me-1"></i>Upcoming Schedules</span>
                <a href="/calibrations/schedules" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Instrument</th><th>Standard</th><th>Frequency</th><th>Next Due</th><th>Assigned To</th></tr></thead>
                    <tbody>
                        <?php foreach ($schedules as $s): ?>
                        <tr>
                            <td><?= e($s['instrument_name'] ?? '-') ?></td>
                            <td><?= e($s['standard_name'] ?? '-') ?></td>
                            <td><?= e($s['frequency_days'] ?? '-') ?>d</td>
                            <td><?php $d = $s['next_due_date'] ?? null; if($d): $days = (strtotime($d)-time())/86400; $bg = $days<0?'danger':($days<30?'warning':'success'); ?><span class="badge bg-<?= $bg ?>"><?= e($d) ?></span><?php else: ?><span class="text-muted">-</span><?php endif; ?></td>
                            <td><?= e($s['assigned_name'] ?? 'Unassigned') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($schedules)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No schedules defined.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-journal-text me-1"></i>Recent Records</span>
                <a href="/calibrations/records/" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Date</th><th>Standard</th><th>Type</th><th>Result</th><th>Certificate</th></tr></thead>
                    <tbody>
                        <?php foreach ($records as $r): ?>
                        <tr>
                            <td><small class="text-muted"><?= e($r['calibration_date']) ?></small></td>
                            <td><?= e($r['standard_name'] ?? '-') ?></td>
                            <td><span class="badge bg-secondary bg-opacity-10 text-secondary"><?= e($r['calibration_type'] ?? '-') ?></span></td>
                            <td><span class="badge bg-<?= $r['result']==='Pass'?'success':($r['result']==='Fail'?'danger':'warning') ?>"><?= e($r['result']) ?></span></td>
                            <td><?= e($r['certificate_number'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($records)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No calibration records yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

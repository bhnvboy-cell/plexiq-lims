<?php
$title = 'Stability Study: ' . e($study['study_code']);
layout('app');
$timepointBadges = ['Scheduled'=>'secondary','In Progress'=>'info','Completed'=>'success'];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0">
        <i class="bi bi-clipboard-pulse me-2"></i><?= e($study['study_code']) ?>
        <small class="text-muted fs-6 ms-2"><?= e($study['study_name'] ?? '') ?></small>
    </h4>
    <div class="d-flex gap-2">
        <a href="/stability" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
        <a href="/stability/<?= $study['id'] ?>/edit" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a>
        <?php if ($study['status'] !== 'Closed'): ?>
        <form method="POST" action="/stability/<?= $study['id'] ?>/close" class="d-inline" onsubmit="return confirm('Close this study?')">
            <?= csrf_field() ?>
            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-circle"></i> Close Study</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php $success = session_flash('success'); $error = session_flash('error'); ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<!-- Study Header Info -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-info-circle me-1"></i>Study Details</h6></div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr><td class="text-muted">Study Code</td><td class="fw-bold"><?= e($study['study_code']) ?></td></tr>
                    <tr><td class="text-muted">Study Name</td><td><?= e($study['study_name'] ?? '—') ?></td></tr>
                    <tr><td class="text-muted">Product</td><td><?= e($study['product_name'] ?? '—') ?></td></tr>
                    <tr><td class="text-muted">Batch</td><td><?= e($study['batch_number'] ?? '—') ?></td></tr>
                    <tr><td class="text-muted">Type</td><td><?= e($study['study_type'] ?? '—') ?></td></tr>
                    <tr><td class="text-muted">Protocol</td><td><?= e($study['protocol_ref'] ?? '—') ?></td></tr>
                    <tr><td class="text-muted">Status</td><td><span class="badge bg-<?= match($study['status']){'Active'=>'success','Completed'=>'primary','On Hold'=>'warning','Terminated'=>'danger','Closed'=>'secondary','Scheduled'=>'info',default=>'secondary'} ?>"><?= e($study['status']) ?></span></td></tr>
                    <tr><td class="text-muted">Start Date</td><td><?= $study['start_date'] ? date('d M Y', strtotime($study['start_date'])) : '—' ?></td></tr>
                    <tr><td class="text-muted">End Date</td><td><?= $study['end_date'] ? date('d M Y', strtotime($study['end_date'])) : '—' ?></td></tr>
                    <tr><td class="text-muted">Created By</td><td><?= e($study['created_by_name'] ?? '—') ?></td></tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card shadow-sm h-100">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-thermometer me-1"></i>Storage Conditions</h6></div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-4">
                        <div class="border rounded p-3 text-center">
                            <i class="bi bi-thermometer-half fs-2 d-block mb-1"></i>
                            <div class="fw-bold"><?= e($study['condition_temperature'] ?? '—') ?>°C</div>
                            <small class="text-muted">Temperature</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 text-center">
                            <i class="bi bi-droplet-half fs-2 d-block mb-1"></i>
                            <div class="fw-bold"><?= e($study['condition_humidity'] ?? '—') ?>%</div>
                            <small class="text-muted">Humidity</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 text-center">
                            <i class="bi bi-sun fs-2 d-block mb-1"></i>
                            <div class="fw-bold"><?= e($study['condition_light'] ?? '—') ?></div>
                            <small class="text-muted">Light</small>
                        </div>
                    </div>
                    <?php if (!empty($study['storage_condition'])): ?>
                    <div class="col-12 mt-2">
                        <div class="alert alert-light border small mb-0"><strong>Storage condition:</strong> <?= e($study['storage_condition']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Timepoint -->
<div class="card shadow-sm mb-4">
    <div class="card-header"><h6 class="mb-0"><i class="bi bi-plus-circle me-1"></i>Add Timepoint</h6></div>
    <div class="card-body">
        <form method="POST" action="/stability/<?= $study['id'] ?>/timepoints" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <div class="col-md-3">
                <label class="form-label small mb-1">Label <span class="text-danger">*</span></label>
                <input type="text" name="timepoint_label" class="form-control" required placeholder="e.g. T0 Initial">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Day Offset <span class="text-danger">*</span></label>
                <input type="number" name="day_offset" class="form-control" required placeholder="e.g. 30">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Scheduled Date</label>
                <input type="date" name="scheduled_date" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Sort Order</label>
                <input type="number" name="sort_order" class="form-control" value="0">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Notes</label>
                <input type="text" name="notes" class="form-control">
            </div>
            <div class="col-auto">
                <button class="btn btn-primary"><i class="bi bi-save"></i> Add Timepoint</button>
            </div>
        </form>
    </div>
</div>

<!-- Timepoints Timeline -->
<div class="card shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-clock-history me-1"></i>Timepoints</h6>
        <span class="badge bg-secondary"><?= count($timepoints ?? []) ?> timepoints</span>
    </div>
    <div class="card-body">
        <?php if (empty($timepoints)): ?>
        <div class="text-center text-muted py-3">No timepoints defined for this study.</div>
        <?php else: foreach ($timepoints as $tp): ?>
        <div class="d-flex align-items-start mb-3">
            <div class="me-3 text-center" style="min-width:90px;">
                <div class="fw-bold small"><?= e($tp['timepoint_label'] ?? '') ?></div>
                <small class="text-muted">Day <?= (int)$tp['day_offset'] ?></small>
            </div>
            <div class="flex-grow-1">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="badge bg-<?= $timepointBadges[$tp['status']] ?? 'secondary' ?>"><?= e($tp['status'] ?? 'Scheduled') ?></span>
                    <?php if (empty($tp['notes'])): ?><span></span><?php else: ?><small class="text-muted"><?= e($tp['notes']) ?></small><?php endif; ?>
                </div>
                <small class="text-muted">
                    Scheduled: <?= $tp['scheduled_date'] ? date('d M Y', strtotime($tp['scheduled_date'])) : '—' ?>
                    <?php if ($tp['completed_date']): ?> | Completed: <?= date('d M Y', strtotime($tp['completed_date'])) ?><?php endif; ?>
                </small>
                <div class="mt-1 d-flex gap-2 flex-wrap">
                    <?php if ($tp['status'] === 'Scheduled'): ?>
                    <form method="POST" action="/stability/timepoints/<?= $tp['id'] ?>/start" class="d-inline">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm btn-outline-success" title="Start"><i class="bi bi-play-fill"></i> Start</button>
                    </form>
                    <?php endif; ?>
                    <?php if ($tp['status'] === 'In Progress'): ?>
                    <form method="POST" action="/stability/timepoints/<?= $tp['id'] ?>/complete" class="d-inline">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm btn-outline-primary" title="Complete"><i class="bi bi-check"></i> Complete</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<!-- Add Result -->
<div class="card shadow-sm mb-4">
    <div class="card-header"><h6 class="mb-0"><i class="bi bi-plus-circle me-1"></i>Add Result</h6></div>
    <div class="card-body">
        <form method="POST" action="/stability/timepoints/<?= $timepoints[0]['id'] ?? '' ?>/result" class="row g-2 align-items-end" id="addResultForm">
            <?= csrf_field() ?>
            <div class="col-md-3">
                <label class="form-label small mb-1">Timepoint <span class="text-danger">*</span></label>
                <select name="timepoint_id" class="form-select" id="resultTimepoint" onchange="updateResultForm()" required>
                    <option value="">— Select —</option>
                    <?php foreach ($timepoints as $tp): ?>
                    <option value="<?= $tp['id'] ?>" data-offset="<?= (int)$tp['day_offset'] ?>" data-label="<?= e($tp['timepoint_label']) ?>"><?= e($tp['timepoint_label']) ?> (Day <?= (int)$tp['day_offset'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Test <span class="text-danger">*</span></label>
                <select name="test_id" class="form-select" required>
                    <option value="">— Select —</option>
                    <?php foreach ($tests as $t): ?>
                    <option value="<?= $t['id'] ?>"><?= e($t['test_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Result Value <span class="text-danger">*</span></label>
                <input type="text" name="result_value" class="form-control" required>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Spec Limit</label>
                <input type="text" name="specification_limit" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Tested At</label>
                <input type="datetime-local" name="tested_at" class="form-control">
            </div>
            <div class="col-auto">
                <button class="btn btn-primary"><i class="bi bi-save"></i> Add Result</button>
            </div>
        </form>
    </div>
</div>

<!-- Results Table -->
<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-table me-1"></i>Results</h6>
        <span class="badge bg-secondary"><?= count($results ?? []) ?> results</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Timepoint</th>
                    <th>Test</th>
                    <th>Result</th>
                    <th>Specification</th>
                    <th>Status</th>
                    <th>Tested By</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($results)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No results recorded yet.</td></tr>
                <?php else: foreach ($results as $r): ?>
                <tr>
                    <td><span class="badge bg-info bg-opacity-10 text-info"><?= e($r['timepoint_label'] ?? '') ?> (Day <?= (int)$r['day_offset'] ?>)</span></td>
                    <td><?= e($r['test_name'] ?? '—') ?></td>
                    <td class="fw-bold"><?= e($r['result_value'] ?? '—') ?></td>
                    <td><code><?= e($r['specification_limit'] ?? '—') ?></code></td>
                    <td>
                        <span class="badge bg-<?= match($r['result_status'] ?? 'Pending') { 'Pass'=>'success', 'Fail'=>'danger', 'OOS'=>'warning', default=>'secondary' } ?>">
                            <?= e($r['result_status'] ?? 'Pending') ?>
                        </span>
                    </td>
                    <td><?= e($r['tested_by_name'] ?? '—') ?></td>
                    <td><small class="text-muted"><?= $r['tested_at'] ? date('d M Y H:i', strtotime($r['tested_at'])) : '—' ?></small></td>
                    <td><a href="/stability/results/<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function updateResultForm() {
    const sel = document.getElementById('resultTimepoint');
    const form = document.getElementById('addResultForm');
    if (sel && sel.value) {
        form.action = '/stability/timepoints/' + sel.value + '/result';
    }
}
</script>

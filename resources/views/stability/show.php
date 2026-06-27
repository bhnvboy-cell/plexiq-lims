<?php
$title = 'Stability Study: ' . e($study['study_code']);
layout('app');
$timepointBadges = ['Scheduled'=>'secondary','In Progress'=>'info','Completed'=>'success','Overdue'=>'danger'];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0">
        <i class="bi bi-clipboard-pulse me-2"></i><?= e($study['study_code']) ?>
        <small class="text-muted fs-6 ms-2"><?= e($study['study_name'] ?? '') ?></small>
    </h4>
    <div class="d-flex gap-2">
        <a href="/stability" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
        <a href="/stability/<?= $study['id'] ?>/edit" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a>
    </div>
</div>

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
                    <tr><td class="text-muted">Status</td><td><span class="badge bg-<?= match($study['status']){'Active'=>'success','Completed'=>'primary','On Hold'=>'warning','Terminated'=>'danger','Scheduled'=>'info',default=>'secondary'} ?>"><?= e($study['status']) ?></span></td></tr>
                    <tr><td class="text-muted">Start Date</td><td><?= $study['start_date'] ? date('d M Y', strtotime($study['start_date'])) : '—' ?></td></tr>
                    <tr><td class="text-muted">End Date</td><td><?= $study['end_date'] ? date('d M Y', strtotime($study['end_date'])) : '—' ?></td></tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card shadow-sm h-100">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-thermometer me-1"></i>Storage Conditions</h6></div>
            <div class="card-body">
                <div class="row g-2">
                    <?php if (empty($study['conditions'])): ?>
                    <div class="col-12 text-muted small">No conditions configured.</div>
                    <?php else: $conds = is_array($study['conditions']) ? $study['conditions'] : [['condition_name'=>$study['conditions']]]; ?>
                    <?php foreach ($conds as $cond): ?>
                    <div class="col-md-4">
                        <div class="border rounded p-3 text-center">
                            <i class="bi bi-thermometer-half fs-2 d-block mb-1"></i>
                            <div class="fw-bold"><?= e($cond['condition_name'] ?? $cond) ?></div>
                            <?php if (!empty($cond['temperature'])): ?>
                            <small class="text-muted"><?= e($cond['temperature']) ?>°C / <?= e($cond['humidity'] ?? '—') ?>% RH</small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
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
        <?php else: ?>
        <div class="timeline">
            <?php foreach ($timepoints as $tp): ?>
            <div class="d-flex align-items-start mb-3 position-relative">
                <div class="me-3 text-center" style="min-width:80px;">
                    <div class="fw-bold small">T<?= $tp['timepoint_index'] ?? 0 ?></div>
                    <small class="text-muted"><?= $tp['label'] ?? '' ?></small>
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-center">
                        <strong><?= $tp['days'] ?> days</strong>
                        <span class="badge bg-<?= $timepointBadges[$tp['status']] ?? 'secondary' ?>"><?= e($tp['status'] ?? 'Scheduled') ?></span>
                    </div>
                    <small class="text-muted">
                        Target: <?= $tp['target_date'] ? date('d M Y', strtotime($tp['target_date'])) : '—' ?>
                        <?php if ($tp['completed_date']): ?> | Completed: <?= date('d M Y', strtotime($tp['completed_date'])) ?><?php endif; ?>
                    </small>
                    <?php if (!empty($tp['notes'])): ?>
                    <div class="small text-muted mt-1"><?= e($tp['notes']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="ms-2">
                    <?php if ($tp['status'] === 'Scheduled'): ?>
                    <form method="POST" action="/stability/timepoints/<?= $tp['id'] ?>/start" class="d-inline">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm btn-outline-success" title="Start"><i class="bi bi-play-fill"></i></button>
                    </form>
                    <?php endif; ?>
                    <?php if ($tp['status'] === 'In Progress'): ?>
                    <form method="POST" action="/stability/timepoints/<?= $tp['id'] ?>/complete" class="d-inline">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm btn-outline-primary" title="Complete"><i class="bi bi-check"></i></button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (!next($timepoints) === false): ?>
            <div class="border-start border-2 ms-4 mb-3" style="height:10px;margin-left:40px!important;"></div>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
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
                    <th>Test Parameter</th>
                    <th>Result</th>
                    <th>Specification</th>
                    <th>Within Spec</th>
                    <th>Performed By</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($results)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No results recorded yet.</td></tr>
                <?php else: foreach ($results as $r): ?>
                <tr>
                    <td><span class="badge bg-info bg-opacity-10 text-info">T<?= $r['timepoint_index'] ?? '?' ?></span></td>
                    <td><?= e($r['test_name'] ?? '—') ?></td>
                    <td class="fw-bold"><?= e($r['result_value'] ?? $r['result_text'] ?? '—') ?></td>
                    <td><code><?= e($r['specification'] ?? '—') ?></code></td>
                    <td>
                        <?php if ($r['is_within_spec']): ?>
                        <span class="text-success"><i class="bi bi-check-circle-fill"></i> Pass</span>
                        <?php else: ?>
                        <span class="text-danger"><i class="bi bi-x-circle-fill"></i> Fail</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($r['performed_by_name'] ?? '—') ?></td>
                    <td><small class="text-muted"><?= $r['test_date'] ? date('d M Y', strtotime($r['test_date'])) : '—' ?></small></td>
                    <td><a href="/stability/results/<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

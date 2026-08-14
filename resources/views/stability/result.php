<?php $title = 'Stability Result'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-clipboard-check me-2"></i>Stability Result</h4>
    <div class="d-flex gap-2">
        <a href="/stability/<?= $result['study_id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Study</a>
        <a href="/stability/<?= $result['study_id'] ?>/edit" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Edit Study</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-info-circle me-1"></i>Result Details</h6></div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr><td class="text-muted">Study</td><td class="fw-bold"><?= e($result['study_code']) ?></td></tr>
                    <tr><td class="text-muted">Study Name</td><td><?= e($result['study_name'] ?? '—') ?></td></tr>
                    <tr><td class="text-muted">Timepoint</td><td><?= e($result['timepoint_label'] ?? '—') ?> (Day <?= (int)$result['day_offset'] ?>)</td></tr>
                    <tr><td class="text-muted">Test</td><td><?= e($result['test_name'] ?? '—') ?></td></tr>
                    <tr><td class="text-muted">Result Value</td><td class="fw-bold fs-5"><?= e($result['result_value'] ?? '—') ?></td></tr>
                    <tr><td class="text-muted">Specification Limit</td><td><code><?= e($result['specification_limit'] ?? $result['spec_limit_text'] ?? '—') ?></code></td></tr>
                    <tr><td class="text-muted">Result Status</td><td>
                        <span class="badge bg-<?= match($result['result_status'] ?? 'Pending') { 'Pass'=>'success', 'Fail'=>'danger', 'OOS'=>'warning', default=>'secondary' } ?>">
                            <?= e($result['result_status'] ?? 'Pending') ?>
                        </span>
                    </td></tr>
                    <tr><td class="text-muted">Tested By</td><td><?= e($result['tested_by_name'] ?? '—') ?></td></tr>
                    <tr><td class="text-muted">Tested At</td><td><?= $result['tested_at'] ? date('d M Y H:i', strtotime($result['tested_at'])) : '—' ?></td></tr>
                    <tr><td class="text-muted">Recorded At</td><td><?= date('d M Y H:i', strtotime($result['created_at'])) ?></td></tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-clipboard-check me-1"></i>Assessment</h6></div>
            <div class="card-body text-center py-5">
                <?php $status = $result['result_status'] ?? 'Pending'; ?>
                <i class="bi bi-<?= $status === 'Pass' ? 'check-circle-fill text-success' : ($status === 'Fail' ? 'x-circle-fill text-danger' : ($status === 'OOS' ? 'exclamation-triangle-fill text-warning' : 'hourglass text-secondary')) ?> display-3 d-block mb-3"></i>
                <h5><?= e($status) ?></h5>
            </div>
        </div>
    </div>
</div>

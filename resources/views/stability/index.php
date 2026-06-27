<?php $title = 'Stability Studies'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-clipboard-pulse me-2"></i>Stability Studies</h4>
    <div>
        <span class="badge bg-secondary me-2"><?= count($studies) ?> studies</span>
        <a href="/stability/create" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New Study</a>
    </div>
</div>

<?php if (empty($studies)): ?>
<div class="card shadow-sm"><div class="card-body text-center text-muted py-5"><i class="bi bi-clipboard-pulse display-4 d-block mb-3"></i>No stability studies found. <a href="/stability/create" class="alert-link">Create your first study</a>.</div></div>
<?php else: ?>
<div class="row g-3">
    <?php foreach ($studies as $s): ?>
    <div class="col-md-6 col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h5 class="card-title mb-0"><?= e($s['study_code']) ?></h5>
                        <small class="text-muted"><?= e($s['study_name'] ?? '') ?></small>
                    </div>
                    <?php
                    $statusBadge = match ($s['status']) {
                        'Active' => 'success', 'Completed' => 'primary', 'On Hold' => 'warning',
                        'Terminated' => 'danger', 'Scheduled' => 'info', default => 'secondary'
                    };
                    ?>
                    <span class="badge bg-<?= $statusBadge ?>"><?= e($s['status']) ?></span>
                </div>
                <p class="card-text small text-muted flex-grow-1">
                    <strong>Product:</strong> <?= e($s['product_name'] ?? '—') ?><br>
                    <strong>Batch:</strong> <?= e($s['batch_number'] ?? '—') ?><br>
                    <strong>Condition:</strong> <?= e($s['conditions'] ?? '—') ?>
                </p>
                <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                    <small class="text-muted">
                        <?php if ($s['start_date']): ?><i class="bi bi-calendar me-1"></i><?= date('M Y', strtotime($s['start_date'])) ?><?php endif; ?>
                        <?php if ($s['end_date']): ?> &rarr; <?= date('M Y', strtotime($s['end_date'])) ?><?php endif; ?>
                    </small>
                    <a href="/stability/<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> View</a>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

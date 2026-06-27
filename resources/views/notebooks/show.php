<?php $title = 'Notebook: ' . e($notebook['notebook_name']); layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0">
        <i class="bi bi-journal-text me-2"></i><?= e($notebook['notebook_name']) ?>
        <small class="text-muted fs-6 ms-2"><?= e($notebook['category'] ?? 'General') ?></small>
    </h4>
    <div class="d-flex gap-2">
        <a href="/notebooks" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
        <a href="/notebooks/<?= $notebook['id'] ?>/entries/create" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New Entry</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-info-circle me-1"></i>Notebook Details</h6></div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr><td class="text-muted">Name</td><td class="fw-bold"><?= e($notebook['notebook_name']) ?></td></tr>
                    <tr><td class="text-muted">Category</td><td><span class="badge bg-info bg-opacity-10 text-info"><?= e($notebook['category'] ?? 'General') ?></span></td></tr>
                    <tr><td class="text-muted">Description</td><td><?= e($notebook['description'] ?? '—') ?></td></tr>
                    <tr><td class="text-muted">Owner</td><td><?= e($notebook['owner_name'] ?? '—') ?></td></tr>
                    <tr><td class="text-muted">Entries</td><td><?= count($entries ?? []) ?></td></tr>
                    <tr><td class="text-muted">Created</td><td><?= date('d M Y', strtotime($notebook['created_at'])) ?></td></tr>
                    <tr><td class="text-muted">Updated</td><td><?= date('d M Y', strtotime($notebook['updated_at'] ?? $notebook['created_at'])) ?></td></tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-file-text me-1"></i>Entries</h6>
                <span class="badge bg-secondary"><?= count($entries ?? []) ?> entries</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($entries)): ?>
                <div class="text-center text-muted py-5">
                    <i class="bi bi-file-earmark-text display-5 d-block mb-2"></i>
                    <p>No entries in this notebook yet.</p>
                    <a href="/notebooks/<?= $notebook['id'] ?>/entries/create" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Create First Entry</a>
                </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($entries as $e): ?>
                    <a href="/notebooks/<?= $notebook['id'] ?>/entries/<?= $e['id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                        <div>
                            <div class="fw-bold"><?= e($e['title']) ?></div>
                            <small class="text-muted">
                                <span class="badge bg-<?= match($e['entry_type'] ?? 'General') { 'Protocol'=>'info', 'Observation'=>'warning', 'Result'=>'success', 'Conclusion'=>'primary', default=>'secondary' } ?> bg-opacity-10 text-dark me-1"><?= e($e['entry_type'] ?? 'General') ?></span>
                                <span class="badge bg-<?= match($e['status'] ?? 'Draft') { 'Draft'=>'secondary', 'Submitted'=>'info', 'Reviewed'=>'primary', 'Approved'=>'success', 'Rejected'=>'danger', default=>'secondary' } ?> bg-opacity-10 text-dark me-2"><?= e($e['status'] ?? 'Draft') ?></span>
                                <i class="bi bi-person me-1"></i><?= e($e['created_by_name'] ?? '—') ?>
                                <i class="bi bi-calendar ms-2 me-1"></i><?= date('d M Y', strtotime($e['created_at'])) ?>
                            </small>
                        </div>
                        <i class="bi bi-chevron-right text-muted"></i>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
